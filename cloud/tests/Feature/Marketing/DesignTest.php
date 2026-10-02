<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Enums\OrgRole;
use App\Messaging\Accounts\SmtpSender;
use App\Models\Campaign;
use App\Models\EmailTemplate;
use App\Models\Flow;
use App\Models\FlowStep;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\ContactService;
use App\Services\EmailBlocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class DesignTest extends TestCase
{
    use RefreshDatabase;

    /** @var \ArrayObject<int, Email> */
    private \ArrayObject $mails;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mails = new \ArrayObject;
        $transport = new class($this->mails) extends AbstractTransport
        {
            public function __construct(private \ArrayObject $sink)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                $this->sink[] = $message->getOriginalMessage();
            }

            public function __toString(): string
            {
                return 'test://';
            }
        };
        $this->app->instance(SmtpSender::class, new SmtpSender(fn () => $transport));
    }

    /** @return array{0: Organization, 1: User} */
    private function firm(string $name = 'Podreg'): array
    {
        $org = $this->makeOrganization($name);
        $org->forceFill(['company_name' => $name.' SRL'])->save();
        $owner = User::factory()->create(['email' => strtolower($name).'@firma.ro', 'last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $owner->id, 'role' => OrgRole::Owner]));
        $this->actingAs($owner);
        $this->put("/app/{$org->slug}/canale/email", ['host' => 'mail.podreg.ro', 'port' => '465', 'encryption' => 'ssl', 'username' => 'office@podreg.ro',
            'password' => 'x', 'from_email' => 'office@podreg.ro', 'hourly_limit' => 100])->assertSessionHasNoErrors();

        return [$org, $owner];
    }

    public function test_blocks_are_sanitized_and_rendered_safely(): void
    {
        $clean = EmailBlocks::clean([
            ['type' => 'heading', 'text' => '<script>alert(1)</script>Salut', 'size' => 'h9', 'align' => 'evil'],
            ['type' => 'button', 'label' => 'Click', 'url' => 'javascript:alert(1)', 'color' => 'red;background:url(x)'],
            ['type' => 'image', 'url' => 'http://nesigur.ro/a.png', 'width' => 500, 'onerror' => 'x'],
            ['type' => 'iframe', 'src' => 'https://ex.ro'],
            'gunoi',
        ]);
        $this->assertCount(3, $clean);
        $this->assertSame(['type' => 'heading', 'text' => '<script>alert(1)</script>Salut', 'size' => 'h1', 'align' => 'left'], $clean[0]);
        $this->assertNull($clean[1]['url']);
        $this->assertNull($clean[1]['color']);
        $this->assertNull($clean[2]['url']);
        $this->assertSame(100, $clean[2]['width']);
        $this->assertArrayNotHasKey('onerror', $clean[2]);

        $html = EmailBlocks::html($clean, ['color' => '#123456', 'logo_url' => 'https://ex.ro/l.png'], fn ($t) => $t, 'Firma SRL', 'https://ai.ex/d/abc', 'Pre');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('background:#123456', $html);
        $this->assertStringContainsString('https://ai.ex/d/abc', $html);
    }

    public function test_designed_campaign_sends_with_brand_tracking_and_text_part(): void
    {
        [$org] = $this->firm();
        $this->put("/app/{$org->slug}/brand", ['color' => '#e11d48', 'font' => 'Georgia, serif', 'logo_url' => 'https://podreg.ro/logo.png',
            'facebook' => 'https://facebook.com/podreg'])->assertSessionHas('ok');
        $this->put("/app/{$org->slug}/brand", ['color' => 'rosu'])->assertSessionHasErrors('color');
        $this->get("/app/{$org->slug}/brand")->assertOk()->assertSee('https://podreg.ro/logo.png');

        $this->post("/app/{$org->slug}/campanii", ['name' => 'Vizual', 'channel' => 'email']);
        $campaign = $this->tenant()->runAs($org, fn () => Campaign::query()->sole());
        $base = "/app/{$org->slug}/campanii/{$campaign->id}";
        $this->put($base, ['name' => 'Vizual', 'subject' => 'Oferta, {{prenume}}'])->assertSessionHasNoErrors();
        $this->get("{$base}/design")->assertOk()->assertSee('Designuri gata făcute')->assertSee('Ofertă / reducere');

        $blocks = [
            ['type' => 'logo', 'align' => 'center'],
            ['type' => 'heading', 'text' => 'Bună {{prenume}}', 'size' => 'h1', 'align' => 'left'],
            ['type' => 'text', 'text' => 'Avem **reduceri**.', 'align' => 'left'],
            ['type' => 'button', 'label' => 'Vezi', 'url' => 'https://podreg.ro/oferta', 'align' => 'center'],
            ['type' => 'social'],
        ];
        $this->postJson("/app/{$org->slug}/design/previzualizare", ['blocks' => json_encode($blocks), 'subject' => 'x'])
            ->assertOk()->assertJsonPath('html', fn ($h) => str_contains($h, 'Bună Maria') && str_contains($h, '#e11d48') && str_contains($h, 'podreg.ro/logo.png'));
        $this->put("{$base}/design", ['blocks' => json_encode($blocks), 'preheader' => 'Doar azi'])->assertSessionHas('ok');
        $this->get($base)->assertOk()->assertSee('Deschide editorul vizual');
        // salvarea formularului de campanie nu șterge designul și nici nu cere text
        $this->put($base, ['name' => 'Vizual', 'subject' => 'Oferta, {{prenume}}'])->assertSessionHasNoErrors();
        $this->tenant()->runAs($org, fn () => $this->assertCount(5, $campaign->fresh()->blocks));

        $this->tenant()->runAs($org, function () {
            $c = app(ContactService::class)->create(['first_name' => 'Ioana', 'email' => 'ioana@ex.ro'], ContactSource::Manual);
            app(ConsentService::class)->record($c, Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Granted, 'test');
        });
        $this->post("{$base}/aprobare", ['confirm' => '1'])->assertSessionHas('ok');
        $this->artisan('vitim:campaigns')->assertSuccessful();

        $mail = $this->mails[count($this->mails) - 1];
        $this->assertSame('Oferta, Ioana', $mail->getSubject());
        $html = (string) $mail->getHtmlBody();
        $this->assertStringContainsString('Bună Ioana', $html);
        $this->assertStringContainsString('Doar azi', $html);
        $this->assertStringContainsString('<strong>reduceri</strong>', $html);
        $this->assertStringContainsString('Georgia', $html);
        $this->assertStringContainsString('/t/c/', $html, 'linkurile trec prin urmărirea de click');
        $this->assertStringContainsString('/t/o/', $html, 'pixelul de deschidere');
        $this->assertStringContainsString('BUNĂ IOANA', (string) $mail->getTextBody());
        $this->assertStringContainsString('Vezi: https://podreg.ro/oferta', (string) $mail->getTextBody());

        // după aprobare designul nu se mai schimbă
        $this->put("{$base}/design", ['blocks' => '[]'])->assertStatus(422);
    }

    public function test_uploads_templates_flow_steps_and_isolation(): void
    {
        Storage::fake('local');
        [$org, $owner] = $this->firm();
        $url = $this->post("/app/{$org->slug}/design/imagini", ['image' => UploadedFile::fake()->image('poza.png', 40, 40)], ['Accept' => 'application/json'])
            ->assertOk()->json('url');
        $this->assertMatchesRegularExpression('#/m/'.$org->id.'/[A-Za-z0-9]{32}\.png$#', $url);
        auth()->logout();
        $this->get((string) parse_url($url, PHP_URL_PATH))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/m/'.$org->id.'/'.str_repeat('a', 32).'.png')->assertNotFound();
        $this->actingAs($owner);
        $this->post("/app/{$org->slug}/design/imagini", ['image' => UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"/>')], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->postJson("/app/{$org->slug}/design/sabloane", ['name' => 'Al nostru', 'blocks' => json_encode([['type' => 'text', 'text' => 'Salut']])])->assertOk();
        $this->tenant()->runAs($org, fn () => $this->assertSame('Al nostru', EmailTemplate::query()->sole()->name));

        $this->post("/app/{$org->slug}/automatizari", ['name' => 'Flux'])->assertRedirect();
        $flow = $this->tenant()->runAs($org, fn () => Flow::query()->sole());
        $this->post("/app/{$org->slug}/automatizari/{$flow->id}/pasi", ['type' => 'email'])->assertRedirect();
        $step = $this->tenant()->runAs($org, fn () => FlowStep::query()->sole());
        $this->put("/app/{$org->slug}/automatizari/{$flow->id}/pasi/{$step->id}", ['subject' => 'Salut', 'body' => ''])->assertRedirect();
        $this->get("/app/{$org->slug}/automatizari/{$flow->id}/pasi/{$step->id}/design")->assertOk()->assertSee('Al nostru');
        $this->put("/app/{$org->slug}/automatizari/{$flow->id}/pasi/{$step->id}/design", ['blocks' => json_encode([['type' => 'heading', 'text' => 'Hei {{prenume}}']])])->assertSessionHas('ok');
        $this->tenant()->runAs($org, function () use ($step) {
            $config = FlowStep::query()->find($step->id)->config;
            $this->assertSame('Salut', $config['subject']);
            $this->assertSame('Hei {{prenume}}', $config['blocks'][0]['text']);
        });

        $other = $this->makeOrganization('Alta');
        $stranger = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($other, fn () => Membership::create(['user_id' => $stranger->id, 'role' => OrgRole::Owner]));
        $this->actingAs($stranger);
        $this->get("/app/{$other->slug}/automatizari/{$flow->id}/pasi/{$step->id}/design")->assertNotFound();
        $this->put("/app/{$other->slug}/automatizari/{$flow->id}/pasi/{$step->id}/design", ['blocks' => '[]'])->assertNotFound();
        $this->get("/app/{$other->slug}/design/previzualizare")->assertStatus(405);
        $this->get("/app/{$other->slug}/brand")->assertOk()->assertDontSee('Al nostru');

        $operator = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $operator->id, 'role' => OrgRole::Agent]));
        $this->actingAs($operator);
        $this->get("/app/{$org->slug}/brand")->assertForbidden();
        $this->post("/app/{$org->slug}/design/imagini", ['image' => UploadedFile::fake()->image('p.png')])->assertForbidden();
    }
}
