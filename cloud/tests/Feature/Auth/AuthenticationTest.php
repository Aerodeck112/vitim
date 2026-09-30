<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\PlatformRole;
use App\Models\User;
use App\Security\Totp;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function code(string $secret): string
    {
        return Totp::code($secret, intdiv(time(), 30));
    }

    public function test_totp_matches_rfc6238_vector(): void
    {
        // RFC 6238, anexa B: secretul ASCII „12345678901234567890” (base32 GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ), T=59 s → 287082 (ultimele 6 cifre)
        $this->assertSame('287082', Totp::code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 1));
        $this->assertTrue(Totp::verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287 082', time: 59));
        $this->assertFalse(Totp::verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', time: 59 + 120));
    }

    public function test_login_success_and_failure(): void
    {
        $user = User::factory()->create(['email' => 'ion@firma.ro', 'password' => 'parola-foarte-sigura']);

        $this->post('/login', ['email' => 'ion@firma.ro', 'password' => 'gresit'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'ION@firma.ro', 'password' => 'parola-foarte-sigura'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'ion@firma.ro', 'password' => 'parola-foarte-sigura']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'ion@firma.ro', 'password' => 'gresit']);
        }
        $this->post('/login', ['email' => 'ion@firma.ro', 'password' => 'parola-foarte-sigura'])->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Prea multe încercări', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_platform_staff_must_enable_two_factor(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['platform_role' => PlatformRole::Admin])->save();

        $this->actingAs($user)->get('/admin')->assertRedirect(route('2fa.setup'));
        $this->get('/2fa/activare')->assertOk()->assertSee('obligatorie');
        $secret = (string) session('totp_pending');

        $this->post('/2fa/activare', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/2fa/activare', ['code' => $this->code($secret)])->assertRedirect(route('home'));
        $this->assertTrue($user->fresh()->hasTwoFactor());
        $this->get('/admin')->assertOk();
    }

    public function test_user_with_two_factor_must_enter_code_after_login(): void
    {
        $secret = Totp::secret();
        $user = User::factory()->create(['email' => 'ion@firma.ro', 'password' => 'parola-foarte-sigura']);
        $user->forceFill(['totp_secret' => $secret, 'totp_confirmed_at' => now()])->save();

        $this->post('/login', ['email' => 'ion@firma.ro', 'password' => 'parola-foarte-sigura']);
        $this->get('/')->assertRedirect(route('2fa.challenge'));
        $this->post('/2fa', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->post('/2fa', ['code' => $this->code($secret)])->assertRedirect(route('home'));
        $this->get('/')->assertOk(); // fără firmă: pagina de alegere
    }

    public function test_totp_secret_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['totp_secret' => 'JBSWY3DPEHPK3PXP', 'totp_confirmed_at' => now()])->save();

        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', (string) \DB::table('users')->where('id', $user->id)->value('totp_secret'));
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ion@firma.ro']);

        $this->post('/parola', ['email' => 'nimeni@firma.ro'])->assertSessionHas('ok'); // același răspuns
        $this->post('/parola', ['email' => 'ion@firma.ro'])->assertSessionHas('ok');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });
        $this->post('/parola/noua', ['token' => $token, 'email' => 'ion@firma.ro', 'password' => 'scurta', 'password_confirmation' => 'scurta'])
            ->assertSessionHasErrors('password');
        $this->post('/parola/noua', ['token' => $token, 'email' => 'ion@firma.ro', 'password' => 'parola-noua-sigura', 'password_confirmation' => 'parola-noua-sigura'])
            ->assertRedirect(route('login'));
        $this->post('/login', ['email' => 'ion@firma.ro', 'password' => 'parola-noua-sigura']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_setup_creates_first_admin_only_with_token_and_empty_database(): void
    {
        $payload = ['token' => 'token-lung-de-instalare-123', 'name' => 'Admin', 'email' => 'admin@vitim.ro', 'password' => 'parola-foarte-sigura', 'password_confirmation' => 'parola-foarte-sigura'];

        $this->get('/setup')->assertNotFound(); // fără SETUP_TOKEN
        config(['vitim.setup_token' => 'token-lung-de-instalare-123']);
        $this->post('/setup', ['token' => 'gresit'] + $payload)->assertSessionHasErrors('token');
        $this->post('/setup', $payload)->assertRedirect(route('2fa.setup'));
        $this->assertSame(PlatformRole::Admin, User::where('email', 'admin@vitim.ro')->first()->platform_role);

        auth()->logout();
        $this->get('/setup')->assertNotFound(); // există deja utilizatori
    }
}
