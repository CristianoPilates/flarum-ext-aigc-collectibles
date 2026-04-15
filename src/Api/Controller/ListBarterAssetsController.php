<?php

namespace Donk\AigcCollectibles\Api\Controller;

use Donk\AigcCollectibles\Model\BarterProposal;
use Donk\AigcCollectibles\Service\Contracts\BarterServiceInterface;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListBarterAssetsController implements RequestHandlerInterface
{
    public function __construct(
        private readonly BarterServiceInterface $barterService,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $query = $request->getQueryParams();

        if ($query === []) {
            parse_str($request->getUri()->getQuery(), $query);
        }

        $filters = is_array(Arr::get($query, 'filter')) ? Arr::get($query, 'filter') : [];

        $payload = $this->barterService->listThreadAssets(
            actor: $actor,
            threadType: trim((string) ($filters['thread_type'] ?? $filters['threadType'] ?? BarterProposal::THREAD_DIALOG)),
            threadId: (int) ($filters['thread_id'] ?? $filters['threadId'] ?? 0),
            counterpartyUserId: (int) ($filters['counterparty_user_id'] ?? $filters['counterpartyUserId'] ?? 0),
        );

        return new JsonResponse(['data' => $payload]);
    }
}
