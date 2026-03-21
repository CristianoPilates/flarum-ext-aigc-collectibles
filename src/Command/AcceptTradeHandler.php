<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Model\Trade;
use Donk\AigcCollectibles\Repository\TradeRepository;
use Donk\AigcCollectibles\Service\Contracts\TradeServiceInterface;

class AcceptTradeHandler
{
    protected TradeServiceInterface $tradeService;
    protected TradeRepository $tradeRepository;

    public function __construct(TradeServiceInterface $tradeService, TradeRepository $tradeRepository)
    {
        $this->tradeService = $tradeService;
        $this->tradeRepository = $tradeRepository;
    }

    public function handle(AcceptTrade $command): Trade
    {
        $actor = $command->actor;

        $actor->assertRegistered();

        $trade = $this->tradeRepository->findOrFail($command->tradeId, $actor);

        $actor->assertCan('accept', $trade);

        return $this->tradeService->acceptTrade($trade, $actor);
    }
}
