<?php

declare(strict_types=1);

namespace AttachmentAV\Model;

/**
 * A failed attempt of attachmentAV to deliver a scan result to the callback URL.
 */
final class CallbackFailure implements \JsonSerializable
{
    private string $id;

    private ?\DateTimeImmutable $time;

    private ?string $downloadUrl;

    private ?string $callbackUrl;

    private ?string $errorJson;

    public function __construct(string $id, ?\DateTimeImmutable $time, ?string $downloadUrl, ?string $callbackUrl, ?string $errorJson)
    {
        $this->id = $id;
        $this->time = $time;
        $this->downloadUrl = $downloadUrl;
        $this->callbackUrl = $callbackUrl;
        $this->errorJson = $errorJson;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $time = null;
        if (isset($data['time']) && is_string($data['time'])) {
            try {
                $time = new \DateTimeImmutable($data['time']);
            } catch (\Exception $e) {
                $time = null;
            }
        }
        $errorJson = $data['error_json'] ?? null;
        if (is_array($errorJson)) {
            $errorJson = json_encode($errorJson);
        }

        return new self(
            (string) ($data['id'] ?? ''),
            $time,
            isset($data['download_url']) ? (string) $data['download_url'] : null,
            isset($data['callback_url']) ? (string) $data['callback_url'] : null,
            is_string($errorJson) ? $errorJson : null
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTime(): ?\DateTimeImmutable
    {
        return $this->time;
    }

    public function getDownloadUrl(): ?string
    {
        return $this->downloadUrl;
    }

    public function getCallbackUrl(): ?string
    {
        return $this->callbackUrl;
    }

    /**
     * Metadata about the error as JSON string.
     */
    public function getErrorJson(): ?string
    {
        return $this->errorJson;
    }

    /**
     * Metadata about the error, decoded.
     *
     * @return array<string, mixed>|null
     */
    public function getError(): ?array
    {
        if ($this->errorJson === null) {
            return null;
        }
        $decoded = json_decode($this->errorJson, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'time' => $this->time !== null ? $this->time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z') : null,
            'download_url' => $this->downloadUrl,
            'callback_url' => $this->callbackUrl,
            'error_json' => $this->errorJson,
        ];
    }
}
