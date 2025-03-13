<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn() => redirect()->route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::group(['prefix' => 'databases'], function () {
        Route::post('create', [App\Http\Controllers\DashboardController::class, 'createDatabase'])->name('database.create');
        Route::delete('delete/{database}', [App\Http\Controllers\DashboardController::class, 'deleteDatabase'])->name('database.delete');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/api.php';
require __DIR__ . '/trigger.php';
