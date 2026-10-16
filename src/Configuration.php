<?php

declare(strict_types=1);

namespace AttachmentAV;

use AttachmentAV\Exception\InvalidArgumentException;

/**
 * Configuration of the attachmentAV API client.
 *
 * Use one of the named constructors:
 *
 * - {@see Configuration::saas()} for the attachmentAV API (SaaS), authenticated via the `x-api-key` header.
 * - {@see Configuration::selfHosted()} for the attachmentAV API (Self-hosted on AWS), authenticated via a bearer token.
 */
final class Configuration
{
    public const DEPLOYMENT_SAAS = 'saas';
    public const DEPLOYMENT_SELF_HOSTED = 'self-hosted';

    public const DEFAULT_MAX_RETRIES = 3;
    public const DEFAULT_RETRY_DELAY_MS = 500;

    private string $deployment;

    private string $apiKey;

    private string $baseUrl;

    private ?string $region;

    private int $maxRetries = self::DEFAULT_MAX_RETRIES;

    private int $retryDelayMs = self::DEFAULT_RETRY_DELAY_MS;

    /** @var array<string, string> */
    private array $defaultHeaders = [];

    private function __construct(string $deployment, string $apiKey, string $baseUrl, ?string $region)
    {
        if (trim($apiKey) === '') {
            throw new InvalidArgumentException('The API key must not be empty.');
        }
        $this->deployment = $deployment;
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->region = $region;
    }

    /**
     * Configuration for the attachmentAV API (SaaS).
     *
     * @param string $apiKey The API key of your subscription.
     * @param string $region The region of your subscription, see {@see Region}. Defaults to Europe.
     */
    public static function saas(string $apiKey, string $region = Region::EU): self
    {
        $region = Region::normalize($region);

        return new self(self::DEPLOYMENT_SAAS, $apiKey, Region::endpoint($region), $region);
    }

    /**
     * Configuration for the attachmentAV API (Self-hosted on AWS).
     *
     * @param string $apiKey  One of the API keys specified via the `ApiKeys` parameter of the CloudFormation stack.
     * @param string $baseUrl The URL of your installation, e.g. `https://attachmentav.yourcompany.com/api/v1`.
     *                        If the URL contains no path (e.g. `https://attachmentav.yourcompany.com`), `/api/v1` is appended.
     */
    public static function selfHosted(string $apiKey, string $baseUrl): self
    {
        return new self(self::DEPLOYMENT_SELF_HOSTED, $apiKey, self::normalizeSelfHostedUrl($baseUrl), null);
    }

    /**
     * Create a configuration from an array, e.g. the Laravel config file or Symfony parameters.
     *
     * Supported keys: `deployment` (`saas` or `self-hosted`), `api_key`, `region` (SaaS only),
     * `base_url` (required for self-hosted, optional override for SaaS), `max_retries`, `retry_delay_ms`.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $deployment = (string) ($config['deployment'] ?? self::DEPLOYMENT_SAAS);
        $apiKey = (string) ($config['api_key'] ?? '');
        $baseUrl = isset($config['base_url']) && $config['base_url'] !== '' ? (string) $config['base_url'] : null;

        switch ($deployment) {
            case self::DEPLOYMENT_SAAS:
                $configuration = self::saas($apiKey, (string) ($config['region'] ?? Region::EU));
                if ($baseUrl !== null) {
                    $configuration->baseUrl = rtrim($baseUrl, '/');
                }
                break;
            case self::DEPLOYMENT_SELF_HOSTED:
                if ($baseUrl === null) {
                    throw new InvalidArgumentException('The base URL is required for the self-hosted deployment.');
                }
                $configuration = self::selfHosted($apiKey, $baseUrl);
                break;
            default:
                throw new InvalidArgumentException(sprintf(
                    'Unknown deployment "%s". Supported deployments: %s, %s.',
                    $deployment,
                    self::DEPLOYMENT_SAAS,
                    self::DEPLOYMENT_SELF_HOSTED
                ));
        }

        if (isset($config['max_retries'])) {
            $configuration = $configuration->withMaxRetries((int) $config['max_retries']);
        }
        if (isset($config['retry_delay_ms'])) {
            $configuration = $configuration->withRetryDelayMs((int) $config['retry_delay_ms']);
        }

        return $configuration;
    }

    /**
     * Number of retries when the API responds with `429 Too Many Requests` because of throttling.
     * Requests exceeding the monthly quota are never retried. Set to 0 to disable retries.
     */
    public function withMaxRetries(int $maxRetries): self
    {
        if ($maxRetries < 0) {
            throw new InvalidArgumentException('The maximum number of retries must not be negative.');
        }
        $clone = clone $this;
        $clone->maxRetries = $maxRetries;

        return $clone;
    }

    /**
     * Base delay of the exponential backoff in milliseconds (delay = base * 2^attempt, plus jitter).
     */
    public function withRetryDelayMs(int $retryDelayMs): self
    {
        if ($retryDelayMs < 0) {
            throw new InvalidArgumentException('The retry delay must not be negative.');
        }
        $clone = clone $this;
        $clone->retryDelayMs = $retryDelayMs;

        return $clone;
    }

    /**
     * Additional headers sent with every request.
     *
     * @param array<string, string> $headers
     */
    public function withDefaultHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->defaultHeaders = $headers;

        return $clone;
    }

    public function getDeployment(): string
    {
        return $this->deployment;
    }

    public function isSaas(): bool
    {
        return $this->deployment === self::DEPLOYMENT_SAAS;
    }

    public function isSelfHosted(): bool
    {
        return $this->deployment === self::DEPLOYMENT_SELF_HOSTED;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * The region of the SaaS subscription, null for self-hosted deployments.
     */
    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getRetryDelayMs(): int
    {
        return $this->retryDelayMs;
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    /**
     * The headers used to authenticate requests: `x-api-key` for SaaS, `Authorization: Bearer` for self-hosted.
     *
     * @return array<string, string>
     */
    public function getAuthenticationHeaders(): array
    {
        if ($this->isSelfHosted()) {
            return ['Authorization' => 'Bearer ' . $this->apiKey];
        }

        return ['x-api-key' => $this->apiKey];
    }

    private static function normalizeSelfHostedUrl(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);
        if (strpos($baseUrl, '://') === false) {
            $baseUrl = 'https://' . $baseUrl;
        }
        $parts = parse_url($baseUrl);
        if ($parts === false || !isset($parts['host'])) {
            throw new InvalidArgumentException(sprintf('Invalid base URL "%s".', $baseUrl));
        }
        $baseUrl = rtrim($baseUrl, '/');
        if (!isset($parts['path']) || trim($parts['path'], '/') === '') {
            $baseUrl .= '/api/v1';
        }

        return $baseUrl;
    }
}
