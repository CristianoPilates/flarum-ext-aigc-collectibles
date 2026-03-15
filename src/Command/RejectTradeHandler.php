<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Model\Trade;
use Donk\AigcCollectibles\Repository\TradeRepository;
use Donk\AigcCollectibles\Service\TradeService;

class RejectTradeHandler
{
    protected TradeService $tradeService;
    protected TradeRepository $tradeRepository;

    public function __construct(TradeService $tradeService, TradeRepository $tradeRepository)
    {
        $this->tradeService = $tradeService;
        $this->tradeRepository = $tradeRepository;
    }

    public function handle(RejectTrade $command): Trade
    {
        $actor = $command->actor;

        $actor->assertRegistered();

        $trade = $this->tradeRepository->findOrFail($command->tradeId, $actor);

        $actor->assertCan('reject', $trade);

        return $this->tradeService->rejectTrade($trade, $actor);
    }
}
