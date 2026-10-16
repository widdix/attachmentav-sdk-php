<?php

declare(strict_types=1);

namespace AttachmentAV\Model;

use AttachmentAV\Exception\UnexpectedResponseException;

/**
 * The result of a virus and malware scan.
 */
class ScanResult implements \JsonSerializable
{
    protected string $status;

    protected ?string $finding;

    protected ?int $size;

    protected ?string $realFileType;

    /** @var array<string, mixed> */
    protected array $raw;

    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(string $status, ?string $finding = null, ?int $size = null, ?string $realFileType = null, array $raw = [])
    {
        $this->status = $status;
        $this->finding = $finding;
        $this->size = $size;
        $this->realFileType = $realFileType;
        $this->raw = $raw;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['status']) || !is_string($data['status'])) {
            throw new UnexpectedResponseException('Scan result is missing the "status" property.');
        }

        return new self(
            $data['status'],
            self::optionalString($data, 'finding'),
            self::optionalInt($data, 'size'),
            self::optionalString($data, 'realfiletype'),
            $data
        );
    }

    /**
     * The scan result: `clean`, `infected`, or `no` (file could not be scanned), see {@see ScanStatus}.
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * For infected files, the type of virus/malware that was detected.
     */
    public function getFinding(): ?string
    {
        return $this->finding;
    }

    /**
     * The file size in bytes.
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * The Real File Type detected by the Sophos engine (e.g. `Adobe Portable Document Format (PDF)`).
     *
     * @see https://attachmentav.com/help/virus-malware-scan-api/developer/realfiletype.html
     */
    public function getRealFileType(): ?string
    {
        return $this->realFileType;
    }

    public function isClean(): bool
    {
        return $this->status === ScanStatus::CLEAN;
    }

    public function isInfected(): bool
    {
        return $this->status === ScanStatus::INFECTED;
    }

    /**
     * True if the file could not be scanned (status `no`), e.g. because it is encrypted.
     */
    public function isUnscannable(): bool
    {
        return $this->status === ScanStatus::NO;
    }

    /**
     * The decoded JSON returned by the API, including properties not (yet) modeled by the SDK.
     *
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status,
            'finding' => $this->finding,
            'size' => $this->size,
            'realfiletype' => $this->realFileType,
        ], static function ($value): bool {
            return $value !== null;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function optionalString(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_scalar($data[$key]) ? (string) $data[$key] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function optionalInt(array $data, string $key): ?int
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function optionalFloat(array $data, string $key): ?float
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (float) $data[$key] : null;
    }
}
