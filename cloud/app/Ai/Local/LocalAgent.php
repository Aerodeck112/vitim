<?php

declare(strict_types=1);

namespace App\Ai\Local;

use App\Ai\Tools\ToolResult;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ToolExecution;
use App\Services\IdentityNormalizer;
use Closure;

/**
 * Agentul fără model AI extern (gratuit): răspunde din „Informații despre firmă”, adună datele de contact
 * cu acordul explicit al vizitatorului și anunță echipa când e nevoie de un om. Folosește aceleași acțiuni
 * (create_lead, request_human) și aceleași reguli ca agentul cu AI, deci lead-urile și notificările sunt identice.
 */
final class LocalAgent
{
    /** @var Closure(string, array<string, mixed>): array{0: ToolResult, 1: ToolExecution} */
    private Closure $run;

    private bool $formal = false;

    /**
     * @param  array<string, mixed>  $tools  acțiunile permise agentului (doar cheile contează aici)
     * @param  Closure(string, array<string, mixed>): array{0: ToolResult, 1: ToolExecution}  $run
     * @return array{0: string, 1: list<ToolExecution>}
     */
    public function reply(Agent $agent, Conversation $conversation, string $text, array $tools, Closure $run): array
    {
        $this->run = $run;
        $system = $agent->system_configuration;
        $this->formal = ($system['tone'] ?? '') === 'formal';
        $state = (array) ($conversation->bot_state ?? []) + ['lead' => [], 'awaiting' => null, 'lead_saved' => false, 'human' => false];
        $executions = [];
        $folded = Knowledge::fold($text);
        $canLead = isset($tools['create_lead']);
        $canHuman = isset($tools['request_human']);
        $contactLine = trim((string) ($system['contact_line'] ?? ''));

        $answer = (function () use ($agent, $conversation, $text, $folded, $system, $canLead, $canHuman, $contactLine, &$state, &$executions): string {
            // 1. datele de contact din mesaj (telefon, email, nume)
            $found = $this->contactDetails($text, $state['awaiting'] === 'name');
            if ($canLead && $found) {
                $state['lead'] = array_filter($found + $state['lead']);
                if ($this->consentIn($folded)) {
                    return $this->saveLead($agent, $conversation, $system, $state, $executions);
                }

                return $this->nextLeadStep($system, $state);
            }

            // 2. răspuns la întrebarea de acord
            if (in_array($state['awaiting'], ['consent', 'name'], true)) {
                if ($state['awaiting'] === 'consent' && $this->isYes($folded)) {
                    return $this->saveLead($agent, $conversation, $system, $state, $executions);
                }
                if ($this->isNo($folded)) {
                    $state['awaiting'] = null;
                    $state['lead'] = [];

                    return $this->say('În regulă, nu păstrez datele. Cu ce te mai pot ajuta?', 'În regulă, nu păstrăm datele. Cu ce vă mai putem ajuta?');
                }
            }

            // 3. cere un om / reclamație
            $complaint = (bool) preg_match('/\b(reclamati|nemultumit|plangere|plang|inselat|teapa|suparat|bataie de joc)/', $folded);
            $wantsHuman = (bool) preg_match('/\b(operator|om real|persoana reala|agent uman|un om\b|cu un om|cu cineva|cu o persoana|cu un coleg|cu un consultant|vorbesc cu|sunati-?ma|suna-?ma|ma puteti suna)/', $folded);
            if ($canHuman && (($wantsHuman && ($system['handoff_rules']['on_request'] ?? true)) || ($complaint && ($system['handoff_rules']['on_complaint'] ?? true)))) {
                return $this->handoff($conversation, $state, $executions, $complaint ? 'complaint' : 'visitor_request', $text, $canLead);
            }

            // 4. formule scurte de politețe
            if (preg_match('/^\W*(buna|salut|salutare|hello|hei|hey|neata|buna ziua|buna seara|buna dimineata|servus)\W*$/', $folded)) {
                $greeting = trim((string) ($system['greeting'] ?? ''));

                return $greeting !== '' ? $greeting : $this->say('Bună! Cu ce te pot ajuta?', 'Bună ziua! Cu ce vă putem ajuta?');
            }
            if (preg_match('/^\W*(multumesc|multumim|mersi|merci|mulțumesc)( frumos| mult)?\W*$/', $folded)) {
                return $this->say('Cu plăcere! Mai pot ajuta cu ceva?', 'Cu plăcere! Vă mai putem ajuta cu ceva?');
            }
            if (preg_match('/^\W*(pa|la revedere|o zi buna|numai bine|o seara buna)\W*$/', $folded)) {
                return $this->say('O zi bună! Revino oricând ai întrebări.', 'O zi bună! Ne puteți scrie oricând.');
            }

            // 5. răspunsul din informațiile firmei
            $knowledge = new Knowledge((string) ($system['business_facts'] ?? ''));
            $hits = $knowledge->search($text);
            $best = $hits[0] ?? null;
            if ($best && ($best['coverage'] >= 0.34 || $best['score'] >= 1.2)) {
                $parts = [$this->format($best, $text)];
                $second = $hits[1] ?? null;
                if ($second && (($second['score'] >= $best['score'] * 0.8 && $second['coverage'] >= 0.5) || ($second['coverage'] >= $best['coverage'] && $second['score'] >= $best['score'] * 0.6))) {
                    $parts[] = $this->format($second, $text);
                }
                $terms = Knowledge::terms($text);
                if ($canLead && ! $state['lead_saved'] && array_intersect($terms, ['#pret', '#programare']) !== []) {
                    $parts[] = $this->say('Pentru o ofertă exactă sau o programare, lasă-mi un număr de telefon sau un email și te contactăm.',
                        'Pentru o ofertă exactă sau o programare, ne puteți lăsa un număr de telefon sau un email și vă contactăm.');
                }

                return implode("\n\n", $parts);
            }

            // 6. nu știe: comportamentul ales în pagina agentului
            $topics = $knowledge->topics();
            $menu = $topics ? "\n\n".$this->say('Te pot ajuta cu: ', 'Vă putem ajuta cu: ').implode(', ', array_map('mb_strtolower', $topics)).'.' : '';
            $fallback = $system['fallback_behavior'] ?? 'collect_contact';
            if ($canHuman && ($fallback === 'handoff' || ($system['handoff_rules']['on_low_confidence'] ?? false) && $fallback !== 'apologize' && ! $canLead)) {
                return $this->handoff($conversation, $state, $executions, 'no_answer', $text, $canLead, true);
            }
            if ($fallback === 'collect_contact' && $canLead && ! $state['lead_saved']) {
                $state['awaiting'] = 'contact';

                return $this->say('Nu am găsit informația asta. Lasă-mi un număr de telefon sau un email și un coleg îți răspunde cât mai curând.',
                    'Nu am găsit această informație. Ne puteți lăsa un număr de telefon sau un email și un coleg vă răspunde cât mai curând.').$menu;
            }

            return $this->say('Îmi pare rău, nu am informația asta.', 'Ne pare rău, nu avem această informație aici.')
                .($contactLine !== '' ? ' '.$this->say('Ne poți contacta la ', 'Ne puteți contacta la ').$contactLine.'.' : '').$menu;
        })();

        $conversation->forceFill(['bot_state' => $state])->save();

        return [$answer, $executions];
    }

