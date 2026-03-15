# Flarum Extension Patterns — Research Findings

> Extracted from reference plugins and Flarum 1.8 core source code.
> All code snippets are verified against actual source files.

---

## 1. composer.json Template

```json
{
    "name": "donk/flarum-ext-aigc-collectibles",
    "description": "AIGC blind box digital collectible generation and circulation system.",
    "type": "flarum-extension",
    "license": "MIT",
    "require": {
        "flarum/core": "^1.8"
    },
    "autoload": {
        "psr-4": {
            "Donk\\AigcCollectibles\\": "src/"
        }
    },
    "extra": {
        "flarum-extension": {
            "title": "AIGC Collectibles",
            "category": "feature",
            "icon": {
                "name": "fas fa-gem",
                "backgroundColor": "#7C3AED",
                "color": "#fff"
            }
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

**Source**: `antoinefr/flarum-ext-money/composer.json`, `ziiven/flarum-daily-check-in/composer.json`

Key points:
- `"type": "flarum-extension"` is mandatory
- PSR-4 autoload maps namespace to `src/`
- `extra.flarum-extension` provides admin panel metadata
- `category` can be: `feature`, `theme`, `language`

---

## 2. extend.php Patterns

All backend registration goes through `extend.php` which returns an array of Extender objects.

### 2.1 Frontend Extender

Registers JS/CSS assets for forum and admin frontends.

```php
// Source: blomstra/web3/extend.php:20-22, ziiven/flarum-daily-check-in/extend.php:12-13
(new Extend\Frontend('forum'))
    ->js(__DIR__ . '/js/dist/forum.js')
    ->css(__DIR__ . '/less/forum.less'),

(new Extend\Frontend('admin'))
    ->js(__DIR__ . '/js/dist/admin.js'),
```

### 2.2 Routes Extender

Registers API routes. Each route needs: HTTP method, path, unique name, controller class.

```php
// Source: blomstra/web3/extend.php:36-41
(new Extend\Routes('api'))
    ->get('/web3/accounts', 'web3-accounts.index', Api\Controller\ListWeb3AccountsController::class)
    ->post('/web3/accounts', 'web3-accounts.create', Api\Controller\CreateWeb3AccountController::class)
    ->delete('/web3/accounts/{id}', 'web3-accounts.delete', Api\Controller\DeleteWeb3AccountController::class),
```

### 2.3 Model Extender

Adds relationships, casts, and defaults to existing models (e.g., User).

```php
// Source: flarum/core/src/Extend/Model.php — API surface
(new Extend\Model(User::class))
    ->hasMany('collectibles', Collectible::class, 'user_id')
    ->hasOne('showcaseCollectible', Collectible::class, 'id', 'showcase_collectible_id')
    ->default('blind_box_count', 0)
    ->cast('blind_box_count', 'integer'),
```

Methods available on `Extend\Model`:
- `hasOne(name, related, foreignKey, localKey)`
- `hasMany(name, related, foreignKey, localKey)`
- `belongsTo(name, related, foreignKey, ownerKey)`
- `belongsToMany(name, related, table, foreignPivotKey, relatedPivotKey)`
- `relationship(name, callback)` — for complex custom relationships
- `default(attribute, value)` — set default attribute value
- `cast(attribute, castType)` — add Eloquent cast

### 2.4 ApiSerializer Extender

Adds attributes to existing serializers (e.g., UserSerializer).

```php
// Source: antoinefr/flarum-ext-money/extend.php:28-29
(new Extend\ApiSerializer(UserSerializer::class))
    ->attributes(AddUserMoneyAttributes::class),

// Source: blomstra/web3/extend.php:56-59 — inline closure variant
(new Extend\ApiSerializer(CurrentUserSerializer::class))
    ->attribute('isEmailFake', function (CurrentUserSerializer $serializer, $user): bool {
        return str_contains($user->email, '@users.noreply');
    }),
```

The attributes callback class pattern (invokable):

```php
// Source: antoinefr/flarum-ext-money/src/AddUserMoneyAttributes.php
class AddUserMoneyAttributes
{
    public function __invoke(UserSerializer $serializer, User $user)
    {
        return [
            'money' => $user->money,
            'canEditMoney' => $serializer->getActor()->can('edit_money', $user),
        ];
    }
}
```

### 2.5 ServiceProvider Extender

Registers a service provider for IoC container bindings.

```php
// Source: blomstra/web3/extend.php:50-51
(new Extend\ServiceProvider())
    ->register(Web3ServiceProvider::class),
```

### 2.6 Event Extender

Listens to domain events.

```php
// Source: antoinefr/flarum-ext-money/extend.php:35-44
(new Extend\Event())
    ->listen(Posted::class, [Listeners\GiveMoney::class, 'postWasPosted'])
    ->listen(Saving::class, [Listeners\GiveMoney::class, 'userWillBeSaved']),
```

### 2.7 Policy Extender

Registers model-level authorization policies.

```php
// Source: blomstra/web3/extend.php:33-34
(new Extend\Policy())
    ->modelPolicy(Web3Account::class, Access\Web3AccountPolicy::class),
```

### 2.8 Settings Extender

Exposes settings to the forum frontend and sets defaults.

```php
// Source: blomstra/web3/extend.php:61-69
(new Extend\Settings())
    ->default('donk-aigc-collectibles.checkin-reward', 1)
    ->default('donk-aigc-collectibles.rarity-common', 60)
    ->serializeToForum('donk-aigc-collectibles.checkin-reward', 'donk-aigc-collectibles.checkin-reward', 'intval')
    ->serializeToForum('donk-aigc-collectibles.aigc-enabled', 'donk-aigc-collectibles.aigc-enabled', 'boolval'),
