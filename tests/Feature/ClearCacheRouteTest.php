<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** /admin/clear-cache (11/11/2026): clears every cache from the browser, for an Admin only. */
class ClearCacheRouteTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_only_an_admin_can_clear_every_cache_from_the_browser(): void
    {
        $this->get('/admin/clear-cache')->assertRedirect(route('login'));

        $admin = User::where('email', 'admin@nursery.local')->first();
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Rahul Kulkarni', 'email' => 'rahul@company.example', 'mobile' => '+91 98220 00002',
            'password' => 'Sales2026x', 'password_confirmation' => 'Sales2026x', 'role' => 'Sales',
            'units' => [Unit::first()->id], 'status' => 'Active', 'permissions' => [],
        ])->assertSessionHasNoErrors();
        $sales = User::where('email', 'rahul@company.example')->firstOrFail();
        $this->actingAs($sales)->get('/admin/clear-cache')->assertForbidden();

        Cache::put('nursery-test-key', 'x', 600);
        $res = $this->actingAs($admin)->get('/admin/clear-cache')->assertOk();
        $this->assertStringStartsWith('text/plain', $res->headers->get('content-type'));
        foreach (['All caches cleared.', 'php artisan optimize:clear', 'php artisan permission:cache-reset'] as $s) {
            $this->assertStringContainsString($s, $res->getContent(), $s);
        }
        $this->assertStringNotContainsString('exit code', $res->getContent());
        $this->assertNull(Cache::get('nursery-test-key'));
    }
}
