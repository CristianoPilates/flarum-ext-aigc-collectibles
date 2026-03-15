<?php

namespace Donk\AigcCollectibles\Api\Controller;

use Donk\AigcCollectibles\Api\Serializer\CollectibleSerializer;
use Donk\AigcCollectibles\Repository\CollectibleRepository;
use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class UpdateCollectibleController extends AbstractShowController
{
    public $serializer = CollectibleSerializer::class;

    public $include = ['user'];

    protected CollectibleRepository $repository;

    public function __construct(CollectibleRepository $repository)
    {
        $this->repository = $repository;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $id = Arr::get($request->getQueryParams(), 'id');
        $data = $request->getParsedBody();

        $actor->assertRegistered();

        $collectible = $this->repository->findOrFail((int) $id, $actor);

        $actor->assertCan('update', $collectible);

        $isShowcase = Arr::get($data, 'data.attributes.isShowcase');

        if ($isShowcase !== null) {
            if ($isShowcase) {
                $actor->showcase_collectible_id = $collectible->id;
            } else {
                if ($actor->showcase_collectible_id === $collectible->id) {
                    $actor->showcase_collectible_id = null;
                }
            }
            $actor->save();
        }

        return $collectible;
    }
}