```

### 2.9 Locales Extender

```php
// Source: all reference extensions
new Extend\Locales(__DIR__ . '/locale'),
```

### 2.10 ModelVisibility Extender

Scopes model queries based on actor permissions.

```php
// Source: blomstra/web3/extend.php:47-48
(new Extend\ModelVisibility(Web3Account::class))
    ->scope(Access\ScopeAccountVisiblity::class),
```

### 2.11 ErrorHandling Extender

Maps custom exception classes to HTTP status codes.

```php
// Source: blomstra/web3/extend.php:53-54
(new Extend\ErrorHandling())
    ->status('invalid_crypto_signature', StatusCodeInterface::STATUS_UNAUTHORIZED),
```

### 2.12 Complete extend.php Skeleton

```php
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
        ->post('/collectibles/generate', 'donk-aigc-collectibles.collectibles.generate', Api\Controller\GenerateCollectibleController::class)
        ->get('/collectibles/{id}', 'donk-aigc-collectibles.collectibles.show', Api\Controller\ShowCollectibleController::class)
        ->post('/trades', 'donk-aigc-collectibles.trades.create', Api\Controller\CreateTradeController::class)
        ->post('/trades/{id}/accept', 'donk-aigc-collectibles.trades.accept', Api\Controller\AcceptTradeController::class)
        ->post('/trades/{id}/reject', 'donk-aigc-collectibles.trades.reject', Api\Controller\RejectTradeController::class)
        ->delete('/trades/{id}', 'donk-aigc-collectibles.trades.cancel', Api\Controller\CancelTradeController::class)
        ->post('/web3/accounts', 'donk-aigc-collectibles.web3.bind', Api\Controller\BindWalletController::class)
        ->delete('/web3/accounts/{id}', 'donk-aigc-collectibles.web3.unbind', Api\Controller\UnbindWalletController::class),

    (new Extend\Model(User::class))
        ->hasMany('collectibles', Model\Collectible::class, 'user_id')
        ->hasMany('trades', Model\Trade::class, 'from_user_id')
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
        ->default('donk-aigc-collectibles.rarity-legendary', 3),
];
```

---

## 3. Migration Patterns

Flarum migrations return `['up' => fn, 'down' => fn]` arrays. The `Migration` helper class provides shortcuts.

### 3.1 createTable — New Table

```php
<?php
// Source: blomstra/web3/migrations/2022_07_21_000000_create_web3_accounts_table.php

use Flarum\Database\Migration;
use Flarum\User\User;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'web3_accounts',
    function (Blueprint $table) {
        $table->increments('id');
        $table->foreignIdFor(User::class);
        $table->string('address', 80)->unique();
        $table->string('source');
        $table->string('type');
        $table->timestamp('attached_at')->nullable();
        $table->timestamp('last_verified_at')->nullable();
    }
);
```

`Migration::createTable()` returns:
```php
// Source: flarum/core/src/Database/Migration.php:27-39
[
    'up' => function (Builder $schema) use ($name, $definition) {
        $schema->create($name, function (Blueprint $table) use ($definition) {
            $definition($table);
        });
    },
    'down' => function (Builder $schema) use ($name) {
        $schema->drop($name);
    }
]
```

### 3.2 addColumns — Add Columns to Existing Table

```php
<?php
// Source: ziiven/flarum-daily-check-in/migrations/2022_09_22_000000_add_checkin_property_to_users_table.php

use Flarum\Database\Migration;

return Migration::addColumns('users', [
    'total_checkin_count' => ['integer', 'default' => 0],
    'total_continuous_checkin_count' => ['integer', 'default' => 0]
]);
```

```php
<?php
// Source: ziiven/flarum-daily-check-in/migrations/2022_09_22_000000_add_checkin_time_to_users_table.php

use Flarum\Database\Migration;

return Migration::addColumns('users', [
    'last_checkin_time' => ['datetime', 'nullable' => true]
]);
```

Column definition format: `'column_name' => ['type', ...options]`
- First element is the column type (string, integer, datetime, text, boolean, float, etc.)
- Remaining elements are key-value options: `'default'`, `'nullable'`, `'unsigned'`, `'length'`

### 3.3 Raw Migration — Manual up/down

For complex schema changes not covered by helpers:

```php
<?php
// Source: antoinefr/flarum-ext-money/migrations/2017_01_22_000000_change_money_to_float.php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('users', function (Blueprint $table) {
            $table->float('money')->default(0)->change();
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('users', function (Blueprint $table) {
            $table->integer('money')->default(0)->change();
        });
    }
];
```

### 3.4 Migration Naming Convention

Format: `YYYY_MM_DD_HHMMSS_snake_case_description.php`

Example filenames for our extension:
```
2026_01_01_000001_create_collectibles_table.php
2026_01_01_000002_create_trades_table.php
2026_01_01_000003_create_checkin_records_table.php
2026_01_01_000004_create_web3_accounts_table.php
2026_01_01_000005_create_collectible_events_table.php
2026_01_01_000006_add_blindbox_fields_to_users.php
```

---

## 4. Model Patterns

### 4.1 AbstractModel Base Class

All Flarum models extend `Flarum\Database\AbstractModel` which extends `Illuminate\Database\Eloquent\Model`.

Key differences from standard Eloquent:
- `$timestamps = false` by default (must explicitly enable)
- Supports runtime custom relations via `static::$customRelations`
- Supports runtime custom casts via `static::$customCasts`
- Supports runtime defaults via `static::$defaults`
- Has `afterSave()` and `afterDelete()` callback registration

```php
// Source: flarum/core/src/Database/AbstractModel.php:28-35
abstract class AbstractModel extends Eloquent
{
    public $timestamps = false;
    protected $afterSaveCallbacks = [];
    protected $afterDeleteCallbacks = [];
    public static $customRelations = [];
    public static $customCasts = [];
    public static $defaults = [];
}
```

### 4.2 Concrete Model Example

```php
<?php
// Source: blomstra/web3/src/Web3Account.php

