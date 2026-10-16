<?php

declare(strict_types=1);

namespace AttachmentAV\Tests;

use AttachmentAV\Configuration;
use AttachmentAV\Exception\ApiException;
use AttachmentAV\Exception\AuthenticationException;
use AttachmentAV\Exception\InvalidArgumentException;
use AttachmentAV\Exception\RateLimitException;
use AttachmentAV\Exception\TransportException;
use AttachmentAV\Exception\UnexpectedResponseException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Http\Client\Exception\NetworkException;

final class AttachmentAVApiTest extends TestCase
{
    public function testScanSyncBinarySaas(): void
    {
        $api = $this->api();
        $this->respond(200, ['status' => 'clean', 'size' => 8100, 'realfiletype' => 'ASCII text']);

        $result = $api->scanSyncBinary('hello world');

        self::assertTrue($result->isClean());
        self::assertFalse($result->isInfected());
        self::assertSame(8100, $result->getSize());
        self::assertSame('ASCII text', $result->getRealFileType());
        self::assertNull($result->getFinding());

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://eu.developer.attachmentav.com/v1/scan/sync/binary', (string) $request->getUri());
        self::assertSame('test-key', $request->getHeaderLine('x-api-key'));
        self::assertFalse($request->hasHeader('Authorization'));
        self::assertSame('application/octet-stream', $request->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('attachmentav-sdk-php/', $request->getHeaderLine('User-Agent'));
        self::assertSame('hello world', (string) $request->getBody());
    }

    public function testScanSyncBinarySelfHosted(): void
    {
        $api = $this->api(Configuration::selfHosted('self-key', 'https://attachmentav.example.com'));
        $this->respond(200, ['status' => 'infected', 'finding' => 'EICAR-AV-Test', 'size' => 68]);

        $result = $api->scanSyncBinary('X5O!P%@AP');

        self::assertTrue($result->isInfected());
        self::assertSame('EICAR-AV-Test', $result->getFinding());
        $request = $this->lastRequest();
        self::assertSame('https://attachmentav.example.com/api/v1/scan/sync/binary', (string) $request->getUri());
        self::assertSame('Bearer self-key', $request->getHeaderLine('Authorization'));
        self::assertFalse($request->hasHeader('x-api-key'));
    }

    public function testScanSyncBinaryAcceptsResourceAndStream(): void
    {
        $api = $this->api();
        $this->respond(200, ['status' => 'clean']);
        $this->respond(200, ['status' => 'clean']);

        $resource = fopen('php://memory', 'r+b');
        self::assertIsResource($resource);
        fwrite($resource, 'from resource');
        rewind($resource);
        $api->scanSyncBinary($resource);
        self::assertSame('from resource', (string) $this->lastRequest()->getBody());

        $stream = Utils::streamFor('from stream');
        $stream->getContents();
        $api->scanSyncBinary($stream);
        self::assertSame('from stream', (string) $this->lastRequest()->getBody());
    }

    public function testScanSyncBinaryRejectsInvalidContent(): void
    {
        $api = $this->api();
        $this->expectException(InvalidArgumentException::class);
        // @phpstan-ignore-next-line argument.type
        $api->scanSyncBinary(42);
    }

    public function testScanSyncFile(): void
    {
        $api = $this->api();
        $this->respond(200, ['status' => 'no']);
        $path = tempnam(sys_get_temp_dir(), 'av');
        self::assertIsString($path);
        file_put_contents($path, 'file content');

        try {
            $result = $api->scanSyncFile($path);
        } finally {
            unlink($path);
        }

        self::assertTrue($result->isUnscannable());
        self::assertSame('file content', (string) $this->lastRequest()->getBody());
    }

    public function testScanSyncFileMissing(): void
    {
        $api = $this->api();
        $this->expectException(InvalidArgumentException::class);
        $api->scanSyncFile('/does/not/exist');
    }

    public function testScanSyncDownload(): void
    {
        $api = $this->api();
        $this->respond(200, ['status' => 'clean']);

        $api->scanSyncDownload('https://example.com/file.pdf', ['Authorization' => 'Bearer secret']);

        $request = $this->lastRequest();
        self::assertSame('https://eu.developer.attachmentav.com/v1/scan/sync/download', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'download_url' => 'https://example.com/file.pdf',
            'download_headers' => ['Authorization' => 'Bearer secret'],
        ], $this->lastJsonBody());
    }

    public function testScanSyncDownloadWithoutHeaders(): void
    {
        $api = $this->api();
        $this->respond(200, ['status' => 'clean']);
        $api->scanSyncDownload('https://example.com/file.pdf');
        self::assertSame(['download_url' => 'https://example.com/file.pdf'], $this->lastJsonBody());
    }

