<?php
// Autoloader minimal: singura dependență externă este PHPMailer (licență LGPL 2.1).
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'PHPMailer\\PHPMailer\\')) {
        $file = __DIR__ . '/phpmailer/phpmailer/src/' . substr($class, 20) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
