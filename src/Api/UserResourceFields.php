<?php

namespace Donk\AigcCollectibles\Api;

use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Service\Contracts\CheckinServiceInterface;
use Flarum\Api\Schema;
use Flarum\User\User;

class UserResourceFields
{
    protected CheckinServiceInterface $checkinService;

    public function __construct(CheckinServiceInterface $checkinService)
    {
        $this->checkinService = $checkinService;
    }

    public function __invoke(): array
    {
        $showcaseCache = [];

        $getShowcase = function (User $user) use (&$showcaseCache): ?Collectible {
            if (!$user->showcase_collectible_id) {
                return null;
            }

            if (!array_key_exists($user->id, $showcaseCache)) {
                $collectible = Collectible::query()->find($user->showcase_collectible_id);
                $showcaseCache[$user->id] = ($collectible && $collectible->status === 'completed') ? $collectible : null;
            }

            return $showcaseCache[$user->id];
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
        ];
    }
}