    /** @param array<string, mixed> $state @param list<ToolExecution> $executions */
    private function handoff(Conversation $conversation, array &$state, array &$executions, string $reason, string $text, bool $canLead, bool $unknown = false): string
    {
        if (! $state['human']) {
            [$result, $execution] = ($this->run)('request_human', ['reason' => $reason, 'summary' => mb_substr($this->summary($conversation, $text), 0, 1000)]);
            $executions[] = $execution;
            $state['human'] = $result->status === 'ok' || $result->status === 'dry_run';
        }
        $intro = $unknown
            ? $this->say('Nu am informația asta, așa că am anunțat un coleg din echipă.', 'Nu avem această informație aici, așa că am anunțat un coleg din echipă.')
            : $this->say('Am anunțat un coleg din echipă și preia conversația cât mai curând.', 'Am anunțat un coleg din echipă, care preia conversația cât mai curând.');
        if ($canLead && ! $state['lead_saved']) {
            $state['awaiting'] = 'contact';

            return $intro.' '.$this->say('Ca să te poată contacta, lasă-mi un număr de telefon sau un email.', 'Ca să vă poată contacta, ne puteți lăsa un număr de telefon sau un email.');
        }

        return $intro;
    }

    /** Ce mai lipsește pentru lead: datele cerute în pagina agentului, apoi acordul. @param array<string, mixed> $state */
    private function nextLeadStep(array $system, array &$state): string
    {
        $required = $system['lead_rules']['required_fields'] ?? ['name', 'phone'];
        $lead = $state['lead'];
        if (in_array('name', $required, true) && empty($lead['name'])) {
            $state['awaiting'] = 'name';

            return $this->say('Mulțumesc! Cum te numești?', 'Vă mulțumim! Cum vă numiți?');
        }
        if ((in_array('phone', $required, true) && empty($lead['phone'])) || (in_array('email', $required, true) && empty($lead['email']))) {
            $state['awaiting'] = 'contact';

            return in_array('phone', $required, true) && empty($lead['phone'])
                ? $this->say('Îmi lași și un număr de telefon?', 'Ne lăsați și un număr de telefon?')
                : $this->say('Îmi lași și adresa de email?', 'Ne lăsați și adresa de email?');
        }
        if (empty($lead['phone']) && empty($lead['email'])) {
            $state['awaiting'] = 'contact';

            return $this->say('Îmi lași un număr de telefon sau un email?', 'Ne lăsați un număr de telefon sau un email?');
        }
        $state['awaiting'] = 'consent';
        $where = implode(' / ', array_filter([$lead['phone'] ?? null, $lead['email'] ?? null]));

        return $this->say("Ești de acord să te contactăm la {$where} în legătură cu cererea ta? Răspunde cu „Da”.",
            "Sunteți de acord să vă contactăm la {$where} în legătură cu cererea dumneavoastră? Răspundeți cu „Da”.");
    }

