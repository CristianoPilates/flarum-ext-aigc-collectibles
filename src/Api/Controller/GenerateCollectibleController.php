<?php

namespace Donk\AigcCollectibles\Api\Controller;

use Donk\AigcCollectibles\Api\Serializer\CollectibleSerializer;
use Donk\AigcCollectibles\Command\OpenBlindBox;
use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class GenerateCollectibleController extends AbstractCreateController
{
    public $serializer = CollectibleSerializer::class;

    public $include = ['user'];

    protected Dispatcher $bus;

    public function __construct(Dispatcher $bus)
    {
        $this->bus = $bus;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $data = $request->getParsedBody();

        return $this->bus->dispatch(
            new OpenBlindBox($actor, $data)
        );
    }
}
