<?php declare(strict_types=1);

namespace Memora\Service;

use ResourceFoundation\Api\IEntityDataService;
use ResourceFoundation\Api\IEntityFileService;
use ResourceFoundation\Api\IFileStorage;
use ResourceFoundation\Exception\AccessDeniedException;

class MemoraEntityFileService implements IEntityFileService {

	public function __construct(
		private readonly IEntityDataService $entitydataservice,
		private readonly IFileStorage $filestorage
	) {}

	public function createFile(array $file, array $options = []): array {
		$file = $this->normalizeCreateFile($file);

		$entryId = $this->entitydataservice->createEntry(
			$this->buildCreateEntryPayload($file, $options)
		);

		$entry = $this->getFile($entryId, [
			'loadaccess' => true,
			'loadtags' => true,
			'loadallocs' => true,
			'loadmetadata' => true
		]);

		if ($entry === null) {
			throw new \RuntimeException('Created file entry could not be reloaded: ' . $entryId);
		}

		$tmpname = $this->resolveTmpname($entry);
		$binary = $this->decodeBase64Content($file['content_base64']);

		if (!$this->filestorage->write($tmpname, $binary)) {
			$this->cleanupFailedCreate($entryId);
			throw new \RuntimeException('Unable to write file content to storage: ' . $tmpname);
		}

		try {
			$this->entitydataservice->updateEntry($entryId, $this->buildFilePatch($file, $tmpname));
		} catch (\Throwable $e) {
			$this->deletePhysicalFileIfExists($tmpname);
			$this->cleanupFailedCreate($entryId);
			throw $e;
		}

		$result = $this->getFile($entryId, [
			'loadaccess' => true,
			'loadtags' => true,
			'loadallocs' => true,
			'loadmetadata' => true
		]);

		if ($result === null) {
			throw new \RuntimeException('Created file entry disappeared after update: ' . $entryId);
		}

		return $result;
	}

	public function replaceFile(int|string $id, array $file, array $options = []): array {
		$current = $this->requireEditableFileEntry($id);

		$file = $this->normalizeReplaceFile($file, $current);
		$tmpname = $this->resolveTmpname($current);
		$binary = $this->decodeBase64Content($file['content_base64']);

		$hadPreviousFile = $tmpname !== '' && $this->filestorage->exists($tmpname);
		$previousContent = $hadPreviousFile ? $this->filestorage->read($tmpname) : null;

		if (!$this->filestorage->write($tmpname, $binary)) {
			throw new \RuntimeException('Unable to replace file content in storage: ' . $tmpname);
		}

		try {
			$this->entitydataservice->updateEntry($id, $this->buildFilePatch($file, $tmpname));
		} catch (\Throwable $e) {
			$this->restorePreviousPhysicalState($tmpname, $hadPreviousFile, $previousContent);
			throw $e;
		}

		$result = $this->getFile($id, [
			'loadaccess' => true,
			'loadtags' => true,
			'loadallocs' => true,
			'loadmetadata' => true
		]);

		if ($result === null) {
			throw new \RuntimeException('Updated file entry could not be reloaded: ' . $id);
		}

		return $result;
	}

	public function getFile(int|string $id, array $options = []): ?array {
		$options = $this->buildLoadOptions($options);
		return $this->entitydataservice->getEntry($id, $options);
	}

	public function getFileContent(int|string $id, array $options = []): ?string {
		$entry = $this->getFile($id, [
			'loadaccess' => !empty($options['loadaccess'])
		]);

		if ($entry === null) {
			return null;
		}

		$tmpname = trim((string)($entry['data']['tmpname'] ?? ''));
		if ($tmpname === '') {
			return null;
		}
		if (!$this->filestorage->exists($tmpname)) {
			return null;
		}

		$content = $this->filestorage->read($tmpname);
		$encoding = strtolower((string)($options['encoding'] ?? 'base64'));

		return match ($encoding) {
			'raw' => $content,
			'base64' => base64_encode($content),
			default => throw new \InvalidArgumentException('Unsupported file content encoding: ' . $encoding)
		};
	}

