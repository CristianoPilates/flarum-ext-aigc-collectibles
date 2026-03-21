<?php

namespace Donk\AigcCollectibles\Service;

use Donk\AigcCollectibles\Service\Contracts\BlockchainServiceInterface;
use Elliptic\EC;
use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use kornrunner\Keccak;
use RuntimeException;

class BlockchainService implements BlockchainServiceInterface
{
    protected SettingsRepositoryInterface $settings;
    protected Client $client;

    public function __construct(
        SettingsRepositoryInterface $settings,
        ?Client $client = null
    ) {
        $this->settings = $settings;
        $this->client = $client ?? new Client(['timeout' => 30]);
    }

    public function verifySignature(string $message, string $signature, string $expectedAddress): bool
    {
        $address = $this->recoverAddress($message, $signature);

        return strtolower($address) === strtolower($expectedAddress);
    }

    public function recoverAddress(string $message, string $signature): string
    {
        $personalMessage = "\x19Ethereum Signed Message:\n" . strlen($message) . $message;
        $msgHash = Keccak::hash($personalMessage, 256);

        $sign = [
            'r' => substr($signature, 2, 64),
            's' => substr($signature, 66, 64),
        ];

        $v = hexdec(substr($signature, 130, 2));

        if ($v < 27) {
            $v += 27;
        }

        $recId = $v - 27;

        $ec = new EC('secp256k1');
        $pubKey = $ec->recoverPubKey($msgHash, $sign, $recId);

        $pubKeyHex = $pubKey->encode('hex');
        $pubKeyWithoutPrefix = substr($pubKeyHex, 2);

        $address = '0x' . substr(Keccak::hash(hex2bin($pubKeyWithoutPrefix), 256), -40);

        return $address;
    }

    public function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function buildSignMessage(string $nonce, string $domain): string
    {
        return "Sign this message to verify your wallet ownership.\n\n"
            . "Domain: {$domain}\n"
            . "Nonce: {$nonce}\n"
            . "Timestamp: " . time();
    }

    public function mintNFT(string $toAddress, string $tokenURI): int
    {
        $rpcUrl = $this->settings->get('donk-aigc-collectibles.blockchain-rpc-url');
        $contractAddress = $this->settings->get('donk-aigc-collectibles.nft-contract-address');
        $privateKey = $this->settings->get('donk-aigc-collectibles.minter-private-key');

        if (empty($rpcUrl) || empty($contractAddress) || empty($privateKey)) {
            throw new RuntimeException('Blockchain minting is not configured.');
        }

        $mintSelector = '0x' . substr(Keccak::hash('mint(address,string)', 256), 0, 8);

        $encodedAddress = str_pad(substr($toAddress, 2), 64, '0', STR_PAD_LEFT);
        $offset = str_pad(dechex(64), 64, '0', STR_PAD_LEFT);
        $uriLength = str_pad(dechex(strlen($tokenURI)), 64, '0', STR_PAD_LEFT);
        $uriHex = bin2hex($tokenURI);
        $uriPadded = str_pad($uriHex, (int) (ceil(strlen($uriHex) / 64) * 64), '0', STR_PAD_RIGHT);

        $data = $mintSelector . $encodedAddress . $offset . $uriLength . $uriPadded;

        $txHash = $this->sendRawTransaction($rpcUrl, $contractAddress, $data, $privateKey);

        $receipt = $this->waitForReceipt($rpcUrl, $txHash);

        if (empty($receipt['logs'])) {
            throw new RuntimeException('Mint transaction produced no logs.');
        }

        $transferLog = $receipt['logs'][0];
        $tokenId = hexdec($transferLog['topics'][3] ?? '0');

        return $tokenId;
    }

