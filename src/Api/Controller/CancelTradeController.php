<?php

namespace Donk\AigcCollectibles\Api\Controller;

use Donk\AigcCollectibles\Command\CancelTrade;
use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class CancelTradeController extends AbstractDeleteController
{
    protected Dispatcher $bus;

    public function __construct(Dispatcher $bus)
    {
        $this->bus = $bus;
    }

    protected function delete(ServerRequestInterface $request)
    {
        $actor = RequestUtil::getActor($request);
        $tradeId = (int) Arr::get($request->getQueryParams(), 'id');

        $this->bus->dispatch(
            new CancelTrade($tradeId, $actor)
        );
    }
}