namespace Blomstra\Web3;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;

/**
 * @property int $id
 * @property string $address
 * @property string $source
 * @property string $type
 * @property int $user_id
 * @property Carbon $attached_at
 * @property Carbon $last_verified_at
 * @property-read User $user
 */
class Web3Account extends AbstractModel
{
    use ScopeVisibilityTrait;

    const CREATED_AT = 'attached_at';
    const UPDATED_AT = 'last_verified_at';

    public $timestamps = true;

    protected $table = 'web3_accounts';

    public static function create(User $actor, string $address, string $source, string $type): self
    {
        $account = new static;
        $account->user_id = $actor->id;
        $account->address = $address;
        $account->source = $source;
        $account->type = $type;
        return $account;
    }
}
```

Key patterns:
- Use `ScopeVisibilityTrait` for query scoping with `whereVisibleTo($actor)`
- Override `CREATED_AT` / `UPDATED_AT` constants for custom timestamp column names
- Set `$timestamps = true` explicitly if needed
- Set `$table` explicitly
- Static `create()` factory method (does NOT save — caller must call `->save()`)
- PHPDoc `@property` annotations for IDE support

### 4.3 Relationships via Extend\Model

Relationships on existing models (like User) are added via `extend.php`, NOT by modifying the model class:

```php
// In extend.php
(new Extend\Model(User::class))
    ->hasMany('collectibles', Collectible::class, 'user_id')
```

Relationships on YOUR OWN models can be defined directly in the model class:

```php
class Collectible extends AbstractModel
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function originalUser()
    {
        return $this->belongsTo(User::class, 'original_user_id');
    }

    public function events()
    {
        return $this->hasMany(CollectibleEvent::class);
    }
}
```

---

## 5. Controller Patterns

Flarum provides four abstract controller base classes. All follow JSON:API spec.

### 5.1 AbstractCreateController (POST — returns 201)

```php
<?php
// Source: blomstra/web3/src/Api/Controller/CreateWeb3AccountController.php

namespace Donk\AigcCollectibles\Api\Controller;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class CreateTradeController extends AbstractCreateController
{
    public $serializer = TradeSerializer::class;

    // Optional: include relationships by default
    public $include = ['collectible', 'fromUser', 'toUser'];

    protected $bus;

    public function __construct(Dispatcher $bus)
    {
        $this->bus = $bus;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $payload = $request->getParsedBody();

        return $this->bus->dispatch(
            new CreateTrade($actor, $payload)
        );
    }
}
```

### 5.2 AbstractShowController (GET single — returns 200)

```php
<?php
namespace Donk\AigcCollectibles\Api\Controller;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ShowCollectibleController extends AbstractShowController
{
    public $serializer = CollectibleSerializer::class;

    public $include = ['user', 'originalUser'];
    public $optionalInclude = ['events'];

    protected $repository;

    public function __construct(CollectibleRepository $repository)
    {
        $this->repository = $repository;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $id = Arr::get($request->getQueryParams(), 'id');

        return $this->repository->findOrFail($id, $actor);
    }
}
```

### 5.3 AbstractListController (GET collection — returns 200)

```php
<?php
// Source: blomstra/web3/src/Api/Controller/ListWeb3AccountsController.php

namespace Donk\AigcCollectibles\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListCollectiblesController extends AbstractListController
{
    public $serializer = CollectibleSerializer::class;

    public $include = ['user'];
    public $sortFields = ['createdAt', 'rarity'];
    public $sort = ['createdAt' => 'desc'];

    protected $repository;

    public function __construct(CollectibleRepository $repository)
    {
        $this->repository = $repository;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $filter = $this->extractFilter($request);
        $sort = $this->extractSort($request);
        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);

        $results = $this->repository->query()
            ->whereVisibleTo($actor)
            ->orderBy(array_key_first($sort), array_values($sort)[0])
            ->skip($offset)
            ->take($limit + 1)
            ->get();

        $hasMore = $results->count() > $limit;
        $results = $results->take($limit);

        $document->addPaginationLinks(
            $this->url->to('api')->route('donk-aigc-collectibles.collectibles.index'),
            $request->getQueryParams(),
            $offset,
            $limit,
            $hasMore ? null : 0
        );

        return $results;
    }
}
```

### 5.4 AbstractDeleteController (DELETE — returns 204 empty)

```php
<?php
// Source: blomstra/web3/src/Api/Controller/DeleteWeb3AccountController.php

namespace Donk\AigcCollectibles\Api\Controller;

use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class CancelTradeController extends AbstractDeleteController
{
    protected $bus;

    public function __construct(Dispatcher $bus)
    {
        $this->bus = $bus;
    }

    protected function delete(ServerRequestInterface $request)
    {
        $actor = RequestUtil::getActor($request);
        $tradeId = Arr::get($request->getQueryParams(), 'id');

        $this->bus->dispatch(
            new CancelTrade($tradeId, $actor)
        );
    }
}
```

### 5.5 Controller Inheritance Chain

```
AbstractSerializeController (implements RequestHandlerInterface)
├── AbstractShowController (single resource → Resource element)
│   └── AbstractCreateController (same as Show but returns 201)
├── AbstractListController (collection → Collection element)
└── AbstractDeleteController (standalone, returns 204 EmptyResponse)
```

Key properties on AbstractSerializeController:
- `$serializer` — serializer class name (required)
- `$include` — default included relationships
- `$optionalInclude` — relationships available on request
- `$limit` / `$maxLimit` — pagination defaults
- `$sortFields` / `$sort` — sorting configuration

---

## 6. Serializer Patterns

### 6.1 AbstractSerializer Base

```php
// Source: flarum/core/src/Api/Serializer/AbstractSerializer.php
// Key methods:
abstract protected function getDefaultAttributes($model);  // MUST implement
public function hasOne($model, $serializer, $relation = null);
public function hasMany($model, $serializer, $relation = null);
public function formatDate(DateTime $date = null);  // returns RFC3339 string
// Access: $this->actor, $this->request, $this->getActor()
```

### 6.2 Concrete Serializer Example

```php
<?php
// Source: blomstra/web3/src/Api/Serializer/Web3AccountSerializer.php

