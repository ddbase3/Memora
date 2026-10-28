<?php declare(strict_types=1);

namespace Memora\Dto;

/** Server-only marker for a verified, resource-constrained technical query. */
final class VerifiedResourceQuery {

    private function __construct(private readonly array $effectiveFilter) {}

    /** The caller must already have verified the technical credential and its operation grant. */
    public static function fromVerifiedFilter(array $filter): self {
        if (($filter['type'] ?? null) !== 'op' || !is_string($filter['operator'] ?? null) || !is_array($filter['params'] ?? null)) {
            throw new \InvalidArgumentException('A structured resource filter is required.');
        }
        return new self($filter);
    }

    /** Reject modifications to the verified predicate after the authorization boundary. */
    public function matches(array $filter): bool {
        return $this->effectiveFilter === $filter;
    }
}
