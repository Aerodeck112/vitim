<?php

declare(strict_types=1);

namespace App\Ai\Local;

/**
 * Informațiile firmei (textul scris de client în pagina agentului) împărțite în bucăți căutabile:
 * întrebări și răspunsuri, rânduri „Titlu: conținut”, liste sub un titlu și paragrafe.
 * Căutarea e lexicală, adaptată pentru română: fără diacritice, cu forme flexionate și sinonime uzuale.
 */
final class Knowledge
{
    /** Cuvinte fără conținut (după eliminarea diacriticelor). */
    private const STOP = ['a', 'ai', 'al', 'ale', 'alt', 'am', 'ar', 'are', 'as', 'asa', 'asta', 'aceasta', 'acest', 'acesta', 'acum', 'aici', 'atat',
        'au', 'avem', 'aveti', 'ca', 'cand', 'care', 'ce', 'cei', 'cel', 'cele', 'cu', 'da', 'daca', 'de', 'deci', 'din', 'doar', 'dumneavoastra',
        'e', 'ea', 'el', 'eu', 'este', 'esti', 'fi', 'fie', 'foarte', 'imi', 'in', 'inca', 'intr', 'intre', 'la', 'le', 'li', 'lor', 'lui', 'ma', 'mai',
        'mi', 'mie', 'mult', 'multe', 'multumesc', 'ne', 'nici', 'noi', 'nu', 'o', 'ok', 'pe', 'pentru', 'poate', 'pot', 'poti', 'puteti', 'sa', 'se',
        'si', 'sunt', 'sunteti', 'ta', 'te', 'tu', 'un', 'una', 'unei', 'unui', 'va', 'vreau', 'vrea', 'vrem', 'vom', 'voi', 'buna', 'salut', 'ziua',
        'seara', 'dimineata', 'va', 'rog', 'stiu', 'spune', 'spuneti', 'zice', 'aveti', 'avea', 'fost', 'cum', 'cat', 'cate', 'cati', 'ati', 'ii', 'iti',
        'mea', 'meu', 'mele', 'mei', 'vostru', 'voastra', 'nostru', 'noastra', 'sau', 'ori', 'dar', 'iar', 'prin', 'fara', 'despre', 'dupa', 'pana'];

    /** Sinonime: orice cuvânt care începe cu unul dintre prefixe devine termenul canonic. */
    private const SYNONYMS = [
        'pret' => ['pret', 'cost', 'tarif', 'lei', 'euro', 'eur', 'ron', 'ieftin', 'scump', 'oferta', 'buget', 'deviz'],
        'program' => ['program', 'orar', 'deschi', 'inchi', 'luni', 'marti', 'miercuri', 'joi', 'vineri', 'sambat', 'duminic', 'weekend', 'sarbator'],
        'adresa' => ['adres', 'unde', 'locati', 'sediu', 'gasesc', 'gasim', 'situat', 'harta', 'strada', 'str', 'judet', 'oras', 'magazin', 'showroom'],
        'contact' => ['telefon', 'tel', 'numar', 'mail', 'email', 'contact', 'suna', 'whatsapp'],
        'livrare' => ['livr', 'transport', 'curier', 'expedi', 'trimite'],
        'plata' => ['plat', 'card', 'cash', 'numerar', 'rate', 'factur', 'transfer', 'avans'],
        'garantie' => ['garant', 'retur', 'return', 'schimb'],
        'programare' => ['programar', 'programez', 'rezerv', 'appoint', 'consult'],
        'durata' => ['durat', 'dureaz', 'termen', 'gata', 'execut', 'timp'],
        'istorie' => ['istori', 'infiint', 'fondat', 'experient', 'echip', 'exist', 'vechime', 'pornit', 'incepu', 'famili'],
        'servicii' => ['servic', 'ofer', 'produs', 'faceti', 'lucrari', 'realiz'],
    ];

    /** Etichete prea generale ca să decidă singure răspunsul. */
    private const WEAK = ['#servicii'];

    /** @var list<array{title: string, body: string, terms: array<string, float>}> */
    private array $chunks = [];

    /** @var array<string, int> */
    private array $df = [];

    public function __construct(string $text)
    {
        foreach (self::split($text) as [$title, $body]) {
            $terms = [];
            foreach (self::terms($title) as $t) {
                $terms[$t] = ($terms[$t] ?? 0) + 2.0;
            }
            foreach (self::terms($body) as $t) {
                $terms[$t] = ($terms[$t] ?? 0) + 1.0;
            }
            if ($terms === []) {
                continue;
            }
            $this->chunks[] = ['title' => $title, 'body' => $body, 'terms' => $terms];
            foreach (array_keys($terms) as $t) {
                $this->df[$t] = ($this->df[$t] ?? 0) + 1;
            }
        }
    }

