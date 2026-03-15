<?php

namespace Donk\AigcCollectibles\Validator;

use Flarum\Foundation\AbstractValidator;

class Web3LoginValidator extends AbstractValidator
{
    protected function getRules(): array
    {
        return [
            'address' => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{40}$/'],
            'signature' => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{130}$/'],
            'nonce' => ['required', 'string'],
        ];
    }
}
