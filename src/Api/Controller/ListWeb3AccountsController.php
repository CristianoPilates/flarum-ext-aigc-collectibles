<?php

namespace Donk\AigcCollectibles\Api\Controller;

use Donk\AigcCollectibles\Api\Serializer\Web3AccountSerializer;
use Donk\AigcCollectibles\Model\Web3Account;
use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListWeb3AccountsController extends AbstractListController
{
    public $serializer = Web3AccountSerializer::class;

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        return Web3Account::query()
            ->where('user_id', $actor->id)
            ->get();
    }
}
