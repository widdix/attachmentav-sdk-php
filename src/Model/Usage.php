<?php

declare(strict_types=1);

namespace AttachmentAV\Model;

use AttachmentAV\Exception\UnexpectedResponseException;

/**
 * Remaining credits and quota of the SaaS subscription.
 */
final class Usage implements \JsonSerializable
{
    private int $credits;

    private int $quotaLimit;

    private string $quotaPeriod;

    public function __construct(int $credits, int $quotaLimit, string $quotaPeriod)
    {
        $this->credits = $credits;
        $this->quotaLimit = $quotaLimit;
        $this->quotaPeriod = $quotaPeriod;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['credits'], $data['quota']['limit'], $data['quota']['period'])
            || !is_numeric($data['credits'])
            || !is_numeric($data['quota']['limit'])
        ) {
            throw new UnexpectedResponseException('Usage response is missing the "credits" or "quota" property.');
        }

        return new self((int) $data['credits'], (int) $data['quota']['limit'], (string) $data['quota']['period']);
    }

    /**
     * The remaining number of scans/requests for the current period.
     */
    public function getCredits(): int
    {
        return $this->credits;
    }

    /**
     * The maximum number of scans/requests within a period.
     */
    public function getQuotaLimit(): int
    {
        return $this->quotaLimit;
    }

    /**
     * The billing period (e.g. `MONTH`).
     */
    public function getQuotaPeriod(): string
    {
        return $this->quotaPeriod;
    }

    /**
     * @return array{credits: int, quota: array{limit: int, period: string}}
     */
    public function toArray(): array
    {
        return [
            'credits' => $this->credits,
            'quota' => ['limit' => $this->quotaLimit, 'period' => $this->quotaPeriod],
        ];
    }

    /**
     * @return array{credits: int, quota: array{limit: int, period: string}}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
