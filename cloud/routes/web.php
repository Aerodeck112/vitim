<?php

use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Api\V1 as Api;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SetupController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\HomeController;
use App\Http\Middleware\LogApiRequest;
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

// --- API intern v1 (sesiune + CSRF; vezi docs/VITIM-AI-API.md) ---
Route::prefix('api/v1')->name('api.')->middleware(['auth', '2fa', 'throttle:api-v1', LogApiRequest::class])->group(function () {
    Route::prefix('admin')->name('admin.')->middleware('platform')->group(function () {
        Route::get('organizations', [Api\Admin\OrganizationController::class, 'index'])->name('organizations.index');
        Route::post('organizations', [Api\Admin\OrganizationController::class, 'store'])->name('organizations.store');
        Route::get('organizations/{organization}', [Api\Admin\OrganizationController::class, 'show'])->whereNumber('organization')->name('organizations.show');
        Route::patch('organizations/{organization}', [Api\Admin\OrganizationController::class, 'update'])->whereNumber('organization')->name('organizations.update');
    });

    Route::prefix('orgs/{organization}')->middleware('org')->group(function () {
        $id = '[0-9]+';
        Route::get('sites', [Api\SiteController::class, 'index'])->middleware('can:view_reports')->name('sites.index');
        Route::post('sites', [Api\SiteController::class, 'store'])->middleware('can:manage_sites')->name('sites.store');
        Route::get('sites/{site}', [Api\SiteController::class, 'show'])->middleware('can:view_reports')->where('site', $id)->name('sites.show');
        Route::patch('sites/{site}', [Api\SiteController::class, 'update'])->middleware('can:manage_sites')->where('site', $id)->name('sites.update');
        Route::post('sites/{site}/rotate-keys', [Api\SiteController::class, 'rotateKeys'])->middleware('can:manage_sites')->where('site', $id)->name('sites.rotate');

        Route::get('agents', [Api\AgentController::class, 'index'])->middleware('can:view_reports')->name('agents.index');
        Route::post('agents', [Api\AgentController::class, 'store'])->middleware('can:manage_agents')->name('agents.store');
        Route::get('agents/{agent}', [Api\AgentController::class, 'show'])->middleware('can:view_reports')->where('agent', $id)->name('agents.show');
        Route::patch('agents/{agent}', [Api\AgentController::class, 'update'])->middleware('can:manage_agents')->where('agent', $id)->name('agents.update');
        Route::delete('agents/{agent}', [Api\AgentController::class, 'destroy'])->middleware('can:manage_agents')->where('agent', $id)->name('agents.destroy');

        Route::get('users', [Api\MemberController::class, 'index'])->middleware('can:manage_users')->name('users.index');
        Route::post('users', [Api\MemberController::class, 'store'])->middleware('can:manage_users')->name('users.store');
        Route::patch('users/{member}', [Api\MemberController::class, 'update'])->middleware('can:manage_users')->where('member', $id)->name('users.update');
        Route::delete('users/{member}', [Api\MemberController::class, 'destroy'])->middleware('can:manage_users')->where('member', $id)->name('users.destroy');

        Route::get('contacts', [Api\ContactController::class, 'index'])->middleware('can:view_contacts')->name('contacts.index');
        Route::post('contacts', [Api\ContactController::class, 'store'])->middleware('can:manage_contacts')->name('contacts.store');
        Route::get('contacts/{contact}', [Api\ContactController::class, 'show'])->middleware('can:view_contacts')->where('contact', $id)->name('contacts.show');
        Route::patch('contacts/{contact}', [Api\ContactController::class, 'update'])->middleware('can:manage_contacts')->where('contact', $id)->name('contacts.update');
        Route::delete('contacts/{contact}', [Api\ContactController::class, 'destroy'])->middleware('can:delete_data')->where('contact', $id)->name('contacts.destroy');
        Route::get('contacts/{contact}/consents', [Api\ContactController::class, 'consents'])->middleware('can:view_contacts')->where('contact', $id)->name('contacts.consents');
        Route::post('contacts/{contact}/consents', [Api\ContactController::class, 'recordConsent'])->middleware('can:manage_consent')->where('contact', $id)->name('contacts.consents.store');

        Route::get('leads', [Api\LeadController::class, 'index'])->middleware('can:view_leads')->name('leads.index');
        Route::post('leads', [Api\LeadController::class, 'store'])->middleware('can:manage_leads')->name('leads.store');
        Route::get('leads/{lead}', [Api\LeadController::class, 'show'])->middleware('can:view_leads')->where('lead', $id)->name('leads.show');
        Route::patch('leads/{lead}', [Api\LeadController::class, 'update'])->middleware('can:manage_leads')->where('lead', $id)->name('leads.update');
        Route::delete('leads/{lead}', [Api\LeadController::class, 'destroy'])->middleware('can:delete_data')->where('lead', $id)->name('leads.destroy');
    });
});

// --- aplicația ---
Route::middleware(['auth', '2fa'])->group(function () {
    Route::get('/', [HomeController::class, 'home'])->name('home');

    // echipa VITIM
    Route::prefix('admin')->name('admin.')->middleware('platform')->group(function () {
        Route::get('/', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/clienti/nou', [OrganizationController::class, 'create'])->name('organizations.create');
        Route::post('/clienti', [OrganizationController::class, 'store'])->name('organizations.store');
        Route::prefix('clienti/{organization}')->middleware('org')->group(function () {
            Route::get('/', [OrganizationController::class, 'show'])->name('organizations.show');
            Route::post('/site-uri', [SiteController::class, 'store'])->name('sites.store');
            Route::post('/site-uri/{site}/chei', [SiteController::class, 'rotate'])->whereNumber('site')->name('sites.rotate');
        });
    });

    // portalul clientului
    Route::prefix('app/{organization}')->name('portal.')->middleware('org')->group(function () {
        Route::get('/', [HomeController::class, 'portal'])->name('home');
    });
});
