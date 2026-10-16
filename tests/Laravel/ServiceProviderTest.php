<?php

declare(strict_types=1);

namespace AttachmentAV\Tests\Laravel;

use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Callback\CallbackVerifier;
use AttachmentAV\Exception\InvalidArgumentException;
use AttachmentAV\Laravel\Facades\AttachmentAV;

final class ServiceProviderTest extends LaravelTestCase
{
    public function testResolvesSaasClientFromConfig(): void
    {
        config(['attachmentav.region' => 'us']);

        $api = $this->app->make(AttachmentAVApi::class);

        self::assertSame($api, $this->app->make(AttachmentAVApi::class));
        self::assertSame($api, $this->app->make('attachmentav'));
        self::assertSame($api, AttachmentAV::getFacadeRoot());
        self::assertTrue($api->getConfiguration()->isSaas());
        self::assertSame('https://us.developer.attachmentav.com/v1', $api->getConfiguration()->getBaseUrl());
    }

    public function testResolvesSelfHostedClientFromConfig(): void
    {
        config([
            'attachmentav.deployment' => 'self-hosted',
            'attachmentav.base_url' => 'https://attachmentav.example.com',
        ]);

        $configuration = $this->app->make(AttachmentAVApi::class)->getConfiguration();

        self::assertTrue($configuration->isSelfHosted());
        self::assertSame('https://attachmentav.example.com/api/v1', $configuration->getBaseUrl());
        self::assertSame(['Authorization' => 'Bearer test-key'], $configuration->getAuthenticationHeaders());
    }

    public function testBlankRegionDefaultsToEurope(): void
    {
        config(['attachmentav.region' => '']);

        self::assertSame('https://eu.developer.attachmentav.com/v1', $this->app->make(AttachmentAVApi::class)->getConfiguration()->getBaseUrl());
    }

    public function testHttpClientUsesConfiguredTimeout(): void
    {
        config(['attachmentav.timeout' => 42]);

        $api = $this->app->make(AttachmentAVApi::class);
        $property = new \ReflectionProperty($api, 'httpClient');
        $property->setAccessible(true);
        $client = $property->getValue($api);

        self::assertInstanceOf(\GuzzleHttp\Client::class, $client);
        self::assertSame(42.0, $client->getConfig('timeout'));
    }

    public function testMissingApiKeyFailsLoudly(): void
    {
        config(['attachmentav.api_key' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(AttachmentAVApi::class);
    }

    public function testCallbackVerifierRequiresTenantId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(CallbackVerifier::class);
    }

    public function testCallbackVerifier(): void
    {
        config(['attachmentav.tenant_id' => 'tenant-1']);

        self::assertInstanceOf(CallbackVerifier::class, $this->app->make(CallbackVerifier::class));
    }

    public function testConfigIsPublishable(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'attachmentav-config'])->assertExitCode(0);

        self::assertFileExists(config_path('attachmentav.php'));
        unlink(config_path('attachmentav.php'));
    }
}
