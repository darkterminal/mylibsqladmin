<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

trait RespondsWithInternalError
{
    /**
     * The identical "catch ($e)" tail across the JSON endpoints:
     * ModelNotFoundException maps to 404, anything else to 500.
     */
    protected function jsonNotFoundOrError(\Throwable $e, string $message = 'Internal server error'): JsonResponse
    {
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
