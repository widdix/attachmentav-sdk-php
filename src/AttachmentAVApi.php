<?php

declare(strict_types=1);

namespace AttachmentAV;

use AttachmentAV\Exception\ApiException;
use AttachmentAV\Exception\InvalidArgumentException;
use AttachmentAV\Exception\NotFoundException;
use AttachmentAV\Exception\RateLimitException;
use AttachmentAV\Exception\TransportException;
use AttachmentAV\Exception\UnexpectedResponseException;
use AttachmentAV\Model\CallbackFailureList;
use AttachmentAV\Model\ScanResult;
use AttachmentAV\Model\Usage;
use AttachmentAV\Model\Whoami;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Client for the attachmentAV Virus and Malware Scan API (SaaS and Self-hosted on AWS).
 *
 * The client relies on PSR-18 (HTTP client) and PSR-17 (HTTP factories). If no implementation is passed, an installed
 * implementation (e.g. Guzzle or Symfony HttpClient) is discovered automatically.
 *
 * @see https://attachmentav.com/help/virus-malware-scan-api/developer/definition.html
 * @see https://attachmentav.com/help/virus-malware-scan-api-aws/developer/definition.html
 */
class AttachmentAVApi
{
    public const VERSION = '1.0.0';

    private Configuration $configuration;

    private ClientInterface $httpClient;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    /** @var callable(int): void */
    private $sleeper;

    public function __construct(
        Configuration $configuration,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ) {
        $this->configuration = $configuration;
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->sleeper = static function (int $milliseconds): void {
            usleep($milliseconds * 1000);
        };
    }

    /**
     * Shortcut for `new AttachmentAVApi(Configuration::saas($apiKey, $region))`.
     */
    public static function saas(string $apiKey, string $region = Region::EU, ?ClientInterface $httpClient = null): self
    {
        return new self(Configuration::saas($apiKey, $region), $httpClient);
    }

    /**
     * Shortcut for `new AttachmentAVApi(Configuration::selfHosted($apiKey, $baseUrl))`.
     */
    public static function selfHosted(string $apiKey, string $baseUrl, ?ClientInterface $httpClient = null): self
    {
        return new self(Configuration::selfHosted($apiKey, $baseUrl), $httpClient);
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    /**
     * Replace the function used to wait between retries (receives milliseconds). Intended for tests.
     *
     * @param callable(int): void $sleeper
     */
    public function setSleeper(callable $sleeper): void
    {
        $this->sleeper = $sleeper;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Sync scans
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Upload a file, scan the file, and return the scan result.
     *
     * SaaS: maximum file size is 10 MB, the request timeout is 60 seconds.
     *
     * @param string|resource|StreamInterface $content The file content: a string, a stream resource (e.g. `fopen($path, 'rb')`),
     *                                                 or a PSR-7 stream. Use streams for large files to keep memory usage low.
     */
    public function scanSyncBinary($content): ScanResult
    {
        $request = $this->createRequest('POST', '/scan/sync/binary')
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withBody($this->createStream($content));

        return ScanResult::fromArray($this->decodeJson($this->send($request)));
    }

    /**
     * Read a local file (e.g. an uploaded file), scan the file, and return the scan result.
     *
     * Works with the path of Laravel's `UploadedFile::getRealPath()` or Symfony's `UploadedFile::getPathname()`.
     */
    public function scanSyncFile(string $path): ScanResult
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException(sprintf('File "%s" does not exist or is not readable.', $path));
        }

        return $this->scanSyncBinary($this->streamFactory->createStreamFromFile($path, 'rb'));
    }

    /**
     * Download a file from a remote location (HTTP/HTTPS), scan the file, and return the scan result.
     *
     * SaaS: maximum file size is 200 MB, the request timeout is 60 seconds.
     *
     * @param array<string, string> $downloadHeaders Headers to send when downloading the file.
     */
    public function scanSyncDownload(string $downloadUrl, array $downloadHeaders = []): ScanResult
    {
        $body = ['download_url' => $this->assertUrl($downloadUrl, 'download URL')];
        if ($downloadHeaders !== []) {
            $body['download_headers'] = $this->assertHeaders($downloadHeaders);
        }

        return ScanResult::fromArray($this->decodeJson($this->sendJson('POST', '/scan/sync/download', $body)));
    }

