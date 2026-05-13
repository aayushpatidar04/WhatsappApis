<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;

class MessageCleanupCommand extends Command
{
    protected $signature   = 'messages:cleanup {--days=90 : Delete messages older than this many days}';
    protected $description = 'Delete old message records to keep DB size manageable.';

    public function handle(): int
    {
        $days    = (int) $this->option('days');
        $cutoff  = now()->subDays($days);

        $deleted = Message::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} messages older than {$days} days.");
        return self::SUCCESS;
    }
}