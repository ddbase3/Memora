<?php declare(strict_types=1);

namespace Memora\Api;

use Base3\Api\ISortable;

interface IMemoraCreateExtension extends ISortable {

	/**
	 * Determines whether this extension should be applied for the given entry payload.
	 */
	public function isApplicable(array $entry): bool;

	/**
	 * Early normalization/validation and context preparation.
	 * Implementations may throw if required data is missing/invalid.
	 *
	 * @param array $entry The incoming entry payload (by reference, may be normalized)
	 * @param array $context Shared create context (by reference)
	 */
	public function beforeCreate(array &$entry, array &$context): void;

	/**
	 * Performs persistence for this aspect (base entry, type, name, tags, allocs, access, data, metadata, ...).
	 * Implementations may rely on $context values set by earlier extensions (e.g. entry_id).
	 *
	 * @param array $entry The normalized entry payload
	 * @param array $context Shared create context (by reference)
	 */
	public function create(array $entry, array &$context): void;

	/**
	 * Post-processing after all create steps have run (optional).
	 *
	 * @param array $entry The normalized entry payload
	 * @param array $context Shared create context (by reference)
	 */
	public function afterCreate(array $entry, array &$context): void;
}
