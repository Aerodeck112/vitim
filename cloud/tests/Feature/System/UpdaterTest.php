<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Services\Updater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

final class UpdaterTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/vitim-update-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->root.'/app');
        file_put_contents($this->root.'/VERSION', "0.3.0\n");
        file_put_contents($this->root.'/.env', 'APP_KEY=secret-original');
        file_put_contents($this->root.'/app/Old.php', 'old');
        $this->app->instance(Updater::class, new Updater($this->root));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    /** @param array<string, string> $files */
    private function zip(array $files, string $prefix = ''): string
    {
        $path = sys_get_temp_dir().'/vitim-ai-test-'.bin2hex(random_bytes(4)).'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($prefix.$name, $content);
        }
        $zip->close();

        return $path;
    }

    /** @return array<string, string> */
    private function release(string $version = '0.4.0'): array
    {
        return ['VERSION' => $version, 'artisan' => '<?php', 'bootstrap/app.php' => '<?php', 'public/index.php' => '<?php',
            'vendor/autoload.php' => '<?php', 'app/Old.php' => 'new', 'app/New.php' => 'added',
            '.env' => 'APP_KEY=from-zip', 'storage/app/x.txt' => 'x', 'public/.htaccess' => 'zip'];
    }

    public function test_applies_new_version_and_protects_env_and_storage(): void
    {
        $result = app(Updater::class)->apply($this->zip($this->release(), 'vitim-ai-0.4.0/'));

        $this->assertSame('0.4.0', $result['version']);
        $this->assertSame('0.4.0', trim((string) file_get_contents($this->root.'/VERSION')));
        $this->assertSame('new', file_get_contents($this->root.'/app/Old.php'));
        $this->assertSame('added', file_get_contents($this->root.'/app/New.php'));
        $this->assertSame('APP_KEY=secret-original', file_get_contents($this->root.'/.env'));
        $this->assertFileDoesNotExist($this->root.'/storage/app/x.txt');
        $this->assertFileDoesNotExist($this->root.'/public/.htaccess');
        $this->assertNotEmpty(glob(storage_path('app/backups/db-*.sql.gz')), 'backup înainte de actualizare');
    }

    public function test_rejects_invalid_archives(): void
    {
        $updater = app(Updater::class);
        foreach ([
            'mai veche' => $this->zip($this->release('0.2.0')),
            'aceeași' => $this->zip($this->release('0.3.0')),
            'incompletă' => $this->zip(['VERSION' => '0.4.0', 'app/x.php' => 'x']),
            'cale ../' => $this->zip($this->release() + ['../evil.php' => 'x']),
        ] as $case => $zip) {
            try {
                $updater->apply($zip);
                $this->fail("Ar fi trebuit respinsă: {$case}");
            } catch (RuntimeException) {
                $this->assertSame('0.3.0', $updater->currentVersion(), $case);
            }
        }
        $this->assertFileDoesNotExist(dirname($this->root).'/evil.php');
        $this->assertSame('old', file_get_contents($this->root.'/app/Old.php'));
    }

    public function test_only_super_admin_with_password_can_update_from_panel(): void
    {
        $archive = fn () => new UploadedFile($this->zip($this->release()), 'vitim-ai-0.4.0.zip', 'application/zip', null, true);

        $this->actingAs($this->staff(PlatformRole::VitimAdmin));
        $this->get('/admin/sistem')->assertForbidden();
        $this->post('/admin/sistem/actualizare', ['archive' => $archive(), 'password' => 'password'])->assertForbidden();

        $admin = $this->staff();
        $this->actingAs($admin);
        $this->get('/admin/sistem')->assertOk()->assertSee('0.3.0');
        $this->post('/admin/sistem/actualizare', ['archive' => $archive(), 'password' => 'gresita'])->assertSessionHasErrors('password');
        $this->assertSame('0.3.0', app(Updater::class)->currentVersion());

        $this->post('/admin/sistem/actualizare', ['archive' => $archive(), 'password' => 'password'])
            ->assertRedirect('/admin/sistem')->assertSessionHas('ok');
        $this->assertSame('0.4.0', app(Updater::class)->currentVersion());
        $this->assertSame(['from' => '0.3.0', 'to' => '0.4.0', 'files' => 7], AuditLog::withoutTenancy()->where('action', 'system.updated')->sole()->meta);
    }

    public function test_archive_uploaded_through_file_manager_can_be_applied(): void
    {
        File::ensureDirectoryExists(Updater::inbox());
        $name = 'vitim-ai-test-'.bin2hex(random_bytes(3)).'.zip';
        copy($this->zip($this->release()), Updater::inbox().'/'.$name);
        $this->actingAs($this->staff());

        $this->get('/admin/sistem')->assertSee($name);
        $this->post('/admin/sistem/actualizare-urcata', ['name' => '../../.env', 'password' => 'password'])->assertSessionHasErrors('name');
        $this->post('/admin/sistem/actualizare-urcata', ['name' => $name, 'password' => 'password'])->assertSessionHas('ok');
        $this->assertSame('0.4.0', app(Updater::class)->currentVersion());
        $this->assertFileDoesNotExist(Updater::inbox().'/'.$name);
    }

    public function test_too_large_upload_gives_a_clear_message(): void
    {
        $this->actingAs($this->staff());
        $this->call('POST', '/admin/sistem/actualizare', [], [], [], ['CONTENT_LENGTH' => (string) (1024 ** 4)])
            ->assertRedirect('/admin/sistem')->assertSessionHas('error');
    }
}
