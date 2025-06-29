<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CliLoginController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string'
        ]);

        $user = User::where('username', $validated['username'])->first();

        // Verify credentials
        if (!$user || !Hash::check((string) $validated['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Create token with 30-day expiration
        $token = $user->createToken(
            name: 'cli-auth-token',
            expiresAt: now()->addDays(30)
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at->toIso8601String(),
            'expires_in' => $token->accessToken->expires_at->diffInSeconds(now())
        ]);
    }
}