namespace Donk\AigcCollectibles\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;
use Donk\AigcCollectibles\Model\Collectible;
use InvalidArgumentException;

class CollectibleSerializer extends AbstractSerializer
{
    protected $type = 'collectibles';

    protected function getDefaultAttributes($model)
    {
        if (! ($model instanceof Collectible)) {
            throw new InvalidArgumentException(
                get_class($this) . ' can only serialize instances of ' . Collectible::class
            );
        }

        return [
            'name'          => $model->name,
            'rarity'        => $model->rarity,
            'status'        => $model->status,
            'ipfsCid'       => $model->ipfs_cid,
            'metadataCid'   => $model->metadata_cid,
            'tokenId'       => $model->token_id,
            'timesTraded'   => (int) $model->times_traded,
            'createdAt'     => $this->formatDate($model->created_at),
            'updatedAt'     => $this->formatDate($model->updated_at),
        ];
    }

    // Relationship methods — name must match the relationship name
    protected function user($model)
    {
        return $this->hasOne($model, BasicUserSerializer::class);
    }

    protected function originalUser($model)
    {
        return $this->hasOne($model, BasicUserSerializer::class);
    }

    protected function events($model)
    {
        return $this->hasMany($model, CollectibleEventSerializer::class);
    }
}
```

Key rules:
- `$type` must match the JSON:API resource type (used in frontend `app.store`)
- `getDefaultAttributes()` returns a flat key-value array
- Relationship methods are named after the relationship and return `$this->hasOne()` or `$this->hasMany()`
- Use `$this->formatDate()` for all datetime fields
- Use `$this->actor` to check permissions for conditional attributes

### 6.3 Adding Attributes to Existing Serializers

Via `Extend\ApiSerializer` in extend.php (invokable class pattern):

```php
<?php
// Source: antoinefr/flarum-ext-money/src/AddUserMoneyAttributes.php

namespace Donk\AigcCollectibles\Api;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;

class AddUserAttributes
{
    public function __invoke(UserSerializer $serializer, User $user)
    {
        $attributes = [];
        $attributes['blindBoxCount'] = (int) $user->blind_box_count;
        $attributes['lastCheckinAt'] = $serializer->formatDate($user->last_checkin_at);
        $attributes['canCheckin'] = $serializer->getActor()->can('checkin', $user);

        return $attributes;
    }
}
```

---

## 7. Command/Handler CQRS Patterns

Flarum uses a CQRS-like pattern: Controllers dispatch Command objects, Handler classes process them. This decouples HTTP handling from business logic.

### 7.1 Command Class (Data Transfer Object)

```php
<?php
// Source: blomstra/web3/src/Command/CreateWeb3Account.php

namespace Donk\AigcCollectibles\Command;

use Flarum\User\User;

class CreateTrade
{
    public $actor;
    public $payload;

    public function __construct(User $actor, array $payload)
    {
        $this->actor = $actor;
        $this->payload = $payload;
    }
}
```

```php
<?php
// Command with ID parameter (for actions on existing resources)
// Source: blomstra/web3/src/Command/DeleteWeb3Account.php

namespace Donk\AigcCollectibles\Command;

use Flarum\User\User;

class AcceptTrade
{
    public $tradeId;
    public $actor;
    public $data;

    public function __construct(int $tradeId, User $actor, array $data = [])
    {
        $this->tradeId = $tradeId;
        $this->actor = $actor;
        $this->data = $data;
    }
}
```

### 7.2 Handler Class

```php
<?php
// Source: blomstra/web3/src/Command/CreateWeb3AccountHandler.php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Model\Trade;
use Donk\AigcCollectibles\Validator\TradeValidator;
use Flarum\Foundation\ValidationException;
use Illuminate\Support\Arr;

class CreateTradeHandler
{
    public function __construct(
        protected TradeValidator $validator,
    ) {}

    public function handle(CreateTrade $command): Trade
    {
        $actor = $command->actor;
        $data = $command->payload;

        $collectibleId = Arr::get($data, 'data.attributes.collectibleId');
        $offeredBoxes = (int) Arr::get($data, 'data.attributes.offeredBoxes');

        // Validate
        $this->validator->assertValid([
            'collectible_id' => $collectibleId,
            'offered_boxes' => $offeredBoxes,
        ]);

        // Business logic...
        $trade = new Trade();
        $trade->from_user_id = $actor->id;
        $trade->collectible_id = $collectibleId;
        $trade->offered_boxes = $offeredBoxes;
        $trade->status = 'pending';
        $trade->save();

        return $trade;
    }
}
```

### 7.3 Handler Auto-Resolution

Flarum uses Laravel's Bus dispatcher. The convention is:
- Command class: `CreateTrade`
- Handler class: `CreateTradeHandler` (same namespace, suffixed with `Handler`)
- Handler method: `handle(CreateTrade $command)`

The bus dispatcher auto-resolves `CreateTrade` → `CreateTradeHandler::handle()` by convention.

---

## 8. Service & Provider Patterns

### 8.1 AbstractServiceProvider

```php
<?php
// Source: blomstra/web3/src/Web3ServiceProvider.php

namespace Donk\AigcCollectibles\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use Illuminate\Contracts\Container\Container;

class CollectibleServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        // Bind interfaces to implementations
        $this->container->singleton(AIGCServiceInterface::class, function (Container $container) {
            return new AIGCService(
                $container->make('flarum.settings')
            );
        });

        $this->container->singleton(IPFSServiceInterface::class, function (Container $container) {
            return new IPFSService(
                $container->make('flarum.settings')
            );
        });
    }

    public function boot(Container $container)
    {
        // Post-registration setup
    }
}
```

`AbstractServiceProvider` extends Laravel's `ServiceProvider`:
- `$this->container` — the IoC container (also available as `$this->app`, deprecated)
- `register()` — bind services (called before boot)
- `boot()` — post-registration logic

### 8.2 Validator Pattern

```php
<?php
// Source: blomstra/web3/src/Web3AccountValidator.php

namespace Donk\AigcCollectibles\Validator;

use Flarum\Foundation\AbstractValidator;

class TradeValidator extends AbstractValidator
{
    protected function getRules()
    {
        return [
            'collectible_id' => ['required', 'integer', 'exists:collectibles,id'],
            'offered_boxes'  => ['required', 'integer', 'min:1'],
        ];
    }
}
```

Usage: `$this->validator->assertValid($attributes)` — throws `ValidationException` on failure.

### 8.3 Repository Pattern

```php
<?php
// Source: blomstra/web3/src/Web3AccountRepository.php

namespace Donk\AigcCollectibles\Repository;

use Donk\AigcCollectibles\Model\Collectible;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

class CollectibleRepository
{
    public function query(): Builder
    {
        return Collectible::query();
    }

    public function findOrFail(int $id, User $actor = null): Collectible
    {
        $query = $this->query()->where('id', $id);

        if ($actor) {
            $query->whereVisibleTo($actor);
        }

        return $query->firstOrFail();
    }

    public function findByUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $this->query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
```

### 8.4 Policy Pattern

```php
<?php
// Source: blomstra/web3/src/Access/Web3AccountPolicy.php

namespace Donk\AigcCollectibles\Access;

use Donk\AigcCollectibles\Model\Collectible;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class CollectiblePolicy extends AbstractPolicy
{
    public function view(User $actor, Collectible $collectible)
    {
        // All completed collectibles are publicly viewable
        if ($collectible->status === 'completed') {
            return $this->allow();
        }

        // Only owner can see non-completed
        if ($actor->id === $collectible->user_id) {
            return $this->allow();
        }
    }

    public function trade(User $actor, Collectible $collectible)
    {
        // Only owner can trade their collectible
        if ($actor->id === $collectible->user_id && $collectible->status === 'completed') {
            return $this->allow();
        }

        return $this->deny();
    }
}
```

### 8.5 Event Pattern

```php
<?php
// Source: antoinefr/flarum-ext-money/src/Event/MoneyUpdated.php

namespace Donk\AigcCollectibles\Event;

use Flarum\User\User;

class CheckedIn
{
    public User $user;
    public int $rewardAmount;

    public function __construct(User $user, int $rewardAmount)
    {
        $this->user = $user;
        $this->rewardAmount = $rewardAmount;
    }
}
```

---

## 9. Frontend Patterns (Mithril.js / TypeScript)

### 9.1 package.json Template

```json
{
  "name": "@donk/flarum-ext-aigc-collectibles",
  "version": "0.0.0",
  "private": true,
  "prettier": "@flarum/prettier-config",
  "devDependencies": {
    "flarum-tsconfig": "^1.0.2",
    "flarum-webpack-config": "^2.0.0",
    "webpack": "^5.73.0",
    "webpack-cli": "^4.10.0"
  },
  "scripts": {
    "dev": "webpack --mode development --watch",
    "build": "webpack --mode production",
    "format": "prettier --write src",
    "format-check": "prettier --check src"
  }
}
```

**Source**: `blomstra/web3/js/package.json`

### 9.2 tsconfig.json Template

```json
{
  "extends": "flarum-tsconfig",
  "include": ["src/**/*", "../vendor/flarum/core/js/dist-typings/@types/**/*"],
  "compilerOptions": {
    "declarationDir": "./dist-typings",
    "baseUrl": ".",
    "paths": {
      "flarum/*": ["../vendor/flarum/core/js/dist-typings/*"]
    }
  }
}
```

**Source**: `blomstra/web3/js/tsconfig.json`

### 9.3 webpack.config.js Template

```js
const config = require('flarum-webpack-config');

module.exports = config();
```

For extensions needing custom webpack config:
```js
// Source: blomstra/web3/js/webpack.config.js
const { merge } = require('webpack-merge');
const config = require('flarum-webpack-config');

module.exports = merge(config(), {
  // custom rules, plugins, etc.
});
```

### 9.4 Forum Entry Point (forum.ts)

The `forum.ts` file exports extenders via the `Extend` namespace. This is the modern pattern (Flarum 1.x+).

```typescript
// js/src/forum.ts
import Extend from 'flarum/common/extenders';
import Collectible from './forum/models/Collectible';
import Trade from './forum/models/Trade';
import CheckinRecord from './forum/models/CheckinRecord';

export { default as extend } from './forum/extend';

// Register models with the store
export const extend = [
  new Extend.Store()
    .add('collectibles', Collectible)
    .add('trades', Trade)
    .add('checkin-records', CheckinRecord),
];
```

Alternative pattern using `app.initializers` (older but still widely used):

```typescript
// Source: ziiven/flarum-daily-check-in/js/src/forum/index.js
// Source: antoinefr/flarum-ext-money/js/src/forum/index.js

import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';

app.initializers.add('donk-aigc-collectibles', () => {
  // Register models, extend components, add routes
});
```

### 9.5 Frontend Model Definition

```typescript
// Source: flarum/core/js/src/common/models/Discussion.tsx (pattern)

import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';

export default class Collectible extends Model {
  // Scalar attributes — use Model.attribute<T>(name)
  name() {
    return Model.attribute<string>('name').call(this);
  }

