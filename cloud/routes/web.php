<?php

use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// --- vizitatori ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/parola', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/parola', [PasswordController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/parola/{token}', [PasswordController::class, 'edit'])->name('password.reset');
    Route::post('/parola/noua', [PasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
    Route::get('/setup', [SetupController::class, 'show'])->name('setup');
    Route::post('/setup', [SetupController::class, 'store'])->middleware('throttle:5,1');
});

// --- autentificat, înainte de 2FA ---
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/2fa', [TwoFactorController::class, 'challenge'])->name('2fa.challenge');
    Route::post('/2fa', [TwoFactorController::class, 'verify'])->middleware('throttle:10,1');
    Route::get('/2fa/activare', [TwoFactorController::class, 'setup'])->name('2fa.setup');
    Route::post('/2fa/activare', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1');
});

// --- aplicația ---
Route::middleware(['auth', '2fa'])->group(function () {
    Route::get('/', [HomeController::class, 'home'])->name('home');

    // echipa VITIM
    Route::prefix('admin')->name('admin.')->middleware('platform')->group(function () {
        Route::get('/', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::middleware('platform:admin')->group(function () {
            Route::get('/clienti/nou', [OrganizationController::class, 'create'])->name('organizations.create');
            Route::post('/clienti', [OrganizationController::class, 'store'])->name('organizations.store');
        });
        Route::prefix('clienti/{organization}')->middleware('org')->group(function () {
            Route::get('/', [OrganizationController::class, 'show'])->name('organizations.show');
            Route::middleware('platform:admin')->group(function () {
                Route::post('/site-uri', [SiteController::class, 'store'])->name('sites.store');
                Route::post('/site-uri/{site}/chei', [SiteController::class, 'rotate'])->whereNumber('site')->name('sites.rotate');
            });
        });
    });

    // portalul clientului
    Route::prefix('app/{organization}')->name('portal.')->middleware('org')->group(function () {
        Route::get('/', [HomeController::class, 'portal'])->name('home');
    });
});
