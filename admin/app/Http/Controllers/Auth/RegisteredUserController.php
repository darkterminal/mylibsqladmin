<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $invitation = session('valid_invitation');
        $role = Role::getRoleName($invitation->permission_level);

        $user = User::create([
            'name' => $validate['name'],
            'username' => $validate['username'],
            'email' => $validate['email'],
            'password' => Hash::make($validate['password']),
            'role' => $invitation ? $role : 'Member'
        ]);

        $role = Role::where('name', $role)->first();
        $user->roles()->attach($role);

        if ($invitation) {
            $user->teams()->attach($invitation->team_id, [
                'permission_level' => $invitation->permission_level,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $invitation->delete();
            session()->forget('valid_invitation');
        }

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}
