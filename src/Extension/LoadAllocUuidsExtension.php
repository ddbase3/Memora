<?php declare(strict_types=1);

namespace Memora\Extension;

use Base3\Api\ISortable;
use Memora\Api\IMemoraQueryExtension;

class LoadAllocUuidsExtension implements IMemoraQueryExtension, ISortable {

        public function isApplicable(array $options): bool {
                return !empty($options['loadallocuuids']);
        }

        public function applyToQuery(array $query, array $options): array {
                // Explicitly request the allocation view alias.
                // This is required so DataHawk can build:
                // sysentry -> sysallocview AS loadallocuuidalloc -> sysentry AS loadallocuuidpeer
                $query['fields'][] = [
                        'element' => [
                                'type' => 'fn',
                                'function' => 'GROUP_CONCAT',
                                'distinct' => true,
                                'params' => [
                                        [
                                                'type' => 'fld',
                                                'table' => 'base3system_sysallocview',
                                                'tablealias' => 'loadallocuuidalloc',
                                                'field' => 'peer_id',
                                                'variant' => 'optional'
                                        ]
                                ]
                        ],
                        'alias' => 'allocuuidpeerids'
                ];

                $query['fields'][] = [
                        'element' => [
                                'type' => 'fn',
                                'function' => 'GROUP_CONCAT',
                                'distinct' => true,
                                'params' => [
                                        [
                                                'type' => 'fn',
                                                'function' => 'HEX',
                                                'params' => [
                                                        [
                                                                'type' => 'fld',
                                                                'table' => 'base3system_sysentry',
                                                                'tablealias' => 'loadallocuuidpeer',
                                                                'field' => 'uuid',
                                                                'variant' => 'optional'
                                                        ]
                                                ]
                                        ]
                                ]
                        ],
                        'alias' => 'allocuuids'
                ];

                return $query;
        }

        public function processResult(array $rows, array $options): array {
                foreach ($rows as &$row) {
                        unset($row['allocuuidpeerids']);

                        if (!empty($row['allocuuids']) && is_string($row['allocuuids'])) {
                                $uuids = explode(',', $row['allocuuids']);
                                $uuids = array_map('trim', $uuids);
                                $uuids = array_filter($uuids, static fn(string $uuid): bool => $uuid !== '');
                                $uuids = array_map('strtolower', $uuids);
                                $uuids = array_values(array_unique($uuids));

                                $row['allocuuids'] = $uuids;
                        } else {
                                $row['allocuuids'] = [];
                        }
                }
                unset($row);

                return $rows;
        }

        public function getPriority(): int {
                return 845;
        }
}
