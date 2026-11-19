<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\OrgRole;
use App\Enums\PlatformRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SocialAsset;
use App\Models\SocialConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Folderul de Google Drive al firmei: fotografii și clipuri aduse prin contul de serviciu al platformei. */
final class SocialDriveTest extends TestCase
{
    use RefreshDatabase;

    private const FOLDER = '1AbCdEfGhIjKlMnOpQrStUvWx';

    private static function photo(): string
    {
        $im = imagecreatetruecolor(900, 700);
        for ($i = 0; $i < 300; $i++) {
            imagefilledrectangle($im, random_int(0, 900), random_int(0, 700), random_int(0, 900), random_int(0, 700), imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        ob_start();
        imagejpeg($im, null, 85);

        return (string) ob_get_clean();
    }

    /** Un MP4 minim, recunoscut ca video după antet. */
    private static function clip(): string
    {
        return "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41".str_repeat("\x00\x00\x00\x08free", 2000);
    }

    private function serviceAccount(): UploadedFile
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);

        return UploadedFile::fake()->createWithContent('cont.json', (string) json_encode(['type' => 'service_account', 'client_email' => 'materiale@platforma.iam.gserviceaccount.com', 'private_key' => $pem]));
    }

    /** @return array{0: Organization, 1: User} */
    private function firm(string $name): array
    {
        $org = $this->makeOrganization($name);
        $user = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $user->id, 'role' => OrgRole::Owner]));

        return [$org, $user];
    }

    private function fakeDrive(): void
    {
        Http::fake(function (Request $r) {
            if ($r->url() === 'https://oauth2.googleapis.com/token') {
                return substr_count((string) $r['assertion'], '.') === 2 ? Http::response(['access_token' => 'jeton-de-citire', 'expires_in' => 3600]) : Http::response(['error_description' => 'bad'], 400);
            }
            if (! $r->hasHeader('Authorization', 'Bearer jeton-de-citire')) {
                return Http::response(['error' => ['message' => 'Unauthorized']], 401);
            }
            $url = urldecode($r->url());
            if (str_contains($url, "'".self::FOLDER."' in parents")) {
                return Http::response(['files' => [
                    ['id' => 'foto000000001', 'name' => 'echipa la lucru.jpg', 'mimeType' => 'image/jpeg', 'size' => '90000'],
                    ['id' => 'clip000000001', 'name' => 'montaj.mp4', 'mimeType' => 'video/mp4', 'size' => '16032'],
                    ['id' => 'doc0000000001', 'name' => 'oferta.pdf', 'mimeType' => 'application/pdf', 'size' => '5000'],
                    ['id' => 'folder00000001', 'name' => 'Cabana Sovata', 'mimeType' => 'application/vnd.google-apps.folder'],
                ]]);
            }
            if (str_contains($url, "'folder00000001' in parents")) {
                return Http::response(['files' => [['id' => 'foto000000002', 'name' => 'fatada.jpg', 'mimeType' => 'image/jpeg', 'size' => '90000']]]);
            }
            if (str_contains($url, 'alt=media')) {
                return str_contains($url, 'clip') ? Http::response(self::clip(), 200, ['Content-Type' => 'video/mp4']) : Http::response(self::photo(), 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response(['error' => ['message' => 'File not found']], 404);
        });
    }

    public function test_photos_and_clips_come_from_the_firm_drive_folder(): void
    {
        Storage::fake('local');
        $this->fakeDrive();
        [$org, $owner] = $this->firm('Podreg');
        $base = "/app/{$org->slug}/social";

        $this->actingAs($owner)->get("{$base}/materiale")->assertOk()->assertSee('Google Drive nu este configurat');

        // super adminul urcă cheia contului de serviciu
        $this->actingAs($this->staff(PlatformRole::VitimAdmin))->put('/admin/agent-ai/drive', ['drive_key' => $this->serviceAccount(), 'password' => 'password'])->assertForbidden();
        $this->actingAs($this->staff())->put('/admin/agent-ai/drive', ['drive_key' => UploadedFile::fake()->createWithContent('x.json', '{"type":"altceva"}'), 'password' => 'password'])->assertSessionHasErrors('drive_key');
        $this->put('/admin/agent-ai/drive', ['drive_key' => $this->serviceAccount(), 'password' => 'password'])->assertSessionHas('status');
        $this->assertStringNotContainsString('PRIVATE KEY', (string) json_encode(DB::table('platform_settings')->pluck('value')));
        $this->get('/admin/agent-ai')->assertOk()->assertSee('materiale@platforma.iam.gserviceaccount.com')->assertDontSee('PRIVATE KEY');

        $this->actingAs($owner)->get("{$base}/materiale")->assertOk()->assertSee('materiale@platforma.iam.gserviceaccount.com');
        $this->put("{$base}/drive", ['folder' => 'https://exemplu.ro/folder'])->assertSessionHasErrors('folder');
        $this->put("{$base}/drive", ['folder' => 'https://drive.google.com/drive/folders/'.self::FOLDER.'?usp=sharing'])->assertSessionHas('ok');
        $this->post("{$base}/drive/citeste")->assertSessionHas('ok', 'Din Drive: 2 fotografii și 1 clipuri noi, 1 fișiere sărite (alt tip, prea mari sau prea mici).');

        $assets = $this->tenant()->runAs($org, fn () => SocialAsset::query()->orderBy('id')->get());
        $this->assertSame(['image', 'video', 'image'], $assets->pluck('kind')->all());
        $this->assertSame('Cabana Sovata: fatada', $assets[2]->title, 'numele subfolderului descrie materialul');
        Storage::disk('local')->assertExists($assets[1]->path);
        $this->get("{$base}/materiale")->assertOk()->assertSee('din Drive')->assertSee('conectat')->assertSee('<video', false);
        $this->get("{$base}/materiale/{$assets[1]->id}/fisier")->assertOk();

        // a doua citire nu dublează nimic
        $this->post("{$base}/drive/citeste")->assertSessionHas('ok', fn ($m) => str_starts_with($m, 'Din Drive: 0 fotografii și 0 clipuri noi'));
        $this->assertSame(3, $this->tenant()->runAs($org, fn () => SocialAsset::query()->count()));
    }

    public function test_drive_folders_stay_with_their_firm(): void
    {
        Storage::fake('local');
        Http::fake();
        [$a, $ownerA] = $this->firm('Firma A');
        [$b] = $this->firm('Firma B');
        $this->tenant()->runAs($b, fn () => SocialConnection::create(['network' => 'drive', 'config' => ['folder_id' => self::FOLDER]]));

        $this->actingAs($ownerA)->get("/app/{$a->slug}/social/materiale")->assertOk()->assertDontSee(self::FOLDER);
        $this->post("/app/{$a->slug}/social/drive/citeste")->assertNotFound();
        $this->put("/app/{$b->slug}/social/drive", ['folder' => self::FOLDER])->assertNotFound();
        Http::assertNothingSent();

        $viewer = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($a, fn () => Membership::create(['user_id' => $viewer->id, 'role' => OrgRole::Viewer]));
        $this->actingAs($viewer)->put("/app/{$a->slug}/social/drive", ['folder' => self::FOLDER])->assertForbidden();
    }
}
