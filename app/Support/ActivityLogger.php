<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Record one operation. $changes holds what changed, e.g.
     * ['name' => ['old' => 'A', 'new' => 'B']] or ['added' => [...], 'removed' => [...]].
     */
    public static function log(string $action, ?Model $subject, string $description, ?array $changes = null, ?User $actor = null): ActivityLog
    {
        $actor ??= auth()->user();
        $request = request();

        return ActivityLog::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject ? self::label($subject) : null,
            'description' => $description,
            'changes' => $changes ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
        ]);
    }

    /** Field-by-field difference between two arrays, for the log's "changes" column. */
    public static function diff(array $old, array $new): array
    {
        $out = [];
        foreach ($new as $key => $value) {
            $before = $old[$key] ?? null;
            if ($before != $value) {
                $out[$key] = ['old' => $before, 'new' => $value];
            }
        }

        return $out;
    }

    /** Added and removed items between two lists. */
    public static function listDiff(array $old, array $new): array
    {
        return array_filter([
            'added' => array_values(array_diff($new, $old)),
            'removed' => array_values(array_diff($old, $new)),
        ]);
    }

    private static function label(Model $subject): string
    {
        return match (true) {
            $subject instanceof User => $subject->name.' ('.$subject->email.')',
            method_exists($subject, 'activityLabel') => $subject->activityLabel(),
            default => (string) ($subject->name ?? $subject->getKey()),
        };
    }
}
