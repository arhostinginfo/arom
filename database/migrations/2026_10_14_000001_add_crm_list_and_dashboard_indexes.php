<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Database review of the CRM changes (14/10/2026): indexes only, no column or row changes.
| DB-C2: dashboard open-lead counts, lead ageing, month and pie figures read the index, not the table.
| DB-C3: picker searches on enquiry no and contact no ("starts with"), and PO no.
| DB-C4: default sorts of the Quotation, Site visit and Enquiry lists.
| S1 / S6: PO pending check, and the lead report's latest follow-up and site visit per lead.
*/
return new class extends Migration
{
    private const INDEXES = [
        'enquiries' => [
            'enquiries_unit_open_date_index' => ['unit_id', 'deleted_at', 'status', 'enquiry_date'],
            'enquiries_unit_figures_index' => ['unit_id', 'deleted_at', 'enquiry_date', 'lead_source_id', 'service_category_id'],
            'enquiries_unit_next_index' => ['unit_id', 'next_action_date'],
            'enquiries_unit_expected_index' => ['unit_id', 'expected_closing_date'],
            'enquiries_unit_contact_index' => ['unit_id', 'contact_no'],
            'enquiries_unit_number_index' => ['unit_id', 'enquiry_no'],
        ],
        'quotations' => [
            'quotations_unit_latest_date_index' => ['unit_id', 'is_latest', 'quotation_date'],
        ],
        'site_visits' => [
            'site_visits_unit_visit_index' => ['unit_id', 'visit_at'],
            'site_visits_enquiry_visit_index' => ['enquiry_id', 'visit_at'],
        ],
        'customer_pos' => [
            'customer_pos_enquiry_deleted_index' => ['enquiry_id', 'deleted_at'],
            'customer_pos_unit_number_index' => ['unit_id', 'po_no'],
        ],
        'activities' => [
            'activities_enquiry_date_index' => ['enquiry_id', 'activity_date'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $t) use ($indexes) {
                foreach ($indexes as $name => $columns) {
                    $t->index($columns, $name);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $t) use ($indexes) {
                foreach (array_keys($indexes) as $name) {
                    $t->dropIndex($name);
                }
            });
        }
    }
};
