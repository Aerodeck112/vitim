<?php

use Illuminate\Support\Facades\Schedule;

/*
| Pe cPanel există un singur cron, la fiecare minut:
|   cd ~/vitim-ai && php artisan vitim:deploy && php artisan schedule:run
| vitim:deploy rulează separat, înainte: la o instalare nouă creează tabelele (inclusiv cache-ul
| de care depinde scheduler-ul). Tot restul pornește de aici (fără procese permanente).
*/

// coada rulează în bucăți sub un minut, ca să nu depășească limitele hostingului
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(2);

Schedule::command('vitim:events')->everyMinute()->withoutOverlapping(5);

Schedule::command('vitim:backup')->dailyAt('03:17')->withoutOverlapping(60);
Schedule::command('auth:clear-resets')->daily();

// auditul SEO / securitate / legal: câteva site-uri la 5 minute, fiecare o dată pe zi
Schedule::command('vitim:audit')->everyFiveMinutes()->withoutOverlapping(30);
