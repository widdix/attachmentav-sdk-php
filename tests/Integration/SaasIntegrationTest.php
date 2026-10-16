<?php

declare(strict_types=1);

namespace AttachmentAV\Tests\Integration;

use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Configuration;
use AttachmentAV\Exception\AuthenticationException;
use AttachmentAV\Exception\RateLimitException;
use AttachmentAV\Model\ScanStatus;
use AttachmentAV\Region;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests against the attachmentAV API (SaaS), region EU.
 *
 * Every test sends real requests that count against the monthly quota of the subscription.
 *
 *     ATTACHMENTAV_API_KEY=... vendor/bin/phpunit --testsuite integration
 *
 * @group integration
 */
final class SaasIntegrationTest extends TestCase
{
    private const DOWNLOAD_URL = 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf';

    private AttachmentAVApi $api;

    protected function setUp(): void
    {
        $apiKey = getenv('ATTACHMENTAV_API_KEY');
        if ($apiKey === false || $apiKey === '') {
            self::markTestSkipped('Set ATTACHMENTAV_API_KEY to run the integration tests.');
        }
        $this->api = new AttachmentAVApi(Configuration::saas($apiKey, Region::EU));
    }

    /**
     * The EICAR anti-virus test file, assembled at runtime so that the source file itself is not flagged by scanners.
     */
    private static function eicar(): string
    {
        return base64_decode('WDVPIVAlQEFQWzRcUFpYNTQoUF4pN0NDKTd9JEVJQ0FSLVNUQU5EQVJELUFOVElWSVJVUy1URVNULUZJTEUhJEgrSCo=', true) ?: '';
    }

    public function testApiKeyIsValid(): void
    {
        $this->api->test();
        $this->addToAssertionCount(1);
    }

    public function testInvalidApiKeyIsRejected(): void
    {
        $api = new AttachmentAVApi(Configuration::saas('invalid-api-key', Region::EU));

        $this->expectException(AuthenticationException::class);
        $api->test();
    }

    public function testWhoami(): void
    {
        self::assertNotSame('', $this->api->whoami()->getTenantId());
    }

    public function testUsage(): void
    {
        $usage = $this->api->getUsage();

        self::assertGreaterThan(0, $usage->getQuotaLimit());
        self::assertGreaterThanOrEqual(0, $usage->getCredits());
        self::assertNotSame('', $usage->getQuotaPeriod());
    }

    public function testScanSyncBinaryClean(): void
    {
        $content = "attachmentAV PHP SDK integration test\n";
        $result = $this->api->scanSyncBinary($content);

        self::assertSame(ScanStatus::CLEAN, $result->getStatus());
        self::assertNull($result->getFinding());
        self::assertSame(strlen($content), $result->getSize());
    }

    public function testScanSyncBinaryInfected(): void
    {
        $result = $this->api->scanSyncBinary(self::eicar());

        self::assertSame(ScanStatus::INFECTED, $result->getStatus());
        self::assertNotEmpty($result->getFinding());
    }

    public function testScanSyncFileFromStream(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'attachmentav');
        self::assertIsString($path);
        file_put_contents($path, str_repeat('attachmentAV ', 10000));

        try {
            $result = $this->api->scanSyncFile($path);
        } finally {
            unlink($path);
        }

        self::assertTrue($result->isClean());
        self::assertSame(130000, $result->getSize());
    }

    public function testScanSyncDownload(): void
    {
        $result = $this->api->scanSyncDownload(self::DOWNLOAD_URL);

        self::assertTrue($result->isClean());
        self::assertGreaterThan(0, $result->getSize());
        self::assertNotNull($result->getRealFileType());
        self::assertStringContainsString('PDF', $result->getRealFileType());
    }

    public function testScanAsyncDownloadWithPolling(): void
    {
        $traceId = 'sdk-php-' . bin2hex(random_bytes(8));
        $this->api->scanAsyncDownload(self::DOWNLOAD_URL, [], null, [], $traceId);

        $result = null;
        $deadline = time() + 120;
        while ($result === null && time() < $deadline) {
            sleep(5);
            $result = $this->api->getAsyncResult($traceId);
        }

        self::assertNotNull($result, 'No async scan result after 120 seconds.');
        self::assertTrue($result->isClean());
        self::assertGreaterThan(0, $result->getSize());
    }

    public function testGetAsyncResultUnknownTraceIdReturnsNull(): void
    {
        self::assertNull($this->api->getAsyncResult('sdk-php-unknown-' . bin2hex(random_bytes(8))));
    }

    public function testGetCallbackFailures(): void
    {
        $failures = $this->api->getCallbackFailures('https://sdk-php-' . bin2hex(random_bytes(8)) . '.example.com/callback');

        self::assertCount(0, $failures);
        self::assertFalse($failures->hasMore());
    }

    public function testScanSyncS3(): void
    {
        $bucket = getenv('ATTACHMENTAV_TEST_S3_BUCKET');
        $key = getenv('ATTACHMENTAV_TEST_S3_KEY');
        if ($bucket === false || $bucket === '' || $key === false || $key === '') {
            self::markTestSkipped('Set ATTACHMENTAV_TEST_S3_BUCKET and ATTACHMENTAV_TEST_S3_KEY (bucket policy required) to test S3 scans.');
        }

        $result = $this->api->scanSyncS3($bucket, $key);

        self::assertContains($result->getStatus(), [ScanStatus::CLEAN, ScanStatus::INFECTED, ScanStatus::NO]);
    }

    public function testThrottlingIsHandled(): void
    {
        // Burst a few requests; throttled requests are retried by the SDK, so none should fail with "Rate limit exceeded".
        try {
            for ($i = 0; $i < 8; $i++) {
                $this->api->scanSyncBinary('burst ' . $i);
            }
        } catch (RateLimitException $e) {
            self::assertTrue($e->isQuotaExceeded(), 'Throttled request was not retried: ' . $e->getMessage());
        }
        $this->addToAssertionCount(1);
    }
}
