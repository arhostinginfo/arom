<?php

namespace Tests\Feature;

use App\Enums\QuotationStatus;
use App\Models\LeadSource;
use App\Models\Quotation;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fri 23/10/2026: every Add new / New / Add and Upload / Import button is green (class "btn add", public/css/app.css),
 * so users find it easily; the quotation status bar's Email quotation and Mark as sent are green too.
 * Save, Delete, Cancel and the other buttons keep their own colours.
 */
class GreenAddButtonsTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\CrmFixtures;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    /** CrmFixtures::newQuotationPayload() needs a sales person. */
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

    /** The class attribute of each <a>/<button> whose visible text (tags stripped) is exactly $label. */
    private function classesOf(string $html, string $label): array
    {
        preg_match_all('#<(a|button)\b([^>]*)>(.*?)</\1>#s', $html, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $el) {
            $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($el[3]))));
            if ($text === $label && preg_match('/\bclass="([^"]*)"/', $el[2], $c)) {
                $out[] = $c[1];
            }
        }

        return $out;
    }

    private function isGreen(string $class): bool
    {
        $c = preg_split('/\s+/', trim($class));

        return in_array('btn', $c, true) && in_array('add', $c, true);
    }

    private function assertGreen(string $html, string $label): void
    {
        $classes = $this->classesOf($html, $label);
        $this->assertNotEmpty($classes, "No \"$label\" button on the page.");
        foreach ($classes as $class) {
            $this->assertTrue($this->isGreen($class), "\"$label\" should be green (btn add), has class=\"$class\".");
        }
    }

    private function assertNotGreen(string $html, string $label): void
    {
        $classes = $this->classesOf($html, $label);
        $this->assertNotEmpty($classes, "No \"$label\" button on the page.");
        foreach ($classes as $class) {
            $this->assertFalse($this->isGreen($class), "\"$label\" must keep its own colour, has class=\"$class\".");
        }
    }

    private function page(string $url): string
    {
        return $this->actingAs($this->admin())->get($url)->assertOk()->getContent();
    }

    public function test_app_css_has_the_green_add_rule_with_readable_colours(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('--add-bg:#15803D', $css);
        $this->assertStringContainsString('--add-text:#FFFFFF', $css);
        $this->assertMatchesRegularExpression('/\.btn\.add,[^{]*\.btn\.iob\.iob-import\{background:var\(--add-bg\);color:var\(--add-text\)/', $css);
        $this->assertMatchesRegularExpression('/\.btn\.add:hover[^{]*\{background:var\(--add-bg-hover\)/', $css);
        $this->assertMatchesRegularExpression('/\.btn\.add:focus-visible[^{]*\{outline:2px solid/', $css);
        // it must not resize buttons: .btn.sm keeps its height
        $rule = preg_match('/\.btn\.add,a\.btn\.add,\.btn\.iob\.iob-import\{([^}]*)\}/', $css, $m) ? $m[1] : '';
        $this->assertStringNotContainsString('height', $rule);
        $this->assertStringNotContainsString('padding', $rule);
        // app.css loads after nursery.css, so .btn.add wins over .btn.pri / .btn.sec at equal specificity
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertLessThan(strpos($layout, "css/app.css'"), strpos($layout, "css/nursery.css'"));
    }

    public function test_crm_lists_have_a_green_add_button(): void
    {
        $this->assertGreen($this->page(route('crm.enquiries.list')), 'Add enquiry');
        $this->assertGreen($this->page(route('crm.site-visits.list')), 'Add site visit');
        $this->assertGreen($this->page(route('crm.contacts.index')), 'Add contact');
    }

    public function test_enquiry_page_add_new_prospect_add_contact_and_upload_are_green_save_is_not(): void
    {
        $e = $this->crmEnquiry();
        $html = $this->page(route('crm.enquiries.show', $e));

        $this->assertGreen($html, 'Add new');      // entry bar
        $this->assertGreen($html, 'Add');          // next to Prospect
        $this->assertGreen($html, 'Add contact');  // contacts box
        $this->assertGreen($html, 'Upload');       // documents
        $this->assertNotGreen($html, 'Save changes');
        $this->assertNotGreen($html, 'Save prospect'); // prospect drawer keeps its primary Save
        $this->assertNotGreen($html, 'Cancel');
        $this->assertDoesNotMatchRegularExpression('/class="btn (?:pri|sec|gh|dan)[^"]*\badd\b/', $html);
    }

    public function test_prospect_page_import_buttons_are_green(): void
    {
        $p = $this->prospectFixture();
        $html = $this->page(route('crm.prospects.show', $p));

        $this->assertGreen($html, 'Add new');
        $this->assertGreen($html, 'Import'); // the submit button in the import layer
        $this->assertMatchesRegularExpression('/class="btn iob iob-import"/', $html); // icon-only Import (solid green by CSS)
        $this->assertNotGreen($html, 'Save changes');
        $this->assertNotGreen($html, 'Download template');
    }

    public function test_quotation_status_bar_email_and_mark_as_sent_are_green_approve_and_update_status_are_not(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('crm.quotations.store'), $this->newQuotationPayload())->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('crm.quotations.submit', $q))->assertSessionHasNoErrors();
        $this->assertSame(QuotationStatus::Approved, $q->fresh()->status);

        $html = $this->page(route('crm.quotations.show', $q));
        $this->assertGreen($html, 'Email quotation');
        $this->assertGreen($html, 'Mark as sent');
        $this->assertNotGreen($html, 'Send email');

        $this->actingAs($admin)->post(route('crm.quotations.sent', $q))->assertSessionHasNoErrors();
        $html = $this->page(route('crm.quotations.show', $q));
        $this->assertGreen($html, 'Email quotation');
        $this->assertNotGreen($html, 'Update status');

        $this->actingAs($admin)->post(route('crm.quotations.customer-status', $q), ['status' => 'Accepted'])->assertSessionHasNoErrors();
        $this->assertGreen($this->page(route('crm.quotations.show', $q)), 'Enter PO / agreement');
    }

    public function test_quotation_awaiting_approval_keeps_approve_as_primary(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('crm.quotations.store'), $this->newQuotationPayload(['lines' => [['description' => 'Large job', 'qty' => '1', 'rate' => '250000']]]))->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('crm.quotations.submit', $q))->assertSessionHasNoErrors();
        $this->assertSame(QuotationStatus::InternalApproval, $q->fresh()->status);

        $this->assertNotGreen($this->page(route('crm.quotations.show', $q)), 'Approve');
    }

    public function test_sales_lists_and_pages(): void
    {
        $this->assertGreen($this->page(route('sales.orders.list')), 'New sales order');
        $this->assertGreen($this->page(route('sales.invoices.list')), 'New tax invoice');

        $html = $this->page(route('sales.orders.create'));
        $this->assertGreen($html, 'Add item');
        $this->assertGreen($html, 'Import');
        $this->assertNotGreen($html, 'Save sales order');

        $html = $this->page(route('sales.invoices.create'));
        $this->assertGreen($html, 'Add item');
        $this->assertNotGreen($html, 'Fill every PO balance');
    }

    public function test_admin_lists_have_green_add_buttons(): void
    {
        $this->assertGreen($this->page(route('admin.users.index')), 'Add user');
        $this->assertGreen($this->page(route('admin.roles.index')), 'Create role');
        $this->assertGreen($this->page(route('admin.employees.index')), 'Add employee');
        $this->assertGreen($this->page(route('admin.items.index')), 'Add item');
        $this->assertGreen($this->page(route('admin.parties.index')), 'Add party');
    }

    public function test_master_lists_add_is_green_but_save_changes_when_editing_is_not(): void
    {
        $this->assertGreen($this->page(route('admin.masters.show', 'sources')), 'Add');
        $source = LeadSource::firstOrFail();
        $html = $this->page(route('admin.masters.show', ['master' => 'sources', 'edit' => $source->id]));
        $this->assertNotGreen($html, 'Save changes');
        $this->assertSame([], array_filter($this->classesOf($html, 'Add'), fn ($c) => $this->isGreen($c)));

        $this->assertGreen($this->page(route('admin.sales-masters.show', 'tax-structures')), 'Add');
        $tax = TaxStructure::firstOrFail();
        $this->assertNotGreen($this->page(route('admin.sales-masters.show', ['master' => 'tax-structures', 'edit' => $tax->id])), 'Save changes');
    }

    public function test_save_and_delete_buttons_are_never_green_in_views(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $f) {
            if (! str_ends_with($f->getFilename(), '.blade.php')) {
                continue;
            }
            preg_match_all('#<(a|button)\b[^>]*class="[^"]*\bbtn\b[^"]*\badd\b[^"]*"[^>]*>(.*?)</\1>#s', file_get_contents($f->getPathname()), $m);
            foreach ($m[2] as $inner) {
                $text = trim(strip_tags($inner));
                $this->assertDoesNotMatchRegularExpression('/^(Save|Delete|Cancel|Remove|Close|Approve|Reject)\b/i', $text, $f->getPathname().': "'.$text.'" must not be green.');
            }
        }
    }
}
