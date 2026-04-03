<?php

namespace Donk\AigcCollectibles;

use Flarum\Extend;
use Flarum\Search\Database\DatabaseSearchDriver;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/resources/less/forum.less')
        ->content(Frontend\DefaultFavicon::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->content(Frontend\DefaultFavicon::class),

    new Extend\Locales(__DIR__.'/resources/locale'),

    // API Resources (replaces Routes + ApiSerializer)
    new Extend\ApiResource(Api\Resource\BlindBoxResource::class),
    new Extend\ApiResource(Api\Resource\BarterProposalResource::class),
    new Extend\ApiResource(Api\Resource\BarterProposalItemResource::class),
    new Extend\ApiResource(Api\Resource\CollectibleResource::class),
    new Extend\ApiResource(Api\Resource\CheckinRecordResource::class),
    new Extend\ApiResource(Api\Resource\Web3AccountResource::class),
    new Extend\ApiResource(Api\Resource\CollectibleEventResource::class),

    (new Extend\Routes('api'))
        ->get('/barter-assets', 'donk.aigc-collectibles.barter-assets.index', Api\Controller\ListBarterAssetsController::class),

    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addSearcher(Model\Collectible::class, Search\CollectibleSearcher::class)
        ->addFilter(Search\CollectibleSearcher::class, Search\Filter\CollectibleUserFilter::class)
        ->addFilter(Search\CollectibleSearcher::class, Search\Filter\CollectibleOwnerFilter::class),

    (new Extend\Model(User::class))
        ->hasMany('collectibles', Model\Collectible::class, 'owner_id')
        ->hasMany('checkinRecords', Model\CheckinRecord::class, 'user_id')
        ->hasMany('web3Accounts', Model\Web3Account::class, 'user_id')
        ->hasOne('web3Account', Model\Web3Account::class, 'user_id')
        ->hasMany('barterProposalsCreated', Model\BarterProposal::class, 'proposer_user_id')
        ->hasMany('barterProposalsReceived', Model\BarterProposal::class, 'counterparty_user_id')
        ->hasOne('showcaseCollectible', Model\Collectible::class, 'id', 'showcase_collectible_id')
        ->default('blind_box_count', 0)
        ->cast('blind_box_count', 'integer'),

    (new Extend\ServiceProvider)
        ->register(Provider\CollectibleServiceProvider::class),

    (new Extend\Policy)
        ->modelPolicy(Model\Collectible::class, Access\CollectiblePolicy::class)
        ->modelPolicy(Model\BarterProposal::class, Access\BarterProposalPolicy::class),

    (new Extend\Settings)
        ->default('donk-aigc-collectibles.checkin-reward', 1)
        ->default('donk-aigc-collectibles.rarity-common', 60)
        ->default('donk-aigc-collectibles.rarity-rare', 25)
        ->default('donk-aigc-collectibles.rarity-epic', 12)
        ->default('donk-aigc-collectibles.rarity-legendary', 3)
        ->default('donk-aigc-collectibles.aigc-enabled', true)
        ->default('donk-aigc-collectibles.aigc-api-url', getenv('AIGC_API_URL') ?: '')
        ->default('donk-aigc-collectibles.aigc-api-key', '')
        ->default('donk-aigc-collectibles.ipfs-api-url', getenv('IPFS_API_URL') ?: '')
        ->default('donk-aigc-collectibles.ipfs-gateway-url', getenv('IPFS_GATEWAY_URL') ?: 'https://ipfs.io/ipfs/')
        ->default('donk-aigc-collectibles.blockchain-rpc-url', getenv('ANVIL_RPC_URL') ?: '')
        ->default('donk-aigc-collectibles.nft-contract-address', '')
        ->default('donk-aigc-collectibles.minter-private-key', '')
        ->serializeToForum('donk-aigc-collectibles.checkin-reward', 'donk-aigc-collectibles.checkin-reward', 'intval')
        ->serializeToForum('donk-aigc-collectibles.aigc-enabled', 'donk-aigc-collectibles.aigc-enabled', 'boolval')
        ->serializeToForum('donk-aigc-collectibles.ipfs-gateway-url', 'donk-aigc-collectibles.ipfs-gateway-url'),
];
