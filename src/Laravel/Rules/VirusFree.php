<?php

declare(strict_types=1);

namespace AttachmentAV\Laravel\Rules;

use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Exception\ApiException;
use AttachmentAV\Exception\ExceptionInterface;
use AttachmentAV\Exception\RateLimitException;
use AttachmentAV\Exception\TransportException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Laravel validation rule (Laravel 10+) that scans an uploaded file with attachmentAV.
 *
 *     $request->validate([
 *         'attachment' => ['required', 'file', 'max:10240', new VirusFree()],
 *     ]);
 *
 * The rule fails closed: if the file cannot be scanned (status `no`) or the API request fails, validation fails.
 * With `failOnError: false`, only transient errors (network errors, throttling, 5xx) let the file pass. Errors that
 * would let every file pass unscanned (invalid configuration or API key, exceeded quota) always fail validation.
 */
class VirusFree implements ValidationRule
{
    private bool $allowUnscannable;

    private bool $failOnError;

    /**
     * @param bool $allowUnscannable Accept files attachmentAV cannot scan (e.g. encrypted archives).
     * @param bool $failOnError      Fail validation if the API is temporarily unavailable (recommended).
     */
    public function __construct(bool $allowUnscannable = false, bool $failOnError = true)
    {
        $this->allowUnscannable = $allowUnscannable;
        $this->failOnError = $failOnError;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof File) {
            $fail('The :attribute must be a file.');

            return;
        }
        $path = $value->getRealPath();
        if ($path === false) {
            $fail('The :attribute could not be scanned for viruses.');

            return;
        }

        // Resolved outside the try block: a misconfiguration (e.g. missing API key) must not be mistaken for an outage.
        $api = app(AttachmentAVApi::class);

        try {
            $result = $api->scanSyncFile($path);
        } catch (ExceptionInterface $e) {
            if ($this->failOnError || !self::isTransient($e)) {
                Log::error('attachmentAV scan failed: ' . $e->getMessage(), ['exception' => $e]);
                $fail('The :attribute could not be scanned for viruses.');
            } else {
                Log::warning('attachmentAV scan failed, file accepted unscanned: ' . $e->getMessage(), ['exception' => $e]);
            }

            return;
        }

        if ($result->isInfected()) {
            $fail('The :attribute contains a virus or malware.');
        } elseif ($result->isUnscannable() && !$this->allowUnscannable) {
            $fail('The :attribute could not be scanned for viruses.');
        }
    }

    private static function isTransient(ExceptionInterface $e): bool
    {
        if ($e instanceof TransportException) {
            return true;
        }
        if ($e instanceof RateLimitException) {
            return $e->isThrottled();
        }

        return $e instanceof ApiException && $e->getStatusCode() >= 500;
    }
}
