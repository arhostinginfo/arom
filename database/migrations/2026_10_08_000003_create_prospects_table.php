<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CRM > Prospect (workbook CRM tab). Mandatory: name, Domestic/Export, Contact no 1 (decision S4).
// unit_id is the unit the prospect was added in, used by the Prospects report's Unit filter (decision C6).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->enum('market', ['Domestic', 'Export'])->index();
            $table->string('contact_person', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('phone_1', 20);
            $table->string('phone_2', 20)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('industry_type', 100)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