  rarity() {
    return Model.attribute<string>('rarity').call(this);
  }

  status() {
    return Model.attribute<string>('status').call(this);
  }

  ipfsCid() {
    return Model.attribute<string | null>('ipfsCid').call(this);
  }

  metadataCid() {
    return Model.attribute<string | null>('metadataCid').call(this);
  }

  tokenId() {
    return Model.attribute<number | null>('tokenId').call(this);
  }

  timesTraded() {
    return Model.attribute<number>('timesTraded').call(this);
  }

  // Date attributes — use Model.transformDate
  createdAt() {
    return Model.attribute<Date | undefined, string | undefined>('createdAt', Model.transformDate).call(this);
  }

  // Relationships — use Model.hasOne / Model.hasMany
  user() {
    return Model.hasOne<User>('user').call(this);
  }

  originalUser() {
    return Model.hasOne<User>('originalUser').call(this);
  }
}
```

Key facts about frontend Model (from `flarum/core/js/src/common/Model.ts`):
- `Model.attribute<T>(name)` returns a getter function; call it with `.call(this)`
- `Model.attribute<T, O>(name, transform)` applies a transform (e.g., `Model.transformDate`)
- `Model.hasOne<M>(name)` returns `M | false` (false = no relationship data)
- `Model.hasMany<M>(name)` returns `(M | undefined)[] | false`
- `model.save(attributes)` sends POST (new) or PATCH (existing) to `apiEndpoint()`
- `model.delete()` sends DELETE
- `apiEndpoint()` returns `'/' + type + '/' + id` by default

### 9.6 Extending Existing Components with extend()

The `extend()` function mutates the return value of a method.

```typescript
// Source: flarum/core/js/src/common/extend.ts
// Signature: extend(object, methods, callback)

import { extend } from 'flarum/common/extend';
import PostUser from 'flarum/forum/components/PostUser';
import IndexPage from 'flarum/forum/components/IndexPage';
import UserCard from 'flarum/forum/components/UserCard';

// Example: Add collectible badge next to post author
// Source pattern: PostUser.js:53 — badges are an ItemList
extend(PostUser.prototype, 'userViewItems', function (items, user, post) {
  const collectible = user.attribute('showcaseCollectible');
  if (collectible) {
    items.add('collectible-badge',
      <div className="PostCollectibleBadge">
        <img src={gatewayUrl(collectible.ipfsCid)} />
      </div>,
      85 // priority: between name(100) and badges(90)
    );
  }
});

// Example: Add check-in button to sidebar
// Source pattern: ziiven/flarum-daily-check-in/js/src/forum/index.js:9
extend(IndexPage.prototype, 'sidebarItems', function (items) {
  if (app.session.user) {
    items.add('checkin', <CheckinButton />, 50);
  }
});

// Example: Add balance to user card
// Source pattern: antoinefr/flarum-ext-money/js/src/forum/index.js:12
extend(UserCard.prototype, 'infoItems', function (items) {
  const count = this.attrs.user.attribute('blindBoxCount');
  items.add('blind-box-count',
    <span>{app.translator.trans('donk-aigc-collectibles.forum.blind_box_count', { count })}</span>
  );
});
```

### 9.7 Adding User Model Attributes from Frontend

```typescript
// Source: antoinefr/flarum-ext-money/js/src/forum/index.js:10
import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';

// Add getter methods to User prototype
User.prototype.blindBoxCount = Model.attribute('blindBoxCount');
User.prototype.lastCheckinAt = Model.attribute('lastCheckinAt', Model.transformDate);
User.prototype.canCheckin = Model.attribute('canCheckin');
```

### 9.8 Admin Settings Page

```typescript
// Source: antoinefr/flarum-ext-money/js/src/admin/index.js

import app from 'flarum/admin/app';

app.initializers.add('donk-aigc-collectibles', () => {
  app.extensionData
    .for('donk-aigc-collectibles')
    .registerSetting({
      setting: 'donk-aigc-collectibles.checkin-reward',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.checkin_reward'),
      type: 'number',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.aigc-api-key',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_api_key'),
      type: 'password',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.aigc-api-url',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.aigc_api_url'),
      type: 'text',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.ipfs-gateway',
      label: app.translator.trans('donk-aigc-collectibles.admin.settings.ipfs_gateway'),
      type: 'text',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.rarity-common',
      label: 'Common rarity weight (%)',
      type: 'number',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.rarity-rare',
      label: 'Rare rarity weight (%)',
      type: 'number',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.rarity-epic',
      label: 'Epic rarity weight (%)',
      type: 'number',
    })
    .registerSetting({
      setting: 'donk-aigc-collectibles.rarity-legendary',
      label: 'Legendary rarity weight (%)',
      type: 'number',
    })
    .registerPermission(
      {
        icon: 'fas fa-calendar-check',
        label: app.translator.trans('donk-aigc-collectibles.admin.permissions.checkin'),
        permission: 'donk-aigc-collectibles.checkin',
      },
      'start',
    );
});
```

Setting types available: `text`, `number`, `password`, `checkbox`, `select`, `textarea`, or a custom JSX callback.

### 9.9 Mithril.js Component Pattern

Flarum components extend `flarum/common/Component` which wraps Mithril.

```typescript
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';

export default class CheckinButton extends Component {
  // Lifecycle: oninit → view → oncreate → (updates) → onremove
  oninit(vnode) {
    super.oninit(vnode);
    this.loading = false;
  }

