<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHP reads at most max_input_vars form fields (1,000 by default) and silently drops the rest, so a very
 * large form would reach the app half-read (full test B1). When a form arrives with that many fields it
 * may have been cut off, so it is refused with a clear message and nothing is saved.
 */
class EnsureFormIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true) && self::fieldCount($request->request->all()) >= self::limit()) {
            $message = 'The form has too many fields for the server to read in full, so nothing was saved. Split it into smaller parts, for example fewer items per quotation.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['form' => [$message]]], 422);
            }

            return back()->withErrors(['form' => $message]);
        }

        return $next($request);
    }

    /** The server's limit (php.ini max_input_vars), or config nursery.max_input_vars when set. */
    public static function limit(): int
    {
        return (int) (config('nursery.max_input_vars') ?: ini_get('max_input_vars') ?: 1000);
    }

    private static function fieldCount(array $input): int
    {
        $n = 0;
        array_walk_recursive($input, function () use (&$n) {
            $n++;
        });

        return $n;
    }
}
