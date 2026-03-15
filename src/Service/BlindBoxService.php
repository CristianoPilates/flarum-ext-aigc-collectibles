<?php

namespace Donk\AigcCollectibles\Service;

use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

class BlindBoxService
{
    protected ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function balanceOf(User $user): int
    {
        return (int) $user->blind_box_count;
    }

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
