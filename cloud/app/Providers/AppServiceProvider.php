<?php

declare(strict_types=1);

namespace App\Providers;

use App\Ai\AiClient;
use App\Ai\AnthropicAiClient;
use App\Enums\Permission;
use App\Models\User;
use App\Services\Authorizer;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // o instanță per cerere / job (se resetează între ele)
        $this->app->scoped(TenantContext::class);
        $this->app->bind(AiClient::class, AnthropicAiClient::class);
    }

    public function boot(): void
    {
        // emailul de setare/resetare a parolei, în română (folosit și pentru conturile noi)
        ResetPassword::toMailUsing(fn (User $user, string $token) => (new MailMessage)
            ->subject('Setează parola pentru VITIM AI')
            ->greeting('Bună, '.$user->name.'!')
            ->line('Folosește butonul de mai jos ca să îți setezi parola pentru contul VITIM AI.')
            ->action('Setează parola', route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->line('Linkul expiră în '.config('auth.passwords.users.expire').' de minute. Dacă nu ai cerut acest email, îl poți ignora.')
            ->salutation('Echipa VITIM'));

        RateLimiter::for('agent-test', fn (Request $request) => Limit::perMinute(20)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('api-v1', fn (Request $request) => Limit::perMinute(120)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => app(Authorizer::class)->allows($user, $permission));
        }
    }
}