    public function testScanSyncDownloadRejectsInvalidUrl(): void
    {
        $api = $this->api();
        $this->expectException(InvalidArgumentException::class);
        $api->scanSyncDownload('ftp://example.com/file.pdf');
    }

    public function testScanSyncS3(): void
    {
        $api = $this->api();
        $this->respond(200, ['status' => 'clean']);

        $api->scanSyncS3('my-bucket', 'path/to/file.pdf', 'v1');

        self::assertSame('https://eu.developer.attachmentav.com/v1/scan/sync/s3', (string) $this->lastRequest()->getUri());
        self::assertSame(['bucket' => 'my-bucket', 'key' => 'path/to/file.pdf', 'version' => 'v1'], $this->lastJsonBody());
    }

    public function testScanAsyncDownloadWithCallback(): void
    {
        $api = $this->api();
        $this->respond(204);

        $api->scanAsyncDownload(
            'https://example.com/file.pdf',
            [],
            'https://app.example.com/callback',
            ['X-Secret' => 'abc'],
            null,
            '{"id":1}'
        );

        self::assertSame('https://eu.developer.attachmentav.com/v1/scan/async/download', (string) $this->lastRequest()->getUri());
        self::assertSame([
            'download_url' => 'https://example.com/file.pdf',
            'callback_url' => 'https://app.example.com/callback',
            'callback_headers' => ['X-Secret' => 'abc'],
            'custom_data' => '{"id":1}',
        ], $this->lastJsonBody());
    }

    public function testScanAsyncS3WithTraceId(): void
    {
        $api = $this->api();
        $this->respond(201, null, ['Location' => '/v1/scan/async/result?trace_id=t-1']);

        $api->scanAsyncS3('my-bucket', 'file.pdf', null, null, [], 't-1');

        self::assertSame('https://eu.developer.attachmentav.com/v1/scan/async/s3', (string) $this->lastRequest()->getUri());
        self::assertSame(['bucket' => 'my-bucket', 'key' => 'file.pdf', 'trace_id' => 't-1'], $this->lastJsonBody());
    }

    public function testAsyncRequiresCallbackOrTraceId(): void
    {
        $api = $this->api();
        $this->expectException(InvalidArgumentException::class);
        $api->scanAsyncDownload('https://example.com/file.pdf');
    }

    public function testAsyncRejectsTooLargeCustomData(): void
    {
        $api = $this->api();
        $this->expectException(InvalidArgumentException::class);
        $api->scanAsyncS3('bucket', 'key', null, null, [], 'trace', str_repeat('a', 16 * 1024 + 1));
    }

    public function testGetAsyncResult(): void
    {
        $api = $this->api();
        $this->respond(200, ['status' => 'clean', 'size' => 1]);

        $result = $api->getAsyncResult('trace id/1');

        self::assertNotNull($result);
        self::assertTrue($result->isClean());
        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://eu.developer.attachmentav.com/v1/scan/async/result?trace_id=trace+id%2F1', (string) $request->getUri());
    }

    public function testGetAsyncResultNotFoundReturnsNull(): void
    {
        $api = $this->api();
        $this->respond(404, ['message' => 'Not Found']);
        self::assertNull($api->getAsyncResult('trace'));
    }

    public function testGetCallbackFailures(): void
    {
        $api = $this->api();
        $this->respond(200, [
            'failures' => [[
                'id' => 'f-1',
                'time' => '2026-01-02T03:04:05Z',
                'download_url' => 'https://example.com/file.pdf',
                'callback_url' => 'https://app.example.com/callback',
                'error_json' => '{"statusCode":500}',
            ]],
            'next_cursor' => 'abc',
        ]);

        $list = $api->getCallbackFailures('https://app.example.com/callback', 'cur');

        self::assertCount(1, $list);
        self::assertTrue($list->hasMore());
        self::assertSame('abc', $list->getNextCursor());
        $failure = $list->getFailures()[0];
        self::assertSame('f-1', $failure->getId());
        self::assertNotNull($failure->getTime());
        self::assertSame('2026-01-02', $failure->getTime()->format('Y-m-d'));
        self::assertSame(['statusCode' => 500], $failure->getError());
        self::assertSame(
            'https://eu.developer.attachmentav.com/v1/callback/failures?callback_url=https%3A%2F%2Fapp.example.com%2Fcallback&cursor=cur',
            (string) $this->lastRequest()->getUri()
        );
    }

    public function testGetUsage(): void
    {
        $api = $this->api();
        $this->respond(200, ['credits' => 981, 'quota' => ['limit' => 1000, 'period' => 'MONTH']]);

        $usage = $api->getUsage();

        self::assertSame(981, $usage->getCredits());
        self::assertSame(1000, $usage->getQuotaLimit());
        self::assertSame('MONTH', $usage->getQuotaPeriod());
        self::assertSame('https://eu.developer.attachmentav.com/v1/usage', (string) $this->lastRequest()->getUri());
    }

