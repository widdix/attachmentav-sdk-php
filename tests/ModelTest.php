<?php

declare(strict_types=1);

namespace AttachmentAV\Tests;

use AttachmentAV\Exception\RateLimitException;
use AttachmentAV\Model\CallbackFailure;
use PHPUnit\Framework\Attributes\DataProvider;

final class ModelTest extends \PHPUnit\Framework\TestCase
{
    public function testCallbackFailureSerializesTimeInUtc(): void
    {
        $failure = CallbackFailure::fromArray(['id' => 'f', 'time' => '2025-01-01T10:00:00+02:00']);

        self::assertSame('2025-01-01T08:00:00Z', $failure->jsonSerialize()['time']);
    }

    /**
     * @return iterable<array{string, bool}>
     */
    public static function rateLimitProvider(): iterable
    {
        yield ['Quota exceeded.', true];
        yield ['Limit Exceeded', true];
        yield ['Rate limit exceeded.', false];
        yield ['Too Many Requests', false];
    }

    /**
     * @dataProvider rateLimitProvider
     */
    #[DataProvider('rateLimitProvider')]
    public function testRateLimitClassification(string $message, bool $quotaExceeded): void
    {
        $e = new RateLimitException('x', 429, '', $message);

        self::assertSame($quotaExceeded, $e->isQuotaExceeded());
        self::assertSame(!$quotaExceeded, $e->isThrottled());
    }
}
