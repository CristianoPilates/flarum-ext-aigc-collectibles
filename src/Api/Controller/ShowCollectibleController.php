<?php

namespace Donk\AigcCollectibles\Api\Controller;

use Donk\AigcCollectibles\Api\Serializer\CollectibleSerializer;
use Donk\AigcCollectibles\Repository\CollectibleRepository;
use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ShowCollectibleController extends AbstractShowController
{
    public $serializer = CollectibleSerializer::class;

    public $include = ['user', 'originalUser'];

    public $optionalInclude = ['events', 'trades'];

    protected CollectibleRepository $repository;

    public function __construct(CollectibleRepository $repository)
    {
        $this->repository = $repository;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $id = Arr::get($request->getQueryParams(), 'id');

        $include = $this->extractInclude($request);

        $collectible = $this->repository->findOrFail((int) $id, $actor);

        if (in_array('events', $include)) {
            $collectible->load('events');
        }

        if (in_array('trades', $include)) {
            $collectible->load('trades');
        }

        return $collectible;
    }
}
