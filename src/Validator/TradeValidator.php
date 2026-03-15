<?php

namespace Donk\AigcCollectibles\Validator;

use Flarum\Foundation\AbstractValidator;

class TradeValidator extends AbstractValidator
{
    protected function getRules(): array
    {
        return [
            'collectible_id' => ['required', 'integer', 'exists:collectibles,id'],
            'offered_boxes' => ['required', 'integer', 'min:1'],
        ];
    }
}
