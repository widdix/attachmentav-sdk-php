<?php

declare(strict_types=1);

namespace AttachmentAV\Exception;

/**
 * Thrown when the API returns a successful status code but the response body cannot be parsed.
 */
class UnexpectedResponseException extends \RuntimeException implements ExceptionInterface
{
}
