<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The dashboard writes amounts in Indian grouping (₹34,60,000), like the rest of the app (tester screenshot, 14/10/2026). */
class DashboardIndianGroupingTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function sales(): User
    {
        if ($u = User::where('email', 'priya@company.example')->first()) {
            return $u;
        }
        $u = User::create(['name' => 'Priya', 'email' => 'priya@company.example', 'mobile' => '9822000010', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Sales');
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    public function test_indian_grouping_helper(): void
    {
        $this->assertSame('0', Money::indian(0));
        $this->assertSame('999', Money::indian(999));
        $this->assertSame('1,000', Money::indian(1000));
        $this->assertSame('99,999', Money::indian(99999));
        $this->assertSame('1,00,000', Money::indian(100000));
        $this->assertSame('34,60,000', Money::indian(3460000));
        $this->assertSame('1,83,000.30', Money::indian('183000.3', 2));
        $this->assertSame('12,34,56,789.00', Money::indian(123456789, 2));
        $this->assertSame('-1,00,000', Money::indian(-100000));
        $this->assertSame('₹34,60,000', Money::rupees(3460000));
    }

    public function test_quotation_list_page_pdf_and_email_use_indian_grouping(): void
    {
        $this->actingAs($this->sales())->post('/crm/quotations', $this->newQuotationPayload(['lines' => [['description' => 'Vertical garden', 'qty' => '1', 'rate' => '106660.20']]]))->assertSessionHasNoErrors();
        $q = \App\Models\Quotation::firstOrFail();

        $list = $this->actingAs($this->admin())->get('/crm/quotations/list')->assertOk()->getContent();
        $this->assertStringContainsString('INR 1,06,660.20', $list);
        $this->assertStringNotContainsString('106,660.20', $list);

        $page = $this->actingAs($this->admin())->get("/crm/quotations/{$q->id}")->getContent();
        $this->assertStringContainsString('1,06,660.20', $page);
        $this->assertStringNotContainsString('106,660.20', $page);

        $pdf = view('pdf.quotation', ['q' => $q->fresh(['lines', 'currency', 'unit', 'salesPerson', 'preparer', 'prospect'])])->render();
        $this->assertStringContainsString('1,06,660.20', $pdf);
        $this->assertStringNotContainsString('106,660.20', $pdf);

        $mail = (new \App\Mail\QuotationMail($q->fresh(['currency', 'salesPerson', 'unit']), null))->render();
        $this->assertStringContainsString('Total: INR 1,06,660.20', $mail);
    }

    public function test_crm_report_pdf_groups_money_the_indian_way_and_csv_stays_plain(): void
    {
        $this->crmEnquiry(['enquiry_date' => now()->format('d/m/Y'), 'estimated_value' => '106660.20']);
        $year = \App\Models\FinancialYear::forDate(now());
        $range = ['from' => $year->starts_on->format('d/m/Y'), 'to' => $year->ends_on->format('d/m/Y')];

        $csv = $this->actingAs($this->admin())->get('/reports/crm/enquiries/download?'.http_build_query($range + ['format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('106660.20', $csv);
        $this->assertStringNotContainsString('1,06,660.20', $csv);

        // The PDF is built from these rows: capture what the report view receives.
        $seen = null;
        \Illuminate\Support\Facades\View::composer('pdf.report', function ($view) use (&$seen) {
            $seen = $view->getData();
        });
        foreach (['enquiries', 'lead-ageing'] as $report) {
            $this->actingAs($this->admin())->get("/reports/crm/$report/download?".http_build_query($range + ["format" => "pdf"]))->assertOk();
            $col = array_search('Estimated value', $seen['headers'], true);
            $this->assertSame('1,06,660.20', $seen['rows'][0][$col], $report);
        }
    }

    public function test_dashboard_amounts_use_indian_grouping(): void
    {
        // three leads closing this month with ₹11,53,333.33 each: ₹34,60,000 in all
        foreach (range(1, 3) as $i) {
            $this->crmEnquiry(['enquiry_date' => now()->format('d/m/Y'), 'estimated_value' => '1153333.33',
                'expected_closing_date' => now()->endOfMonth()->format('d/m/Y'), 'next_action_date' => now()->addDays(3)->format('d/m/Y')]);
        }
        $html = $this->actingAs($this->admin())->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('₹34,60,000 estimated', $html);
        $this->assertStringNotContainsString('3,460,000', $html);
        $this->assertStringContainsString('PO value this year: ₹0', $html);

        // the PO pending list writes its totals the same way
        $this->actingAs($this->sales())->post('/crm/quotations', $this->newQuotationPayload(['lines' => [['description' => 'Vertical garden', 'qty' => '1', 'rate' => '183000.30']]]))->assertSessionHasNoErrors();
        $q = \App\Models\Quotation::firstOrFail();
        \App\Models\Quotation::whereKey($q->id)->update(['status' => 'Accepted']);
        \Illuminate\Support\Facades\Cache::flush();
        $html = $this->actingAs($this->admin())->get('/dashboard')->getContent();
        $this->assertStringContainsString('1,83,000.30', $html);
        $this->assertStringNotContainsString('183,000.30', $html);
    }
}
