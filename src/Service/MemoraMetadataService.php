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

namespace Base3\Memora\Service;

use Base3\Memora\Api\IMemoraQueryService;
use Base3\ResourceFoundation\Api\IEntityDataService;
use Base3\ResourceFoundation\Api\IEntityMetadataService;

class MemoraMetadataService extends AbstractMemoraTableService implements IEntityMetadataService {

	public function __construct(
		IMemoraQueryService $dataqueryservice,
		private readonly IEntityDataService $entitydataservice
	) {
		parent::__construct($dataqueryservice);
	}

	public function getMetadata(int|string $entryId): array {
		$entryId = $this->requireId($entryId, 'entry');
		$rows = $this->fetchRows(
			'base3system_sysmetadata',
			['entry_id', 'name', 'data'],
			$this->eq('base3system_sysmetadata', 'entry_id', $entryId),
			[['element' => $this->fld('base3system_sysmetadata', 'name'), 'direction' => 'ASC']]
		);

		$result = [];
		foreach ($rows as $row) {
			$name = (string)($row['name'] ?? '');
			if ($name === '') continue;
			$result[$name] = $this->decodeValue($row['data'] ?? null);
		}

		return $result;
	}

	public function getMetadataValue(int|string $entryId, string $name, mixed $default = null): mixed {
		$name = trim($name);
		if ($name === '') {
			return $default;
		}

		$metadata = $this->getMetadata($entryId);
		return array_key_exists($name, $metadata) ? $metadata[$name] : $default;
	}

	public function setMetadata(int|string $entryId, array $metadata): void {
		if (empty($metadata)) {
			return;
		}

		$this->entitydataservice->updateEntry($entryId, [
			'setmetadata' => $metadata
		]);
	}

	public function removeMetadata(int|string $entryId, array $names): void {
		$names = $this->normalizeStrings($names);
		if (empty($names)) {
			return;
		}

		$this->entitydataservice->updateEntry($entryId, [
			'unsetmetadata' => $names
		]);
	}

	public function replaceMetadata(int|string $entryId, array $metadata): void {
		$current = $this->getMetadata($entryId);
		$remove = array_values(array_diff(array_keys($current), array_keys($metadata)));
		$patch = [];

		if (!empty($remove)) {
			$patch['unsetmetadata'] = $remove;
		}
		if (!empty($metadata)) {
			$patch['setmetadata'] = $metadata;
		}
		if (empty($patch)) {
			return;
		}

		$this->entitydataservice->updateEntry($entryId, $patch);
	}

	private function decodeValue(mixed $value): mixed {
		if (!is_string($value)) {
			return $value;
		}

		$trimmed = trim($value);
		if ($trimmed === '') {
			return $value;
		}
		if (!in_array($trimmed[0], ['{', '[', '"'], true) && !in_array($trimmed, ['true', 'false', 'null'], true) && !is_numeric($trimmed)) {
			return $value;
		}

		$decoded = json_decode($value, true);
		return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
	}

	private function requireId(int|string $id, string $name): int {
		$id = $this->normalizeId($id);
		if ($id === null) {
			throw new \InvalidArgumentException('Invalid ' . $name . ' id.');
		}

		return $id;
	}
}