    public function isEmpty(): bool
    {
        return $this->chunks === [];
    }

    /** Titlurile secțiunilor (pentru „Te pot ajuta cu…”). @return list<string> */
    public function topics(int $max = 6): array
    {
        $titles = array_values(array_unique(array_filter(array_map(
            fn (array $c) => ! str_ends_with($c['title'], '?') && mb_strlen($c['title']) <= 30 ? $c['title'] : '',
            $this->chunks,
        ))));

        return array_slice($titles, 0, $max);
    }

    /**
     * Cele mai potrivite bucăți pentru întrebare, cu scor și acoperire (ce parte din termenii întrebării se regăsesc).
     *
     * @return list<array{title: string, body: string, score: float, coverage: float}>
     */
    public function search(string $question, int $limit = 2): array
    {
        $groups = self::groups($question);
        if ($groups === [] || $this->chunks === []) {
            return [];
        }
        $n = count($this->chunks);
        $results = [];
        foreach ($this->chunks as $chunk) {
            $score = 0.0;
            $covered = 0.0;
            foreach ($groups as [$stem, $tags]) {
                // potrivire pe cuvânt (formă redusă sau prefix comun lung), altfel pe sinonim (cu pondere mai mică)
                $best = 0.0;
                $term = null;
                if (isset($chunk['terms'][$stem])) {
                    [$best, $term] = [1.0, $stem];
                } elseif (strlen($stem) >= 5) {
                    foreach ($chunk['terms'] as $t => $_) {
                        $t = (string) $t;
                        // fără potriviri între clase diferite de sinonime (program ≠ programare)
                        if ($t[0] !== '#' && strlen($t) >= 5 && (str_starts_with($t, $stem) || str_starts_with($stem, $t)) && self::tags($t) == $tags) {
                            [$best, $term] = [0.9, $t];
                            break;
                        }
                    }
                }
                foreach ($tags as $tag) {
                    $weight = in_array($tag, self::WEAK, true) ? 0.4 : 0.8;
                    if ($weight > $best && isset($chunk['terms'][$tag])) {
                        [$best, $term] = [$weight, $tag];
                    }
                }
                if ($term !== null) {
                    $tf = $chunk['terms'][$term];
                    $score += $best * log(1 + $n / ($this->df[$term] ?? 1)) * ($tf / ($tf + 1.0));
                    $covered += $best;
                }
            }
            if ($score > 0) {
                $results[] = ['title' => $chunk['title'], 'body' => $chunk['body'], 'score' => $score, 'coverage' => $covered / count($groups)];
            }
        }
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Cuvintele întrebării: forma redusă + etichetele de sinonime.
     *
     * @return list<array{0: string, 1: list<string>}>
     */
    public static function groups(string $text): array
    {
        $out = [];
        foreach (self::words($text) as $word) {
            $out[] = [self::stem($word), self::tags($word)];
        }
        // „Cât e / cât costă / cât face…” = întrebare despre preț
        if (preg_match('/\bcat (e|este|ar fi|costa|ar costa|face|vine|ma costa|ne costa)\b|\bcat\W*$/', self::fold($text))) {
            $out[] = ['#pret', []];
        }

        return $out;
    }

    /** Fragmentul relevant dintr-o bucată lungă: propozițiile care conțin termenii întrebării (max. 3). */
    public static function excerpt(string $body, string $question, int $max = 520): string
    {
        if (mb_strlen($body) <= $max) {
            return $body;
        }
        $query = array_flip(self::terms($question));
        $sentences = preg_split('/(?<=[.!?])\s+|\R/u', $body) ?: [];
        $picked = array_values(array_filter($sentences, fn (string $s) => array_intersect_key(array_flip(self::terms($s)), $query) !== []));
        $text = implode(' ', array_slice($picked ?: $sentences, 0, 3));

        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)).'…' : $text;
    }

    /** @return list<string> termenii normalizați ai unui text (fără cuvinte de legătură, cu sinonime și forme reduse) */
    public static function terms(string $text): array
    {
        $out = [];
        foreach (self::words($text) as $word) {
            $out[] = self::stem($word);
            array_push($out, ...self::tags($word));
        }

        return $out;
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        return array_values(array_filter(preg_split('/[^a-z0-9]+/', self::fold($text)) ?: [],
            fn (string $w) => $w !== '' && ! in_array($w, self::STOP, true) && (strlen($w) >= 3 || ctype_digit($w))));
    }

    /** @return list<string> */
    private static function tags(string $word): array
    {
        $tags = preg_match('/^(19|20)\d\d$/', $word) ? ['#istorie'] : [];
        foreach (self::SYNONYMS as $canonical => $prefixes) {
            foreach ($prefixes as $p) {
                if (str_starts_with($word, $p) && ! ($canonical === 'program' && preg_match('/^programa?r|^programez/', $word))) {
                    $tags[] = '#'.$canonical;
                    break;
                }
            }
        }

        return $tags;
    }

    /** Litere mici, fără diacritice (inclusiv ş/ţ cu sedilă). */
    public static function fold(string $text): string
    {
        return strtr(mb_strtolower($text), ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't', 'é' => 'e', 'ü' => 'u', 'ö' => 'o']);
    }

    /** Reducere simplă a formelor flexionate: pret / pretul / preturile → pret. */
    private static function stem(string $word): string
    {
        if (ctype_digit($word)) {
            return $word;
        }
        foreach (['urilor', 'urile', 'ilor', 'ului', 'elor', 'ul', 'uri', 'ile', 'ele', 'lor', 'ii', 'ea', 'ia', 'le', 'ei', 'a', 'e', 'i', 'u'] as $suffix) {
            if (str_ends_with($word, $suffix) && strlen($word) - strlen($suffix) >= 4) {
                return substr($word, 0, -strlen($suffix));
            }
        }

        return $word;
    }

    /** @return list<array{0: string, 1: string}> [titlu, conținut] */
    private static function split(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return [];
        }
        $chunks = [];
        foreach (preg_split('/\n\s*\n/', $text) ?: [] as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($l) => $l !== ''));
            $i = 0;
            while ($i < count($lines)) {
                $line = $lines[$i];
                // întrebare + răspuns: „Î: …? / R: …” sau un rând care se termină cu „?”
                if (preg_match('/^(?:Î|I|Q|Intrebare|Întrebare)\s*[:.\-]\s*(.+)$/iu', $line, $m) || str_ends_with($line, '?')) {
                    $question = isset($m[1]) ? trim($m[1]) : $line;
                    $answer = [];
                    $i++;
                    while ($i < count($lines) && ! str_ends_with($lines[$i], '?') && ! preg_match('/^(?:Î|I|Q|Intrebare|Întrebare)\s*[:.\-]/iu', $lines[$i])) {
                        $answer[] = preg_replace('/^(?:R|A|Raspuns|Răspuns)\s*[:.\-]\s*/iu', '', $lines[$i]);
                        $i++;
                    }
                    if ($answer) {
                        $chunks[] = [$question, implode("\n", $answer)];
                    }
                    unset($m);

                    continue;
                }
                // „Titlu: conținut” (titlu scurt), cu eventuale rânduri de listă dedesubt
                if (preg_match('/^([^:]{2,40}):\s*(.*)$/u', $line, $m) && ! preg_match('#https?$#', $m[1])) {
                    $body = [$m[2]];
                    $i++;
                    while ($i < count($lines) && preg_match('/^[-•*–]\s*/u', $lines[$i])) {
                        $body[] = $lines[$i];
                        $i++;
                    }
                    $chunks[] = [trim($m[1]), trim(implode("\n", array_filter($body, fn ($b) => $b !== '')))];

                    continue;
                }
                // titlu pe un rând (scurt, fără punct) urmat de o listă / text
                if ($i + 1 < count($lines) && mb_strlen($line) <= 40 && ! preg_match('/[.!,;]$/u', $line)) {
                    $body = [];
                    $i++;
                    while ($i < count($lines) && ! preg_match('/^([^:]{2,40}):/u', $lines[$i]) && ! str_ends_with($lines[$i], '?')) {
                        $body[] = $lines[$i];
                        $i++;
                    }
                    $chunks[] = [$line, implode("\n", $body)];

                    continue;
                }
                // paragraf obișnuit (lung: împărțit în grupuri de propoziții)
                $para = [];
                while ($i < count($lines) && ! str_ends_with($lines[$i], '?') && ! preg_match('/^([^:]{2,40}):/u', $lines[$i])) {
                    $para[] = $lines[$i];
                    $i++;
                }
                foreach (self::paragraphs(implode(' ', $para)) as $p) {
                    $chunks[] = ['', $p];
                }
            }
        }

        return array_values(array_filter($chunks, fn ($c) => trim($c[1]) !== '' || trim($c[0]) !== ''));
    }

    /** Textul liber se caută pe propoziții: răspunsul e exact propoziția care conține informația. @return list<string> */
    private static function paragraphs(string $text): array
    {
        $sentences = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+(?=\p{Lu}|\d)/u', $text) ?: [])));
        $out = [];
        foreach ($sentences as $sentence) {
            // propozițiile foarte scurte rămân lipite de cea dinainte
            if ($out !== [] && mb_strlen($sentence) < 35) {
                $out[count($out) - 1] .= ' '.$sentence;
            } else {
                $out[] = $sentence;
            }
        }

        return $out;
    }
}
