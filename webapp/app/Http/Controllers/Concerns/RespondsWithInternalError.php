<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

trait RespondsWithInternalError
{
    /**
     * The identical "catch ($e)" tail across the JSON endpoints:
     * ModelNotFoundException maps to 404, anything else to 500.
     */
    protected function jsonNotFoundOrError(\Throwable $e, string $message = 'Internal server error'): JsonResponse
    {
        // Validation failures are normal client errors, not server faults.
        // Rethrowing lets Laravel answer with a proper 422 (JSON field errors
        // or a back-link redirect) instead of a generic 500 that hides the
        // cause from both the user and the log reader.
        if ($e instanceof ValidationException) {
            throw $e;
        }

        return response()->json([
            'success' => false,
            'message' => $message,
        ], $e instanceof ModelNotFoundException ? 404 : 500);
    }

    /**
     * The identical "catch ($e)" tail across the Inertia/HTML endpoints.
     */
    protected function backWithInternalError(string $message = 'Internal server error'): RedirectResponse
    {
        return redirect()->back()->with('error', $message);
    }
}