	public function deleteFile(int|string $id, array $options = []): bool {
		try {
			$entry = $this->requireEditableFileEntry($id);
		} catch (\Throwable $e) {
			return false;
		}

		$deletePhysical = array_key_exists('deletephysical', $options)
			? (bool)$options['deletephysical']
			: true;

		$tmpname = trim((string)($entry['data']['tmpname'] ?? ''));

		if ($deletePhysical && $tmpname !== '' && $this->filestorage->exists($tmpname)) {
			if (!$this->filestorage->delete($tmpname)) {
				return false;
			}
		}

		return $this->entitydataservice->deleteEntry($id);
	}

	private function requireEditableFileEntry(int|string $id): array {
		$entry = $this->getFile($id, [
			'loadaccess' => true,
			'loadtags' => true,
			'loadallocs' => true,
			'loadmetadata' => true
		]);

		if ($entry === null) {
			throw new \RuntimeException('File entry not found: ' . $id);
		}
		if (($entry['access'] ?? 'none') !== 'edit') {
			throw new AccessDeniedException('File update denied for entry ' . $id . '.');
		}

		return $entry;
	}

	private function buildLoadOptions(array $options = []): array {
		$defaults = [
			'type' => 'file',
			'loadname' => true,
			'loaddata' => true
		];

		$options = array_merge($defaults, $options);
		$options['type'] = 'file';

		return $options;
	}

	private function buildCreateEntryPayload(array $file, array $options): array {
		$payload = [
			'type' => 'file',
			'name' => $file['filename'],
			'data' => [
				'name' => $file['filename'],
				'filename' => $file['filename'],
				'tmpname' => '',
				'description' => $file['description'],
				'content' => $file['content'],
				'mime' => $file['mime'],
				'size' => $file['size'],
				'preview' => $file['preview']
			]
		];

		$allocs = [];
		if (isset($options['alloc'])) {
			$allocs = array_merge($allocs, (array)$options['alloc']);
		}
		if (isset($options['allocs'])) {
			$allocs = array_merge($allocs, (array)$options['allocs']);
		}
		if (!empty($allocs)) {
			$payload['allocs'] = array_values(array_unique(array_map('intval', $allocs)));
		}

		$tags = [];
		if (isset($options['tag'])) {
			$tags = array_merge($tags, (array)$options['tag']);
		}
		if (isset($options['tags'])) {
			$tags = array_merge($tags, (array)$options['tags']);
		}
		if (!empty($tags)) {
			$payload['tags'] = array_values(array_unique(array_map(
				fn($tag) => trim((string)$tag),
				$tags
			)));
		}

		foreach (['metadata', 'useraccess', 'groupaccess'] as $key) {
			if (isset($options[$key])) {
				$payload[$key] = $options[$key];
			}
		}

		return $payload;
	}

	private function buildFilePatch(array $file, string $tmpname): array {
		return [
			'setname' => $file['name'],
			'setdata' => [
				'name' => $file['name'],
				'filename' => $file['filename'],
				'tmpname' => $tmpname,
				'description' => $file['description'],
				'content' => $file['content'],
				'mime' => $file['mime'],
				'size' => $file['size'],
				'preview' => $file['preview']
			]
		];
	}

	private function normalizeCreateFile(array $file): array {
		$filename = $this->sanitizeFilename((string)($file['filename'] ?? ''));
		if ($filename === '') {
			throw new \InvalidArgumentException('createFile requires a non-empty filename.');
		}

		$contentBase64 = trim((string)($file['content_base64'] ?? ''));
		if ($contentBase64 === '') {
			throw new \InvalidArgumentException('createFile requires non-empty content_base64.');
		}

		$binary = $this->decodeBase64Content($contentBase64);

		return [
			'filename' => $filename,
			'content_base64' => $contentBase64,
			'name' => trim((string)($file['name'] ?? '')) ?: $filename,
			'description' => (string)($file['description'] ?? ''),
			'content' => (string)($file['content'] ?? ''),
			'preview' => (string)($file['preview'] ?? ''),
			'mime' => $this->resolveMimeType($file['mime'] ?? null, $binary),
			'size' => $this->resolveSize($file['size'] ?? null, $binary)
		];
	}

