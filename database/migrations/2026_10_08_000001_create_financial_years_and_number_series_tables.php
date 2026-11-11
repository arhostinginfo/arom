<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Financial years start 1 April (decision D20). Document numbers run per document type,
// unit and financial year, e.g. QT/26-27/0001 (decision D16).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('number_series', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 30);
            $table->string('prefix', 10);
            $table->unsignedInteger('next_number')->default(1);
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['document_type', 'unit_id', 'financial_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_series');
        Schema::dropIfExists('financial_years');
    }
};
