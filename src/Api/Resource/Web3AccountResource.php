<?php

namespace Donk\AigcCollectibles\Api\Resource;

use Donk\AigcCollectibles\Command\BindWallet;
use Donk\AigcCollectibles\Model\Web3Account;
use Donk\AigcCollectibles\Service\Contracts\BlockchainServiceInterface;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Bus\Dispatcher;
use Flarum\Foundation\ValidationException;
use Illuminate\Database\Eloquent\Builder;
use Laminas\Diactoros\Response\JsonResponse;

/**
 * @extends AbstractDatabaseResource<Web3Account>
 */
class Web3AccountResource extends AbstractDatabaseResource
{
    public function __construct(
        protected Dispatcher $bus,
        protected BlockchainServiceInterface $blockchainService,
    ) {
    }

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
        $actor = $context->getActor();

        // Users can only see their own web3 accounts
        $query->where('user_id', $actor->id);
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
                }),

            Endpoint\Endpoint::make('delete')
                ->route('DELETE', '/{id}')
                ->authenticated()
                ->action(function (Context $context) {
                    $actor = $context->getActor();
                    $accountId = (int) $context->modelId;

                    $account = Web3Account::query()
                        ->where('id', $accountId)
                        ->firstOrFail();

                    if ($account->user_id !== $actor->id) {
                        throw new ValidationException([
                            'account' => 'You can only unbind your own wallet.',
                        ]);
                    }

                    $account->delete();

                    return null;
                })
                ->response(fn () => new \Nyholm\Psr7\Response(204)),

            Endpoint\Endpoint::make('nonce')
                ->route('POST', '/nonce')
                ->authenticated()
                ->action(function (Context $context) {
                    $nonce = $this->blockchainService->generateNonce();
                    $domain = $context->request->getUri()->getHost() ?: 'localhost';
                    $message = $this->blockchainService->buildSignMessage($nonce, $domain);

                    return [
                        'nonce' => $nonce,
                        'message' => $message,
                    ];
                })
                ->response(function (Context $context, $data) {
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
