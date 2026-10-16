<?php

declare(strict_types=1);

namespace AttachmentAV\Tests\Laravel;

use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Configuration;
use AttachmentAV\Laravel\AttachmentAVServiceProvider;
use AttachmentAV\Laravel\Facades\AttachmentAV;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client as MockClient;
use Orchestra\Testbench\TestCase;

abstract class LaravelTestCase extends TestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [AttachmentAVServiceProvider::class];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['AttachmentAV' => AttachmentAV::class];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('attachmentav.api_key', 'test-key');
    }

    /**
     * Replace the client in the container with one that answers with the given responses.
     */
    protected function mockApi(Response ...$responses): MockClient
    {
        $http = new MockClient();
        foreach ($responses as $response) {
            $http->addResponse($response);
        }
        $factory = new HttpFactory();
        $api = new AttachmentAVApi(Configuration::saas('test-key')->withMaxRetries(0), $http, $factory, $factory);
        $this->app->instance(AttachmentAVApi::class, $api);

        return $http;
    }

    /**
     * @param array<string, mixed> $json
     */
    protected static function jsonResponse(int $status, array $json): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($json));
    }
}
