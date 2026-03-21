<?php

namespace Donk\AigcCollectibles\Service\Contracts;

interface IPFSServiceInterface
{
    public function upload(string $data): string;

    public function uploadJson(array $metadata): string;

    public function getGatewayUrl(string $cid): string;
}
