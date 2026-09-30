<?php

declare(strict_types=1);

namespace App\Enums;

/** Pipeline-ul implicit. Etapele configurabile per firmă vin într-o fază ulterioară (coloana e string). */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Proposal = 'proposal';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nou',
            self::Contacted => 'Contactat',
            self::Qualified => 'Calificat',
            self::Proposal => 'Ofertă trimisă',
            self::Won => 'Câștigat',
            self::Lost => 'Pierdut',
        };
    }

    public function isClosed(): bool
    {
        return $this === self::Won || $this === self::Lost;
    }
}
