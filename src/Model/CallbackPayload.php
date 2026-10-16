<?php

declare(strict_types=1);

namespace AttachmentAV\Model;

use AttachmentAV\Exception\UnexpectedResponseException;

/**
 * The scan result that attachmentAV sends to the `callback_url` of an asynchronous scan job.
 *
 * Verify the signature with {@see \AttachmentAV\Callback\CallbackVerifier} before trusting the payload.
 *
 * @see https://attachmentav.com/help/virus-malware-scan-api/developer/definition.html#callback
 */
class CallbackPayload extends ScanResult
{
    private ?float $downloadTime;

    private ?float $scanTime;

    private ?string $downloadUrl;

    private ?string $traceId;

    private ?string $customData;

    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        string $status,
        ?string $finding = null,
        ?int $size = null,
        ?string $realFileType = null,
        ?float $downloadTime = null,
        ?float $scanTime = null,
        ?string $downloadUrl = null,
        ?string $traceId = null,
        ?string $customData = null,
        array $raw = []
    ) {
        parent::__construct($status, $finding, $size, $realFileType, $raw);
        $this->downloadTime = $downloadTime;
        $this->scanTime = $scanTime;
        $this->downloadUrl = $downloadUrl;
        $this->traceId = $traceId;
        $this->customData = $customData;
    }

    /**
     * Parse the raw request body of a callback.
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new UnexpectedResponseException('Callback body is not a valid JSON object.');
        }

        return self::fromArray($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['status']) || !is_string($data['status'])) {
            throw new UnexpectedResponseException('Callback payload is missing the "status" property.');
        }

        return new self(
            $data['status'],
            self::optionalString($data, 'finding'),
            self::optionalInt($data, 'size'),
            self::optionalString($data, 'realfiletype'),
            self::optionalFloat($data, 'download_time'),
            self::optionalFloat($data, 'scan_time'),
            self::optionalString($data, 'download_url'),
            self::optionalString($data, 'trace_id'),
            self::optionalString($data, 'custom_data'),
            $data
        );
    }

    /**
     * Time to download the file in seconds.
     */
    public function getDownloadTime(): ?float
    {
        return $this->downloadTime;
    }

    /**
     * Time to scan the file in seconds.
     */
    public function getScanTime(): ?float
    {
        return $this->scanTime;
    }

    /**
     * The downloaded URL.
     */
    public function getDownloadUrl(): ?string
    {
        return $this->downloadUrl;
    }

    /**
     * The trace ID passed when submitting the scan job.
     */
    public function getTraceId(): ?string
    {
        return $this->traceId;
    }

    /**
     * The custom data passed when submitting the scan job.
     */
    public function getCustomData(): ?string
    {
        return $this->customData;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(parent::toArray() + [
            'download_time' => $this->downloadTime,
            'scan_time' => $this->scanTime,
            'download_url' => $this->downloadUrl,
            'trace_id' => $this->traceId,
            'custom_data' => $this->customData,
        ], static function ($value): bool {
            return $value !== null;
        });
    }
}
