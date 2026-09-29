<?php
// Router pentru serverul PHP încorporat (doar dezvoltare): php -S 127.0.0.1:8080 tools/dev-router.php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = dirname(__DIR__);
if (preg_match('#^/(app|storage|tools|dist)(/|$)#', $uri)) { http_response_code(403); exit('Forbidden'); }
if (str_starts_with($uri, '/install')) { $_SERVER['SCRIPT_NAME'] = '/install/index.php'; require $root . '/install/index.php'; return true; }
if ($uri !== '/' && is_file($root . $uri) && !str_ends_with($uri, '.php')) { return false; }
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
