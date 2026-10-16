<?php

declare(strict_types=1);

namespace AttachmentAV\Exception;

/**
 * Thrown when the API responds with `401 Unauthorized` or `403 Forbidden`, e.g. because of an invalid API key
 * or an endpoint that does not match the region of the subscription.
 */
class AuthenticationException extends ApiException
{
}
