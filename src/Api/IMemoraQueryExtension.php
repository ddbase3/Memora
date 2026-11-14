<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of Memora for BASE3 Framework.
 *
 * Memora is a modular XRM/CRM backend and knowledge graph for BASE3.
 * It provides structured entities, relations, metadata and tags.
 * ResourceFoundation contracts govern its shared entity services.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 * https://github.com/ddbase3/Memora
 **********************************************************************/

namespace Base3\Memora\Api;

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
