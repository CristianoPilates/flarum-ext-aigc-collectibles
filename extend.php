<?php

namespace Donk\AigcCollectibles;

use Flarum\Extend;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/resources/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    new Extend\Locales(__DIR__ . '/resources/locale'),

    (new Extend\Routes('api'))
        ->post('/checkin', 'donk-aigc-collectibles.checkin', Api\Controller\CheckinController::class)
        ->get('/collectibles', 'donk-aigc-collectibles.collectibles.index', Api\Controller\ListCollectiblesController::class)
        ->get('/collectibles/{id}', 'donk-aigc-collectibles.collectibles.show', Api\Controller\ShowCollectibleController::class)
        ->post('/collectibles/generate', 'donk-aigc-collectibles.collectibles.generate', Api\Controller\GenerateCollectibleController::class)
        ->patch('/collectibles/{id}', 'donk-aigc-collectibles.collectibles.update', Api\Controller\UpdateCollectibleController::class)
        ->post('/trades', 'donk-aigc-collectibles.trades.create', Api\Controller\CreateTradeController::class)
        ->get('/trades', 'donk-aigc-collectibles.trades.index', Api\Controller\ListTradesController::class)
        ->post('/trades/{id}/accept', 'donk-aigc-collectibles.trades.accept', Api\Controller\AcceptTradeController::class)
        ->post('/trades/{id}/reject', 'donk-aigc-collectibles.trades.reject', Api\Controller\RejectTradeController::class)
        ->delete('/trades/{id}', 'donk-aigc-collectibles.trades.cancel', Api\Controller\CancelTradeController::class)
        ->post('/web3/nonce', 'donk-aigc-collectibles.web3.nonce', Api\Controller\Web3NonceController::class)
        ->post('/web3/accounts', 'donk-aigc-collectibles.web3.verify', Api\Controller\Web3VerifyController::class)
        ->get('/web3/accounts', 'donk-aigc-collectibles.web3.index', Api\Controller\ListWeb3AccountsController::class)
        ->delete('/web3/accounts/{id}', 'donk-aigc-collectibles.web3.delete', Api\Controller\DeleteWeb3AccountController::class),

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

    (new Extend\ApiSerializer(UserSerializer::class))
        ->attributes(Api\AddUserAttributes::class),

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
