<?php

namespace Donk\AigcCollectibles\Api;

use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Service\CheckinService;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;

class AddUserAttributes
{
    protected CheckinService $checkinService;

    public function __construct(CheckinService $checkinService)
    {
        $this->checkinService = $checkinService;
    }

    public function __invoke(UserSerializer $serializer, User $user): array
    {
        $attributes = [];

        $attributes['blindBoxCount'] = (int) $user->blind_box_count;
        $attributes['lastCheckinAt'] = $serializer->formatDate($user->last_checkin_at);
        $attributes['showcaseCollectibleId'] = $user->showcase_collectible_id;

        if ($serializer->getActor()->id === $user->id) {
            $hasCheckedIn = $this->checkinService->hasCheckedInToday($user);
            $attributes['canCheckin'] = !$hasCheckedIn;
            $attributes['hasCheckedInToday'] = $hasCheckedIn;
        }

        if ($user->showcase_collectible_id) {
            $collectible = Collectible::find($user->showcase_collectible_id);
            if ($collectible && $collectible->status === 'completed') {
                $attributes['showcaseCollectibleName'] = $collectible->name;
                $attributes['showcaseCollectibleRarity'] = $collectible->rarity;
                $attributes['showcaseCollectibleCid'] = $collectible->ipfs_cid;
            }
        }

        return $attributes;
    }
}
