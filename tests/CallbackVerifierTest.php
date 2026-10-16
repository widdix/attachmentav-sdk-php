<?php

declare(strict_types=1);

namespace AttachmentAV\Tests;

use AttachmentAV\Callback\CallbackVerifier;
use AttachmentAV\Callback\InvalidSignatureException;
use AttachmentAV\Callback\PublicKeys;
use AttachmentAV\Exception\InvalidArgumentException;
use AttachmentAV\Region;
use GuzzleHttp\Psr7\Request;

final class CallbackVerifierTest extends \PHPUnit\Framework\TestCase
{
    private const TENANT_ID = 'tenant-123';
    private const CALLBACK_URL = 'https://app.example.com/attachmentav/callback';
    private const NOW = 1760000000000;

    private \OpenSSLAsymmetricKey $privateKey;

    private CallbackVerifier $verifier;

    protected function setUp(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        $this->privateKey = $key;
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);

        $this->verifier = new CallbackVerifier($details['key'], self::TENANT_ID);
        $this->verifier->setClock(static function (): int {
            return self::NOW;
        });
    }

    private function sign(string $timestamp, string $body, string $tenantId = self::TENANT_ID, string $callbackUrl = self::CALLBACK_URL): string
    {
        openssl_sign($timestamp . '.' . $tenantId . '.' . $callbackUrl . '.' . $body, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return bin2hex($signature);
    }

    public function testValidSignature(): void
    {
        $body = '{"status":"clean","size":100}';
        $timestamp = (string) self::NOW;

        self::assertTrue($this->verifier->verify($body, $this->sign($timestamp, $body), $timestamp, self::CALLBACK_URL));
    }

    public function testTamperedBody(): void
    {
        $timestamp = (string) self::NOW;
        $signature = $this->sign($timestamp, '{"status":"infected"}');

        self::assertFalse($this->verifier->verify('{"status":"clean"}', $signature, $timestamp, self::CALLBACK_URL));
    }

    public function testWrongTenantOrCallbackUrl(): void
    {
        $body = '{"status":"clean"}';
        $timestamp = (string) self::NOW;

        self::assertFalse($this->verifier->verify($body, $this->sign($timestamp, $body, 'other-tenant'), $timestamp, self::CALLBACK_URL));
        self::assertFalse($this->verifier->verify($body, $this->sign($timestamp, $body), $timestamp, 'https://evil.example.com'));
    }

    public function testTimestampOutsideTolerance(): void
    {
        $body = '{"status":"clean"}';
        $old = (string) (self::NOW - CallbackVerifier::DEFAULT_TOLERANCE_MS - 1);
        $edge = (string) (self::NOW - CallbackVerifier::DEFAULT_TOLERANCE_MS);

        self::assertFalse($this->verifier->verify($body, $this->sign($old, $body), $old, self::CALLBACK_URL));
        self::assertTrue($this->verifier->verify($body, $this->sign($edge, $body), $edge, self::CALLBACK_URL));
    }

    public function testMissingOrMalformedHeaders(): void
    {
        $body = '{"status":"clean"}';
        $timestamp = (string) self::NOW;
        $signature = $this->sign($timestamp, $body);

        self::assertFalse($this->verifier->verify($body, null, $timestamp, self::CALLBACK_URL));
        self::assertFalse($this->verifier->verify($body, $signature, null, self::CALLBACK_URL));
        self::assertFalse($this->verifier->verify($body, 'not-hex', $timestamp, self::CALLBACK_URL));
        self::assertFalse($this->verifier->verify($body, $signature, 'abc', self::CALLBACK_URL));
    }

    public function testVerifyRequest(): void
    {
        $body = '{"status":"clean"}';
        $timestamp = (string) self::NOW;
        $request = new Request('POST', self::CALLBACK_URL, [
            'x-signature' => $this->sign($timestamp, $body),
            'x-timestamp' => $timestamp,
        ], $body);

        self::assertTrue($this->verifier->verifyRequest($request, self::CALLBACK_URL));
        self::assertSame($body, (string) $request->getBody());
    }

    public function testVerifyAndParse(): void
    {
        $body = json_encode([
            'status' => 'infected',
            'finding' => 'EICAR-AV-Test',
            'size' => 68,
            'download_time' => 0.5,
            'scan_time' => 0.25,
            'download_url' => 'https://example.com/eicar.txt',
            'trace_id' => 't-1',
            'custom_data' => 'abc',
            'realfiletype' => 'ASCII text',
        ]);
        self::assertIsString($body);
        $timestamp = (string) self::NOW;

        $payload = $this->verifier->verifyAndParse($body, $this->sign($timestamp, $body), $timestamp, self::CALLBACK_URL);

        self::assertTrue($payload->isInfected());
        self::assertSame('EICAR-AV-Test', $payload->getFinding());
        self::assertSame(68, $payload->getSize());
        self::assertSame(0.5, $payload->getDownloadTime());
        self::assertSame(0.25, $payload->getScanTime());
        self::assertSame('https://example.com/eicar.txt', $payload->getDownloadUrl());
        self::assertSame('t-1', $payload->getTraceId());
        self::assertSame('abc', $payload->getCustomData());
        self::assertSame('ASCII text', $payload->getRealFileType());
    }

    public function testVerifyAndParseThrows(): void
    {
        $this->expectException(InvalidSignatureException::class);
        $this->verifier->verifyAndParse('{"status":"clean"}', 'aa', (string) self::NOW, self::CALLBACK_URL);
    }

    public function testBundledPublicKeysAreValid(): void
    {
        foreach (Region::all() as $region) {
            self::assertNotFalse(openssl_pkey_get_public(PublicKeys::forRegion($region)), $region);
            CallbackVerifier::forRegion($region, self::TENANT_ID);
        }
    }

    public function testInvalidPublicKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CallbackVerifier('not a key', self::TENANT_ID);
    }
}
