<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /** Paginare: ?per_page=1..100 (implicit 25), ?page=N. */
    protected function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->query('per_page', 25)));
    }

    /** Termen de căutare sigur pentru LIKE (fără metacaractere interpretate). */
    protected function like(string $term): string
    {
        return '%'.addcslashes(trim($term), '%_\\').'%';
    }
}
