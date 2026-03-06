<?php declare(strict_types=1);

namespace Memora\Api;

use Base3\Api\ISortable;

interface IMemoraUpdateExtension extends ISortable {

	/**
	 * Determines whether this extension should be applied for the given patch payload.
	 */
	public function isApplicable(array $patch): bool;

	/**
	 * Early normalization/validation and context preparation.
	 * Implementations may throw if required data is missing/invalid.
	 *
	 * @param array $patch The incoming patch payload (by reference, may be normalized)
	 * @param array $context Shared update context (by reference)
	 */
	public function beforeUpdate(array &$patch, array &$context): void;

	/**
	 * Performs persistence for this aspect.
	 * Implementations may rely on $context values set by earlier extensions.
	 *
	 * @param array $patch The normalized patch payload
	 * @param array $context Shared update context (by reference)
	 */
	public function update(array $patch, array &$context): void;

	/**
	 * Post-processing after all update steps have run (optional).
	 *
	 * @param array $patch The normalized patch payload
	 * @param array $context Shared update context (by reference)
	 */
	public function afterUpdate(array $patch, array &$context): void;
}
