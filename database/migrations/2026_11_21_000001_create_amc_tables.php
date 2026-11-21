<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/*
| AMC module (21/11/2026): its masters, AMC Client Master with location lines, the contracts (Customer PO / AMC) and
| their files, and Return & Replace. Every table is new and starts with amc_; no existing table, column or row is
| changed. Adds the AMC permissions (Admin has every right through the gate already).
*/
return new class extends Migration
{
    /** Listed here, not read from App\Support\Access, so this migration does not change if config/amc.php does. */
    private const PERMISSIONS = [
        'amc-dashboard.view',
        'amc-client.view', 'amc-client.add', 'amc-client.edit', 'amc-client-import.add',
        'amc-contract.view', 'amc-contract.add', 'amc-contract.edit', 'amc-contract.delete',
        'amc-return.view', 'amc-return.add', 'amc-return.edit', 'amc-return.delete',
        'amc-vertical.view', 'amc-vertical.add', 'amc-vertical.edit', 'amc-vertical.delete',
        'amc-planter.view', 'amc-planter.add', 'amc-planter.edit', 'amc-planter.delete',
        'amc-plant.view', 'amc-plant.add', 'amc-plant.edit', 'amc-plant.delete',
        'amc-type.view', 'amc-type.add', 'amc-type.edit', 'amc-type.delete',
        'amc-employee.view', 'amc-employee.add', 'amc-employee.edit', 'amc-employee.delete',
    ];

    public function up(): void
    {
        // Simple masters: a unique name and Active (shown in dropdowns). The Plant master also has a size ("5 ft").
        foreach (['amc_verticals', 'amc_planters', 'amc_plants', 'amc_types'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->string('name', 100)->unique();
                if ($table === 'amc_plants') {
                    $t->string('size', 50)->nullable();
                }
                $t->boolean('is_active')->default(true);
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->index(['is_active', 'name']);
            });
        }

        // AMC Employee master: separate from the CRM employees table. photo_path is on the private disk.
        Schema::create('amc_employees', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name', 100)->unique();
            $t->enum('role', ['Operation Manager', 'Gardener', 'Other']);
            $t->string('mobile', 10)->nullable();
            $t->string('photo_path', 255)->nullable();
            $t->enum('status', ['Active', 'Inactive'])->default('Active');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            // Pickers: active employees of one role in name order.
            $t->index(['status', 'role', 'name']);
        });

        Schema::create('amc_clients', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150)->unique();
            $t->foreignId('amc_vertical_id')->constrained('amc_verticals')->restrictOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Location lines of a client: two location texts, a planter and a plant with their quantities, each optional.
        Schema::create('amc_client_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('amc_client_id')->constrained('amc_clients')->cascadeOnDelete();
            $t->unsignedSmallInteger('line_no');
            $t->string('location_1', 150)->nullable();
            $t->string('location_2', 150)->nullable();
            $t->foreignId('amc_planter_id')->nullable()->constrained('amc_planters')->restrictOnDelete();
            $t->unsignedInteger('planter_qty')->nullable();
            $t->foreignId('amc_plant_id')->nullable()->constrained('amc_plants')->restrictOnDelete();
            $t->unsignedInteger('plant_qty')->nullable();
            $t->timestamps();
            $t->index(['amc_client_id', 'line_no']);
        });

        // Customer PO / AMC: one contract of a client. Number shown as AMC-0001 from the id.
        Schema::create('amc_contracts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('amc_client_id')->constrained('amc_clients')->restrictOnDelete();
            $t->foreignId('amc_type_id')->constrained('amc_types')->restrictOnDelete();
            $t->date('start_date');
            $t->date('end_date');
            $t->enum('billing_cycle', ['Monthly', 'Quarterly', 'Half-yearly', 'Yearly']);
            $t->string('visit_plan', 255)->nullable();
            $t->decimal('amount', 15, 2);
            $t->foreignId('operation_manager_id')->nullable()->constrained('amc_employees')->restrictOnDelete();
            $t->foreignId('gardener_id')->nullable()->constrained('amc_employees')->restrictOnDelete();
            $t->enum('status', ['Active', 'Inactive'])->default('Active');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            // AMC list colours, renewal alerts and the dashboard: status with the end date; a client's active contract.
            $t->index(['status', 'end_date']);
            $t->index(['amc_client_id', 'status', 'end_date']);
            $t->index('created_at');
        });

        // Files of a contract on the private disk. Rows are never changed, only added and deleted.
        Schema::create('amc_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('amc_contract_id')->constrained('amc_contracts')->cascadeOnDelete();
            $t->string('category', 20);
            $t->string('path', 255);
            $t->string('original_name', 255);
            $t->string('mime', 100)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->index(['amc_contract_id', 'category']);
            // The daily clean-up of deployment images older than a year.
            $t->index(['category', 'created_at']);
        });

        // Return & Replace: a plant returned from a client's location and its replacement.
        Schema::create('amc_returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('amc_client_id')->constrained('amc_clients')->restrictOnDelete();
            // The location line picked; its texts are copied so the entry keeps them if the line changes later.
            $t->foreignId('amc_client_line_id')->nullable()->constrained('amc_client_lines')->nullOnDelete();
            $t->string('location_1', 150)->nullable();
            $t->string('location_2', 150)->nullable();
            $t->date('request_date');
            $t->date('replace_date')->nullable();
            $t->foreignId('reported_by_id')->nullable()->constrained('amc_employees')->restrictOnDelete();
            $t->foreignId('amc_type_id')->nullable()->constrained('amc_types')->restrictOnDelete();
            $t->foreignId('amc_plant_id')->constrained('amc_plants')->restrictOnDelete();
            // Copied from the Plant master when saved.
            $t->string('plant_size', 50)->nullable();
            $t->unsignedInteger('qty');
            $t->decimal('rate', 15, 2)->nullable();
            $t->foreignId('gardener_id')->nullable()->constrained('amc_employees')->restrictOnDelete();
            $t->string('return_remark', 500)->nullable();
            $t->enum('quality', ['Reusable', 'Needs Treatment', 'Repairable', 'Dead']);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['request_date', 'id']);
            $t->index(['amc_client_id', 'request_date']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['amc_returns', 'amc_files', 'amc_contracts', 'amc_client_lines', 'amc_clients', 'amc_employees', 'amc_types', 'amc_plants', 'amc_planters', 'amc_verticals'] as $table) {
            Schema::dropIfExists($table);
        }
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