    /** @param array<string, mixed> $state @param list<ToolExecution> $executions */
    private function saveLead(Agent $agent, Conversation $conversation, array $system, array &$state, array &$executions): string
    {
        $lead = $state['lead'];
        $missing = $this->nextLeadStep($system, $state);
        if ($state['awaiting'] !== 'consent') {
            return $missing; // încă lipsesc date; acordul se cere după
        }
        $summary = $this->summary($conversation, '');
        [$result, $execution] = ($this->run)('create_lead', [
            'name' => (string) ($lead['name'] ?? ''), 'phone' => (string) ($lead['phone'] ?? ''), 'email' => (string) ($lead['email'] ?? ''),
            'company' => '', 'requested_service' => '', 'product' => '', 'budget' => '', 'preferred_date' => '', 'notes' => '',
            'intent' => 'other', 'summary' => $summary, 'consent' => true,
        ]);
        $executions[] = $execution;
        if ($result->status !== 'ok' && $result->status !== 'dry_run') {
            $state['awaiting'] = 'contact';

            return $this->say('Nu am putut salva datele: ', 'Nu am putut salva datele: ').rtrim(preg_replace('/\s*(Roagă|Cere|Întreabă).*$/u', '', $result->message) ?? '', '. ').'. '
                .$this->say('Le poți scrie din nou?', 'Le puteți scrie din nou?');
        }
        $state['awaiting'] = null;
        $state['lead_saved'] = true;
        $name = trim(explode(' ', (string) ($lead['name'] ?? ''))[0]);

        return $this->say('Mulțumesc'.($name ? ", {$name}" : '').'! Am transmis cererea și un coleg te contactează cât mai curând.',
            'Vă mulțumim'.($name ? ", {$name}" : '').'! Am transmis cererea și un coleg vă contactează cât mai curând.');
    }

