<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete(storage_path('app/deployed_version'));
        File::deleteDirectory(storage_path('app/backups-test'));
        parent::tearDown();
    }

    public function test_deploy_runs_once_per_version(): void
    {
        File::delete(storage_path('app/deployed_version'));

        $this->artisan('vitim:deploy')->expectsOutputToContain('este activă')->assertSuccessful();
        $this->assertSame(trim((string) file_get_contents(base_path('VERSION'))), trim((string) file_get_contents(storage_path('app/deployed_version'))));
        $this->artisan('vitim:deploy')->doesntExpectOutputToContain('este activă')->assertSuccessful();
    }

    public function test_backup_is_created_and_emailed(): void
    {
        config(['vitim.backup_email' => 'backup@example.com', 'mail.default' => 'array']);
        $this->makeOrganization('Firmă cu diacritice ș ț');
        $before = glob(storage_path('app/backups/db-*.sql.gz')) ?: [];

        $sent = [];
        Event::listen(MessageSent::class, function (MessageSent $e) use (&$sent) {
            $sent[] = $e->message;
        });
        $this->artisan('vitim:backup')->assertSuccessful();

        $new = array_values(array_diff(glob(storage_path('app/backups/db-*.sql.gz')) ?: [], $before));
        $this->assertCount(1, $new);
        $sql = (string) gzdecode((string) file_get_contents($new[0]));
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('Firmă cu diacritice ș ț', $sql);

        $this->assertCount(1, $sent);
        $this->assertSame('backup@example.com', $sent[0]->getTo()[0]->getAddress());
        $this->assertCount(1, $sent[0]->getAttachments());
        File::delete($new[0]);
    }
}
