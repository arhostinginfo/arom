<?php

namespace App\Providers;

use App\Support\Roles;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Support\Menu;
use App\Support\WorkContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Financial years already looked up in this request (FinancialYear::forDate). Scoped, so each request starts empty.
        $this->app->scoped('nursery.financial-years', fn () => new \ArrayObject());
        // Page setup and the optional-part switches: read once per request, then from this request's copy.
        $this->app->scoped(\App\Services\PageSetupService::class);
        $this->app->scoped(\App\Services\ModuleSettings::class);
        // Admin > Config settings, e.g. the maximum upload size (22/10/2026).
        $this->app->scoped(\App\Services\AppSettings::class);
    }

    public function boot(): void
    {
        // Short, stable names in polymorphic columns (activity_logs.subject_type, model_has_roles.model_type).
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => \Spatie\Permission\Models\Role::class,
            'permission' => \Spatie\Permission\Models\Permission::class,
            'prospect' => \App\Models\Prospect::class,
            'quotation' => \App\Models\Quotation::class,
            // CRM changes (13/10/2026).
            'enquiry' => \App\Models\Enquiry::class,
            'site_visit' => \App\Models\SiteVisit::class,
            'customer_po' => \App\Models\CustomerPo::class,
            'activity' => \App\Models\Activity::class,
            'document' => \App\Models\Document::class,
            'employee' => \App\Models\Employee::class,
            'lead_source' => \App\Models\LeadSource::class,
            'service_category' => \App\Models\ServiceCategory::class,
            'industry_type' => \App\Models\IndustryType::class,
            'city' => \App\Models\City::class,
            'page_setting' => \App\Models\PageSetting::class,
            'approval_rule' => \App\Models\ApprovalRule::class,
            // Contact / POC (14/10/2026).
            'contact' => \App\Models\Contact::class,
            // Sales module and its masters (15/10/2026).
            'party' => \App\Models\Party::class,
            'item' => \App\Models\Item::class,
            'company' => \App\Models\Company::class,
            'tax_structure' => \App\Models\TaxStructure::class,
            'hsn_sac_code' => \App\Models\HsnSacCode::class,
            'uom' => \App\Models\Uom::class,
            'item_category' => \App\Models\ItemCategory::class,
            'transporter' => \App\Models\Transporter::class,
            'sales_order' => \App\Models\SalesOrder::class,
            'sales_invoice' => \App\Models\SalesInvoice::class,
            'stock_transfer' => \App\Models\StockTransfer::class,
            // Purchase order (27/10/2026).
            'purchase_order' => \App\Models\PurchaseOrder::class,
            // GRN entry (27/10/2026).
            'grn' => \App\Models\Grn::class,
        ]);


        // Behind a load balancer the real client IP and https come from these proxies only (decision A10, docs/PRODUCTION.md).
        // Read from config, not env(), so it still works after "php artisan config:cache".
        $proxies = config('app.trusted_proxies');
        if ($proxies) {
            TrustProxies::at($proxies === ['*'] ? '*' : $proxies);
        }

        // Downloads, PDFs and imports: 10 a minute per user (decision C10).
        RateLimiter::for('downloads', fn (Request $request) => Limit::perMinute(10)->by('u'.($request->user()?->id ?: $request->ip())));
        // Document uploads have their own limit (CRM upload check 18/10/2026): 10 a minute shared with report downloads
        // stopped someone adding a batch of site photos.
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(60)->by('u'.($request->user()?->id ?: $request->ip())));

        // @module('boq') … @endmodule: shows its content only while that optional part of the CRM flow is on.
        \Illuminate\Support\Facades\Blade::if('module', fn (string $module) => \App\Support\Modules::on($module));

        // One place for the optional-part switches (architecture review P1): every right on a page of a part that is
        // switched off ("site-visit.view" while Site visit is off) is refused for every role, Admin included. Routes
        // (permission: middleware), menus, @can, alerts, lookups and documents all ask the gate, so they follow it,
        // and Sales, Purchase or Accounts pages tagged with a 'module' in config get it with no code of their own.
        Gate::before(fn ($user, string $ability) => ($m = \App\Support\Access::moduleOf($ability)) && ! \App\Support\Modules::on($m) ? false : null);

        // Admin sees and does everything (decision D2).
        Gate::before(fn ($user) => $user->hasRole(Roles::ADMIN) ? true : null);

        // Page permissions (spatie/laravel-permission's own check, registered here so it runs after the two above;
        // config permission.register_permission_check_method is false).
        Gate::before(fn ($user, string $ability) => method_exists($user, 'checkPermissionTo') ? ($user->checkPermissionTo($ability) ?: null) : null);

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $data = $view->getData();

            $menu = Menu::build($user, request(), $data['preview'] ?? null);

            $view->with([
                'menu' => $menu,
                // Horizontal menu bar and the menu it selects for the side menu (22/10/2026).
                'menuBar' => Menu::bar($menu),
                'userUnits' => $user->units,
                'currentUnit' => WorkContext::unit($user),
                'financialYear' => WorkContext::financialYear()->label(),
                // Notifications & alerts on the top bar and the red follow-up pop up (CRM changes).
                'alerts' => app(\App\Services\CrmAlerts::class)->for($user, WorkContext::unit($user)),
            ]);
        });
    }
}
