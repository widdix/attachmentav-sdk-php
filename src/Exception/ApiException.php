<?php

declare(strict_types=1);

namespace AttachmentAV\Exception;

use Psr\Http\Message\ResponseInterface;

/**
 * Thrown when the API responds with a non-successful HTTP status code.
 */
class ApiException extends \RuntimeException implements ExceptionInterface
{
    private int $statusCode;

    private string $responseBody;

    private ?string $errorMessage;

    private ?ResponseInterface $response;

    public function __construct(
        string $message,
        int $statusCode,
        string $responseBody = '',
        ?string $errorMessage = null,
        ?ResponseInterface $response = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
        $this->errorMessage = $errorMessage;
        $this->response = $response;
    }

    public static function fromResponse(ResponseInterface $response): self
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $errorMessage = self::extractErrorMessage($body);
        $message = sprintf(
            'attachmentAV API request failed with status %d%s',
            $statusCode,
            $errorMessage !== null ? ': ' . $errorMessage : ($response->getReasonPhrase() !== '' ? ' ' . $response->getReasonPhrase() : '')
        );

        switch (true) {
            case $statusCode === 401 || $statusCode === 403:
                return new AuthenticationException($message, $statusCode, $body, $errorMessage, $response);
            case $statusCode === 404:
                return new NotFoundException($message, $statusCode, $body, $errorMessage, $response);
            case $statusCode === 429:
                return new RateLimitException($message, $statusCode, $body, $errorMessage, $response);
            default:
                return new self($message, $statusCode, $body, $errorMessage, $response);
        }
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    /**
     * The error message returned by the API (e.g. `Rate limit exceeded.`), if any.
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    private static function extractErrorMessage(string $body): ?string
    {
        if ($body === '') {
            return null;
        }
        $data = json_decode($body, true);
        if (is_array($data)) {
            foreach (['error', 'message', 'Message'] as $key) {
                if (isset($data[$key]) && is_string($data[$key])) {
                    return $data[$key];
                }
            }

            return null;
        }
        $body = trim($body);

        return strlen($body) <= 500 ? $body : null;
    }
}
