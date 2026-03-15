<?php

namespace Donk\AigcCollectibles\Api\Controller;

use Donk\AigcCollectibles\Api\Serializer\TradeSerializer;
use Donk\AigcCollectibles\Command\CreateTrade;
use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class CreateTradeController extends AbstractCreateController
{
    public $serializer = TradeSerializer::class;

    public $include = ['collectible', 'fromUser', 'toUser'];

    protected Dispatcher $bus;

    public function __construct(Dispatcher $bus)
    {
        $this->bus = $bus;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $data = $request->getParsedBody();

        return $this->bus->dispatch(
            new CreateTrade($actor, $data)
        );
    }
}
