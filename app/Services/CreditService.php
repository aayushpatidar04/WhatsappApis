<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditService
{
    /**
     * Add credits to a user's wallet.
     * Called when Super Admin manually adjusts or when a purchase is recorded.
     */
    public function addToUser(
        User   $user,
        int    $credits,
        string $type = CreditTransaction::TYPE_MANUAL_ADJUSTMENT,
        string $reference = '',
        ?int   $packageId = null,
        ?int   $actorId = null,
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $credits, $type, $reference, $packageId, $actorId) {
            // Lock user row for update to prevent race conditions
            $user = User::lockForUpdate()->findOrFail($user->id);

            $user->increment('credit_balance', $credits);

            return CreditTransaction::create([
                'owner_id'     => $user->id,
                'owner_type'   => 'user',
                'client_id'    => $user->client_id,
                'type'         => $type,
                'credits'      => +$credits,
                'package_id'   => $packageId,
                'reference'    => $reference,
                'balance_after'=> $user->fresh()->credit_balance,
                'created_by'   => $actorId,
                'created_at'   => now(),
            ]);
        });
    }

    /**
     * Add credits to a Client's wallet (for Master Admin's own instance usage).
     */
    public function addToClient(
        Client $client,
        int    $credits,
        string $type = CreditTransaction::TYPE_MANUAL_ADJUSTMENT,
        string $reference = '',
        ?int   $packageId = null,
        ?int   $actorId = null,
    ): CreditTransaction {
        return DB::transaction(function () use ($client, $credits, $type, $reference, $packageId, $actorId) {
            $client = Client::lockForUpdate()->findOrFail($client->id);

            $client->increment('credit_balance', $credits);

            return CreditTransaction::create([
                'owner_id'     => $client->id,
                'owner_type'   => 'client',
                'client_id'    => $client->id,
                'type'         => $type,
                'credits'      => +$credits,
                'package_id'   => $packageId,
                'reference'    => $reference,
                'balance_after'=> $client->fresh()->credit_balance,
                'created_by'   => $actorId,
                'created_at'   => now(),
            ]);
        });
    }

    /**
     * Allocate N credits from a user/client wallet to a WhatsApp instance.
     * Deducts from wallet, increments instance.credits_assigned, recalculates expires_at.
     *
     * @throws ValidationException
     */
    public function allocateToInstance(
        WhatsappInstance $instance,
        int              $credits,
        ?int             $actorId = null,
    ): CreditTransaction {
        return DB::transaction(function () use ($instance, $credits, $actorId) {
            // Determine the wallet owner
            [$ownerId, $ownerType, $balance, $clientId] = $this->resolveWallet($instance);

            // Validate sufficient balance
            if ($balance < $credits) {
                throw ValidationException::withMessages([
                    'credits' => "Insufficient credits. Available: {$balance}, Requested: {$credits}.",
                ]);
            }

            // Deduct from wallet
            $this->decrementWallet($ownerId, $ownerType, $credits);

            // Increment instance credits and recalculate expiry
            $instance = WhatsappInstance::lockForUpdate()->findOrFail($instance->id);
            $instance->increment('credits_assigned', $credits);

            // Recalculate expiry: if already activated, extend from NOW; else set from activation
            if ($instance->activated_at) {
                $baseDate    = $instance->expires_at && $instance->expires_at->isFuture()
                    ? $instance->expires_at
                    : now();
                $instance->expires_at = $baseDate->addDays($credits * 30);
            }

            // If was suspended due to credit exhaustion, reactivate
            if ($instance->status === WhatsappInstance::STATUS_SUSPENDED) {
                $instance->status = WhatsappInstance::STATUS_PENDING; // will reconnect via Phase 2
            }

            $instance->save();

            $newBalance = $this->getBalance($ownerId, $ownerType);

            return CreditTransaction::create([
                'owner_id'     => $ownerId,
                'owner_type'   => $ownerType,
                'client_id'    => $clientId,
                'type'         => CreditTransaction::TYPE_ALLOCATION,
                'credits'      => -$credits,
                'instance_id'  => $instance->id,
                'balance_after'=> $newBalance,
                'created_by'   => $actorId,
                'created_at'   => now(),
            ]);
        });
    }

    /**
     * Daily credit consumption accrual — called by CreditConsumptionCommand cron job.
     * Deducts 1/30th of a credit per active instance per day.
     */
    public function consumeDaily(WhatsappInstance $instance): void
    {
        DB::transaction(function () use ($instance) {
            $instance = WhatsappInstance::lockForUpdate()->findOrFail($instance->id);

            if ($instance->status !== WhatsappInstance::STATUS_ACTIVE) {
                return;
            }

            $dailyRate = 1 / 30; // 1 credit per 30 days
            $instance->increment('credits_consumed', $dailyRate);

            // Check if consumed >= assigned
            if ($instance->credits_consumed >= $instance->credits_assigned) {
                $instance->status       = WhatsappInstance::STATUS_SUSPENDED;
                $instance->suspended_at = now();
                $instance->save();

                // TODO Phase 2: Fire event to disconnect Baileys session
                // event(new InstanceSuspended($instance));
            }

            [$ownerId, $ownerType, , $clientId] = $this->resolveWallet($instance);
            $newBalance = $this->getBalance($ownerId, $ownerType);

            CreditTransaction::create([
                'owner_id'     => $ownerId,
                'owner_type'   => $ownerType,
                'client_id'    => $clientId,
                'type'         => CreditTransaction::TYPE_CONSUMPTION,
                'credits'      => 0, // consumption tracked on instance, not wallet
                'instance_id'  => $instance->id,
                'reference'    => 'Daily accrual ' . now()->toDateString(),
                'balance_after'=> $newBalance,
                'created_at'   => now(),
            ]);
        });
    }

    /**
     * Return unused credits from an instance back to the owner's wallet.
     * Called on instance deletion.
     */
    public function deallocateFromInstance(WhatsappInstance $instance, ?int $actorId = null): ?CreditTransaction
    {
        $remaining = $instance->credits_assigned - (int) ceil($instance->credits_consumed);

        if ($remaining <= 0) {
            return null;
        }

        return DB::transaction(function () use ($instance, $remaining, $actorId) {
            [$ownerId, $ownerType, , $clientId] = $this->resolveWallet($instance);

            $this->incrementWallet($ownerId, $ownerType, $remaining);
            $newBalance = $this->getBalance($ownerId, $ownerType);

            return CreditTransaction::create([
                'owner_id'     => $ownerId,
                'owner_type'   => $ownerType,
                'client_id'    => $clientId,
                'type'         => CreditTransaction::TYPE_DEALLOCATION,
                'credits'      => +$remaining,
                'instance_id'  => $instance->id,
                'reference'    => 'Instance deleted — credits returned',
                'balance_after'=> $newBalance,
                'created_by'   => $actorId,
                'created_at'   => now(),
            ]);
        });
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Resolve the wallet for an instance based on its owner_type.
     * Returns [ownerId, ownerType, currentBalance, clientId].
     */
    private function resolveWallet(WhatsappInstance $instance): array
    {
        if ($instance->owner_type === 'client') {
            $client = Client::lockForUpdate()->findOrFail($instance->owner_id);
            return [$client->id, 'client', $client->credit_balance, $client->id];
        }

        $user = User::lockForUpdate()->findOrFail($instance->owner_id);
        return [$user->id, 'user', $user->credit_balance, $user->client_id];
    }

    private function decrementWallet(int $ownerId, string $ownerType, int $amount): void
    {
        if ($ownerType === 'client') {
            Client::where('id', $ownerId)->decrement('credit_balance', $amount);
        } else {
            User::where('id', $ownerId)->decrement('credit_balance', $amount);
        }
    }

    private function incrementWallet(int $ownerId, string $ownerType, int $amount): void
    {
        if ($ownerType === 'client') {
            Client::where('id', $ownerId)->increment('credit_balance', $amount);
        } else {
            User::where('id', $ownerId)->increment('credit_balance', $amount);
        }
    }

    private function getBalance(int $ownerId, string $ownerType): int
    {
        if ($ownerType === 'client') {
            return Client::findOrFail($ownerId)->credit_balance;
        }
        return User::findOrFail($ownerId)->credit_balance;
    }
}