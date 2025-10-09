<?php declare(strict_types=1);

namespace Memora\DataHawk;

use DataHawk\Api\IReportSchemaProvider;
use DataHawk\Dto\TableMetadata;
use DataHawk\Dto\FieldMetadata;
use DataHawk\Dto\JoinMetadata;

class MemoraReportSchemaProvider implements IReportSchemaProvider {

	// Implementation of IReportSchemaProvider

	public function getSchema(): array {
		return [
			$this->getSysAllocTable(),
			$this->getSysEntryTable(),
			$this->getSysNameTable(),
			$this->getSysTagTable(),
			$this->getSysTypeTable()
		];
	}

	public function getTable(string $tableName): ?TableMetadata {
		foreach ($this->getSchema() as $table) {
			if ($table->name === $tableName) {
				return $table;
			}
		}
		return null;
	}

	// Private methods

	private function getSysAllocTable(): TableMetadata {
		return new TableMetadata(
			name: 'base3system_sysalloc',
			label: 'XRM Allocation',
			description: 'XRM entry allocations',
			domain: 'xrm',
			category: 'graph',
			tags: ['graph', 'allocation', 'edge', 'connection'],
			fields: [
				new FieldMetadata('id', 'integer', 'Allocation ID', true),
				new FieldMetadata('entry_id_1', 'integer', 'Entry ID 1', true),
				new FieldMetadata('entry_id_2', 'integer', 'Entry ID 2', true)
			],
			joins: [
				new JoinMetadata(
					targetTable: 'base3system_sysentry',
					on: ['base3system_sysalloc.entry_id_2' => 'base3system_sysentry.id'],
					type: 'INNER',
					meta: ['default' => true]
				),
				new JoinMetadata(
					targetTable: 'base3system_sysentry',
					on: ['base3system_sysalloc.entry_id_1' => 'base3system_sysentry.id'],
					type: 'INNER',
					meta: ['default' => true]
				)
			],
			defaultFilters: [],
			position: [ 'x' => 700, 'y' => 200 ]
		);
	}

	private function getSysEntryTable(): TableMetadata {
		return new TableMetadata(
			name: 'base3system_sysentry',
			label: 'XRM Entry',
			description: 'XRM data entries',
			domain: 'xrm',
			category: 'entry',
			tags: ['entry', 'record', 'entity'],
			fields: [
				new FieldMetadata('id', 'integer', 'Entry ID', true),
				new FieldMetadata('uuid', 'binary', 'Unique ID', true),
				new FieldMetadata('type_id', 'integer', 'Type ID', true),
				new FieldMetadata('archive', 'boolean', 'Entry archived', true),
				new FieldMetadata('dellock', 'boolean', 'Entry locked against deletion', true),
				new FieldMetadata('connections', 'integer', 'Number of connected entries', true),
				new FieldMetadata('etag', 'binary', 'Change tag for versioning', true),
				new FieldMetadata('created', 'datetime', 'Date and time of creation', true),
				new FieldMetadata('changed', 'datetime', 'Date and time of last change', true)
			],
			joins: [
				new JoinMetadata(
					targetTable: 'base3system_sysalloc',
					on: ['base3system_sysentry.id' => 'base3system_sysalloc.entry_id_1'],
					type: 'LEFT',
					meta: ['default' => true]
				),
				new JoinMetadata(
					targetTable: 'base3system_sysalloc',
					on: ['base3system_sysentry.id' => 'base3system_sysalloc.entry_id_2'],
					type: 'LEFT',
					meta: ['default' => true]
				),
				new JoinMetadata(
					targetTable: 'base3system_sysname',
					on: ['base3system_sysentry.id' => 'base3system_sysname.entry_id'],
					type: 'LEFT',
					meta: ['default' => true]
				),
				new JoinMetadata(
					targetTable: 'base3system_systag',
					on: ['base3system_sysentry.id' => 'base3system_systag.entry_id'],
					type: 'LEFT',
					meta: ['default' => true]
				),
				new JoinMetadata(
					targetTable: 'base3system_systype',
					on: ['base3system_sysentry.type_id' => 'base3system_systype.id'],
					type: 'INNER',
					meta: ['default' => true]
				)
			],
			defaultFilters: [
				// TODO id != 1
			],
			position: [ 'x' => 400, 'y' => 80 ]
		);
	}

	private function getSysNameTable(): TableMetadata {
		return new TableMetadata(
			name: 'base3system_sysname',
			label: 'Entry Name',
			description: 'XRM entry names',
			domain: 'xrm',
			category: 'entry',
			tags: ['name'],
			fields: [
				new FieldMetadata('entry_id', 'integer', 'Entry ID', true),
				new FieldMetadata('lang_id', 'integer', 'Language ID', true),
				new FieldMetadata('name', 'string', 'Name of the entry', true)
			],
			joins: [
				new JoinMetadata(
					targetTable: 'base3system_sysentry',
					on: ['base3system_sysname.entry_id' => 'base3system_sysentry.id'],
					type: 'INNER',
					meta: ['default' => true]
				)
			],
			defaultFilters: [
				// TODO lang_id = 1
			],
			position: [ 'x' => 100, 'y' => 40 ]
		);
	}

	private function getSysTagTable(): TableMetadata {
		return new TableMetadata(
			name: 'base3system_systag',
			label: 'Entry Type',
			description: 'XRM entry types',
			domain: 'xrm',
			category: 'meta',
			tags: ['tag'],
			fields: [
				new FieldMetadata('entry_id', 'integer', 'Entry ID', true),
				new FieldMetadata('tag', 'string', 'Entry tag', true)
			],
			joins: [
				new JoinMetadata(
					targetTable: 'base3system_sysentry',
					on: ['base3system_systag.entry_id' => 'base3system_sysentry.id'],
					type: 'INNER',
					meta: ['default' => true]
				)
			],
			defaultFilters: [],
			position: [ 'x' => 700, 'y' => 40 ]
		);
	}

	private function getSysTypeTable(): TableMetadata {
		return new TableMetadata(
			name: 'base3system_systype',
			label: 'Entry Type',
			description: 'XRM entry types',
			domain: 'xrm',
			category: 'entry',
			tags: ['type'],
			fields: [
				new FieldMetadata('id', 'integer', 'Type ID', true),
				new FieldMetadata('alias', 'string', 'Type alias', true),
				new FieldMetadata('dbtable', 'string', 'Database table for entry payload data', true),
				new FieldMetadata('primary', 'string', 'Primary key field of payload data table', true)
			],
			joins: [
				new JoinMetadata(
					targetTable: 'base3system_sysentry',
					on: ['base3system_systype.id' => 'base3system_sysentry.type_id'],
					type: 'LEFT',
					meta: ['default' => true]
				),
				new JoinMetadata(
					targetTable: 'base3system_sysentry',
					on: ['base3system_systype.id' => 'base3system_sysentry.type_id'],
					type: 'INNER'
				)
			],
			defaultFilters: [
				// TODO id != 1
			],
			position: [ 'x' => 100, 'y' => 200 ]
		);
	}
}
