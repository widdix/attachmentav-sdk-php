<?php

declare(strict_types=1);

namespace AttachmentAV\Model;

use AttachmentAV\Exception\UnexpectedResponseException;

/**
 * Information about the authenticated tenant.
 */
final class Whoami implements \JsonSerializable
{
    private string $tenantId;

    public function __construct(string $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['tenantId']) || !is_string($data['tenantId'])) {
            throw new UnexpectedResponseException('Whoami response is missing the "tenantId" property.');
        }

        return new self($data['tenantId']);
    }

    /**
     * The tenant ID, required to verify callbacks.
     */
    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    /**
     * @return array{tenantId: string}
     */
    public function jsonSerialize(): array
    {
        return ['tenantId' => $this->tenantId];
    }
}