    /** @return array<string, string> telefon, email, nume găsite în mesaj */
    private function contactDetails(string $text, bool $expectingName): array
    {
        $found = [];
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m) && IdentityNormalizer::email($m[0]) !== null) {
            $found['email'] = $m[0];
        }
        if (preg_match('/(?:\+|00)?\d[\d\s.\-()]{7,16}\d/', $text, $m) && IdentityNormalizer::phone($m[0]) !== null) {
            $found['phone'] = trim($m[0]);
        }
        if (preg_match('/(?i:mă numesc|ma numesc|numele meu (?:este|e)|eu sunt|sunt|aici e|aici este)\s+(\p{Lu}[\p{L}\-]+(?:\s+\p{Lu}[\p{L}\-]+)?)/u', $text, $m)) {
            $found['name'] = $m[1];
        } elseif ($expectingName && preg_match('/^\s*(\p{L}[\p{L}\-]+(?:\s+\p{L}[\p{L}\-]+){0,2})\s*[.!]?\s*$/u', $text, $m)
            && ! preg_match('/\b(cat|ce|cum|unde|cand|care|aveti|vreau|pret|nu|da|ok|program|buna|salut|multumesc|mersi)\b/', Knowledge::fold($text))) {
            $found['name'] = mb_convert_case($m[1], MB_CASE_TITLE);
        } elseif ($found && preg_match('/^\s*(\p{Lu}[\p{L}\-]+(?:\s+\p{Lu}[\p{L}\-]+)?)\s*[,\-]/u', $text, $m)) {
            $found['name'] = $m[1]; // „Ion Pop, 0722…”
        }

        return $found;
    }

    private function consentIn(string $folded): bool
    {
        return (bool) preg_match('/\b(de acord|sunati-?ma|suna-?ma|contactati-?ma|ma puteti (suna|contacta)|astept (telefon|apel)|da,|^da\b)/', $folded);
    }

    private function isYes(string $folded): bool
    {
        return (bool) preg_match('/^\W*(da|sigur|desigur|ok|okay|bine|accept|confirm|sunt de acord|de acord|evident|fireste|yes)\b/', $folded);
    }

    private function isNo(string $folded): bool
    {
        return (bool) preg_match('/^\W*(nu|nu multumesc|nu vreau|refuz|no)\b/', $folded);
    }

    /** Ce a cerut vizitatorul (ultimele mesaje), pentru colegul care preia. */
    private function summary(Conversation $conversation, string $text): string
    {
        $messages = Message::query()->where('conversation_id', $conversation->getKey())->where('direction', 'inbound')
            ->latest('id')->limit(6)->pluck('content')->reverse()->values()->all();
        if ($text !== '' && end($messages) !== $text) {
            $messages[] = $text;
        }

        return "Mesajele vizitatorului în chat:\n- ".implode("\n- ", array_map(fn ($m) => mb_substr((string) $m, 0, 300), $messages));
    }

    /** @param array{title: string, body: string} $hit */
    private function format(array $hit, string $question): string
    {
        $body = trim(Knowledge::excerpt($hit['body'], $question));
        $title = trim($hit['title']);
        if ($title === '' || str_ends_with($title, '?')) {
            return $body;
        }

        return $body === '' ? $title : "**{$title}**: {$body}";
    }

    private function say(string $informal, string $formal): string
    {
        return $this->formal ? $formal : $informal;
    }
}
