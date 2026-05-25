<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Jobs\SyncInstanceStatuses;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Artisan::command('instances:heartbeat', function () {
    // You can keep this empty if you already have a command class
})->describe('Ping Baileys for every active/disconnected instance');

Artisan::command('instances:expiry-check', function () {
    // Same here, if you have a command class, just leave it
})->describe('Daily expiry check for instances');

// Schedule definitions
app(Schedule::class)->command('instances:heartbeat')
    ->everyTwoMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('instances:heartbeat failed');
    });

app(Schedule::class)->command('instances:expiry-check')
    ->dailyAt('09:00')
    ->withoutOverlapping();

// Clean up old message records (keep 90 days)
app(Schedule::class)->command('messages:cleanup --days=90')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Launch scheduled campaigns whose schedule_time has arrived
app(Schedule::class)->command('campaigns:dispatch')
    ->everyMinute()->withoutOverlapping()->runInBackground();

// ── Credit accrual ───────────────────────────────────────────
app(Schedule::class)->command('credits:accrue-daily')
    ->everyMinute()->withoutOverlapping();

// Queue cleanup
app(Schedule::class)->command('queue:prune-failed --hours=720')
    ->daily();

app(Schedule::class)->job(new SyncInstanceStatuses)->everyMinute()->withoutOverlapping();