<?php

declare(strict_types=1);

namespace AttachmentAV\Laravel\Rules;

use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Exception\ExceptionInterface;
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
 * The rule fails closed: if the file cannot be scanned (status `no`) or the API is not reachable, validation fails.
 */
class VirusFree implements ValidationRule
{
    private bool $allowUnscannable;

    private bool $failOnError;

    /**
     * @param bool $allowUnscannable Accept files attachmentAV cannot scan (e.g. encrypted archives).
     * @param bool $failOnError      Fail validation if the API request fails (recommended).
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

        try {
            $result = app(AttachmentAVApi::class)->scanSyncFile($path);
        } catch (ExceptionInterface $e) {
            Log::warning('attachmentAV scan failed: ' . $e->getMessage(), ['exception' => $e]);
            if ($this->failOnError) {
                $fail('The :attribute could not be scanned for viruses.');
            }

            return;
        }

        if ($result->isInfected()) {
            $fail('The :attribute contains a virus or malware.');
        } elseif ($result->isUnscannable() && !$this->allowUnscannable) {
            $fail('The :attribute could not be scanned for viruses.');
        }
    }
}
