<?php

namespace Donk\AigcCollectibles\StateMachine;

use Illuminate\Container\Container;
use ReflectionClass;
use SM\Factory\FactoryInterface;
use SM\StateMachine\StateMachineInterface;

trait HasStateMachine
{
    public function getStateMachine(string $graph): StateMachineInterface
    {
        $factory = Container::getInstance()->make(FactoryInterface::class);

        return $factory->get($this, $graph);
    }

    public function transition(string $transition): void
    {
        $this->getStateMachine($this->getDefaultStateMachineGraph())->apply($transition);
    }

    protected function getDefaultStateMachineGraph(): string
    {
        return lcfirst((new ReflectionClass($this))->getShortName());
    }
}
