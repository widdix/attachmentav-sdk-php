<?php

declare(strict_types=1);

namespace AttachmentAV\Exception;

/**
 * Thrown when the SDK is used with invalid arguments, before a request is sent.
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
}
