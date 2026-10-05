<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\Org\InvitationController;
use App\Http\Controllers\Org\MemberController;
use App\Http\Controllers\Org\OrganizationHomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('throttle:public-forms')->group(function () {
    Route::get('/invitations/{token}', [InvitationAcceptanceController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationAcceptanceController::class, 'accept'])->name('invitations.accept');
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

        Route::middleware('can:manageMembers,organization')->group(function () {
            Route::get('/members', [MemberController::class, 'index'])->name('members.index');
            Route::get('/members/{membership}', [MemberController::class, 'show'])->whereNumber('membership')->name('members.show');

            Route::get('/invitations/create', [InvitationController::class, 'create'])->name('invitations.create');
            Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
            Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->whereNumber('invitation')->name('invitations.resend');
            Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->whereNumber('invitation')->name('invitations.destroy');
        });
    });

Route::prefix('operator')
    ->name('operator.')
    ->middleware(['auth', 'verified', 'can:operate-platform'])
    ->group(function () {
        //
    });
