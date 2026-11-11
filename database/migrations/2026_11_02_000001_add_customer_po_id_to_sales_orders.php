<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Create Sales Order from a customer PO (02/11/2026). sales_orders.customer_po_id records the customer PO an order was
| made from (CRM › 6 PO / Agreement). Nullable: orders typed in Sales or imported have none. POs are
| soft-deleted, so the link stays; a hard delete of the PO clears it. Only adds.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $t) {
            $t->foreignId('customer_po_id')->nullable()->after('party_id')->constrained('customer_pos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('customer_po_id');
        });
    }
};
