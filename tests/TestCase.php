<?php

declare(strict_types=1);

namespace AttachmentAV\Tests;

use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Configuration;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client as MockClient;
use Psr\Http\Message\RequestInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected MockClient $http;

    /** @var list<int> */
    protected array $sleeps = [];

    protected function api(?Configuration $configuration = null): AttachmentAVApi
    {
        $this->http = new MockClient();
        $factory = new HttpFactory();
        $api = new AttachmentAVApi($configuration ?? Configuration::saas('test-key'), $this->http, $factory, $factory);
        $api->setSleeper(function (int $ms): void {
            $this->sleeps[] = $ms;
        });

        return $api;
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string>     $headers
     */
    protected function respond(int $status, ?array $json = null, array $headers = []): void
    {
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        $this->http->addResponse(new Response($status, $headers, $json !== null ? json_encode($json, JSON_THROW_ON_ERROR) : ''));
    }

    protected function lastRequest(): RequestInterface
    {
        $request = $this->http->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    protected function lastJsonBody(): array
    {
        $body = (string) $this->lastRequest()->getBody();

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}
