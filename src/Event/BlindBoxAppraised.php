<?php

namespace Donk\AigcCollectibles\Event;

use Flarum\User\User;
use Donk\AigcCollectibles\Model\BlindBox;

class BlindBoxAppraised
{
    public function __construct(
        public readonly User $actor,
        public readonly BlindBox $blindBox,
    ) {}
}
