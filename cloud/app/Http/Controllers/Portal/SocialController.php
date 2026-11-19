<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Ai\AiUnavailable;
use App\Models\SocialPost;
use App\Services\AuditLogger;
use App\Social\SocialPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Social media: planul săptămânii scris de agent, aprobarea postărilor și atelierul de imagine / video. */
final class SocialController extends PortalController
{
    public function index(Request $request, SocialPlanner $planner): View
    {
        $week = SocialPlanner::monday($request->query('s'));
        $posts = SocialPost::query()->betweenDays($week->toDateString(), $week->copy()->addDays(6)->toDateString())->orderBy('day')->orderBy('id')->get();

        return view('portal.social.index', [
            'organization' => $this->organization(),
            'week' => $week,
            'posts' => $posts,
            'settings' => SocialPlanner::settings($this->organization()),
            'available' => $planner->available(),
        ]);
    }

    public function plan(Request $request, SocialPlanner $planner, AuditLogger $audit): RedirectResponse
    {
        $week = SocialPlanner::monday((string) $request->input('s'));
        abort_if($week->lt(SocialPlanner::monday()->subWeek()) || $week->gt(SocialPlanner::monday()->addWeeks(4)), 422);
        try {
            $count = $planner->plan($week);
        } catch (AiUnavailable $e) {
            return $this->to('portal.social', ['s' => $week->toDateString()])->with('error', $e->getMessage());
        }
        $audit->record('social.planned', null, ['week' => $week->toDateString(), 'posts' => $count]);

        return $this->to('portal.social', ['s' => $week->toDateString()], $count > 0 ? "Agentul a scris {$count} postări. Citește-le și aprobă-le înainte de publicare."
            : 'Nu era nimic de scris: zilele săptămânii au deja postări aprobate sau scrise de tine.');
    }

    public function show(int $post): View
    {
        return view('portal.social.show', ['organization' => $this->organization(), 'post' => SocialPost::query()->findOrFail($post),
            'settings' => SocialPlanner::settings($this->organization())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['day' => ['required', 'date', 'after:-8 days', 'before:+60 days']]);
        $post = SocialPost::create(['day' => $data['day'], 'topic' => 'Postare nouă', 'hook' => 'Titlul de pe imagine', 'caption' => 'Scrie aici textul postării.', 'status' => 'draft', 'source' => 'manual']);

        return $this->to('portal.social.show', ['post' => $post->id]);
    }

    public function update(Request $request, int $post): RedirectResponse
    {
        $model = SocialPost::query()->findOrFail($post);
        $data = $request->validate([
            'day' => ['required', 'date'], 'topic' => ['required', 'string', 'max:150'], 'hook' => ['required', 'string', 'max:190'],
            'caption' => ['required', 'string', 'max:2200'], 'hashtags' => ['nullable', 'string', 'max:490'], 'visual' => ['nullable', 'string', 'max:1000'],
            'scenes' => ['nullable', 'array', 'max:8'], 'scenes.*.text' => ['nullable', 'string', 'max:140'], 'scenes.*.shot' => ['nullable', 'string', 'max:300'],
            'scenes.*.seconds' => ['nullable', 'integer', 'min:2', 'max:10'],
        ]);
        $data['scenes'] = array_values(array_map(fn (array $s) => ['text' => trim((string) $s['text']), 'shot' => trim((string) ($s['shot'] ?? '')), 'seconds' => (int) ($s['seconds'] ?? 4)],
            array_filter($data['scenes'] ?? [], fn ($s) => trim((string) ($s['text'] ?? '')) !== '')));
        $model->fill($data)->save();

        return $this->to('portal.social.show', ['post' => $model->id], 'Postarea a fost salvată.');
    }

    public function status(Request $request, AuditLogger $audit, int $post): RedirectResponse
    {
        $model = SocialPost::query()->findOrFail($post);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(SocialPost::STATUSES))]]);
        $model->fill(['status' => $data['status'], 'published_at' => $data['status'] === 'published' ? now() : null])->save();
        $audit->record('social.status', $model, ['status' => $model->status]);

        return back()->with('ok', 'Postarea din '.$model->day->format('d.m').' este acum: '.mb_strtolower(SocialPost::STATUSES[$model->status]).'.');
    }

    public function rewrite(Request $request, SocialPlanner $planner, int $post): RedirectResponse
    {
        $model = SocialPost::query()->findOrFail($post);
        $data = $request->validate(['instruction' => ['nullable', 'string', 'max:300']]);
        try {
            $ok = $planner->rewrite($model, (string) ($data['instruction'] ?? ''));
        } catch (AiUnavailable $e) {
            return $this->to('portal.social.show', ['post' => $model->id])->with('error', $e->getMessage());
        }

        return $this->to('portal.social.show', ['post' => $model->id])->with($ok ? 'ok' : 'error', $ok ? 'Agentul a rescris postarea. Verific-o înainte să o aprobi.' : 'Agentul nu a putut rescrie postarea. Încearcă din nou.');
    }

    public function destroy(int $post): RedirectResponse
    {
        $model = SocialPost::query()->findOrFail($post);
        $week = SocialPlanner::monday($model->day->toDateString())->toDateString();
        $model->delete();

        return $this->to('portal.social', ['s' => $week], 'Postarea a fost ștearsă.');
    }

    public function settings(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'platforms' => ['required', 'array', 'min:1'], 'platforms.*' => [Rule::in(array_keys(SocialPlanner::PLATFORMS))],
            'days' => ['required', 'array', 'min:1'], 'days.*' => ['integer', 'between:1,7'],
            'tone' => ['nullable', 'string', 'max:300'], 'topics' => ['nullable', 'string', 'max:600'], 'avoid' => ['nullable', 'string', 'max:400'],
            'cta' => ['nullable', 'string', 'max:200'], 'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], ['platforms.required' => 'Alege cel puțin o rețea.', 'days.required' => 'Alege cel puțin o zi.']);
        $organization = $this->organization();
        $organization->forceFill(['social' => [
            'platforms' => array_values($data['platforms']), 'days' => array_values(array_unique(array_map('intval', $data['days']))),
            'tone' => trim((string) ($data['tone'] ?? '')), 'topics' => trim((string) ($data['topics'] ?? '')), 'avoid' => trim((string) ($data['avoid'] ?? '')),
            'cta' => trim((string) ($data['cta'] ?? '')), 'color' => strtolower($data['color']),
            'auto' => $request->boolean('auto'), 'remind' => $request->boolean('remind'),
        ]])->save();
        $audit->record('social.settings', $organization);

        return $this->to('portal.social', [], 'Setările agentului de social media au fost salvate.');
    }
}
