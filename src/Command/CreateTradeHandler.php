<?php

namespace Donk\AigcCollectibles\Command;

use Donk\AigcCollectibles\Model\Trade;
use Donk\AigcCollectibles\Service\TradeService;
use Donk\AigcCollectibles\Validator\TradeValidator;
use Illuminate\Support\Arr;

class CreateTradeHandler
{
    protected TradeService $tradeService;
    protected TradeValidator $validator;

    public function __construct(TradeService $tradeService, TradeValidator $validator)
    {
        $this->tradeService = $tradeService;
        $this->validator = $validator;
    }

    public function handle(CreateTrade $command): Trade
    {
        $actor = $command->actor;
        $data = $command->data;

        $actor->assertRegistered();

        $collectibleId = (int) Arr::get($data, 'data.attributes.collectibleId');
        $offeredBoxes = (int) Arr::get($data, 'data.attributes.offeredBoxes');
        $note = Arr::get($data, 'data.attributes.note');

        $this->validator->assertValid([
            'collectible_id' => $collectibleId,
            'offered_boxes' => $offeredBoxes,
        ]);

        return $this->tradeService->createOffer($actor, $collectibleId, $offeredBoxes, $note);
    }
}
