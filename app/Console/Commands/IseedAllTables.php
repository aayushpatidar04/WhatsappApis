<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class IseedAllTables extends Command
{
    protected $signature = 'iseed:all';
    protected $description = 'Generate seeders for all tables in the current database';

    public function handle()
    {
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        foreach ($tables as $table) {
            $this->info("Seeding table: {$table->name}");
            Artisan::call('iseed', ['tables' => $table->name]);
        }

        $this->info('All seeders generated successfully!');
    }
}
