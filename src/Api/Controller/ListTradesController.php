<?php

namespace Donk\AigcCollectibles\Api\Controller;

use Donk\AigcCollectibles\Api\Serializer\TradeSerializer;
use Donk\AigcCollectibles\Repository\TradeRepository;
use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListTradesController extends AbstractListController
{
    public $serializer = TradeSerializer::class;

    public $include = ['collectible', 'fromUser', 'toUser'];

    public $sortFields = ['createdAt'];

    public $sort = ['createdAt' => 'desc'];

    public $limit = 20;

    public $maxLimit = 50;

    protected TradeRepository $repository;
    protected UrlGenerator $url;

    public function __construct(TradeRepository $repository, UrlGenerator $url)
    {
        $this->repository = $repository;
        $this->url = $url;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $filter = $this->extractFilter($request);
        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);

        $actor->assertRegistered();

        $query = $this->repository->query()
            ->whereVisibleTo($actor)
            ->where(function ($q) use ($actor) {
                $q->where('from_user_id', $actor->id)
                    ->orWhere('to_user_id', $actor->id);
            });

        $status = Arr::get($filter, 'status');
        if ($status) {
            $query->where('status', $status);
        }

        $collectibleId = Arr::get($filter, 'collectible');
        if ($collectibleId) {
            $query->where('collectible_id', (int) $collectibleId);
        }

        $results = $query
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit + 1)
            ->get();

        $hasMore = $results->count() > $limit;
        $results = $results->take($limit);

        $document->addPaginationLinks(
            $this->url->to('api')->route('donk-aigc-collectibles.trades.index'),
            $request->getQueryParams(),
            $offset,
            $limit,
            $hasMore ? null : 0
        );

        return $results;
    }
}
