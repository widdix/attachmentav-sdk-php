<?php

declare(strict_types=1);

namespace AttachmentAV\Callback;

use AttachmentAV\Exception\ExceptionInterface;

/**
 * Thrown when the signature of a callback is invalid or the timestamp is outside the accepted window.
 */
class InvalidSignatureException extends \RuntimeException implements ExceptionInterface
{
}
