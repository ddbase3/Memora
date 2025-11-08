<?php declare(strict_types=1);

namespace Memora\Api;

interface IMemoraQueryExtension {

	/**
	 * Determines whether this extension should be applied based on given options.
	 */
	public function isApplicable(array $options): bool;

	/**
	 * Modifies the query array before execution.
	 *
	 * @param array $query Current query structure
	 * @param array $options Input options
	 * @return array Modified query
	 */
	public function applyToQuery(array $query, array $options): array;

	/**
	 * Processes result rows after query execution.
	 *
	 * @param array $rows Query result rows
	 * @param array $options Input options
	 * @return array Processed rows
	 */
	public function processResult(array $rows, array $options): array;
}