  view() {
    const user = app.session.user;
    const canCheckin = user.attribute('canCheckin');

    return (
      <Button
        className="Button CheckinButton"
        icon={canCheckin ? 'fas fa-gift' : 'fas fa-check'}
        disabled={!canCheckin || this.loading}
        loading={this.loading}
        onclick={this.handleCheckin.bind(this)}
      >
        {canCheckin
          ? app.translator.trans('donk-aigc-collectibles.forum.checkin_button')
          : app.translator.trans('donk-aigc-collectibles.forum.already_checked_in')
        }
      </Button>
    );
  }

  async handleCheckin() {
    this.loading = true;
    m.redraw();

    try {
      await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/checkin',
      });
      // ... handle success
    } catch (error) {
      // ... handle error
    } finally {
      this.loading = false;
      m.redraw();
    }
  }
}
```

### 9.10 Modal Component Pattern

```typescript
import Modal from 'flarum/common/components/Modal';

export default class TradeRequestModal extends Modal {
  className() {
    return 'TradeRequestModal Modal--small';
  }

  title() {
    return app.translator.trans('donk-aigc-collectibles.forum.trade_modal_title');
  }

  content() {
    return (
      <div className="Modal-body">
        {/* modal content */}
      </div>
    );
  }

  onsubmit(e) {
    e.preventDefault();
    // handle form submission
  }
}

// Usage: app.modal.show(TradeRequestModal, { collectible });
```

### 9.11 Making API Requests

```typescript
// Using model.save() — preferred for JSON:API resources
const collectible = app.store.createRecord('collectibles');
await collectible.save({ rarity: 'common' });

// Using app.request() — for non-standard endpoints
const response = await app.request({
  method: 'POST',
  url: app.forum.attribute('apiUrl') + '/checkin',
});

// Using model's save with custom attributes
await app.session.user.save({ canCheckin: false });
```

### 9.12 Path Aliases (IMPORTANT)

These import paths are NOT file paths — they are webpack aliases resolved to Flarum core globals:

```typescript
// These resolve to flarum core JS at runtime, NOT to node_modules
import app from 'flarum/forum/app';          // ForumApplication instance
import Component from 'flarum/common/Component';
import Model from 'flarum/common/Model';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import { extend } from 'flarum/common/extend';
import ItemList from 'flarum/common/utils/ItemList';
import User from 'flarum/common/models/User';
import Extend from 'flarum/common/extenders';

// Forum-specific components
import IndexPage from 'flarum/forum/components/IndexPage';
import PostUser from 'flarum/forum/components/PostUser';
import UserCard from 'flarum/forum/components/UserCard';
import UserPage from 'flarum/forum/components/UserPage';

// Admin
import app from 'flarum/admin/app';  // AdminApplication instance (in admin.ts)
```

---

## 10. Key Code Snippets from Reference Plugins

### 10.1 Safe Balance Increment/Decrement (antoinefr/flarum-ext-money)

```php
// Source: antoinefr/flarum-ext-money/src/Listeners/GiveMoney.php:52-64
public function giveMoney(?User $user, float $money): bool
{
    if (!is_null($user)) {
        $user->money += $money;  // Direct increment on model
        $user->save();           // Saves the full model

        $this->events->dispatch(new MoneyUpdated($user));
        return true;
    }
    return false;
}
```

**IMPORTANT for our extension**: This pattern is NOT concurrency-safe. For blind box balance transfers, we MUST use database-level atomic operations:

```php
// Recommended pattern for concurrency-safe balance operations:
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($buyer, $seller, $offeredBoxes) {
    // Lock rows with FOR UPDATE
    $buyerRow = DB::table('users')->where('id', $buyer->id)->lockForUpdate()->first();

    if ($buyerRow->blind_box_count < $offeredBoxes) {
        throw new ValidationException(['Insufficient blind boxes']);
    }

    // Atomic decrement with affected rows check
    $affected = DB::table('users')
        ->where('id', $buyer->id)
        ->where('blind_box_count', '>=', $offeredBoxes)
        ->decrement('blind_box_count', $offeredBoxes);

    if ($affected !== 1) {
        throw new ValidationException(['Concurrent modification detected']);
    }

    DB::table('users')
        ->where('id', $seller->id)
        ->increment('blind_box_count', $offeredBoxes);
});
```

### 10.2 Check-in Logic Pattern (ziiven/flarum-daily-check-in)

```php
// Source: ziiven/flarum-daily-check-in/src/Listeners/doCheckin.php:21-62
public function checkinSaved(Saving $event)
{
    $actor = $event->actor;
    $user = $event->user;

    $attributes = Arr::get($event->data, 'attributes', []);

    if (array_key_exists('canCheckin', $attributes)) {
        $timezone = intval($this->settings->get('ziven-forum-checkin.checkinTimeZone', 0));
        $current_timestamp = time() + $timezone * 60 * 60;
        $current_data_at_midnight = strtotime(date('Y-m-d', $current_timestamp) . " 00:00:00");

        $last_checkin_time = $user->last_checkin_time;
        // ... date comparison to prevent double check-in ...

        if ($canCheckin) {
            // Update continuous streak
            if (($current_timestamp - $checkin_date_at_midnight) / 3600 < 48) {
                $user->total_continuous_checkin_count += 1;
            } else {
                $user->total_continuous_checkin_count = 1;
            }

            $user->last_checkin_time = date('Y-m-d H:i:s', $current_timestamp);
            $user->total_checkin_count += 1;

            // Reward money if extension is present
            if (isset($user->money) === true) {
                $checkinRewardMoney = (float)$this->settings->get('...');
                $user->money += $checkinRewardMoney;
            }
        }
    }
}
```

Note: This plugin hooks into `Saving` event on User. Our extension should use a dedicated `POST /api/checkin` route with its own controller + command instead, for cleaner separation.

### 10.3 Frontend Check-in Button Pattern (ziiven/flarum-daily-check-in)

```javascript
// Source: ziiven/flarum-daily-check-in/js/src/forum/index.js:8-94

