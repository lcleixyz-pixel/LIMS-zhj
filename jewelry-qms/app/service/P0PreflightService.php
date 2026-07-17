<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

final class P0PreflightService
{
    private const UUID_REGEX = '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$';

    public static function scan(): array
    {
        $checks = [
            'duplicate_complaint_numbers' => self::duplicateNumber(
                'customer_complaints',
                'complaint_number'
            ),
            'duplicate_capa_numbers' => self::duplicateNumber('capas', 'capa_number'),
            'duplicate_nc_numbers' => self::duplicateNumber('nonconformities', 'nc_number'),
            'malformed_business_numbers' => self::malformedBusinessNumbers(),
            'duplicate_capa_sources' => self::rows(
                "SELECT MIN(id) AS id FROM capas
                 WHERE source_type IS NOT NULL AND source_record_id IS NOT NULL
                 GROUP BY company_id, source_type, source_record_id HAVING COUNT(*) > 1"
            ),
            'invalid_capa_ids' => self::rows(
                "SELECT id FROM capas WHERE soft_delete = 0 AND id NOT REGEXP ?",
                [self::UUID_REGEX]
            ),
            'invalid_source_capa_links' => self::invalidSourceLinks(),
            'orphan_source_capas' => self::orphanSourceCapas(),
            'reverse_link_mismatches' => self::reverseLinkMismatches(),
        ];

        $counts = [];
        $candidates = [];
        foreach ($checks as $key => $rows) {
            $counts[$key] = count($rows);
            $candidates[$key] = array_values(array_slice(array_map(
                static fn (array $row): string => (string)($row['id'] ?? ''),
                $rows
            ), 0, 100));
        }

        return [
            'mode' => 'read_only',
            'generated_at' => date(DATE_ATOM),
            'blocked' => array_sum($counts) > 0,
            'counts' => $counts,
            'candidate_ids' => $candidates,
            'privacy' => '仅输出记录标识和汇总数量，不输出客户、人员或联系方式',
        ];
    }

    private static function duplicateNumber(string $table, string $field): array
    {
        return self::rows(
            "SELECT MIN(id) AS id FROM {$table}
             GROUP BY company_id, {$field} HAVING COUNT(*) > 1"
        );
    }

    private static function malformedBusinessNumbers(): array
    {
        return self::rows(
            "SELECT id FROM customer_complaints
             WHERE complaint_number NOT REGEXP '^CP[0-9]{4}[0-9]{3,6}$'
             UNION
             SELECT id FROM capas
             WHERE capa_number NOT REGEXP '^CAPA[0-9]{4}[0-9]{3,6}$'
             UNION
             SELECT id FROM nonconformities
             WHERE nc_number NOT REGEXP '^NC[0-9]{4}[0-9]{3,6}$'"
        );
    }

    private static function invalidSourceLinks(): array
    {
        $rows = [];
        foreach (['customer_complaints', 'nonconformities', 'audit_findings'] as $table) {
            $rows = array_merge($rows, self::rows(
                "SELECT id FROM {$table}
                 WHERE soft_delete = 0 AND capa_id IS NOT NULL AND capa_id <> '' AND capa_id NOT REGEXP ?",
                [self::UUID_REGEX]
            ));
        }
        return $rows;
    }

    private static function orphanSourceCapas(): array
    {
        return self::rows(
            "SELECT c.id
             FROM capas c
             LEFT JOIN customer_complaints cp
               ON c.source_type = 'complaint' AND cp.id = c.source_record_id AND cp.soft_delete = 0
             LEFT JOIN nonconformities nc
               ON c.source_type = 'nc' AND nc.id = c.source_record_id AND nc.soft_delete = 0
             LEFT JOIN audit_findings af
               ON c.source_type = 'audit' AND af.id = c.source_record_id AND af.soft_delete = 0
             LEFT JOIN review_actions ra
               ON c.source_type = 'review' AND ra.id = c.source_record_id AND ra.soft_delete = 0
             WHERE c.soft_delete = 0 AND (
                (c.source_type = 'complaint' AND cp.id IS NULL)
                OR (c.source_type = 'nc' AND nc.id IS NULL)
                OR (c.source_type = 'audit' AND af.id IS NULL)
                OR (c.source_type = 'review' AND ra.id IS NULL)
                OR c.source_type NOT IN ('audit', 'complaint', 'nc', 'review', 'internal')
             )"
        );
    }

    private static function reverseLinkMismatches(): array
    {
        return self::rows(
            "SELECT c.id
             FROM capas c
             LEFT JOIN customer_complaints cp
               ON c.source_type = 'complaint' AND cp.id = c.source_record_id AND cp.soft_delete = 0
             LEFT JOIN nonconformities nc
               ON c.source_type = 'nc' AND nc.id = c.source_record_id AND nc.soft_delete = 0
             LEFT JOIN audit_findings af
               ON c.source_type = 'audit' AND af.id = c.source_record_id AND af.soft_delete = 0
             WHERE c.soft_delete = 0 AND (
                (c.source_type = 'complaint' AND cp.id IS NOT NULL AND COALESCE(cp.capa_id, '') <> c.id)
                OR (c.source_type = 'nc' AND nc.id IS NOT NULL AND COALESCE(nc.capa_id, '') <> c.id)
                OR (c.source_type = 'audit' AND af.id IS NOT NULL AND COALESCE(af.capa_id, '') <> c.id)
             )
             UNION
             SELECT cp.id FROM customer_complaints cp
             LEFT JOIN capas c
               ON c.id = cp.capa_id AND c.source_type = 'complaint'
               AND c.source_record_id = cp.id AND c.soft_delete = 0
             WHERE cp.soft_delete = 0 AND COALESCE(cp.capa_id, '') <> '' AND c.id IS NULL
             UNION
             SELECT nc.id FROM nonconformities nc
             LEFT JOIN capas c
               ON c.id = nc.capa_id AND c.source_type = 'nc'
               AND c.source_record_id = nc.id AND c.soft_delete = 0
             WHERE nc.soft_delete = 0 AND COALESCE(nc.capa_id, '') <> '' AND c.id IS NULL
             UNION
             SELECT af.id FROM audit_findings af
             LEFT JOIN capas c
               ON c.id = af.capa_id AND c.source_type = 'audit'
               AND c.source_record_id = af.id AND c.soft_delete = 0
             WHERE af.soft_delete = 0 AND COALESCE(af.capa_id, '') <> '' AND c.id IS NULL"
        );
    }

    private static function rows(string $sql, array $bind = []): array
    {
        return array_map(
            static fn (array $row): array => ['id' => (string)($row['id'] ?? '')],
            Db::query($sql, $bind)
        );
    }
}
