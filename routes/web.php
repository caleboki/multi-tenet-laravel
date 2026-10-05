<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\Operator\OrganizationController as OperatorOrganizationController;
use App\Http\Controllers\Operator\OrganizationReviewController;
use App\Http\Controllers\Operator\OrganizationSuspensionController;
use App\Http\Controllers\Org\InvitationController;
use App\Http\Controllers\Org\JoinRequestController;
use App\Http\Controllers\Org\MemberController;
use App\Http\Controllers\Org\MembershipController;
use App\Http\Controllers\Org\OrganizationHomeController;
use App\Http\Controllers\Org\SettingsController;
use App\Http\Controllers\Org\SignupLinkController;
use App\Http\Controllers\OrganizationRequestController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/organizations/request', [OrganizationRequestController::class, 'create'])->name('organization-requests.create');
Route::post('/organizations/request', [OrganizationRequestController::class, 'store'])->middleware('throttle:public-forms')->name('organization-requests.store');

Route::get('/join/{signupToken}', [JoinController::class, 'show'])->name('join.show');
Route::post('/join/{signupToken}', [JoinController::class, 'store'])->middleware('throttle:public-forms')->name('join.store');

Route::middleware('throttle:public-forms')->group(function () {
    Route::get('/invitations/{token}', [InvitationAcceptanceController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationAcceptanceController::class, 'accept'])->name('invitations.accept');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

Route::prefix('orgs/{organization:slug}')
    ->name('orgs.')
    ->middleware(['auth', 'verified', 'member'])
    ->scopeBindings()
    ->group(function () {
        Route::get('/', OrganizationHomeController::class)->name('show');
        Route::delete('/membership', [MembershipController::class, 'destroy'])->name('membership.destroy');

        Route::middleware('can:manageMembers,organization')->group(function () {
            Route::get('/members', [MemberController::class, 'index'])->name('members.index');
            Route::get('/members/{membership}', [MemberController::class, 'show'])->whereNumber('membership')->name('members.show');
            Route::patch('/members/{membership}', [MemberController::class, 'update'])->whereNumber('membership')->name('members.update');

            Route::get('/invitations/create', [InvitationController::class, 'create'])->name('invitations.create');
            Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
            Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->whereNumber('invitation')->name('invitations.resend');
            Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->whereNumber('invitation')->name('invitations.destroy');

            Route::get('/join-requests', [JoinRequestController::class, 'index'])->name('join-requests.index');
            Route::post('/join-requests/{membership}/approve', [JoinRequestController::class, 'approve'])->whereNumber('membership')->name('join-requests.approve');
            Route::delete('/join-requests/{membership}', [JoinRequestController::class, 'destroy'])->whereNumber('membership')->name('join-requests.destroy');

            Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::post('/settings/signup-link', [SignupLinkController::class, 'store'])->name('signup-link.store');
        });
    });

Route::prefix('operator')
    ->name('operator.')
    ->middleware(['auth', 'verified', 'can:operate-platform'])
    ->group(function () {
        Route::get('/organizations', [OperatorOrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/organizations/{organization:slug}', [OperatorOrganizationController::class, 'show'])->name('organizations.show');
        Route::post('/organizations/{organization:slug}/approve', [OrganizationReviewController::class, 'approve'])->name('organizations.approve');
        Route::post('/organizations/{organization:slug}/reject', [OrganizationReviewController::class, 'reject'])->name('organizations.reject');
        Route::post('/organizations/{organization:slug}/suspend', [OrganizationSuspensionController::class, 'suspend'])->name('organizations.suspend');
        Route::post('/organizations/{organization:slug}/reinstate', [OrganizationSuspensionController::class, 'reinstate'])->name('organizations.reinstate');
    });
