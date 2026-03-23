<?php

namespace Donk\AigcCollectibles\Service;

use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Donk\AigcCollectibles\Event\BlindBoxAppraised;
use Donk\AigcCollectibles\Event\BlindBoxOpened;
use Donk\AigcCollectibles\Model\BlindBox;
use Donk\AigcCollectibles\Model\BlindBoxDrawRule;
use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Model\PhrasePool;
use Donk\AigcCollectibles\Service\Contracts\BlindBoxServiceInterface;

class BlindBoxService implements BlindBoxServiceInterface
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Dispatcher $events,
    ) {}

    /* ───────────────────── Appraise ───────────────────── */

    public function appraise(User $actor, int $boxId, string $nonce, string $hash): BlindBox
    {
        return $this->db->transaction(function () use ($actor, $boxId, $nonce, $hash) {
            /** @var BlindBox $box */
            $box = BlindBox::query()->lockForUpdate()->findOrFail($boxId);

            if ($box->user_id !== $actor->id) {
                throw new ValidationException(['box' => 'You do not own this blind box.']);
            }

            if ($box->status !== BlindBox::STATUS_UNAPPRAISED) {
                throw new ValidationException(['status' => 'This blind box has already been appraised.']);
            }

            // Verify PoW: SHA-256(seed + nonce_hex) must equal submitted hash
            $computed = hash('sha256', $box->seed . $nonce);
            if (!hash_equals($computed, $hash)) {
                throw new ValidationException(['hash' => 'Invalid PoW result.']);
            }

            $leadingZeros = strspn($hash, '0');
            $budget = $this->zerosToBudget($leadingZeros);

            $box->status = BlindBox::STATUS_APPRAISED;
            $box->budget = $budget;
            $box->save();

            $this->events->dispatch(new BlindBoxAppraised($actor, $box));

            return $box;
        });
    }

    /* ───────────────────── Open ───────────────────── */

    public function open(User $actor, int $boxId): BlindBox
    {
        return $this->db->transaction(function () use ($actor, $boxId) {
            /** @var BlindBox $box */
            $box = BlindBox::query()->lockForUpdate()->findOrFail($boxId);

            if ($box->user_id !== $actor->id) {
                throw new ValidationException(['box' => 'You do not own this blind box.']);
            }

            if ($box->status !== BlindBox::STATUS_APPRAISED) {
                throw new ValidationException(['status' => 'Blind box must be appraised before opening.']);
            }

            // 1. Load draw rules for this box type
            $rules = BlindBoxDrawRule::query()
                ->where('blindbox_type', $box->type)
                ->get();

            if ($rules->isEmpty()) {
                throw new ValidationException(['type' => 'No draw rules configured for box type: ' . $box->type]);
            }

            // 2. Purchase phrases with budget
            $phrases = $this->purchasePhrases($rules, $box->budget);
            $aigcPrompt = implode(', ', $phrases);

            // 3. Determine rarity from budget
            $rarity = $this->budgetToRarity($box->budget);

            // 4. Create collectible (draft — no image until Phase 4)
            $collectible = Collectible::createDraft(
                ownerId: $actor->id,
                aigcPrompt: $aigcPrompt,
                rarity: $rarity,
            );

            // 5. Transition blind box
            $box->status = BlindBox::STATUS_OPENED;
            $box->collectible_id = $collectible->id;
            $box->save();

            $this->events->dispatch(new BlindBoxOpened($actor, $box, $collectible));

            return $box;
        });
    }

    /* ───────────────────── Phrase purchasing ───────────────────── */

    /**
     * Load all affordable phrases once, then spend budget in-memory.
     *
     * @param Collection<BlindBoxDrawRule> $rules
     * @return string[]
     */
    private function purchasePhrases(Collection $rules, int $budget): array
    {
        $allCategories = $rules->pluck('pool_category');

        // Single query: load every phrase that could possibly be afforded
        $pool = PhrasePool::query()
            ->whereIn('category', $allCategories)
            ->where('is_active', true)
            ->where('cost', '<=', $budget)
            ->get();

        if ($pool->isEmpty()) {
            throw new ValidationException(['budget' => 'No affordable phrases available for this budget.']);
        }

        $selected  = [];
        $remaining = $budget;

        // Required categories first — each must contribute at least 1 phrase
        foreach ($rules->where('required', true) as $rule) {
            $candidates = $pool
                ->where('category', $rule->pool_category)
                ->where('cost', '<=', $remaining)
                ->whereNotIn('phrase', $selected);

            if ($candidates->isEmpty()) {
                continue;
            }

            $pick = $candidates->random();
            $selected[]  = $pick->phrase;
            $remaining  -= $pick->cost;
        }

        // Spend remaining budget on any allowed category
        while ($remaining > 0) {
            $candidates = $pool
                ->where('cost', '<=', $remaining)
                ->whereNotIn('phrase', $selected);

            if ($candidates->isEmpty()) {
                break;
            }

            $pick = $candidates->random();
            $selected[]  = $pick->phrase;
            $remaining  -= $pick->cost;
        }

        if (empty($selected)) {
            throw new ValidationException(['budget' => 'Budget too low to purchase any phrases.']);
        }

        return $selected;
    }

    /* ───────────────────── Balance operations ───────────────────── */

    public function balanceOf(User $user): int
    {
        return (int) $this->db->table('users')
            ->where('id', $user->id)
            ->value('blind_box_count');
    }

    public function transfer(User $from, User $to, int $amount): void
    {
        $affected = $this->db->table('users')
            ->where('id', $from->id)
            ->where('blind_box_count', '>=', $amount)
            ->decrement('blind_box_count', $amount);

        if ($affected !== 1) {
            throw new ValidationException(['blind_box' => 'Insufficient blind boxes.']);
        }

        $this->db->table('users')
            ->where('id', $to->id)
            ->increment('blind_box_count', $amount);
    }

    /* ───────────────────── Mapping helpers ───────────────────── */

    private function zerosToBudget(int $leadingZeros): int
    {
        return match (true) {
            $leadingZeros >= 5 => 200,
            $leadingZeros >= 4 => 120,
            $leadingZeros >= 3 => 70,
            $leadingZeros >= 2 => 40,
            $leadingZeros >= 1 => 20,
            default            => 10,
        };
    }

    private function budgetToRarity(int $budget): string
    {
        return match (true) {
            $budget >= 200 => 'legendary',
            $budget >= 120 => 'epic',
            $budget >= 70  => 'rare',
            $budget >= 40  => 'uncommon',
            default        => 'common',
        };
    }
}
