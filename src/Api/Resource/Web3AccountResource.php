<?php

namespace Donk\AigcCollectibles\Api\Resource;

use Donk\AigcCollectibles\Command\BindWallet;
use Donk\AigcCollectibles\Command\UnbindWallet;
use Donk\AigcCollectibles\Model\Web3Account;
use Donk\AigcCollectibles\Service\Contracts\WalletVerificationServiceInterface;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Serializer;
use Flarum\Bus\Dispatcher;
use Flarum\Foundation\ValidationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;

use function Tobyz\JsonApiServer\json_api_response;

/**
 * @extends AbstractDatabaseResource<Web3Account>
 */
class Web3AccountResource extends AbstractDatabaseResource
{
    public function __construct(
        protected Dispatcher $bus,
        protected WalletVerificationServiceInterface $walletService,
    ) {}

    public function type(): string
    {
        return 'web3-accounts';
    }

    public function model(): string
    {
        return Web3Account::class;
    }

    public function scope(Builder $query, \Tobyz\JsonApiServer\Context $context): void
    {
        $query->where('user_id', $context->getActor()->id);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated(),

            Endpoint\Endpoint::make('verify')
                ->route('POST', '/')
                ->authenticated()
                ->action(function (Context $context) {
                    $data = $context->body();
                    $data['domain'] = $context->request->getUri()->getHost() ?: 'localhost';

                    return $this->bus->dispatch(
                        new BindWallet($context->getActor(), $data)
                    );
                })
                ->response(function (Context $context, Web3Account $account) {
                    $serializer = new Serializer($context);
                    $serializer->addPrimary(
                        $context->resource($context->collection->resource($account, $context)),
                        $account,
                        []
                    );

                    [$primary, $included] = $serializer->serialize();
                    $document = ['data' => $primary[0]];

                    if (count($included)) {
                        $document['included'] = $included;
                    }

                    return json_api_response($document);
                }),

            Endpoint\Endpoint::make('delete')
                ->route('DELETE', '/{id}')
                ->authenticated()
                ->action(function (Context $context) {
                    $this->bus->dispatch(
                        new UnbindWallet($context->getActor(), (int) $context->modelId)
                    );

                    return null;
                })
                ->response(fn () => new Response(204)),

            Endpoint\Endpoint::make('nonce')
                ->route('POST', '/nonce')
                ->authenticated()
                ->action(function (Context $context) {
                    $actor = $context->getActor();
                    $address = strtolower((string) Arr::get($context->body(), 'data.attributes.address', ''));

                    if (! preg_match('/^0x[0-9a-f]{40}$/', $address)) {
                        throw new ValidationException([
                            'address' => 'A valid wallet address is required to request nonce.',
                        ]);
                    }

                    $domain = $context->request->getUri()->getHost() ?: 'localhost';

                    return $this->walletService->createNonceChallenge($actor->id, $address, $domain);
                })
                ->response(function (Context $context, array $data) {
                    return new JsonResponse([
                        'data' => [
                            'attributes' => $data,
                        ],
                    ]);
                }),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('address'),
            Schema\Str::make('source'),
            Schema\Str::make('type'),
            Schema\DateTime::make('attachedAt')
                ->property('attached_at'),
            Schema\DateTime::make('lastVerifiedAt')
                ->property('last_verified_at'),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [];
    }
}
