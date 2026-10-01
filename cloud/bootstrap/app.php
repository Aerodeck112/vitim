<?php

use App\Http\Api\ApiError;
use App\Http\Middleware\EnsurePlatformStaff;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SetOrganizationFromRoute;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // pluginul WordPress / conectorul PHP: fără sesiune și CSRF, autentificare prin semnătură HMAC
        api: __DIR__.'/../routes/connector.php',
        apiPrefix: 'connector',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'org' => SetOrganizationFromRoute::class,
            '2fa' => EnsureTwoFactor::class,
            'platform' => EnsurePlatformStaff::class,
        ]);
        // ordinea contează: autentificare → 2FA → firma curentă → model binding → permisiuni (can:)
        $middleware->appendToPriorityList(AuthenticatesRequests::class, EnsureTwoFactor::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, SetOrganizationFromRoute::class);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'connector/*') || $request->expectsJson(),
        );
        $exceptions->render(fn (Throwable $e, Request $request) => $request->is('api/*') ? ApiError::render($e) : null);
        // arhivă de actualizare mai mare decât limita de upload a hostingului: mesaj clar, nu pagina 413
        $exceptions->render(fn (PostTooLargeException $e, Request $request) => $request->is('admin/sistem/*')
            ? redirect()->route('admin.system')->with('error', 'Arhiva depășește limita de upload a hostingului. Urc-o cu File Manager în vitim-ai/storage/app/updates/ și apasă „Aplică” aici.')
            : null);
    })->create();
