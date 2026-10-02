<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Imaginile din emailuri (trebuie să fie publice: le încarcă clientul de email al destinatarului). Nume aleatoare, fără listare. */
final class MediaController extends Controller
{
    public function show(int $organization, string $file): Response
    {
        $path = 'marketing/'.$organization.'/'.$file;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'public, max-age=31536000, immutable', 'X-Content-Type-Options' => 'nosniff']);
    }
}
