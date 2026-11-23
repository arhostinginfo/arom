<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\AmcFixtures;
use Tests\TestCase;

/** AMC rights and menu (21/11/2026), and that CRM and Sales are untouched by the AMC module. */
class AmcAccessTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    private const PAGES = ['amc.dashboard', 'amc.clients.index', 'amc.clients.create', 'amc.contract.index', 'amc.contract.create', 'amc.contracts.list',
        'amc.returns.index', 'amc.returns.create', 'amc.employees.index', 'amc.masters.verticals.index', 'amc.masters.planters.index',
        'amc.masters.plants.index', 'amc.masters.types.index', 'amc.clients.export', 'amc.contracts.export', 'amc.returns.export'];

    public function test_permissions_are_in_the_database_and_on_access_rights(): void
    {
        $names = Access::expand(['amc-dashboard.*', 'amc-client.*', 'amc-client-import.*', 'amc-contract.*', 'amc-return.*', 'amc-vertical.*', 'amc-planter.*', 'amc-plant.*', 'amc-type.*', 'amc-employee.*']);
        $this->assertCount(33, $names);
        $this->assertSame(33, Permission::whereIn('name', $names)->count());
        // The AMC group sits before Admin; nothing else moved.
        $groups = array_keys(config('nursery.pages'));
        $this->assertSame(['Dashboard', 'CRM', 'Sales', 'Purchase', 'Accounts', 'AMC', 'Admin', 'Reports'], $groups);
        $this->actingAs($this->admin())->get(route('admin.roles.create'))->assertOk()
            ->assertSee('AMC Client Inventory · Import')->assertSee('Return &amp; Replace', false)->assertSee('AMC Employee master');
    }

    public function test_user_without_amc_rights_gets_403_and_no_amc_menu(): void
    {
        $u = User::create(['name' => 'Sales Only', 'email' => 'sales.only@nursery.local', 'mobile' => '9811100011', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Sales');
        $u->units()->sync([Unit::first()->id]);

        foreach (self::PAGES as $name) {
            $this->actingAs($u)->get(route($name))->assertForbidden();
        }
        $client = $this->amcClient();
        $this->actingAs($u)->get(route('amc.clients.edit', $client))->assertForbidden();
        $this->actingAs($u)->get(route('amc.clients.info', $client))->assertForbidden();
        $this->actingAs($u)->get(route('amc.lookup.clients'))->assertForbidden();
        $this->actingAs($u)->post(route('amc.clients.store'), ['name' => 'X', 'amc_vertical_id' => 1])->assertForbidden();

        $html = $this->actingAs($u)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="tn-amc"', $html);
        $this->assertStringNotContainsString('AMC Client Inventory', $html);
    }

    public function test_menu_shows_only_the_amc_pages_the_user_may_open(): void
    {
        $u = $this->amcUser(['amc-client.view', 'amc-return.*']);
        $html = $this->actingAs($u)->get(route('amc.clients.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<a class="tn on" id="tn-amc"/', $html);
        preg_match('/<div class="tndd" id="tndd-amc".*?<\/div>/s', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString('AMC Client Inventory', $m[0]);
        $this->assertStringContainsString('Return &amp; Replace', $m[0]);
        foreach (['Dashboard', 'AMC Tracker', 'AMC list', 'Plant master', 'AMC Employee master'] as $hidden) {
            $this->assertStringNotContainsString('>'.$hidden.'<', $m[0], $hidden);
        }
        // View only: no Add new, no Import on the client list.
        $this->assertStringNotContainsString(route('amc.clients.create'), $html);
        $this->assertStringNotContainsString('#importLayer', $html);
        $this->actingAs($u)->get(route('amc.clients.create'))->assertForbidden();
        $this->actingAs($u)->get(route('amc.returns.create'))->assertOk();
    }

    public function test_admin_opens_every_amc_page_and_sees_the_full_menu(): void
    {
        $admin = $this->admin();
        foreach (self::PAGES as $name) {
            $name === 'amc.contract.index'
                // AMC Tracker opens a new contract for a user who may add one.
                ? $this->actingAs($admin)->get(route($name))->assertRedirect(route('amc.contract.create'))
                : $this->actingAs($admin)->get(route($name))->assertSuccessful();
        }
        $html = $this->actingAs($admin)->get(route('amc.dashboard'))->getContent();
        preg_match('/<div class="tndd" id="tndd-amc".*?<\/div>/s', $html, $m);
        // (22/11/2026) The five masters are grouped under one "Masters" item.
        foreach (['Dashboard', 'AMC Client Inventory', 'AMC Tracker', 'Return &amp; Replace', 'Masters'] as $item) {
            $this->assertStringContainsString('>'.$item.'<', $m[0], $item);
        }
        // (22/11/2026) the AMC list is now "AMC Report", under the AMC reports and on Reports, not a menu page
        $this->assertStringNotContainsString('>AMC list<', $m[0]);
        $this->assertStringContainsString('AMC reports', $html);
        $this->assertStringContainsString('>AMC Report<', $html);
        $this->assertStringContainsString(e(route('reports.amc.report', ['in' => 'amc'])), $html);
        foreach (['Business segment master', 'Planter master', 'Plant master', 'AMC type master', 'AMC Employee master'] as $item) {
            $this->assertStringNotContainsString('>'.$item.'<', $m[0], $item);
        }
    }

    public function test_crm_and_sales_pages_still_open(): void
    {
        $admin = $this->admin();
        foreach (['/dashboard', '/crm/enquiries/list', '/crm/prospects', '/crm/quotations', '/crm/pos', '/crm/contacts', '/sales/invoices', '/sales/orders', '/admin/roles'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $sales = User::create(['name' => 'Sales Two', 'email' => 'sales.two@nursery.local', 'mobile' => '9811100012', 'password' => 'Secret2026', 'status' => 'Active']);
        $sales->assignRole('Sales');
        $sales->units()->sync([Unit::first()->id]);
        foreach (['/crm/enquiries/list', '/sales/invoices'] as $url) {
            $this->actingAs($sales)->get($url)->assertOk();
        }
    }
}
