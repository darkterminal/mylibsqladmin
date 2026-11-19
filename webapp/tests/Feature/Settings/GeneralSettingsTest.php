<?php

use App\Models\Role;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
    Cache::forget('general_settings.timezone');
});

function generalSettingsAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $user;
}

function generalSettingsMember(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Member')->firstOrFail()->id);

    return $user;
}

test('a member cannot view the general settings page', function () {
    $this->actingAs(generalSettingsMember())->get('/settings/general')->assertForbidden();
});

test('a member cannot update the general settings', function () {
    $this->actingAs(generalSettingsMember())
        ->patch('/settings/general', ['timezone' => 'Asia/Jakarta'])
        ->assertForbidden();
});

test('a super admin can view the general settings page', function () {
    $this->actingAs(generalSettingsAdmin())->get('/settings/general')->assertOk();
});

test('a super admin can set the display timezone', function () {
    $response = $this->actingAs(generalSettingsAdmin())->patch('/settings/general', [
        'timezone' => 'Asia/Jakarta',
    ]);

    $response->assertRedirect();
    expect(SystemSettings::timezone())->toBe('Asia/Jakarta');
    expect(SystemSettings::timezoneSource())->toBe('system-settings');
});

test('an unrecognised timezone is rejected and changes nothing', function () {
    $response = $this->actingAs(generalSettingsAdmin())->patch('/settings/general', [
        'timezone' => 'Mars/Olympus_Mons',
    ]);

    $response->assertSessionHasErrors('timezone');
    expect(SystemSettings::timezone())->toBe('UTC');
    expect(SystemSettings::timezoneSource())->not->toBe('system-settings');
});

test('a stored timezone overrides the environment default', function () {
    $this->actingAs(generalSettingsAdmin())->patch('/settings/general', ['timezone' => 'Asia/Tokyo']);
    expect(SystemSettings::timezone())->toBe('Asia/Tokyo');
});
