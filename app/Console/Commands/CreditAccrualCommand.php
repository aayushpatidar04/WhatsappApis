<?php

namespace App\Console\Commands;

use App\Models\InstanceCredit;
use App\Models\WhatsappInstance;
use App\Services\CreditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Runs daily at 00:05.
 * For every ACTIVE instance: deducts 1/30th of a credit.
 * If credits run out → instance is suspended.
 */
class CreditAccrualCommand extends Command
{
    protected $signature = 'credits:accrue-daily';
    protected $description = 'Expire instance credits and update instance state.';

    public function handle(CreditService $creditService): int
    {
        $this->info('Running daily credit accrual…');

        $expiredCredits = InstanceCredit::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredCredits as $credit) {
            try {
                $credit->update([
                    'status' => 'expired',
                ]);

                $instance = $credit->instance()->lockForUpdate()->first();

                // Adjust counters
                $instance->decrement('credits_assigned', $credit->credits);
                $instance->increment('credits_consumed', $credit->credits);

                // Look for next queued credit
                $nextCredit = InstanceCredit::where('instance_id', $instance->id)
                    ->where('status', 'queued')
                    ->orderBy('starts_at')
                    ->first();

                if ($nextCredit) {
                    // Activate queued credit
                    $nextCredit->update([
                        'status' => 'active',
                        'activated_at' => now(),
                    ]);

                    $instance->status = WhatsappInstance::STATUS_ACTIVE;
                } else {
                    // No credits left → suspend
                    $instance->status = WhatsappInstance::STATUS_SUSPENDED;
                    $instance->activated_at = null;
                    $instance->expires_at = null;
                }

                $instance->save();

                $this->info("Expired credit for instance {$instance->id} → status {$instance->status}");
            } catch (\Throwable $e) {
                Log::error("Credit expiry failed for credit {$credit->id}: {$e->getMessage()}");
                $this->error("Error: [{$credit->id}] {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}