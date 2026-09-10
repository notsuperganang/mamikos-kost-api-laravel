<?php

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Enums\UserRole;
use App\Exceptions\InsufficientCreditException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditService
{
    /**
     * One statement per role: lock the eligible rows, write the ledger entries, then reset the
     * balance. Users that already received this month's recharge are skipped, which makes re-runs
     * and overlapping executions on several nodes harmless.
     */
    private const string RECHARGE_SQL = <<<'SQL'
        WITH eligible AS (
            SELECT u.id, u.credit
            FROM users u
            WHERE u.role = ?
              AND NOT EXISTS (
                  SELECT 1 FROM credit_transactions t
                  WHERE t.user_id = u.id
                    AND t.type = 'monthly_recharge'
                    AND t.created_at >= ?::timestamptz
              )
            FOR UPDATE
        ),
        ledger AS (
            INSERT INTO credit_transactions (user_id, amount, balance_after, type, reference_id, created_at)
            SELECT id, ? - credit, ?, 'monthly_recharge', NULL, now()
            FROM eligible
        )
        UPDATE users SET credit = ?, updated_at = now()
        WHERE id IN (SELECT id FROM eligible)
        SQL;

    private const string ELIGIBLE_COUNT_SQL = <<<'SQL'
        SELECT count(*) AS total
        FROM users u
        WHERE u.role = ?
          AND NOT EXISTS (
              SELECT 1 FROM credit_transactions t
              WHERE t.user_id = u.id
                AND t.type = 'monthly_recharge'
                AND t.created_at >= ?::timestamptz
          )
        SQL;

    public function inquiryCost(): int
    {
        return (int) config('credits.inquiry_cost');
    }

    public function recordInitialGrant(User $user): void
    {
        if ($user->credit > 0) {
            $user->creditTransactions()->create([
                'amount' => $user->credit,
                'balance_after' => $user->credit,
                'type' => CreditTransactionType::InitialGrant,
            ]);
        }
    }

    /**
     * Deducts the amount atomically or throws when the balance is insufficient.
     *
     * @return int the balance after the deduction
     *
     * @throws InsufficientCreditException
     */
    public function deduct(User $user, int $amount, CreditTransactionType $type, ?int $referenceId = null): int
    {
        return DB::transaction(function () use ($user, $amount, $type, $referenceId): int {
            $updated = User::query()
                ->whereKey($user->getKey())
                ->where('credit', '>=', $amount)
                ->decrement('credit', $amount);

            if ($updated === 0) {
                throw new InsufficientCreditException($amount);
            }

            $balance = (int) User::query()->whereKey($user->getKey())->value('credit');

            $user->creditTransactions()->create([
                'amount' => -$amount,
                'balance_after' => $balance,
                'type' => $type,
                'reference_id' => $referenceId,
            ]);

            $user->credit = $balance;

            return $balance;
        });
    }

    /**
     * Resets every regular / premium user to their monthly allowance. Owners are untouched.
     *
     * @return int number of users updated
     */
    public function rechargeAll(?CarbonImmutable $now = null): int
    {
        $monthStart = $this->startOfMonth($now);
        $total = 0;

        foreach (self::rechargeableRoles() as $role) {
            $allowance = $role->monthlyAllowance();
            $updated = DB::affectingStatement(self::RECHARGE_SQL, [
                $role->value, $monthStart, $allowance, $allowance, $allowance,
            ]);
            Log::info("Monthly credit recharge: {$updated} {$role->value} user(s) reset to {$allowance}");
            $total += $updated;
        }

        return $total;
    }

    /**
     * @return array<string, int> users that would be recharged, keyed by role
     */
    public function eligibleForRecharge(?CarbonImmutable $now = null): array
    {
        $monthStart = $this->startOfMonth($now);
        $counts = [];

        foreach (self::rechargeableRoles() as $role) {
            $counts[$role->value] = (int) DB::scalar(self::ELIGIBLE_COUNT_SQL, [$role->value, $monthStart]);
        }

        return $counts;
    }

    public function startOfMonth(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        return $now->setTimezone(config('credits.timezone'))->startOfMonth()->toIso8601String();
    }

    /**
     * @return list<UserRole>
     */
    private static function rechargeableRoles(): array
    {
        return array_values(array_filter(UserRole::cases(), fn (UserRole $role) => $role->monthlyAllowance() > 0));
    }
}
