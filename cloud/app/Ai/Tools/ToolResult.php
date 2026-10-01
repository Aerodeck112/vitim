<?php

declare(strict_types=1);

namespace App\Ai\Tools;

/** Rezultatul trimis modelului (`message`) + starea pentru jurnal. status: ok | rejected | error | dry_run */
final readonly class ToolResult
{
    private function __construct(public string $status, public string $message) {}

    public static function ok(string $message): self
    {
        return new self('ok', $message);
    }

    /** Cererea modelului nu respectă regulile (ex. lipsește acordul): modelul primește motivul și poate reveni. */
    public static function rejected(string $message): self
    {
        return new self('rejected', $message);
    }

    public static function error(string $message): self
    {
        return new self('error', $message);
    }

    public static function dryRun(string $message): self
    {
        return new self('dry_run', $message);
    }

    public function isError(): bool
    {
        return $this->status === 'rejected' || $this->status === 'error';
    }
}
