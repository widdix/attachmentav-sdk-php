<?php

declare(strict_types=1);

namespace AttachmentAV\Tests;

use AttachmentAV\Configuration;
use AttachmentAV\Exception\InvalidArgumentException;
use AttachmentAV\Region;

final class ConfigurationTest extends \PHPUnit\Framework\TestCase
{
    public function testSaasDefaultsToEurope(): void
    {
        $config = Configuration::saas('key');
        self::assertTrue($config->isSaas());
        self::assertSame('https://eu.developer.attachmentav.com/v1', $config->getBaseUrl());
        self::assertSame(['x-api-key' => 'key'], $config->getAuthenticationHeaders());
        self::assertSame(Region::EU, $config->getRegion());
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function regionProvider(): iterable
    {
        yield ['us', 'https://us.developer.attachmentav.com/v1'];
        yield ['CANADA', 'https://canada.developer.attachmentav.com/v1'];
        yield ['india', 'https://india.developer.attachmentav.com/v1'];
        yield ['australia', 'https://australia.developer.attachmentav.com/v1'];
    }

    /**
     * @dataProvider regionProvider
     */
    public function testSaasRegions(string $region, string $expected): void
    {
        self::assertSame($expected, Configuration::saas('key', $region)->getBaseUrl());
    }

    public function testUnknownRegion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Configuration::saas('key', 'mars');
    }

    public function testEmptyApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Configuration::saas(' ');
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function selfHostedUrlProvider(): iterable
    {
        yield ['https://attachmentav.example.com/api/v1', 'https://attachmentav.example.com/api/v1'];
        yield ['https://attachmentav.example.com/api/v1/', 'https://attachmentav.example.com/api/v1'];
        yield ['https://attachmentav.example.com', 'https://attachmentav.example.com/api/v1'];
        yield ['https://attachmentav.example.com/', 'https://attachmentav.example.com/api/v1'];
        yield ['attachmentav.example.com', 'https://attachmentav.example.com/api/v1'];
        yield ['http://localhost:8080/api/v1', 'http://localhost:8080/api/v1'];
    }

    /**
     * @dataProvider selfHostedUrlProvider
     */
    public function testSelfHosted(string $url, string $expected): void
    {
        $config = Configuration::selfHosted('key', $url);
        self::assertTrue($config->isSelfHosted());
        self::assertNull($config->getRegion());
        self::assertSame($expected, $config->getBaseUrl());
        self::assertSame(['Authorization' => 'Bearer key'], $config->getAuthenticationHeaders());
    }

    public function testFromArraySaas(): void
    {
        $config = Configuration::fromArray(['api_key' => 'key', 'region' => 'us', 'max_retries' => 0, 'retry_delay_ms' => 100]);
        self::assertTrue($config->isSaas());
        self::assertSame('https://us.developer.attachmentav.com/v1', $config->getBaseUrl());
        self::assertSame(0, $config->getMaxRetries());
        self::assertSame(100, $config->getRetryDelayMs());
    }

    public function testFromArraySaasWithBaseUrlOverride(): void
    {
        $config = Configuration::fromArray(['api_key' => 'key', 'base_url' => 'https://proxy.example.com/v1/']);
        self::assertSame('https://proxy.example.com/v1', $config->getBaseUrl());
        self::assertSame(['x-api-key' => 'key'], $config->getAuthenticationHeaders());
    }

    public function testFromArraySelfHosted(): void
    {
        $config = Configuration::fromArray(['deployment' => 'self-hosted', 'api_key' => 'key', 'base_url' => 'https://av.example.com', 'region' => 'eu']);
        self::assertTrue($config->isSelfHosted());
        self::assertSame('https://av.example.com/api/v1', $config->getBaseUrl());
    }

    public function testFromArraySelfHostedRequiresBaseUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Configuration::fromArray(['deployment' => 'self-hosted', 'api_key' => 'key']);
    }

    public function testFromArrayUnknownDeployment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Configuration::fromArray(['deployment' => 'on-prem', 'api_key' => 'key']);
    }

    public function testWithersAreImmutable(): void
    {
        $config = Configuration::saas('key');
        $other = $config->withMaxRetries(0)->withDefaultHeaders(['X-Foo' => 'bar']);
        self::assertSame(Configuration::DEFAULT_MAX_RETRIES, $config->getMaxRetries());
        self::assertSame([], $config->getDefaultHeaders());
        self::assertSame(0, $other->getMaxRetries());
        self::assertSame(['X-Foo' => 'bar'], $other->getDefaultHeaders());
    }
}
