<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Knowledge\SiteKnowledge;
use App\Models\Agent;

/** Catalogul de tool-uri (cod). Fiecare agent folosește doar acțiunile permise în configurația lui. */
final class ToolRegistry
{
    /** @var array<string, class-string<Tool>> */
    private const TOOLS = [
        'create_lead' => CreateLeadTool::class,
        'request_human' => RequestHumanTool::class,
    ];

    /** @return array<string, Tool> în ordine deterministă (prefixul cache-uit nu se schimbă între cereri) */
    public function forAgent(Agent $agent): array
    {
        $allowed = $agent->system_configuration['allowed_actions'] ?? [];
        $tools = [];
        foreach (self::TOOLS as $name => $class) {
            if (in_array($name, $allowed, true)) {
                $tools[$name] = app($class);
            }
        }

        // ce știe din site nu e o acțiune de permis: e disponibil când site-ul agentului a fost citit
        if (SiteKnowledge::available($agent->site_id)) {
            $tools['search_site'] = app(SearchSiteTool::class);
        }

        return $tools;
    }
}
