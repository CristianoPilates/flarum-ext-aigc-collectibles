<?php

namespace Donk\AigcCollectibles\Api\Controller;

use Donk\AigcCollectibles\Api\Serializer\CollectibleSerializer;
use Donk\AigcCollectibles\Repository\CollectibleRepository;
use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListCollectiblesController extends AbstractListController
{
    public $serializer = CollectibleSerializer::class;

    public $include = ['user'];

    public $optionalInclude = ['originalUser'];

    public $sortFields = ['createdAt', 'rarity', 'timesTraded'];

    public $sort = ['createdAt' => 'desc'];

    public $limit = 20;

    public $maxLimit = 50;

    protected CollectibleRepository $repository;
    protected UrlGenerator $url;

    public function __construct(CollectibleRepository $repository, UrlGenerator $url)
    {
        $this->repository = $repository;
        $this->url = $url;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $filter = $this->extractFilter($request);
        $sort = $this->extractSort($request);
        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);

        $query = $this->repository->query()
            ->whereVisibleTo($actor)
            ->where('status', 'completed');

        $userId = Arr::get($filter, 'user');
        if ($userId) {
            $query->where('user_id', (int) $userId);
        }

        $rarity = Arr::get($filter, 'rarity');
        if ($rarity) {
            $query->where('rarity', $rarity);
        }

        $sortColumn = 'created_at';
        $sortDirection = 'desc';

        if (!empty($sort)) {
            $field = array_key_first($sort);
            $sortDirection = $sort[$field];

            $sortMap = [
                'createdAt' => 'created_at',
                'rarity' => 'rarity',
                'timesTraded' => 'times_traded',
            ];

            $sortColumn = $sortMap[$field] ?? 'created_at';
        }

        $results = $query
            ->orderBy($sortColumn, $sortDirection)
            ->skip($offset)
            ->take($limit + 1)
            ->get();

        $hasMore = $results->count() > $limit;
        $results = $results->take($limit);

        $document->addPaginationLinks(
            $this->url->to('api')->route('donk-aigc-collectibles.collectibles.index'),
            $request->getQueryParams(),
            $offset,
            $limit,
            $hasMore ? null : 0
        );

        return $results;
    }
}
