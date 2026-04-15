<?php

namespace Donk\AigcCollectibles\Service\Contracts;

use Flarum\User\User;
use Donk\AigcCollectibles\Model\BlindBox;

interface BlindBoxServiceInterface
{
    public function createForUser(User $user, string $type): BlindBox;

    /**
     * Verify PoW result, quantify budget, transition to appraised.
     */
    public function appraise(User $actor, int $boxId, string $nonce, string $hash): BlindBox;

    /**
     * Spend budget on phrases, assemble prompt, create collectible, transition to opened.
     */
    public function open(User $actor, int $boxId): BlindBox;

    /**
     * Get user's blind box balance.
     */
    public function balanceOf(User $user): int;

    public function syncUserBalance(int $userId): void;

    public function resolveDrawRuleType(string $boxType): string;

    /**
     * @return array<int, array{category: string, required: bool}>
     */
    public function describeDrawRules(string $boxType): array;

    /**
     * Transfer blind boxes from one user to another atomically.
     */
    public function transfer(User $from, User $to, int $amount): void;

    /**
     * @param int[] $boxIds
     */
    public function transferSpecific(User $from, User $to, array $boxIds): void;
}
