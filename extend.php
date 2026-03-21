<?php

namespace Donk\AigcCollectibles;

use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Service\CheckinService;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/resources/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    new Extend\Locales(__DIR__ . '/resources/locale'),

    // API Resources (replaces Routes + ApiSerializer)
    new Extend\ApiResource(Api\Resource\CollectibleResource::class),
    new Extend\ApiResource(Api\Resource\TradeResource::class),
    new Extend\ApiResource(Api\Resource\CheckinRecordResource::class),
    new Extend\ApiResource(Api\Resource\Web3AccountResource::class),
    new Extend\ApiResource(Api\Resource\CollectibleEventResource::class),

    // Extend UserResource with collectible-related fields
    (new Extend\ApiResource(UserResource::class))
        ->fields(fn () => [
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
                ->get(function (User $user) {
                    $checkinService = resolve(CheckinService::class);
                    return !$checkinService->hasCheckedInToday($user);
                }),
            Schema\Boolean::make('hasCheckedInToday')
                ->visible(fn (User $user, $context) => $context->getActor()->id === $user->id)
                ->get(function (User $user) {
                    $checkinService = resolve(CheckinService::class);
                    return $checkinService->hasCheckedInToday($user);
                }),
            Schema\Str::make('showcaseCollectibleName')
                ->get(function (User $user) {
                    if (!$user->showcase_collectible_id) return null;
                    $collectible = Collectible::query()->find($user->showcase_collectible_id);
                    return ($collectible && $collectible->status === 'completed') ? $collectible->name : null;
                })
                ->nullable(),
            Schema\Str::make('showcaseCollectibleRarity')
                ->get(function (User $user) {
                    if (!$user->showcase_collectible_id) return null;
                    $collectible = Collectible::query()->find($user->showcase_collectible_id);
                    return ($collectible && $collectible->status === 'completed') ? $collectible->rarity : null;
                })
                ->nullable(),
            Schema\Str::make('showcaseCollectibleCid')
                ->get(function (User $user) {
                    if (!$user->showcase_collectible_id) return null;
                    $collectible = Collectible::query()->find($user->showcase_collectible_id);
                    return ($collectible && $collectible->status === 'completed') ? $collectible->ipfs_cid : null;
                })
                ->nullable(),
        ]),

    (new Extend\Model(User::class))
        ->hasMany('collectibles', Model\Collectible::class, 'user_id')
        ->hasMany('originatedCollectibles', Model\Collectible::class, 'original_user_id')
        ->hasMany('tradesInitiated', Model\Trade::class, 'from_user_id')
        ->hasMany('tradesReceived', Model\Trade::class, 'to_user_id')
        ->hasMany('checkinRecords', Model\CheckinRecord::class, 'user_id')
        ->hasMany('web3Accounts', Model\Web3Account::class, 'user_id')
        ->hasOne('showcaseCollectible', Model\Collectible::class, 'id', 'showcase_collectible_id')
        ->default('blind_box_count', 0)
        ->cast('blind_box_count', 'integer'),

    (new Extend\ServiceProvider())
        ->register(Provider\CollectibleServiceProvider::class),

    (new Extend\Policy())
        ->modelPolicy(Model\Collectible::class, Access\CollectiblePolicy::class)
        ->modelPolicy(Model\Trade::class, Access\TradePolicy::class),

    (new Extend\Settings())
        ->default('donk-aigc-collectibles.checkin-reward', 1)
        ->default('donk-aigc-collectibles.rarity-common', 60)
        ->default('donk-aigc-collectibles.rarity-rare', 25)
        ->default('donk-aigc-collectibles.rarity-epic', 12)
        ->default('donk-aigc-collectibles.rarity-legendary', 3)
        ->default('donk-aigc-collectibles.aigc-enabled', true)
        ->default('donk-aigc-collectibles.aigc-api-url', '')
        ->default('donk-aigc-collectibles.aigc-api-key', '')
        ->default('donk-aigc-collectibles.ipfs-api-url', 'http://127.0.0.1:5001')
        ->default('donk-aigc-collectibles.ipfs-gateway-url', 'https://ipfs.io/ipfs/')
        ->default('donk-aigc-collectibles.blockchain-rpc-url', 'http://127.0.0.1:8545')
        ->default('donk-aigc-collectibles.nft-contract-address', '')
        ->default('donk-aigc-collectibles.minter-private-key', '')
        ->serializeToForum('donk-aigc-collectibles.checkin-reward', 'donk-aigc-collectibles.checkin-reward', 'intval')
        ->serializeToForum('donk-aigc-collectibles.aigc-enabled', 'donk-aigc-collectibles.aigc-enabled', 'boolval')
        ->serializeToForum('donk-aigc-collectibles.ipfs-gateway-url', 'donk-aigc-collectibles.ipfs-gateway-url'),
];
