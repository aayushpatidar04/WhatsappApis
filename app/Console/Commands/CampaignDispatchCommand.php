<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Runs every minute via Laravel scheduler.
 * Finds campaigns with schedule_time <= now() and status = scheduled,
 * then launches them.
 */
class CampaignDispatchCommand extends Command
{
    protected $signature   = 'campaigns:dispatch';
    protected $description = 'Launch scheduled campaigns whose time has arrived.';

    public function handle(CampaignService $campaignService): int
    {
        $due = Campaign::where('status', Campaign::STATUS_SCHEDULED)
            ->where('schedule_time', '<=', now())
            ->whereNotNull('schedule_time')
            ->get();
        if ($due->isEmpty()) {
            return self::SUCCESS;
        }

        $this->info("Dispatching {$due->count()} scheduled campaign(s)…");

        foreach ($due as $campaign) {
            try {
                $campaignService->launch($campaign);
                $this->line("  ✓ Launched: [{$campaign->id}] {$campaign->name}");
                Log::info("Scheduled campaign launched: {$campaign->id}");
            } catch (\Throwable $e) {
                $campaign->update(['status' => Campaign::STATUS_FAILED]);
                $this->error("  ✗ Failed: [{$campaign->id}] {$campaign->name} — {$e->getMessage()}");
                Log::error("Scheduled campaign failed: {$campaign->id} — {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}