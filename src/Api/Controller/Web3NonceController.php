<?php

namespace Donk\AigcCollectibles\Api\Controller;

use Donk\AigcCollectibles\Service\BlockchainService;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Web3NonceController implements RequestHandlerInterface
{
    protected BlockchainService $blockchainService;

    public function __construct(BlockchainService $blockchainService)
    {
        $this->blockchainService = $blockchainService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $address = Arr::get($request->getParsedBody(), 'data.attributes.address', '');

        $nonce = $this->blockchainService->generateNonce();
        $domain = $request->getServerParams()['HTTP_HOST'] ?? 'localhost';
        $message = $this->blockchainService->buildSignMessage($nonce, $domain);

        return new JsonResponse([
            'data' => [
                'attributes' => [
                    'nonce' => $nonce,
                    'message' => $message,
                ],
            ],
        ]);
    }
}
