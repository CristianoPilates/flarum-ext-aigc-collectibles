<?php

namespace Donk\AigcCollectibles\Api\Controller;

use Donk\AigcCollectibles\Model\Web3Account;
use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use Flarum\Foundation\ValidationException;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class DeleteWeb3AccountController extends AbstractDeleteController
{
    protected function delete(ServerRequestInterface $request)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $accountId = (int) Arr::get($request->getQueryParams(), 'id');

        $account = Web3Account::query()
            ->where('id', $accountId)
            ->firstOrFail();

        if ($account->user_id !== $actor->id) {
            throw new ValidationException([
                'account' => 'You can only unbind your own wallet.',
            ]);
        }

        $account->delete();
    }
}
