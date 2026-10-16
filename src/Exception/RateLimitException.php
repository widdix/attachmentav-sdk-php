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
    /**
     * True for `{"error": "Quota exceeded."}` and Amazon API Gateway's usage plan response `{"message": "Limit Exceeded"}`.
     * A 429 without a recognizable message (e.g. from a proxy) is treated as throttling.
     */
    public function isQuotaExceeded(): bool
    {
        $message = strtolower(trim((string) $this->getErrorMessage(), " \t\n\r."));

        return strpos($message, 'quota') !== false || $message === 'limit exceeded';
    }

    public function isThrottled(): bool
    {
        return !$this->isQuotaExceeded();
    }
}
