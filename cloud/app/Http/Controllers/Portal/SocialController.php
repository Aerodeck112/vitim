<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Ai\AiUnavailable;
use App\Models\Site;
use App\Models\SocialAsset;
use App\Models\SocialConnection;
use App\Models\SocialPost;
use App\Models\SocialReference;
use App\Services\AuditLogger;
use App\Social\MediaLibrary;
use App\Social\MetaError;
use App\Social\MetaResearch;
use App\Social\SocialPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'settings' => SocialPlanner::settings($this->organization()), 'assets' => SocialAsset::query()->orderByDesc('id')->limit(24)->get(['id', 'title'])]);
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
            'asset_id' => ['nullable', 'integer', Rule::exists('social_assets', 'id')->where('organization_id', $this->organization()->id)],
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

    /** Materialele firmei: pozele strânse din site și din pagina de Meta, încărcările și legătura cu Meta. */
    public function media(): View
    {
        $connection = SocialConnection::query()->where('network', 'meta')->first();

        return view('portal.social.media', [
            'organization' => $this->organization(),
            'assets' => SocialAsset::query()->orderByDesc('id')->paginate(48),
            'connection' => $connection,
            'pageId' => $connection?->config['page_id'] ?? '',
            'igId' => $connection?->config['ig_id'] ?? '',
            'references' => SocialReference::query()->orderByDesc('engagement')->limit(8)->get(),
            'referenceCount' => SocialReference::query()->count(),
            'sites' => Site::query()->where('status', 'active')->orderBy('domain')->get(),
        ]);
    }

    public function file(int $asset): StreamedResponse
    {
        $model = SocialAsset::query()->findOrFail($asset);
        abort_unless(Storage::disk('local')->exists($model->path), 404);

        return Storage::disk('local')->response($model->path, null, ['Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function collect(Request $request, MediaLibrary $library): RedirectResponse
    {
        $site = Site::query()->where('status', 'active')->findOrFail((int) $request->input('site'));
        $count = $library->collectFromSite($site);

        return $this->to('portal.social.media', [], $count > 0 ? "Am adăugat {$count} fotografii din {$site->domain}." : "Nu am găsit fotografii noi, destul de mari, pe {$site->domain}.");
    }

    public function upload(Request $request, MediaLibrary $library): RedirectResponse
    {
        $request->validate(['photos' => ['required', 'array', 'max:10'], 'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8000']]);
        $count = 0;
        foreach ($request->file('photos') as $file) {
            $count += $library->upload((string) file_get_contents($file->getRealPath()), $file->getClientOriginalName()) ? 1 : 0;
        }

        return $this->to('portal.social.media', [], "{$count} fotografii adăugate. Cele prea mici (sub 500 px) sau deja existente au fost sărite.");
    }

    public function deleteAsset(MediaLibrary $library, int $asset): RedirectResponse
    {
        $library->delete(SocialAsset::query()->findOrFail($asset));

        return $this->to('portal.social.media', [], 'Fotografia a fost ștearsă.');
    }

    public function connect(Request $request, AuditLogger $audit): RedirectResponse
    {
        $connection = SocialConnection::query()->firstOrNew(['network' => 'meta']);
        $data = $request->validate([
            'page_id' => ['required', 'regex:/^\d{5,30}$/'], 'ig_id' => ['nullable', 'regex:/^\d{5,30}$/'],
            'token' => [$connection->exists ? 'nullable' : 'required', 'string', 'min:20', 'max:600'],
        ], ['page_id.regex' => 'ID-ul paginii este un număr (îl găsești în Meta Business Suite).', 'ig_id.regex' => 'ID-ul contului de Instagram este un număr.']);
        // codul de acces se păstrează criptat; câmpul gol la editare îl lasă pe cel existent
        $connection->fill(['config' => ['page_id' => $data['page_id'], 'ig_id' => $data['ig_id'] ?? null, 'token' => $data['token'] ?? ($connection->config['token'] ?? '')],
            'status' => 'new', 'last_error' => null])->save();
        $audit->record('social.meta_connected', $connection);

        return $this->to('portal.social.media', [], 'Pagina a fost salvată. Apasă „Citește pagina acum” pentru prima cercetare.');
    }

    public function disconnect(AuditLogger $audit): RedirectResponse
    {
        SocialConnection::query()->where('network', 'meta')->get()->each->delete();
        $audit->record('social.meta_disconnected', null);

        return $this->to('portal.social.media', [], 'Legătura cu Meta a fost ștearsă. Fotografiile și postările deja citite rămân.');
    }

    public function sync(MetaResearch $research): RedirectResponse
    {
        $connection = SocialConnection::query()->where('network', 'meta')->firstOrFail();
        try {
            $result = $research->sync($connection);
        } catch (MetaError $e) {
            return $this->to('portal.social.media')->with('error', $e->getMessage());
        }

        return $this->to('portal.social.media', [], "Am citit pagina: {$result['posts']} postări noi și {$result['images']} fotografii noi.");
    }
}
