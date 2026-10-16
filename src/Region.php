<?php

declare(strict_types=1);

namespace AttachmentAV;

use AttachmentAV\Exception\InvalidArgumentException;

/**
 * Regions of the attachmentAV API (SaaS).
 *
 * Your API subscription is valid for one of the regions only. The default is Europe.
 *
 * @see https://attachmentav.com/help/virus-malware-scan-api/developer/definition.html#api-endpoints
 */
final class Region
{
    public const EU = 'eu';
    public const US = 'us';
    public const CANADA = 'canada';
    public const INDIA = 'india';
    public const AUSTRALIA = 'australia';

    private const ENDPOINTS = [
        self::EU => 'https://eu.developer.attachmentav.com/v1',
        self::US => 'https://us.developer.attachmentav.com/v1',
        self::CANADA => 'https://canada.developer.attachmentav.com/v1',
        self::INDIA => 'https://india.developer.attachmentav.com/v1',
        self::AUSTRALIA => 'https://australia.developer.attachmentav.com/v1',
    ];

    private function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::ENDPOINTS);
    }

    public static function endpoint(string $region): string
    {
        $region = self::normalize($region);

        return self::ENDPOINTS[$region];
    }

    public static function normalize(string $region): string
    {
        $normalized = strtolower(trim($region));
        if (!isset(self::ENDPOINTS[$normalized])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown region "%s". Supported regions: %s.',
                $region,
                implode(', ', self::all())
            ));
        }

        return $normalized;
    }
}
