<?php

declare(strict_types=1);

namespace AttachmentAV\Model;

/**
 * A page of callback failures, youngest failure first.
 *
 * @implements \IteratorAggregate<int, CallbackFailure>
 */
final class CallbackFailureList implements \IteratorAggregate, \Countable
{
    /** @var list<CallbackFailure> */
    private array $failures;

    private ?string $nextCursor;

    /**
     * @param list<CallbackFailure> $failures
     */
    public function __construct(array $failures, ?string $nextCursor)
    {
        $this->failures = $failures;
        $this->nextCursor = $nextCursor;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $failures = [];
        foreach (is_array($data['failures'] ?? null) ? $data['failures'] : [] as $failure) {
            if (is_array($failure)) {
                $failures[] = CallbackFailure::fromArray($failure);
            }
        }
        $nextCursor = isset($data['next_cursor']) && $data['next_cursor'] !== '' ? (string) $data['next_cursor'] : null;

        return new self($failures, $nextCursor);
    }

    /**
     * @return list<CallbackFailure>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * Pass this value as `$cursor` to get the next page. Null if the end of failures is reached.
     */
    public function getNextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    /**
     * @return \ArrayIterator<int, CallbackFailure>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->failures);
    }

    public function count(): int
    {
        return count($this->failures);
    }
}
