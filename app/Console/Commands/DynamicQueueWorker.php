<?php

namespace App\Console\Commands;

use App\Models\WhatsappInstance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DynamicQueueWorker extends Command
{
    protected $signature = 'queue:worker:dynamic';

    protected $description = 'Maintain queue worker with dynamic queue discovery';

    public function handle(): int
    {
        Log::info('==============================');
        Log::info('DynamicQueueWorker started');

        try {

            /*
            |--------------------------------------------------------------------------
            | Discover queues
            |--------------------------------------------------------------------------
            */
            $instances = WhatsappInstance::query()
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->pluck('instance_token')
                ->toArray();

            $queues = [
                'high',
                'default',
                'webhooks',
                'background',
            ];

            foreach ($instances as $token) {
                $queues[] = 'instance-' . substr($token, 0, 16);
            }

            sort($queues);

            $queueString = implode(',', $queues);

            Log::info('Discovered queues', [
                'count' => count($queues),
                'queues' => $queueString,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Files
            |--------------------------------------------------------------------------
            */
            $stateFile = storage_path('app/dynamic-worker-queues.txt');
            $pidFile = storage_path('app/dynamic-worker.pid');

            $previousQueues = file_exists($stateFile)
                ? trim(file_get_contents($stateFile))
                : '';

            $workerRunning = false;
            $currentPid = null;

            /*
            |--------------------------------------------------------------------------
            | Check existing worker
            |--------------------------------------------------------------------------
            */
            if (file_exists($pidFile)) {

                $currentPid = trim(file_get_contents($pidFile));

                Log::info("PID file found: {$currentPid}");

                if ($this->isProcessRunning($currentPid)) {
                    $workerRunning = true;

                    Log::info("Worker process is running", [
                        'pid' => $currentPid
                    ]);
                } else {

                    Log::warning("PID exists but process not running", [
                        'pid' => $currentPid
                    ]);

                    @unlink($pidFile);
                }
            } else {
                Log::info('No PID file found');
            }

            /*
            |--------------------------------------------------------------------------
            | Worker not running
            |--------------------------------------------------------------------------
            */
            if (!$workerRunning) {

                Log::info('No active worker found. Starting worker.');

                $this->startWorker($queueString);

                file_put_contents($stateFile, $queueString);

                return self::SUCCESS;
            }

            /*
            |--------------------------------------------------------------------------
            | Queue configuration changed
            |--------------------------------------------------------------------------
            */
            if ($previousQueues !== $queueString) {

                Log::warning('Queue configuration changed');

                Log::info('Previous queues', [
                    'queues' => $previousQueues
                ]);

                Log::info('Current queues', [
                    'queues' => $queueString
                ]);

                Log::info("Stopping worker PID {$currentPid}");

                $this->killProcess($currentPid);

                sleep(2);

                Log::info('Starting replacement worker');

                $this->startWorker($queueString);

                file_put_contents($stateFile, $queueString);

                return self::SUCCESS;
            }

            Log::info('Worker healthy and queue list unchanged');

        } catch (\Throwable $e) {

            Log::error('DynamicQueueWorker failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Start worker
     */
    protected function startWorker(string $queueString): void
    {
        $pidFile = storage_path('app/dynamic-worker.pid');

        $artisan = base_path('artisan');
        $php = PHP_BINARY;

        $logFile = storage_path('logs/dynamic-queue-worker.log');

        if (PHP_OS_FAMILY == 'Windows') {

            $command =
                'start /B "" "' . $php . '" "' . $artisan . '"' .
                ' queue:work database' .
                ' --queue="' . $queueString . '"' .
                ' --sleep=1' .
                ' --tries=3' .
                ' --memory=256';

            Log::info('Starting Windows worker', [
                'command' => $command
            ]);

            pclose(popen($command, 'r'));

            sleep(3);

            $pid = $this->findQueueWorkerPid();

            if ($pid) {
                file_put_contents($pidFile, $pid);

                Log::info('Worker started', [
                    'pid' => $pid
                ]);
            }

            return;
        }

        $command =
            '"' . $php . '" "' . $artisan . '"' .
            ' queue:work database' .
            ' --queue="' . $queueString . '"' .
            ' --sleep=1' .
            ' --tries=3' .
            ' --memory=256' .
            ' >> "' . $logFile . '" 2>&1 & echo $!';

        $pid = trim(shell_exec($command));

        file_put_contents($pidFile, $pid);

        Log::info('Worker started', [
            'pid' => $pid
        ]);
    }

    /**
     * Check process
     */
    protected function isProcessRunning($pid): bool
    {
        if (!$pid) {
            return false;
        }

        if (PHP_OS_FAMILY == 'Windows') {

            exec("tasklist /FI \"PID eq {$pid}\"", $output);

            foreach ($output as $line) {
                if (str_contains($line, (string) $pid)) {
                    return true;
                }
            }

            return false;
        }

        return posix_kill((int) $pid, 0);
    }

    /**
     * Kill process
     */
    protected function killProcess($pid): void
    {
        if (!$pid) {
            return;
        }

        if (PHP_OS_FAMILY == 'Windows') {

            exec("taskkill /PID {$pid} /F");

            Log::info("Killed Windows process {$pid}");

            return;
        }

        exec("kill -9 {$pid}");

        Log::info("Killed Linux process {$pid}");
    }

    /**
     * Find queue worker pid on Windows
     */
    protected function findQueueWorkerPid(): ?string
    {
        exec('wmic process get ProcessId,CommandLine', $output);

        foreach ($output as $line) {

            if (
                str_contains($line, 'queue:work') &&
                str_contains($line, 'artisan')
            ) {

                preg_match('/(\d+)\s*$/', trim($line), $matches);

                return $matches[1] ?? null;
            }
        }

        return null;
    }
}