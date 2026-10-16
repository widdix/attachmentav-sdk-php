<?php

declare(strict_types=1);

namespace AttachmentAV\Exception;

/**
 * Thrown when the HTTP request could not be sent or no response was received (e.g. DNS failure, timeout).
 */
class TransportException extends \RuntimeException implements ExceptionInterface
{
}
