<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Models\Flow;
use App\Models\FlowStep;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\CampaignRenderer;
use App\Services\EmailBlocks;
use App\Services\EmailTemplates;
use App\Services\MessageContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Editorul vizual de email (campanii și pașii automatizărilor), imaginile încărcate, șabloanele și brandul firmei. */
final class DesignController extends PortalController
{
    public function campaign(int $campaign): View
    {
        $model = Campaign::query()->where('channel', 'email')->findOrFail($campaign);

        return $this->editor($model->name, (array) ($model->blocks ?: EmailTemplates::all()['simple']['blocks']), $model->preheader, (string) $model->subject,
            route('portal.design.campaign.save', [$this->organization()->slug, $model->id]), route('portal.campaigns.show', [$this->organization()->slug, $model->id]), $model->editable());
    }

    public function saveCampaign(Request $request, AuditLogger $audit, int $campaign): RedirectResponse
    {
        $model = Campaign::query()->where('channel', 'email')->findOrFail($campaign);
        abort_unless($model->editable(), 422, 'Campania a fost aprobată și nu se mai poate modifica.');
        $model->forceFill(['blocks' => $this->blocks($request), 'preheader' => mb_substr(trim((string) $request->input('preheader')), 0, 150) ?: null])->save();
        $audit->record('campaign.designed', $model);

        return $this->to('portal.design.campaign', ['campaign' => $model->id], 'Designul a fost salvat.');
    }

    public function step(int $flow, int $step): View
    {
        $model = FlowStep::query()->where('flow_id', Flow::query()->findOrFail($flow)->id)->where('type', 'email')->findOrFail($step);

        return $this->editor('Email din automatizare', (array) ($model->conf('blocks') ?: EmailTemplates::all()['simple']['blocks']), $model->conf('preheader'), (string) $model->conf('subject'),
            route('portal.flows.steps.design.save', [$this->organization()->slug, $flow, $model->id]), route('portal.flows.show', [$this->organization()->slug, $flow]).'#step-'.$model->id, true);
    }

    public function saveStep(Request $request, int $flow, int $step): RedirectResponse
    {
        $model = FlowStep::query()->where('flow_id', Flow::query()->findOrFail($flow)->id)->where('type', 'email')->findOrFail($step);
        $model->forceFill(['config' => array_merge((array) $model->config, ['blocks' => $this->blocks($request), 'preheader' => mb_substr(trim((string) $request->input('preheader')), 0, 150) ?: null])])->save();

        return $this->to('portal.flows.steps.design', ['flow' => $flow, 'step' => $model->id], 'Designul a fost salvat.');
    }

    /** Previzualizarea live din editor (același randament ca emailul real, pentru un contact de exemplu). */
    public function preview(Request $request, CampaignRenderer $renderer): JsonResponse
    {
        $blocks = EmailBlocks::clean(json_decode((string) $request->input('blocks', '[]'), true));
        $content = new MessageContent(Channel::Email, (string) $request->input('subject', ''), '', null, $blocks, (string) $request->input('preheader', '') ?: null);
        $rendered = $renderer->render($content, $this->organization(), new Contact(['first_name' => 'Maria', 'last_name' => 'Popescu']), route('unsubscribe', 'TEST'));

        return response()->json(['html' => $rendered['html']]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'max:3072', 'mimes:jpg,jpeg,png,gif,webp']], ['image.max' => 'Imaginea poate avea cel mult 3 MB.', 'image.mimes' => 'Doar imagini JPG, PNG, GIF sau WebP.']);
        $file = $request->file('image');
        $name = Str::random(32).'.'.strtolower($file->extension() === 'jpeg' ? 'jpg' : $file->extension());
        Storage::disk('local')->putFileAs('marketing/'.$this->organization()->id, $file, $name);

        return response()->json(['url' => route('media', [$this->organization()->id, $name])]);
    }

    public function saveTemplate(Request $request, AuditLogger $audit): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:120']]);
        $template = EmailTemplate::create(['name' => $request->input('name'), 'blocks' => $this->blocks($request)]);
        $audit->record('email_template.saved', $template);

        return response()->json(['id' => $template->id, 'name' => $template->name]);
    }

    public function brand(): View
    {
        return view('portal.brand', ['organization' => $this->organization(), 'brand' => (array) ($this->organization()->branding ?? [])]);
    }

    public function saveBrand(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'logo_url' => ['nullable', 'url:https', 'max:500'], 'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font' => ['nullable', Rule::in(['Arial, Helvetica, sans-serif', 'Georgia, serif', 'Verdana, sans-serif', 'Trebuchet MS, sans-serif'])],
            'facebook' => ['nullable', 'url:https', 'max:300'], 'instagram' => ['nullable', 'url:https', 'max:300'], 'website' => ['nullable', 'url:https', 'max:300'],
            'logo' => ['nullable', 'file', 'max:2048', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);
        $organization = $this->organization();
        if ($request->hasFile('logo')) {
            $name = Str::random(32).'.'.($request->file('logo')->extension() === 'jpeg' ? 'jpg' : $request->file('logo')->extension());
            Storage::disk('local')->putFileAs('marketing/'.$organization->id, $request->file('logo'), $name);
            $data['logo_url'] = route('media', [$organization->id, $name]);
        }
        unset($data['logo']);
        $organization->forceFill(['branding' => array_filter($data + ['font' => 'Arial, Helvetica, sans-serif'])])->save();
        $audit->record('brand.updated', $organization);

        return $this->to('portal.brand', [], 'Brandul a fost salvat. Se aplică automat în emailurile create cu editorul.');
    }

    private function editor(string $title, array $blocks, ?string $preheader, string $subject, string $saveUrl, string $backUrl, bool $editable): View
    {
        return view('portal.design.editor', [
            'organization' => $this->organization(),
            'title' => $title, 'blocks' => EmailBlocks::clean($blocks), 'preheader' => $preheader, 'subject' => $subject,
            'saveUrl' => $saveUrl, 'backUrl' => $backUrl, 'editable' => $editable,
            'library' => EmailTemplates::all(),
            'saved' => EmailTemplate::query()->latest('id')->get(['id', 'name', 'blocks']),
            'types' => EmailBlocks::TYPES,
            'products' => class_exists(Product::class) && Schema::hasTable('products')
                ? Product::query()->orderBy('name')->limit(500)->get(['id', 'name', 'price', 'currency', 'image', 'url']) : collect(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function blocks(Request $request): array
    {
        return EmailBlocks::clean(json_decode((string) $request->input('blocks', '[]'), true));
    }
}
