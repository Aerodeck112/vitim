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
use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SignupForm;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\ContactService;
use App\Services\IssuedSiteKey;
use App\Services\ShopEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class ShopTest extends TestCase
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

    private function signed(string $path, array $payload, IssuedSiteKey $key): TestResponse
    {
        $body = (string) json_encode($payload);
        $ts = (string) time();
        $nonce = Str::random(24);

        return $this->call('POST', '/connector/v1/'.$path, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_VITIM_KEY' => $key->publicKey, 'HTTP_X_VITIM_TIMESTAMP' => $ts,
            'HTTP_X_VITIM_NONCE' => $nonce, 'HTTP_X_VITIM_SIGNATURE' => hash_hmac('sha256', $ts.'.'.$nonce.'.'.$body, $key->secret),
        ], $body);
    }

    /** @return array{0: Organization, 1: User, 2: IssuedSiteKey} */
    private function shop(string $name = 'Podreg', string $domain = 'podreg.ro'): array
    {
        $org = $this->makeOrganization($name);
        $org->forceFill(['company_name' => $name.' SRL'])->save();
        [, $key] = $this->makeSite($org, $domain);
        $owner = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $owner->id, 'role' => OrgRole::Owner]));
        $this->actingAs($owner);
        $this->put("/app/{$org->slug}/canale/email", ['host' => 'mail.podreg.ro', 'port' => '465', 'encryption' => 'ssl', 'username' => 'office@podreg.ro',
            'password' => 'x', 'from_email' => 'office@podreg.ro', 'hourly_limit' => 100])->assertSessionHasNoErrors();

        return [$org, $owner, $key];
    }

    private function subscriber(Organization $org, string $name, string $email): Contact
    {
        return $this->tenant()->runAs($org, function () use ($name, $email) {
            $c = app(ContactService::class)->create(['first_name' => $name, 'email' => $email], ContactSource::Manual);
            app(ConsentService::class)->record($c, Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Granted, 'test');

            return $c;
        });
    }

    public function test_catalog_sync_and_product_picker(): void
    {
        [$org, , $key] = $this->shop();
        $this->signed('products', ['products' => [
            ['id' => 11, 'name' => 'Cabană Alpin', 'price' => 45000, 'currency' => 'RON', 'url' => 'https://podreg.ro/p/alpin', 'image' => 'https://podreg.ro/a.jpg', 'categories' => ['Cabane'], 'in_stock' => true],
            ['id' => 12, 'name' => 'Foișor', 'price' => '8900.5', 'url' => 'javascript:alert(1)'],
        ]], $key)->assertOk()->assertJson(['saved' => 2]);
        $this->signed('products', ['products' => [['id' => 11, 'name' => 'Cabană Alpin XL', 'price' => 47000]]], $key)->assertOk();
        $this->signed('products', ['products' => [['id' => 12, 'deleted' => true]]], $key)->assertOk()->assertJson(['deleted' => 1]);
        $this->tenant()->runAs($org, function (): void {
            $p = Product::query()->sole();
            $this->assertSame('Cabană Alpin XL', $p->name);
            $this->assertSame('47000.00', (string) $p->price);
        });
        $this->post("/app/{$org->slug}/campanii", ['name' => 'C', 'channel' => 'email']);
        $campaign = $this->tenant()->runAs($org, fn () => Campaign::query()->sole());
        $this->get("/app/{$org->slug}/campanii/{$campaign->id}/design")->assertOk()->assertSee('Alpin XL');

        // altă firmă nu poate scrie în catalogul acestui site și nu îl vede
        [$other, , $otherKey] = $this->shop('Alta', 'alta.ro');
        $this->signed('products', ['products' => [['id' => 11, 'name' => 'Furat']]], $otherKey)->assertOk();
        $this->tenant()->runAs($org, fn () => $this->assertSame('Cabană Alpin XL', Product::query()->sole()->name));
        $this->tenant()->runAs($other, fn () => $this->assertSame('Furat', Product::query()->sole()->name));
        $this->call('POST', '/connector/v1/products', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_VITIM_KEY' => $key->publicKey], '{}')->assertUnauthorized();
    }

    public function test_click_identifies_shopper_abandoned_cart_and_revenue_attribution(): void
    {
        [$org, , $key] = $this->shop();
        $ana = $this->subscriber($org, 'Ana', 'ana@ex.ro');
        $this->post("/app/{$org->slug}/automatizari", ['template' => 'abandoned_cart'])->assertRedirect();
        $flow = $this->tenant()->runAs($org, fn () => Flow::query()->sole());
        $this->post("/app/{$org->slug}/automatizari/{$flow->id}/stare", ['status' => 'live'])->assertSessionHas('ok');

        // campanie cu link spre site; click-ul duce la site cu tokenul contactului
        $this->post("/app/{$org->slug}/campanii", ['name' => 'Oferta', 'channel' => 'email']);
        $campaign = $this->tenant()->runAs($org, fn () => Campaign::query()->sole());
        $this->put("/app/{$org->slug}/campanii/{$campaign->id}", ['name' => 'Oferta', 'subject' => 'Oferta', 'body' => "Vezi https://podreg.ro/oferta\n\nși https://altcineva.ro/x"])->assertSessionHasNoErrors();
        $this->post("/app/{$org->slug}/campanii/{$campaign->id}/aprobare", ['confirm' => '1'])->assertSessionHas('ok');
        $this->artisan('vitim:campaigns');
        preg_match_all('#href="([^"]*/t/c/[^"]+)"#', (string) $this->mails[0]->getHtmlBody(), $links);
        $this->assertCount(2, $links[1]);
        $to = $this->get(html_entity_decode($links[1][0]))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://podreg.ro/oferta?vtm=', $to);
        $token = urldecode(explode('vtm=', $to)[1]);
        $this->assertSame(ShopEvents::token($ana), $token);
        $foreign = $this->get(html_entity_decode($links[1][1]))->headers->get('Location');
        $this->assertStringNotContainsString('vtm=', $foreign, 'tokenul nu pleacă spre alte site-uri');

        $cart = ['currency' => 'RON', 'checkout_url' => 'https://podreg.ro/finalizare?vitim_cart=abc', 'items' => [
            ['name' => 'Cabană Alpin', 'product_id' => '11', 'qty' => 1, 'price' => 45000, 'image' => 'https://podreg.ro/a.jpg'],
            ['name' => 'Saună', 'product_id' => '13', 'qty' => 2, 'price' => 1200],
        ]];
        $this->signed('events', ['events' => [
            ['id' => 'view_1', 'type' => 'viewed_product', 'contact_token' => $token, 'value' => 45000, 'data' => ['product' => 'Cabană Alpin', 'product_id' => '11', 'url' => 'https://podreg.ro/p/alpin']],
            ['id' => 'checkout_1', 'type' => 'started_checkout', 'email' => 'ANA@ex.ro', 'value' => 47400, 'data' => $cart],
        ]], $key)->assertOk()->assertJson(['recorded' => 2]);
        $this->signed('events', ['events' => [['id' => 'checkout_1', 'type' => 'started_checkout', 'email' => 'ana@ex.ro', 'data' => $cart]]], $key)->assertJson(['duplicates' => 1]);

        $this->artisan('vitim:flows'); // intră în pasul de așteptare (1 oră)
        $this->travel(17)->hours(); // smart sending: campania a plecat acum mai puțin de 16 ore
        $this->artisan('vitim:flows');
        $mail = $this->mails[count($this->mails) - 1];
        $this->assertSame('Ana, ai uitat ceva în coș', $mail->getSubject());
        $html = (string) $mail->getHtmlBody();
        $this->assertStringContainsString('Cabană Alpin', $html);
        $this->assertStringContainsString('× 2', $html);
        $this->assertStringContainsString('Coșul tău (47.400,00 lei)', $html);
        $this->assertStringContainsString('Finalizează comanda', $html);
        $this->assertStringContainsString('Saună', (string) $mail->getTextBody());

        // comanda: atribuită campaniei (click în ultimele 5 zile), scoate contactul din fluxul de coș
        $this->signed('events', ['events' => [
            ['id' => 'order_77', 'type' => 'placed_order', 'contact_token' => $token, 'email' => 'ana@ex.ro', 'value' => 47400, 'occurred_at' => time(), 'data' => ['order_id' => '77', 'currency' => 'RON', 'items' => $cart['items']]],
            ['id' => 'order_78', 'type' => 'placed_order', 'email' => 'bob@ex.ro', 'first_name' => 'Bob', 'value' => 100, 'marketing_consent' => true, 'consent_text' => 'Vreau oferte', 'data' => ['order_id' => '78']],
            ['id' => 'order_1', 'type' => 'placed_order', 'email' => 'vechi@ex.ro', 'value' => 50, 'occurred_at' => now()->subDays(40)->getTimestamp(), 'data' => ['order_id' => '1']],
            ['id' => 'x', 'type' => 'placed_order', 'value' => 1], // fără email și fără token: ignorat
        ]], $key)->assertOk()->assertJson(['recorded' => 3, 'skipped' => 1]);
        $this->artisan('vitim:flows');

        $this->tenant()->runAs($org, function () use ($campaign, $ana): void {
            $order = ContactEvent::query()->where('type', 'placed_order')->where('contact_id', $ana->id)->sole();
            $this->assertSame($campaign->id, $order->campaign_id);
            $this->assertSame('click', $order->data['attributed']);
            $this->assertSame(['orders' => 1, 'revenue' => 47400.0], ShopEvents::revenue($campaign->id));
            $this->assertSame('exited', FlowRun::query()->where('contact_id', $ana->id)->sole()->status);
            $bob = Contact::query()->where('email', 'bob@ex.ro')->sole();
            $this->assertSame(ContactSource::WooCommerce, $bob->source);
            $this->assertSame(ConsentStatus::Granted, app(ConsentService::class)->current($bob, Channel::Email, ConsentPurpose::Marketing));
            $old = Contact::query()->where('email', 'vechi@ex.ro')->sole();
            $this->assertSame(now()->subDays(40)->toDateString(), ContactEvent::query()->where('contact_id', $old->id)->sole()->occurred_at->toDateString());
        });
        $this->get("/app/{$org->slug}/campanii/{$campaign->id}")->assertOk()->assertSee('Comenzi atribuite')->assertSee('47.400,00 lei');
        $this->get("/app/{$org->slug}/contacte/{$ana->id}")->assertOk()->assertSee('A plasat o comandă');

        // tokenul unei firme nu identifică pe nimeni în magazinul altei firme
        [$other, , $otherKey] = $this->shop('Alta', 'alta.ro');
        $this->signed('events', ['events' => [['id' => 'v9', 'type' => 'viewed_product', 'contact_token' => $token, 'data' => ['product' => 'X']]]], $otherKey)->assertJson(['skipped' => 1]);
        $forged = $other->id.'.'.$ana->id.'.0123456789abcdef';
        $this->signed('events', ['events' => [['id' => 'v10', 'type' => 'viewed_product', 'contact_token' => $forged, 'data' => ['product' => 'X']]]], $otherKey)->assertJson(['skipped' => 1]);
        $this->tenant()->runAs($other, fn () => $this->assertSame(0, ContactEvent::query()->count()));
    }

    public function test_signup_form_returns_contact_token_for_the_site_cookie(): void
    {
        [$org, , $key] = $this->shop();
        $this->post("/app/{$org->slug}/formulare", ['type' => 'embed']);
        $form = $this->tenant()->runAs($org, fn () => SignupForm::query()->sole());
        $this->put("/app/{$org->slug}/formulare/{$form->id}", ['name' => 'F', 'title' => 'T'])->assertSessionHasNoErrors();
        $this->post("/app/{$org->slug}/formulare/{$form->id}/stare", ['status' => 'live']);
        $token = $this->call('POST', '/widget/v1/forms/submit', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_ORIGIN' => 'https://podreg.ro'],
            (string) json_encode(['key' => $key->publicKey, 'form' => $form->id, 'email' => 'nou@ex.ro']))->assertOk()->json('contact');
        $this->tenant()->runAs($org, fn () => $this->assertSame(ShopEvents::token(Contact::query()->sole()), $token));
    }
}
