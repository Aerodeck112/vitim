<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Csrf;
use App\Core\View;

abstract class AdminController
{
    protected string $area = 'dashboard';

    public function __construct()
    {
        if (!Auth::check()) {
            if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                json_out(['ok' => false, 'message' => 'Sesiunea a expirat. Reautentifică-te.'], 401);
            }
            $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? null;
            redirect('/admin/login');
        }
        if (!Auth::can($this->area)) {
            http_response_code(403);
            echo View::render('admin/403', ['title' => 'Acces interzis'], 'admin/layout');
            exit;
        }
        if (request_method() === 'POST' && !Csrf::verify()) {
            if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                json_out(['ok' => false, 'message' => 'Token de securitate invalid. Reîncarcă pagina.'], 419);
            }
            flash('err', 'Sesiunea formularului a expirat. Încearcă din nou.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/admin/dashboard');
        }
    }

    protected function render(string $view, array $data = []): string
    {
        return View::render('admin/' . $view, $data, 'admin/layout');
    }

    protected function isPost(): bool
    {
        return request_method() === 'POST';
    }

    /** După orice modificare de conținut public: golim cache-ul de pagini. */
    protected function contentChanged(array $urls = []): void
    {
        Cache::clear();
        if ($urls) {
            \App\Core\Seo::indexNow(array_map(fn($u) => abs_url($u), $urls));
        }
    }

    protected function paginate(int $total, int $per, int $page): array
    {
        $pages = max(1, (int)ceil($total / $per));
        $page = min(max(1, $page), $pages);
        return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $per, 'per' => $per, 'total' => $total];
    }
}
