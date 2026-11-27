<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| (26/11/2026) Delivery of a customer PO: the Target delivery date; once it has passed, a Revised delivery date and its
| reason; and the date it was Delivered. A PO without a delivered date is a pending delivery, overdue when today is after
| its revised (else target) date. All optional, so existing POs are unchanged.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_pos', function (Blueprint $t) {
            $t->date('target_delivery_date')->nullable()->after('valid_to');
            $t->date('revised_delivery_date')->nullable()->after('target_delivery_date');
            $t->string('revised_reason', 255)->nullable()->after('revised_delivery_date');
            $t->date('delivered_on')->nullable()->after('revised_reason');
            $t->index(['unit_id', 'delivered_on', 'target_delivery_date'], 'customer_pos_unit_delivery_index');
        });
    }

    public function down(): void
    {
        Schema::table('customer_pos', function (Blueprint $t) {
            $t->dropIndex('customer_pos_unit_delivery_index');
            $t->dropColumn(['target_delivery_date', 'revised_delivery_date', 'revised_reason', 'delivered_on']);
        });
    }
};