app.initializers.add('ziven-checkin', () => {
  extend(IndexPage.prototype, 'sidebarItems', function(items) {
    if (app.session.user !== null && app.forum.attribute('allowCheckIn') === true) {
      const canCheckin = app.session.user.attribute("canCheckin");

      if (canCheckin === true) {
        items.add('forum-checkin', Button.component({
          icon: 'fas fa-calendar',
          className: 'Button CheckInButton--yellow',
          onclick: () => {
            // Uses user.save() to trigger the Saving event on backend
            app.session.user.save({
              canCheckin: false,
              totalContinuousCheckIn: /* ... */
            }).then(() => {
              // Show success modal or alert
            });
          }
        }, checkinButtonText), 50);
      } else {
        // Show disabled "already checked in" button
        items.add('forum-checkin', Button.component({
          icon: 'fas fa-calendar-check',
          className: 'Button CheckInButton--green',
          disabled: true
        }, checkinButtonText), 50);
      }
    }
  });
});
```

### 10.4 Displaying Data on User Card (antoinefr/flarum-ext-money)

```javascript
// Source: antoinefr/flarum-ext-money/js/src/forum/index.js:12-26

extend(UserCard.prototype, 'infoItems', function (items) {
  const moneyName = app.forum.attribute('antoinefr-money.moneyname') || '[money]';

  items.add('money',
    <span>{moneyName.replace('[money]', this.attrs.user.data.attributes['money'])}</span>
  );
});
```

### 10.5 PostUser Extension Points

```javascript
// Source: flarum/core/js/src/forum/components/PostUser.js:42-64
// PostUser has two ItemList methods that can be extended:
//   - userViewItems(user, post) — has items: 'postUser-name'(100), 'postUser-badges'(90), 'postUser-card'(80)
//   - linkChildren(user) — has items: 'avatar'(100), 'userOnline'(90), 'username'(80)

// To add a collectible badge next to posts:
extend(PostUser.prototype, 'userViewItems', function (items, user, post) {
  // Add after name (100) but before badges (90)
  items.add('collectible-showcase', <PostCollectibleBadge user={user} />, 95);
});
```

### 10.6 Moderation Controls (antoinefr/flarum-ext-money)

```javascript
// Source: antoinefr/flarum-ext-money/js/src/forum/index.js:28-35

import UserControls from 'flarum/utils/UserControls';

extend(UserControls, 'moderationControls', (items, user) => {
  if (user.canEditMoney()) {
    items.add('money', Button.component({
      icon: 'fas fa-money-bill',
      onclick: () => app.modal.show(UserMoneyModal, { user })
    }, app.translator.trans('...')));
  }
});
```

### 10.7 Web3 Wallet Binding Flow (blomstra/web3)

Backend controller + command + handler flow:

```
POST /api/web3/accounts
  → CreateWeb3AccountController.data()
    → bus.dispatch(new CreateWeb3Account($actor, $payload))
      → CreateWeb3AccountHandler.handle()
        → Web3Account::create($actor, address, source, type)
        → validator.assertValid()
        → verifier.verify(signature, username, address)
        → account.save()
        → return $account
```

### 10.8 Locale File Format

```yaml
# Source: antoinefr/flarum-ext-money/locale/en.yml
antoinefr-money:
  admin:
    settings:
      moneyname: "Money format (use [money] as placeholder)"
      moneyforpost: "Money per post"
    permissions:
      edit_money_label: "Edit user money"
  forum:
    user_controls:
      money_button: "Edit money"
```

For our extension:
```yaml
donk-aigc-collectibles:
  admin:
    settings:
      checkin_reward: "Blind boxes per check-in"
      aigc_api_key: "AIGC API Key"
      aigc_api_url: "AIGC API URL"
      ipfs_gateway: "IPFS Gateway URL"
    permissions:
      checkin: "Allow daily check-in"
  forum:
    checkin_button: "Check In"
    already_checked_in: "Checked In"
    blind_box_count: "{count} Blind Boxes"
    open_blind_box: "Open Blind Box"
    trade_modal_title: "Make Trade Offer"
    rarity:
      common: "Common"
      rare: "Rare"
      epic: "Epic"
      legendary: "Legendary"
```

---

## 11. Architectural Notes & Gotchas

### 11.1 JSON:API Payload Format

Frontend sends data in this shape (via `model.save()`):
```json
{
  "data": {
    "type": "collectibles",
    "attributes": {
      "name": "...",
      "rarity": "common"
    },
    "relationships": {
      "user": {
        "data": { "type": "users", "id": "1" }
      }
    }
  }
}
```

In the handler, extract with `Arr::get($data, 'data.attributes.name')`.

### 11.2 Event Dispatch Flow

```
Controller → bus->dispatch(Command) → Handler::handle()
Handler → $events->dispatch(new DomainEvent())
extend.php Event listener → reacts to DomainEvent
```

### 11.3 Timestamps

- `AbstractModel` has `$timestamps = false` by default
- Set `$timestamps = true` to enable `created_at` / `updated_at`
- Use `$this->formatDate()` in serializers for RFC3339 output
- Use `Model.transformDate` in frontend models for Date objects

### 11.4 Visibility Scoping

For models that need permission-based query filtering:
1. Add `use ScopeVisibilityTrait;` to model
2. Register scope with `(new Extend\ModelVisibility(MyModel::class))->scope(MyScopeClass::class)`
3. Use `->whereVisibleTo($actor)` in repository queries

### 11.5 Frontend Store Registration

The `$type` in the serializer (`protected $type = 'collectibles'`) MUST match the store registration key:
```typescript
new Extend.Store().add('collectibles', Collectible)
```

This is the glue between backend JSON:API responses and frontend model hydration.
