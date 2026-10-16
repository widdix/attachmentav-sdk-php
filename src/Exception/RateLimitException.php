<?php

declare(strict_types=1);

namespace AttachmentAV\Exception;

/**
 * Thrown when the API responds with `429 Too Many Requests`.
 *
 * Either the request was throttled ({@see isThrottled()}) or the monthly quota is exceeded ({@see isQuotaExceeded()}).
 *
 * @see https://attachmentav.com/help/virus-malware-scan-api/developer/throttling-and-quotas.html
 */
class RateLimitException extends ApiException
{
    public function isQuotaExceeded(): bool
    {
        return stripos((string) $this->getErrorMessage(), 'quota') !== false;
    }

    public function isThrottled(): bool
    {
        return !$this->isQuotaExceeded();
    }
}
