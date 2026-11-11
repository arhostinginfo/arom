<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
| CRM changes (Sat, 13/10/2026): PO / Agreement received and Documents & attachments.
| Also adds the new CRM page permissions and gives the CRM pages to the existing Sales role,
| as the seeder does for a new database.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_pos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('enquiry_id')->constrained()->restrictOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('po_no', 60);
            $table->date('po_date');
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('site', 255)->nullable();
            $table->decimal('po_value', 15, 2);
            $table->decimal('po_quantity', 15, 3)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('payment_terms', 255)->nullable();
            $table->string('tax_terms', 150)->nullable();
            $table->string('contact_name', 150)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('employees')->restrictOnDelete();
            $table->date('received_date');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['unit_id', 'po_date'], 'customer_pos_unit_date_index');
        });

        // Every document keeps its uploader, upload time and the record it belongs to.
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type', 40);
            $table->unsignedBigInteger('documentable_id');
            $table->string('category', 40);
            $table->string('original_name', 255);
            $table->string('path', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['documentable_type', 'documentable_id', 'id'], 'documents_subject_index');
        });

        // Listed here, not read from App\Support\Access, so this migration does not change if that class does
        // (database review S7).
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (array_merge(self::PERMISSIONS, self::SALES) as $name) {
            Permission::findOrCreate($name, 'web');
        }
        if ($sales = Role::where('name', 'Sales')->where('guard_name', 'web')->first()) {
            $sales->givePermissionTo(self::SALES);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** New CRM page permissions; those in SALES are also given to the Sales role. */
    private const SALES = ['enquiry.view', 'enquiry.add', 'enquiry.edit', 'enquiry.delete', 'site-visit.view', 'site-visit.add', 'site-visit.edit', 'site-visit.delete',
        'customer-po.view', 'customer-po.add', 'customer-po.edit', 'customer-po.delete'];

    private const PERMISSIONS = ['quotation-approval.view', 'quotation-approval.edit'];

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('customer_pos');
        // Remove the permissions this migration added (and their role and user grants).
        Permission::whereIn('name', array_merge(self::PERMISSIONS, self::SALES))->where('guard_name', 'web')->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