    protected function sendRawTransaction(string $rpcUrl, string $to, string $data, string $privateKey): string
    {
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate(ltrim($privateKey, '0x'));
        $fromAddress = '0x' . substr(Keccak::hash(hex2bin(substr($key->getPublic('hex'), 2)), 256), -40);

        $nonce = $this->rpcCall($rpcUrl, 'eth_getTransactionCount', [$fromAddress, 'pending']);
        $gasPrice = $this->rpcCall($rpcUrl, 'eth_gasPrice', []);

        $tx = [
            'nonce' => $nonce,
            'gasPrice' => $gasPrice,
            'gas' => '0x' . dechex(300000),
            'to' => $to,
            'value' => '0x0',
            'data' => $data,
        ];

        $chainId = hexdec($this->rpcCall($rpcUrl, 'eth_chainId', []));

        $rlpEncoded = $this->rlpEncodeTx($tx, $chainId);
        $txHash = Keccak::hash(hex2bin($rlpEncoded), 256);

        $sig = $key->sign($txHash, ['canonical' => true]);
        $r = str_pad($sig->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($sig->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = dechex($sig->recoveryParam + 27 + $chainId * 2 + 8);

        $signedTx = '0x' . $this->rlpEncodeSignedTx($tx, $v, $r, $s);

        return $this->rpcCall($rpcUrl, 'eth_sendRawTransaction', [$signedTx]);
    }

    protected function waitForReceipt(string $rpcUrl, string $txHash, int $maxRetries = 30): array
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            $receipt = $this->rpcCall($rpcUrl, 'eth_getTransactionReceipt', [$txHash]);

            if ($receipt !== null) {
                if ($receipt['status'] !== '0x1') {
                    throw new RuntimeException('Mint transaction reverted.');
                }
                return $receipt;
            }

            usleep(500000);
        }

        throw new RuntimeException('Transaction receipt timeout after ' . $maxRetries . ' attempts.');
    }

    protected function rpcCall(string $rpcUrl, string $method, array $params)
    {
        $response = $this->client->post($rpcUrl, [
            'json' => [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ],
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        if (isset($body['error'])) {
            throw new RuntimeException('RPC error: ' . ($body['error']['message'] ?? 'Unknown'));
        }

        return $body['result'] ?? null;
    }

    protected function rlpEncodeTx(array $tx, int $chainId): string
    {
        $items = [
            $tx['nonce'],
            $tx['gasPrice'],
            $tx['gas'],
            $tx['to'],
            $tx['value'],
            $tx['data'],
            '0x' . dechex($chainId),
            '0x',
            '0x',
        ];

        return $this->rlpEncodeList($items);
    }

    protected function rlpEncodeSignedTx(array $tx, string $v, string $r, string $s): string
    {
        $items = [
            $tx['nonce'],
            $tx['gasPrice'],
            $tx['gas'],
            $tx['to'],
            $tx['value'],
            $tx['data'],
            '0x' . $v,
            '0x' . $r,
            '0x' . $s,
        ];

        return $this->rlpEncodeList($items);
    }

    protected function rlpEncodeList(array $items): string
    {
        $encoded = '';

        foreach ($items as $item) {
            $encoded .= $this->rlpEncodeItem($item);
        }

        $length = strlen($encoded) / 2;

        if ($length < 56) {
            return dechex(0xc0 + $length) . $encoded;
        }

        $lengthHex = dechex($length);
        if (strlen($lengthHex) % 2 !== 0) {
            $lengthHex = '0' . $lengthHex;
        }

        return dechex(0xf7 + strlen($lengthHex) / 2) . $lengthHex . $encoded;
    }

    protected function rlpEncodeItem(string $hexValue): string
    {
        if (str_starts_with($hexValue, '0x')) {
            $hexValue = substr($hexValue, 2);
        }

        if ($hexValue === '' || $hexValue === '0') {
            return '80';
        }

        if (strlen($hexValue) % 2 !== 0) {
            $hexValue = '0' . $hexValue;
        }

        $length = strlen($hexValue) / 2;

        if ($length === 1 && hexdec($hexValue) < 0x80) {
            return $hexValue;
        }

        if ($length < 56) {
            return dechex(0x80 + $length) . $hexValue;
        }

        $lengthHex = dechex($length);
        if (strlen($lengthHex) % 2 !== 0) {
            $lengthHex = '0' . $lengthHex;
        }

        return dechex(0xb7 + strlen($lengthHex) / 2) . $lengthHex . $hexValue;
    }

    public function isMintingConfigured(): bool
    {
        return !empty($this->settings->get('donk-aigc-collectibles.blockchain-rpc-url'))
            && !empty($this->settings->get('donk-aigc-collectibles.nft-contract-address'))
            && !empty($this->settings->get('donk-aigc-collectibles.minter-private-key'));
    }
}
