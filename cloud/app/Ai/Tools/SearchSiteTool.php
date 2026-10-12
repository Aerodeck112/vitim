<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Knowledge\SiteKnowledge;
use App\Models\Agent;

/**
 * Caută în paginile site-ului firmei (citite de panou) și în produsele magazinului. Site-ul vine din agent (server),
 * modelul trimite doar cuvintele căutate. Disponibil automat când agentul are un site citit sau un magazin sincronizat.
 */
final class SearchSiteTool implements Tool
{
    public function __construct(private readonly SiteKnowledge $knowledge) {}

    public function name(): string
    {
        return 'search_site';
    }

    public function definition(Agent $agent): array
    {
        return [
            'name' => 'search_site',
            'description' => 'Caută pe site-ul firmei: produse, servicii, prețuri, stocuri, livrare, garanție, condiții, program. Folosește-l înainte să spui că nu ai o informație. Primești fragmente de text cu linkul paginii; răspunde doar din ele.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Câteva cuvinte cheie în română (ex. „preț schimb ulei”, „livrare Cluj”, „canapea extensibilă gri”).'],
                ],
                'required' => ['query'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function handle(ToolContext $context, array $input): ToolResult
    {
        $query = mb_substr(trim((string) ($input['query'] ?? '')), 0, 200);
        $siteId = $context->agent->site_id;
        if ($query === '' || $siteId === null) {
            return ToolResult::rejected('Scrie ce cauți (câteva cuvinte cheie).');
        }

        return ToolResult::ok($this->knowledge->answer((int) $siteId, $query));
    }
}
