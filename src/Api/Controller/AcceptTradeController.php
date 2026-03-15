<?php

namespace Donk\AigcCollectibles\Api\Controller;

use Donk\AigcCollectibles\Api\Serializer\TradeSerializer;
use Donk\AigcCollectibles\Command\AcceptTrade;
use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class AcceptTradeController extends AbstractShowController
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
        $tradeId = (int) Arr::get($request->getQueryParams(), 'id');

        return $this->bus->dispatch(
            new AcceptTrade($tradeId, $actor)
        );
    }
}
