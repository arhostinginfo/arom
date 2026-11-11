<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
| Page setup for every page and switches for the optional CRM parts (Sat 14/10/2026), and the Contact / POC
| section of the CRM changes Word file.
| - module_settings: one row per optional part (site visit, BOQ, costing) that an Admin has switched on or off.
|   No row means the default from config/nursery.php 'modules' (all on for Nursery).
| - contacts: the people of a client (a prospect or a customer), with the fields of the Word file.
| Also adds the Contact page permissions and gives them to the existing Sales role, as the seeder does for a
| new database. No existing table, column or row is changed.
*/
return new class extends Migration
{
    /** Listed here, not read from App\Support\Access, so this migration does not change if that class does. */
    private const PERMISSIONS = ['contact.view', 'contact.add', 'contact.edit', 'contact.delete'];

    public function up(): void
    {
        Schema::create('module_settings', function (Blueprint $table) {
            $table->id();
            $table->string('module', 40)->unique();
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            // Contact ID shown to users (CT00001), made from the id after insert, so it never repeats under load.
            $table->string('contact_no', 20)->nullable()->unique();
            // The client: a customer or a prospect, the same pair as on an enquiry.
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('designation', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('mobile', 20)->nullable();
            // Digits only, for "starts with" search however the number was typed.
            $table->string('mobile_digits', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('alternate_no', 20)->nullable();
            $table->string('preferred_communication', 20)->nullable();
            $table->boolean('is_decision_maker')->default(false);
            $table->boolean('is_influencer')->default(false);
            $table->boolean('is_purchase_contact')->default(false);
            $table->boolean('is_facility_contact')->default(false);
            $table->boolean('is_billing_contact')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            // A client's contacts by name (enquiry page, list filtered by client); list search by name or mobile.
            $table->index(['prospect_id', 'deleted_at', 'name'], 'contacts_prospect_name_index');
            $table->index(['party_id', 'deleted_at', 'name'], 'contacts_party_name_index');
            $table->index(['deleted_at', 'name'], 'contacts_name_index');
            $table->index('mobile_digits', 'contacts_mobile_digits_index');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }
        if ($sales = Role::where('name', 'Sales')->where('guard_name', 'web')->first()) {
            $sales->givePermissionTo(self::PERMISSIONS);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('module_settings');
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
