<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Ai\AiClient;
use App\Ai\Evaluation;
use App\Models\Agent;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

final class EvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_are_valid_and_cover_thirty_questions(): void
    {
        $total = 0;
        foreach (Evaluation::sets() as $set) {
            $definition = json_decode((string) file_get_contents(resource_path("evals/{$set}.json")), true, flags: JSON_THROW_ON_ERROR);
            foreach ($definition['cases'] as $case) {
                $this->assertNotEmpty($case['messages'], $case['id']);
                foreach ($case['forbid'] as $pattern) {
                    $this->assertNotFalse(@preg_match('/'.$pattern.'/iu', ''), "regex invalid în {$case['id']}");
                }
            }
            $total += count($definition['cases']);
        }
        $this->assertSame(['auto', 'generic'], Evaluation::sets());
        $this->assertSame(30, $total);
    }

    public function test_grading_rules(): void
    {
        $evaluation = app(Evaluation::class);
        $case = ['id' => 'x', 'expect_any' => ['900'], 'forbid' => ['Claude'], 'expect_tool' => 'none'];
        $this->assertTrue($evaluation->grade($case, 'De la 900 lei.', 'ok', [])['passed']);
        $this->assertFalse($evaluation->grade($case, 'Nu știu.', 'ok', [])['passed']);
        $this->assertFalse($evaluation->grade($case, '900 lei, sunt Claude', 'ok', [])['passed']);
        $this->assertFalse($evaluation->grade($case, '900 lei', 'ok', ['create_lead'])['passed']);
        $this->assertFalse($evaluation->grade($case, '900 lei', 'unavailable', [])['passed']);
        $this->assertTrue($evaluation->grade(['id' => 'y', 'expect_any' => ['închis']], 'Duminica suntem INCHISI.', 'ok', [])['passed'], 'fără diacritice / majuscule');
    }

    public function test_command_runs_a_set_saves_report_and_cleans_up(): void
    {
        Storage::fake('local');
        $this->app->instance(AiClient::class, new FakeAiClient(array_fill(0, 40, FakeAiClient::text('De la 900 lei. ITP 150 lei.'))));
        $org = $this->makeOrganization('VITIM');

        $this->artisan('vitim:eval', ['organization' => $org->slug, 'set' => 'auto'])->assertSuccessful();

        $files = Storage::disk('local')->files('evals');
        $this->assertCount(1, $files);
        $report = json_decode(Storage::disk('local')->get($files[0]), true);
        $this->assertSame(15, $report['total']);
        $this->assertGreaterThan(0, $report['passed']);
        $this->assertGreaterThan(0, $report['cost_usd']);
        $this->tenant()->runAs($org, fn () => $this->assertSame(0, Agent::query()->count() + Conversation::query()->count()));

        $this->artisan('vitim:eval', ['organization' => 'nu-exista'])->assertFailed();
        $this->artisan('vitim:eval', ['organization' => $org->slug, 'set' => 'nu-exista'])->assertFailed();
    }
}
