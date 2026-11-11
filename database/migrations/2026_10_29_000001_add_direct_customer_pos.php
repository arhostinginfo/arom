<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Direct customer PO (29/10/2026). CRM › PO / Agreement can now be entered two ways:
| - "Against CRM quotation" (source = crm): as before, the quotation gives the client and the enquiry;
| - "Direct PO" (source = direct): no enquiry and no quotation, the customer is picked from the Party master.
| customer_pos.enquiry_id becomes nullable (a direct PO has none) and customer_pos.source records the type; every
| existing PO is a CRM PO. customer_po_lines holds the items of a PO, picked from the Item master (Part master), with
| the description, UOM and rate as entered. Only adds; the enquiry_id change keeps its foreign key.
| Laravel's change() works on MySQL / MariaDB and rebuilds the table on SQLite (tests), keeping its indexes.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_pos', function (Blueprint $t) {
            $t->unsignedBigInteger('enquiry_id')->nullable()->change();
        });
        Schema::table('customer_pos', function (Blueprint $t) {
            $t->string('source', 10)->default('crm')->after('financial_year_id');
            // "The same PO no cannot be entered twice for one customer" (direct POs) without a table scan.
            $t->index(['party_id', 'po_no'], 'customer_pos_party_po_index');
        });

        Schema::create('customer_po_lines', function (Blueprint $t) {
            $t->id();
            // POs are soft-deleted, so their lines stay with them; a hard delete takes the lines too.
            $t->foreignId('customer_po_id')->constrained('customer_pos')->cascadeOnDelete();
            $t->unsignedSmallInteger('line_no');
            $t->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $t->string('description', 255);
            $t->foreignId('uom_id')->nullable()->constrained('uoms')->restrictOnDelete();
            $t->decimal('quantity', 15, 3);
            $t->decimal('rate', 15, 4);
            $t->decimal('amount', 15, 2);
            $t->timestamps();
            $t->index(['customer_po_id', 'line_no'], 'customer_po_lines_po_index');
        });
    }

    /**
     * Refused while direct POs exist: enquiry_id cannot become NOT NULL again without deleting them, and that is
     * left to a person, not to a rollback.
     */
    public function down(): void
    {
        if (DB::table('customer_pos')->whereNull('enquiry_id')->exists()) {
            throw new RuntimeException('Direct POs (with no enquiry) exist in customer_pos. Delete them before rolling back 2026_10_29_000001_add_direct_customer_pos.');
        }
        Schema::dropIfExists('customer_po_lines');
        // Tester review (29/10/2026): MariaDB may have dropped the party_id foreign key's own index when (party_id, po_no)
        // was added, and then refuses to drop (party_id, po_no) ("needed in a foreign key constraint"). Put a party_id
        // index back first when no other index starts with party_id.
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            && ! collect(Schema::getIndexes('customer_pos'))->contains(fn ($i) => $i['name'] !== 'customer_pos_party_po_index' && ($i['columns'][0] ?? null) === 'party_id')) {
            Schema::table('customer_pos', function (Blueprint $t) {
                $t->index('party_id', 'customer_pos_party_id_foreign');
            });
        }
        Schema::table('customer_pos', function (Blueprint $t) {
            $t->dropIndex('customer_pos_party_po_index');
        });
        Schema::table('customer_pos', function (Blueprint $t) {
            $t->dropColumn('source');
        });
        Schema::table('customer_pos', function (Blueprint $t) {
            $t->unsignedBigInteger('enquiry_id')->nullable(false)->change();
        });
    }
};
