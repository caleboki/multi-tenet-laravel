<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Org\OrganizationHomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

Route::prefix('orgs/{organization:slug}')
    ->name('orgs.')
    ->middleware(['auth', 'verified', 'member'])
    ->scopeBindings()
    ->group(function () {
        Route::get('/', OrganizationHomeController::class)->name('show');
    });

Route::prefix('operator')
    ->name('operator.')
    ->middleware(['auth', 'verified', 'can:operate-platform'])
    ->group(function () {
        //
    });