    public function testWhoami(): void
    {
        $api = $this->api();
        $this->respond(200, ['tenantId' => 'tenant-1']);
        self::assertSame('tenant-1', $api->whoami()->getTenantId());
    }

    public function testTest(): void
    {
        $api = $this->api();
        $this->respond(204);
        $api->test();
        self::assertSame('https://eu.developer.attachmentav.com/v1/test', (string) $this->lastRequest()->getUri());
    }

    public function testAuthenticationError(): void
    {
        $api = $this->api();
        $this->respond(403, ['message' => 'Forbidden']);

        try {
            $api->whoami();
            self::fail('Expected exception');
        } catch (AuthenticationException $e) {
            self::assertSame(403, $e->getStatusCode());
            self::assertSame('Forbidden', $e->getErrorMessage());
            self::assertStringContainsString('403', $e->getMessage());
        }
    }

    public function testGenericApiError(): void
    {
        $api = $this->api();
        $this->respond(413, ['error' => 'Payload too large.']);

        try {
            $api->scanSyncBinary('x');
            self::fail('Expected exception');
        } catch (ApiException $e) {
            self::assertSame(ApiException::class, get_class($e));
            self::assertSame(413, $e->getStatusCode());
            self::assertSame('Payload too large.', $e->getErrorMessage());
        }
    }

    public function testRetriesThrottledRequests(): void
    {
        $api = $this->api(Configuration::saas('key')->withRetryDelayMs(100));
        $this->respond(429, ['error' => 'Rate limit exceeded.']);
        $this->respond(429, ['error' => 'Rate limit exceeded.']);
        $this->respond(200, ['status' => 'clean']);

        $result = $api->scanSyncBinary('payload');

        self::assertTrue($result->isClean());
        self::assertCount(3, $this->http->getRequests());
        foreach ($this->http->getRequests() as $request) {
            self::assertSame('payload', (string) $request->getBody());
        }
        self::assertCount(2, $this->sleeps);
        self::assertGreaterThanOrEqual(100, $this->sleeps[0]);
        self::assertLessThanOrEqual(150, $this->sleeps[0]);
        self::assertGreaterThanOrEqual(200, $this->sleeps[1]);
        self::assertLessThanOrEqual(300, $this->sleeps[1]);
    }

    public function testRetryHonorsRetryAfter(): void
    {
        $api = $this->api();
        $this->respond(429, ['error' => 'Rate limit exceeded.'], ['Retry-After' => '2']);
        $this->respond(200, ['status' => 'clean']);

        $api->scanSyncDownload('https://example.com/file.pdf');

        self::assertSame([2000], $this->sleeps);
    }

    public function testGivesUpAfterMaxRetries(): void
    {
        $api = $this->api(Configuration::saas('key')->withMaxRetries(1));
        $this->respond(429, ['error' => 'Rate limit exceeded.']);
        $this->respond(429, ['error' => 'Rate limit exceeded.']);

        try {
            $api->whoami();
            self::fail('Expected exception');
        } catch (RateLimitException $e) {
            self::assertTrue($e->isThrottled());
            self::assertFalse($e->isQuotaExceeded());
        }
        self::assertCount(2, $this->http->getRequests());
    }

    public function testDoesNotRetryQuotaExceeded(): void
    {
        $api = $this->api();
        $this->respond(429, ['error' => 'Quota exceeded.']);

        try {
            $api->whoami();
            self::fail('Expected exception');
        } catch (RateLimitException $e) {
            self::assertTrue($e->isQuotaExceeded());
        }
        self::assertCount(1, $this->http->getRequests());
        self::assertSame([], $this->sleeps);
    }

    public function testTransportError(): void
    {
        $api = $this->api();
        $this->http->addException(new NetworkException('Connection refused', new Request('GET', '/')));

        $this->expectException(TransportException::class);
        $api->whoami();
    }

    public function testInvalidJson(): void
    {
        $api = $this->api();
        $this->http->addResponse(new \GuzzleHttp\Psr7\Response(200, [], '<html>'));

        $this->expectException(UnexpectedResponseException::class);
        $api->whoami();
    }

    public function testMissingStatus(): void
    {
        $api = $this->api();
        $this->respond(200, ['size' => 1]);

        $this->expectException(UnexpectedResponseException::class);
        $api->scanSyncBinary('x');
    }

    public function testDefaultHeaders(): void
    {
        $api = $this->api(Configuration::saas('key')->withDefaultHeaders(['X-Request-Id' => 'r-1']));
        $this->respond(200, ['tenantId' => 't']);
        $api->whoami();
        self::assertSame('r-1', $this->lastRequest()->getHeaderLine('X-Request-Id'));
    }
}
