<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Horizontal menu bar (22/10/2026): every top-level menu the user may open sits on the top bar (no breadcrumbs), each
 * menu drops down its pages, and the side menu shows only the reports of the selected menu.
 */
class MenuBarTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const ALL = ['dashboard', 'crm', 'sales', 'purchase', 'accounts', 'admin', 'reports'];

    private function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    private function userWith(array $perms, string $email): User
    {
        $role = Role::findOrCreate('Menu test '.$email, 'web');
        $role->syncPermissions(Access::expand($perms));
        $u = User::create(['name' => 'Menu Tester', 'email' => $email, 'mobile' => '98'.substr((string) crc32($email), 0, 8), 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    private function page(User $u, string $url): string
    {
        return $this->actingAs($u)->get($url)->assertOk()->getContent();
    }

    private function topnav(string $html): string
    {
        $this->assertSame(1, preg_match('/<nav class="topnav" id="topnav" aria-label="Main menu">.*?<\/nav>/s', $html, $m), 'menu bar missing');

        return $m[0];
    }

    private function side(string $html): string
    {
        $this->assertSame(1, preg_match('/<aside class="side" id="side"[^>]*>.*?<\/aside>/s', $html, $m), 'side menu missing');

        return $m[0];
    }

    /** Keys of the menus on the bar, in order (the More button is a <button>, not counted). */
    private function menus(string $html): array
    {
        preg_match_all('/<a class="tn(?: on)?" id="tn-([a-z]+)"/', $this->topnav($html), $m);

        return $m[1];
    }

    private function selected(string $html): array
    {
        preg_match_all('/<a class="tn on" id="tn-([a-z]+)"/', $this->topnav($html), $bar);
        preg_match('/<nav\s+class="scoped" data-selected="([a-z]+)"/', $this->side($html), $side);

        return ['bar' => $bar[1], 'side' => $side[1] ?? null];
    }

    /** Titles of the report links the side menu shows on a wide screen (class repl, inside the open menu). */
    private function sideReports(string $html): array
    {
        preg_match_all('/<a class="repl(?: on)?" href="[^"]*"[^>]*>([^<]+)<\/a>/', $this->side($html), $m);

        return array_map('html_entity_decode', $m[1]);
    }

    public function test_admin_sees_all_seven_menus_in_order_and_no_breadcrumbs(): void
    {
        $html = $this->page($this->admin(), '/dashboard');

        $this->assertSame(self::ALL, $this->menus($html));
        $this->assertStringNotContainsString('id="crumbs"', $html);
        $this->assertStringNotContainsString('class="crumbs"', $html);
        // More starts hidden and holds one (hidden) group per menu, for js/app.js to fill.
        $nav = $this->topnav($html);
        $this->assertStringContainsString('<li class="tnw tnmore" hidden>', $nav);
        foreach (self::ALL as $k) {
            $this->assertStringContainsString('class="tnmg" data-for="'.$k.'" role="none" hidden', $nav);
        }
    }

    public function test_menus_with_pages_have_accessible_drop_downs(): void
    {
        $nav = $this->topnav($this->page($this->admin(), '/crm/enquiries/list'));

        foreach (['crm', 'sales', 'purchase', 'accounts', 'admin'] as $k) {
            $this->assertMatchesRegularExpression('/id="tn-'.$k.'" href="[^"]+"[^>]*aria-haspopup="true" aria-expanded="false" aria-controls="tndd-'.$k.'" data-tndd/', $nav, $k);
            $this->assertStringContainsString('<div class="tndd" id="tndd-'.$k.'" role="menu" aria-labelledby="tn-'.$k.'" hidden>', $nav, $k);
        }
        // Dashboard and Reports have no pages of their own: plain links, no drop-down.
        foreach (['dashboard', 'reports'] as $k) {
            $this->assertMatchesRegularExpression('/<a class="tn(?: on)?" id="tn-'.$k.'" href="[^"]+"\s*>/', $nav, $k);
            $this->assertStringNotContainsString('id="tndd-'.$k.'"', $nav);
        }
        $this->assertStringContainsString('<button class="tn" id="tn-more" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="tndd-more" data-tndd>', $nav);
        $this->assertStringContainsString('<div class="tndd end" id="tndd-more" role="menu" aria-labelledby="tn-more" hidden>', $nav);

        // The current page is marked in its drop-down (and in More), the open menu on the bar.
        $this->assertMatchesRegularExpression('/<a class="tn on" id="tn-crm"[^>]*aria-current="true"/', $nav);
        $this->assertMatchesRegularExpression('/<a role="menuitem" href="[^"]*"\s+class="on" aria-current="page"\s*>1 Enquiry \/ Lead<\/a>/', $nav);
        $this->assertSame(1, preg_match_all('/<a role="menuitem" href="[^"]*"\s+class="on" aria-current="page"\s*>/', $nav));
        $this->assertMatchesRegularExpression('/<a role="menuitem" class="tnms on" href="[^"]*"\s+aria-current="page"\s*>1 Enquiry \/ Lead<\/a>/', $nav);
    }

    public function test_one_menu_is_selected_per_page(): void
    {
        $admin = $this->admin();
        $cases = [
            '/dashboard' => 'dashboard',
            '/crm/enquiries/list' => 'crm',
            '/crm/quotations/list?view=sent' => 'crm',
            '/admin/users' => 'admin',
            // GRN entry is built: /app/grn redirects there (asserted in GrnTest), so the built page is checked.
            route('purchase.grns.index') => 'purchase',
            route('app.page', ['page' => 'reports']) => 'reports',
            route('reports.crm.prospects') => 'reports',
            route('reports.crm.prospects', ['in' => 'crm']) => 'crm',
            route('reports.purchase.po-summary', ['in' => 'purchase']) => 'purchase',
        ];
        foreach ($cases as $url => $key) {
            $sel = $this->selected($this->page($admin, $url));
            $this->assertSame([$key], $sel['bar'], $url);
            $this->assertSame($key, $sel['side'], $url);
        }
    }

    public function test_side_menu_shows_only_the_selected_menus_reports(): void
    {
        $admin = $this->admin();
        $crm = config('nursery.reports.CRM');
        $sales = config('nursery.reports.Sales');

        // A CRM page: the CRM reports (opened "in" CRM), not another menu's reports; its pages are left to the bar.
        $html = $this->page($admin, '/crm/enquiries/list');
        $side = $this->side($html);
        $this->assertSame($crm, $this->sideReports($html));
        $this->assertStringContainsString('<div class="rgrp">CRM reports</div>', $side);
        $this->assertStringContainsString(route('reports.crm.prospects', ['in' => 'crm']), $side);
        $this->assertEmpty(array_intersect(array_diff($sales, $crm), $this->sideReports($html)));
        $this->assertMatchesRegularExpression('/<a class="pg on" href="[^"]*"\s+aria-current="page"\s*>1 Enquiry \/ Lead<\/a>/', $side);
        $this->assertStringNotContainsString('class="norep"', $side);

        // A Sales page: the Sales reports only.
        $html = $this->page($admin, route('sales.invoices.index'));
        $this->assertSame($sales, $this->sideReports($html));

        // A report opened from CRM keeps CRM selected and marks that report.
        $html = $this->page($admin, route('reports.crm.prospects', ['in' => 'crm']));
        $this->assertSame($crm, $this->sideReports($html));
        $this->assertMatchesRegularExpression('/<a class="repl on" href="[^"]*"\s+aria-current="page"\s*>Prospects<\/a>/', $this->side($html));

        // Reports: every group the user may open, each a folding group.
        $html = $this->page($admin, route('app.page', ['page' => 'reports']));
        $side = $this->side($html);
        foreach (['CRM', 'Sales', 'Purchase', 'Accounts'] as $i => $g) {
            $this->assertMatchesRegularExpression('/<button class="rgrp rgt" type="button" aria-expanded="true" aria-controls="rgl-'.$i.'">'.$g.'</', $side, $g);
        }
        $this->assertCount(array_sum(array_map('count', config('nursery.reports'))), $this->sideReports($html)); // no PO register since 02/11/2026

        // Dashboard and Admin have no reports.
        foreach (['/dashboard', '/admin/users'] as $url) {
            $html = $this->page($admin, $url);
            $this->assertStringContainsString('<p class="norep">No reports for this menu.</p>', $this->side($html), $url);
            $this->assertSame([], $this->sideReports($html), $url);
            $this->assertStringContainsString('class="subs nore"', $this->side($html), $url);
        }
    }

    public function test_crm_only_user_sees_only_the_menus_they_may_open(): void
    {
        $u = $this->userWith(['dashboard.view', 'enquiry.*', 'site-visit.*', 'quotation.*', 'customer-po.*', 'prospect.*', 'contact.*', 'reports-crm.view'], 'crm.only@nursery.local');

        $html = $this->page($u, '/crm/enquiries/list');
        $this->assertSame(['dashboard', 'crm', 'reports'], $this->menus($html));
        $nav = $this->topnav($html);
        foreach (['sales', 'purchase', 'accounts', 'admin'] as $k) {
            $this->assertStringNotContainsString('tn-'.$k, $nav, $k);
            $this->assertStringNotContainsString('data-for="'.$k.'"', $nav, $k);
        }
        $this->assertStringNotContainsString('Users list', $nav);
        $this->assertSame(config('nursery.reports.CRM'), $this->sideReports($html));

        // The Reports page lists only the CRM group.
        $html = $this->page($u, route('app.page', ['page' => 'reports']));
        $this->assertSame(['reports'], $this->selected($html)['bar']);
        $this->assertSame(config('nursery.reports.CRM'), $this->sideReports($html));
        $this->assertStringNotContainsString('>Sales<svg', $this->side($html));

        $this->actingAs($u)->get('/admin/users')->assertForbidden();
    }

    public function test_sales_role_sees_no_admin_purchase_or_accounts_menu(): void
    {
        $u = User::create(['name' => 'Sales Menu', 'email' => 'sales.menu@nursery.local', 'mobile' => '9811122233', 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole('Sales');
        $u->units()->sync([Unit::first()->id]);

        $html = $this->page($u, '/dashboard');
        $this->assertSame(['dashboard', 'crm', 'sales', 'reports'], $this->menus($html));
        $this->assertSame(['dashboard'], $this->selected($html)['bar']);
    }
}
