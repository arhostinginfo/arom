<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Masters that the CRM quotation points at. Masters are not financial-year wise (OTHER POINTS A22).
// The Party master screen is not built yet, so parties stays empty until it is (decision C2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('country', 100);
            $table->string('code', 3)->unique();
            $table->string('symbol', 5);
            $table->timestamps();
        });

        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->string('gstin', 15)->nullable()->unique();
            $table->enum('type', ['Customer', 'Supplier', 'Both']);
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->enum('opening_balance_type', ['DR', 'CR'])->default('DR');
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
        Schema::dropIfExists('currencies');
    }
};
