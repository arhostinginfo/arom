<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthActivity
{
    public function handleLogin(Login $event): void
    {
        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        ActivityLogger::log('auth.login', $event->user, $event->user->name.' logged in', null, $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user) {
            ActivityLogger::log('auth.logout', $event->user, $event->user->name.' logged out', null, $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $email = (string) ($event->credentials['email'] ?? '');
        $user = $event->user instanceof User ? $event->user : null;
        $reason = $user ? 'wrong password' : 'unknown email';
        ActivityLog::create([
            'user_id' => null,
            'user_name' => null,
            'action' => 'auth.failed',
            'subject_type' => $user?->getMorphClass(),
            'subject_id' => $user?->id,
            'subject_label' => $user ? $user->name.' ('.$user->email.')' : $email,
            'description' => 'Login failed for '.$email.' ('.$reason.')',
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
        ]);
    }
}
