<?php

namespace Donk\AigcCollectibles\Api;

use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Service\Contracts\CheckinServiceInterface;
use Flarum\Api\Schema\Attribute;
use Flarum\Api\Schema;
use Flarum\User\User;

class UserResourceFields
{
    protected CheckinServiceInterface $checkinService;

    public function __construct(CheckinServiceInterface $checkinService)
    {
        $this->checkinService = $checkinService;
    }

    /**
     * @return array<int, Attribute>
     */
    public function __invoke(): array
    {
        $getShowcase = function (User $user): ?Collectible {
            $collectible = $user->relationLoaded('showcaseCollectible')
                ? $user->getRelation('showcaseCollectible')
                : null;

            if (! $collectible instanceof Collectible) {
                return null;
            }

            return $collectible->status === Collectible::STATUS_COMPLETED ? $collectible : null;
        };

        $getWallet = function (User $user): mixed {
            return $user->relationLoaded('web3Account')
                ? $user->getRelation('web3Account')
                : null;
        };

        return [
            Schema\Integer::make('blindBoxCount')
                ->property('blind_box_count'),
            Schema\DateTime::make('lastCheckinAt')
                ->property('last_checkin_at')
                ->nullable(),
            Schema\Integer::make('showcaseCollectibleId')
                ->property('showcase_collectible_id')
                ->nullable(),
            Schema\Boolean::make('canCheckin')
                ->visible(fn (User $user, $context) => $context->getActor()->id === $user->id)
                ->get(fn (User $user) => !$this->checkinService->hasCheckedInToday($user)),
            Schema\Boolean::make('hasCheckedInToday')
                ->visible(fn (User $user, $context) => $context->getActor()->id === $user->id)
                ->get(fn (User $user) => $this->checkinService->hasCheckedInToday($user)),
            Schema\Str::make('showcaseCollectibleName')
                ->get(fn (User $user) => $getShowcase($user)?->name)
                ->nullable(),
            Schema\Str::make('showcaseCollectibleRarity')
                ->get(fn (User $user) => $getShowcase($user)?->rarity)
                ->nullable(),
            Schema\Str::make('showcaseCollectibleCid')
                ->get(fn (User $user) => $getShowcase($user)?->ipfs_cid)
                ->nullable(),
            Schema\Integer::make('showcaseCollectibleTokenId')
                ->get(fn (User $user) => $getShowcase($user)?->token_id)
                ->nullable(),
            Schema\Str::make('web3Address')
                ->visible(fn (User $user, $context) => $context->getActor()->id === $user->id)
                ->get(fn (User $user) => $getWallet($user)?->address)
                ->nullable(),
            Schema\Integer::make('web3AccountId')
                ->visible(fn (User $user, $context) => $context->getActor()->id === $user->id)
                ->get(fn (User $user) => $getWallet($user)?->id)
                ->nullable(),
        ];
    }
}
