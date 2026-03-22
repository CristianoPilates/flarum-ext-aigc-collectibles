<?php

namespace Donk\AigcCollectibles\Tests\Fake;

use Donk\AigcCollectibles\Service\Contracts\IPFSServiceInterface;

/**
 * Fake IPFS service that returns deterministic CIDs for testing.
 */
class FakeIPFSService implements IPFSServiceInterface
{
    public int $uploadCount = 0;
    public int $uploadJsonCount = 0;
    public bool $shouldFail = false;

    public function upload(string $data): string
    {
        $this->uploadCount++;

        if ($this->shouldFail) {
            throw new \RuntimeException('Fake IPFS upload failed.');
        }

        return 'QmFakeImageCid' . $this->uploadCount;
    }

    public function uploadJson(array $metadata): string
    {
        $this->uploadJsonCount++;

        if ($this->shouldFail) {
            throw new \RuntimeException('Fake IPFS metadata upload failed.');
        }

        return 'QmFakeMetadataCid' . $this->uploadJsonCount;
    }

    public function getGatewayUrl(string $cid): string
    {
        return 'https://fake-gateway.test/ipfs/' . $cid;
    }
}
