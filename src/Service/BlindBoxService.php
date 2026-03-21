<?php

namespace Donk\AigcCollectibles\Service;

use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

class BlindBoxService
{
    public function __construct(protected ConnectionInterface $db) {}

    /**
     * Get the current blind box balance for the given user.
     *
     * @param  User  $user  The user whose blind box balance is being retrieved.
     * @return int The user's blind box count.
     */
    public function balanceOf(User $user): int
    {
        return (int) $user->blind_box_count;
    }

    /**
     * Awards blind boxes to the given user.
     *
     * Validates that the amount is positive, increments the persisted
     * `blind_box_count`, and syncs the in-memory user instance on success.
     *
     * @throws ValidationException If the amount is invalid or the award operation fails.
     */
    public function award(User $user, int $amount): void
    {
        if ($amount <= 0) {
            throw new ValidationException([
                'amount' => 'Award amount must be positive.',
            ]);
        }

        $affected = $this->db->table('users')
            ->where('id', $user->id)
            ->increment('blind_box_count', $amount);

        if ($affected !== 1) {
            throw new ValidationException([
                'user' => 'Failed to award blind boxes.',
            ]);
        }

        $user->blind_box_count += $amount;
    }

    /**
     * Deduct blind boxes from the given user.
     *
     * Validates the requested amount and performs an atomic decrement
     * to prevent overspending under concurrent requests.
     *
     * @param  User  $user  User whose blind boxes will be reduced.
     * @param  int  $amount  Number of blind boxes to spend.
     *
     * @throws ValidationException If the amount is not positive or the user has insufficient blind boxes.
     */
    public function spend(User $user, int $amount): void
    {
        if ($amount <= 0) {
            throw new ValidationException([
                'amount' => 'Spend amount must be positive.',
            ]);
        }

        $affected = $this->db->table('users')
            ->where('id', $user->id)
            ->where('blind_box_count', '>=', $amount)
            ->decrement('blind_box_count', $amount);

        if ($affected !== 1) {
            throw new ValidationException([
                'blind_box' => 'Insufficient blind boxes.',
            ]);
        }

        $user->blind_box_count -= $amount;
    }

    /**
     * Transfer funds between two users.
     *
     * Debits the source user and credits the destination user with the same amount.
     *
     * @param  User  $from  User to debit.
     * @param  User  $to  User to credit.
     * @param  int  $amount  Transfer amount; must be greater than zero.
     *
     * @throws ValidationException If the transfer amount is not positive.
     */
    public function transfer(User $from, User $to, int $amount): void
    {
        if ($amount <= 0) {
            throw new ValidationException([
                'amount' => 'Transfer amount must be positive.',
            ]);
        }

        $this->spend($from, $amount);
        $this->award($to, $amount);
    }
}
