<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\UserDatabaseToken;
use Illuminate\Http\Request;

class CliGetTokenByDatabaseNameController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $token = UserDatabaseToken::getTokenByDatabaseName($request->input('database_name'));

        return response()->json([
            'status' => 'success',
            'message' => 'Token fetched successfully',
            'data' => [
                'token' => $token
            ]
        ]);
    }
}

