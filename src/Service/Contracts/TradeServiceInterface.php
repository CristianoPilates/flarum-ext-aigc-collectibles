<?php

namespace Donk\AigcCollectibles\Service\Contracts;

use Donk\AigcCollectibles\Model\Trade;
use Flarum\User\User;

interface TradeServiceInterface
{
    public function createOffer(User $buyer, int $collectibleId, int $offeredBoxes, ?string $note = null): Trade;

    public function acceptTrade(Trade $trade, User $actor): Trade;

    public function rejectTrade(Trade $trade, User $actor): Trade;

    public function cancelTrade(Trade $trade, User $actor): Trade;
}
