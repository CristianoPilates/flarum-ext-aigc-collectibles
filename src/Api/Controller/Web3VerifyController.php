<?php

namespace Donk\AigcCollectibles\Api\Controller;

use Donk\AigcCollectibles\Api\Serializer\Web3AccountSerializer;
use Donk\AigcCollectibles\Command\BindWallet;
use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class Web3VerifyController extends AbstractCreateController
{
    public $serializer = Web3AccountSerializer::class;

    protected Dispatcher $bus;

    public function __construct(Dispatcher $bus)
    {
        $this->bus = $bus;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $data = $request->getParsedBody();
        $data['HTTP_HOST'] = $request->getServerParams()['HTTP_HOST'] ?? 'localhost';

        return $this->bus->dispatch(
            new BindWallet($actor, $data)
        );
    }
}