    /**
     * Download a file from S3, scan the file, and return the scan result.
     *
     * A bucket policy is required to grant attachmentAV access to the S3 objects (SaaS) or the IAM role of
     * the installation needs access (Self-hosted on AWS). SaaS: maximum file size is 200 MB.
     *
     * @param string|null $version The object version, if versioning is turned on.
     */
    public function scanSyncS3(string $bucket, string $key, ?string $version = null): ScanResult
    {
        $body = $this->s3Body($bucket, $key, $version);

        return ScanResult::fromArray($this->decodeJson($this->sendJson('POST', '/scan/sync/s3', $body)));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Async scans (SaaS)
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Download a file from a remote location (HTTP/HTTPS), scan the file, and send the scan result to the callback URL
     * or store the scan result for polling via {@see getAsyncResult()}.
     *
     * Either `$callbackUrl` or `$traceId` is required. Maximum file size is 5 GB.
     *
     * @param array<string, string> $downloadHeaders Headers to send when downloading the file.
     * @param string|null           $callbackUrl     URL to receive the scan result via HTTPS POST.
     * @param array<string, string> $callbackHeaders Headers to send when invoking the callback.
     * @param string|null           $traceId         ID to trace the scan job; required for polling.
     * @param string|null           $customData      Custom data that attachmentAV passes through (max. 16 KB).
     */
    public function scanAsyncDownload(
        string $downloadUrl,
        array $downloadHeaders = [],
        ?string $callbackUrl = null,
        array $callbackHeaders = [],
        ?string $traceId = null,
        ?string $customData = null
    ): void {
        $body = ['download_url' => $this->assertUrl($downloadUrl, 'download URL')];
        if ($downloadHeaders !== []) {
            $body['download_headers'] = $this->assertHeaders($downloadHeaders);
        }
        $body += $this->asyncBody($callbackUrl, $callbackHeaders, $traceId, $customData);

        $this->sendJson('POST', '/scan/async/download', $body);
    }

    /**
     * Download a file from S3, scan the file, and send the scan result to the callback URL or store the scan result
     * for polling via {@see getAsyncResult()}.
     *
     * Either `$callbackUrl` or `$traceId` is required. Maximum file size is 5 GB.
     *
     * @param string|null           $version         The object version, if versioning is turned on.
     * @param string|null           $callbackUrl     URL to receive the scan result via HTTPS POST.
     * @param array<string, string> $callbackHeaders Headers to send when invoking the callback.
     * @param string|null           $traceId         ID to trace the scan job; required for polling.
     * @param string|null           $customData      Custom data that attachmentAV passes through (max. 16 KB).
     */
    public function scanAsyncS3(
        string $bucket,
        string $key,
        ?string $version = null,
        ?string $callbackUrl = null,
        array $callbackHeaders = [],
        ?string $traceId = null,
        ?string $customData = null
    ): void {
        $body = $this->s3Body($bucket, $key, $version);
        $body += $this->asyncBody($callbackUrl, $callbackHeaders, $traceId, $customData);

        $this->sendJson('POST', '/scan/async/s3', $body);
    }

    /**
     * Retrieve the scan result of an async scan job submitted with a `trace_id` and without `callback_url`.
     *
     * Returns null if no scan result is available (yet): the scan is not finished, the result was deleted after
     * 24 hours, or the trace ID is wrong. Each request counts against your monthly quota.
     */
    public function getAsyncResult(string $traceId): ?ScanResult
    {
        if ($traceId === '') {
            throw new InvalidArgumentException('The trace ID must not be empty.');
        }
        try {
            $response = $this->send($this->createRequest('GET', '/scan/async/result?' . http_build_query(['trace_id' => $traceId])));
        } catch (NotFoundException $e) {
            return null;
        }

        return ScanResult::fromArray($this->decodeJson($response));
    }

    /**
     * List callback failures that prevented attachmentAV from invoking the callback URL, youngest first.
     *
     * Use {@see CallbackFailureList::getNextCursor()} as `$cursor` to fetch the next page.
     */
    public function getCallbackFailures(string $callbackUrl, ?string $cursor = null): CallbackFailureList
    {
        $query = ['callback_url' => $this->assertUrl($callbackUrl, 'callback URL')];
        if ($cursor !== null && $cursor !== '') {
            $query['cursor'] = $cursor;
        }
        $response = $this->send($this->createRequest('GET', '/callback/failures?' . http_build_query($query)));

        return CallbackFailureList::fromArray($this->decodeJson($response));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Account (SaaS)
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Get remaining credits and quota. The request counts against your monthly quota.
     */
    public function getUsage(): Usage
    {
        return Usage::fromArray($this->decodeJson($this->send($this->createRequest('GET', '/usage'))));
    }

    /**
     * Get information about yourself, e.g. the tenant ID required to verify callbacks.
     * The request counts against your monthly quota.
     */
    public function whoami(): Whoami
    {
        return Whoami::fromArray($this->decodeJson($this->send($this->createRequest('GET', '/whoami'))));
    }

    /**
     * Check if the API key is valid. Throws {@see \AttachmentAV\Exception\AuthenticationException} otherwise.
     * The request counts against your monthly quota.
     */
    public function test(): void
    {
        $this->send($this->createRequest('GET', '/test'));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------------------------------------------------

    private function createRequest(string $method, string $path): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $this->configuration->getBaseUrl() . $path)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', 'attachmentav-sdk-php/' . self::VERSION . ' PHP/' . PHP_VERSION);
        foreach ($this->configuration->getDefaultHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        foreach ($this->configuration->getAuthenticationHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function sendJson(string $method, string $path, array $body): ResponseInterface
    {
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $request = $this->createRequest($method, $path)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($json));

        return $this->send($request);
    }

    private function send(RequestInterface $request): ResponseInterface
    {
        $attempt = 0;
        while (true) {
            try {
                $response = $this->httpClient->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                throw new TransportException('Failed to send request to the attachmentAV API: ' . $e->getMessage(), 0, $e);
            }

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                return $response;
            }

            $exception = ApiException::fromResponse($response);
            if (!$this->shouldRetry($exception, $request, $attempt)) {
                throw $exception;
            }
            ($this->sleeper)($this->retryDelay($attempt, $response));
            $attempt++;
            $body = $request->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
        }
    }

    private function shouldRetry(ApiException $exception, RequestInterface $request, int $attempt): bool
    {
        if (!$exception instanceof RateLimitException || !$exception->isThrottled()) {
            return false;
        }
        if ($attempt >= $this->configuration->getMaxRetries()) {
            return false;
        }
        // A consumed, non-seekable stream cannot be sent again.
        $body = $request->getBody();

        return $body->isSeekable() || $body->getSize() === 0;
    }

    private function retryDelay(int $attempt, ResponseInterface $response): int
    {
        $retryAfter = $response->getHeaderLine('Retry-After');
        if ($retryAfter !== '' && ctype_digit($retryAfter)) {
            return (int) $retryAfter * 1000;
        }
        $base = $this->configuration->getRetryDelayMs() * (2 ** $attempt);

        return $base + random_int(0, max(0, intdiv($base, 2)));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new UnexpectedResponseException('The attachmentAV API returned invalid JSON: ' . $e->getMessage(), 0, $e);
        }
        if (!is_array($data)) {
            throw new UnexpectedResponseException('The attachmentAV API returned an unexpected response.');
        }

        return $data;
    }

    /**
     * @param mixed $content
     */
    private function createStream($content): StreamInterface
    {
        if ($content instanceof StreamInterface) {
            if ($content->isSeekable()) {
                $content->rewind();
            }

            return $content;
        }
        if (is_resource($content)) {
            return $this->streamFactory->createStreamFromResource($content);
        }
        if (is_string($content)) {
            return $this->streamFactory->createStream($content);
        }

        throw new InvalidArgumentException(sprintf(
            'The content must be a string, a stream resource, or an instance of %s, %s given.',
            StreamInterface::class,
            get_debug_type($content)
        ));
    }

    /**
     * @return array<string, string>
     */
    private function s3Body(string $bucket, string $key, ?string $version): array
    {
        if ($bucket === '' || $key === '') {
            throw new InvalidArgumentException('The bucket and key must not be empty.');
        }
        $body = ['bucket' => $bucket, 'key' => $key];
        if ($version !== null && $version !== '') {
            $body['version'] = $version;
        }

        return $body;
    }

    /**
     * @param array<string, string> $callbackHeaders
     *
     * @return array<string, mixed>
     */
    private function asyncBody(?string $callbackUrl, array $callbackHeaders, ?string $traceId, ?string $customData): array
    {
        $callbackUrl = $callbackUrl === '' ? null : $callbackUrl;
        $traceId = $traceId === '' ? null : $traceId;
        if ($callbackUrl === null && $traceId === null) {
            throw new InvalidArgumentException('Either a callback URL or a trace ID (for polling) is required for async scans.');
        }
        if ($callbackUrl === null && $callbackHeaders !== []) {
            throw new InvalidArgumentException('Callback headers require a callback URL.');
        }
        if ($customData !== null && strlen($customData) > 16 * 1024) {
            throw new InvalidArgumentException('The custom data must not exceed 16 KB.');
        }

        $body = [];
        if ($callbackUrl !== null) {
            $body['callback_url'] = $this->assertUrl($callbackUrl, 'callback URL');
        }
        if ($callbackHeaders !== []) {
            $body['callback_headers'] = $this->assertHeaders($callbackHeaders);
        }
        if ($traceId !== null) {
            $body['trace_id'] = $traceId;
        }
        if ($customData !== null) {
            $body['custom_data'] = $customData;
        }

        return $body;
    }

    private function assertUrl(string $url, string $name): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (($scheme !== 'http' && $scheme !== 'https') || parse_url($url, PHP_URL_HOST) === null) {
            throw new InvalidArgumentException(sprintf('The %s must be an absolute HTTP(S) URL, "%s" given.', $name, $url));
        }

        return $url;
    }

    /**
     * @param array<mixed> $headers
     *
     * @return array<string, string>
     */
    private function assertHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                throw new InvalidArgumentException('Headers must be an associative array of header names and string values.');
            }
            $result[$name] = (string) $value;
        }

        return $result;
    }
}
