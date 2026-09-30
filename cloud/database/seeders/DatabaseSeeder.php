<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Doar date demo, doar în afara producției. */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