	private function normalizeReplaceFile(array $file, array $current): array {
		$currentData = is_array($current['data'] ?? null) ? $current['data'] : [];

		$filename = $this->sanitizeFilename((string)($file['filename'] ?? ($currentData['filename'] ?? '')));
		if ($filename === '') {
			throw new \InvalidArgumentException('replaceFile requires a filename or an existing file filename.');
		}

		$contentBase64 = trim((string)($file['content_base64'] ?? ''));
		if ($contentBase64 === '') {
			throw new \InvalidArgumentException('replaceFile requires non-empty content_base64.');
		}

		$binary = $this->decodeBase64Content($contentBase64);

		return [
			'filename' => $filename,
			'content_base64' => $contentBase64,
			'name' => trim((string)($file['name'] ?? ($currentData['name'] ?? $filename))) ?: $filename,
			'description' => array_key_exists('description', $file)
				? (string)$file['description']
				: (string)($currentData['description'] ?? ''),
			'content' => array_key_exists('content', $file)
				? (string)$file['content']
				: (string)($currentData['content'] ?? ''),
			'preview' => array_key_exists('preview', $file)
				? (string)$file['preview']
				: (string)($currentData['preview'] ?? ''),
			'mime' => $this->resolveMimeType($file['mime'] ?? ($currentData['mime'] ?? null), $binary),
			'size' => $this->resolveSize($file['size'] ?? null, $binary)
		];
	}

	private function decodeBase64Content(string $contentBase64): string {
		$binary = base64_decode($contentBase64, true);
		if ($binary === false) {
			throw new \InvalidArgumentException('Invalid content_base64 payload.');
		}
		return $binary;
	}

	private function resolveMimeType(mixed $mime, string $binary): string {
		$mime = trim((string)($mime ?? ''));
		if ($mime !== '') {
			return $mime;
		}

		if (class_exists(\finfo::class)) {
			$finfo = new \finfo(FILEINFO_MIME_TYPE);
			$detected = $finfo->buffer($binary);
			if (is_string($detected) && $detected !== '') {
				return $detected;
			}
		}

		return 'application/octet-stream';
	}

	private function resolveSize(mixed $size, string $binary): string {
		if ($size !== null && $size !== '') {
			return (string)$size;
		}
		return (string)strlen($binary);
	}

	private function sanitizeFilename(string $filename): string {
		$filename = trim(str_replace('\\', '/', $filename));
		if ($filename === '') {
			return '';
		}
		return basename($filename);
	}

	private function resolveTmpname(array $entry): string {
		$tmpname = trim((string)($entry['data']['tmpname'] ?? ''));
		if ($tmpname !== '') {
			return $tmpname;
		}

		$uuid = trim((string)($entry['uuid'] ?? ''));
		if ($uuid === '') {
			throw new \RuntimeException('Cannot derive tmpname without entry uuid.');
		}

		$storageName = $this->formatUuidFilename($uuid);

		return substr($uuid, 0, 2) . '/' . substr($uuid, 2, 2) . '/' . $storageName;
	}

	private function formatUuidFilename(string $uuid): string {
		$hex = strtolower(str_replace('-', '', trim($uuid)));

		if (!preg_match('/^[a-f0-9]{32}$/', $hex)) {
			return $uuid;
		}

		return substr($hex, 0, 8) . '-' .
			substr($hex, 8, 4) . '-' .
			substr($hex, 12, 4) . '-' .
			substr($hex, 16, 4) . '-' .
			substr($hex, 20);
	}

	private function cleanupFailedCreate(int|string $entryId): void {
		try {
			$this->entitydataservice->deleteEntry($entryId);
		} catch (\Throwable $e) {
		}
	}

	private function deletePhysicalFileIfExists(string $tmpname): void {
		if ($tmpname === '') {
			return;
		}
		if (!$this->filestorage->exists($tmpname)) {
			return;
		}
		$this->filestorage->delete($tmpname);
	}

	private function restorePreviousPhysicalState(string $tmpname, bool $hadPreviousFile, ?string $previousContent): void {
		try {
			if ($hadPreviousFile && $previousContent !== null) {
				$this->filestorage->write($tmpname, $previousContent);
				return;
			}

			if ($tmpname !== '' && $this->filestorage->exists($tmpname)) {
				$this->filestorage->delete($tmpname);
			}
		} catch (\Throwable $e) {
		}
	}
}
