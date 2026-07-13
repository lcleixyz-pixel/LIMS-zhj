<?php
declare(strict_types=1);

namespace app\service;

use DomainException;
use think\facade\Config;
use think\facade\Db;

class QmsPositionAliasService
{
    private const SOURCE_SCOPE = 'position_catalog';
    private const SITE_SCOPE_KEY = '*';

    public static function defaultDefinitions(): array
    {
        return [
            'lab_director' => self::definition('实验室主任', [
                '实验室主任' => 'confirmed',
                '最高管理者' => 'confirmed',
            ]),
            'technical_manager' => self::definition('技术负责人', [
                '技术负责人' => 'confirmed',
                '技术主管' => 'confirmed',
            ]),
            'quality_manager' => self::definition('质量负责人', [
                '质量负责人' => 'confirmed',
                '质量主管' => 'confirmed',
            ]),
            'office_manager' => self::definition('办公室主任', [
                '办公室主任' => 'confirmed',
                '办公室负责人' => 'confirmed',
                '办公室' => 'confirmed',
            ]),
            'document_controller' => self::definition('资料管理员', [
                '资料管理员' => 'confirmed',
                '资料员' => 'confirmed',
                '文件管理员' => 'confirmed',
                '档案管理员' => 'confirmed',
            ]),
            'equipment_manager' => self::definition('设备管理员', [
                '设备管理员' => 'confirmed',
                '仪器设备管理员' => 'confirmed',
            ]),
            'sample_manager' => self::definition('样品管理员', [
                '样品管理员' => 'confirmed',
            ]),
            'testing_room_manager' => self::definition('检测室主任', [
                '检测室主任' => 'confirmed',
            ]),
            'testing_staff' => self::definition('检测人员', [
                '检测人员' => 'confirmed',
                '检测员' => 'confirmed',
            ]),
            'authorized_signatory' => self::definition('授权签字人', [
                '授权签字人' => 'confirmed',
                '授权签字员' => 'confirmed',
            ]),
            'internal_auditor' => self::definition('内审员', [
                '内审员' => 'confirmed',
                '内部审核员' => 'confirmed',
                '审核员' => 'confirmed',
            ]),
            'supervisor' => self::definition('监督员', [
                '监督员' => 'confirmed',
            ]),
            'company_general_manager' => self::definition('公司总经理', [
                '公司总经理' => 'confirmed',
                '总经理' => 'confirmed',
                '经理' => 'review_required',
            ]),
        ];
    }

    public static function legacyDefinitions(): array
    {
        $definitions = [];
        foreach (self::defaultDefinitions() as $code => $definition) {
            $definitions[$code] = [
                'name' => (string)$definition['name'],
                'aliases' => array_keys(array_filter(
                    (array)$definition['aliases'],
                    static fn (string $status): bool => $status === 'confirmed'
                )),
            ];
        }

        return $definitions;
    }

    public static function seedCatalog(): array
    {
        return self::withSeededCatalogLock(
            static fn (string $companyId, array $positions): array => $positions
        );
    }

    public static function withSeededCatalogLock(callable $callback, ?string $companyId = null): mixed
    {
        $companyId = $companyId ?? (string)Config::get('qms.company_id');

        return Db::transaction(static function () use ($callback, $companyId): mixed {
            $company = Db::name('companies')
                ->where('id', $companyId)
                ->where('soft_delete', 0)
                ->lock(true)
                ->find();
            if (!$company) {
                throw new DomainException('当前公司不存在或已删除，不能在无锁状态初始化岗位与责任链。');
            }

            $positions = self::seedCatalogForLockedCompany($companyId);

            return $callback($companyId, $positions);
        });
    }

    private static function seedCatalogForLockedCompany(string $companyId): array
    {
        $now = date('Y-m-d H:i:s');
        $positions = [];

        foreach (self::defaultDefinitions() as $code => $definition) {
            $row = Db::name('qms_positions')
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->lock(true)
                ->find();
            $positionId = '';
            $positionName = (string)$definition['name'];
            if ($row) {
                $positionId = (string)$row['id'];
                $positionName = (string)$row['name'];
                if ((string)($row['source'] ?? '') === self::SOURCE_SCOPE) {
                    $positionName = (string)$definition['name'];
                    Db::name('qms_positions')
                        ->where('company_id', $companyId)
                        ->where('id', $positionId)
                        ->update([
                            'name' => $positionName,
                            'source' => self::SOURCE_SCOPE,
                            'review_status' => 'published',
                            'publish' => 1,
                            'soft_delete' => 0,
                            'modified' => $now,
                        ]);
                }
            } else {
                $globalConflict = Db::name('qms_positions')->where('code', $code)->lock(true)->find();
                if ($globalConflict) {
                    throw new DomainException(
                        '岗位代码 ' . $code . '已归属其他公司，现有 UNIQUE(code) 约束下无法安全补齐。'
                    );
                }
                $positionId = qms_uuid();
                Db::name('qms_positions')->insert([
                    'id' => $positionId,
                    'company_id' => $companyId,
                    'code' => $code,
                    'name' => $positionName,
                    'source' => self::SOURCE_SCOPE,
                    'review_status' => 'published',
                    'publish' => 1,
                    'soft_delete' => 0,
                    'created' => $now,
                    'modified' => $now,
                ]);
            }
            $positions[$code] = [
                'id' => $positionId,
                'code' => $code,
                'name' => $positionName,
            ];

            foreach ((array)$definition['aliases'] as $alias => $status) {
                $aliasRow = Db::name('qms_position_aliases')
                    ->where('company_id', $companyId)
                    ->where('alias', (string)$alias)
                    ->where('source_scope', self::SOURCE_SCOPE)
                    ->where('site_scope_key', self::SITE_SCOPE_KEY)
                    ->lock(true)
                    ->find();
                $aliasId = (string)($aliasRow['id'] ?? qms_uuid());
                $aliasPayload = [
                    'company_id' => $companyId,
                    'position_id' => $positionId,
                    'alias' => (string)$alias,
                    'confirmation_status' => (string)$status,
                    'source_scope' => self::SOURCE_SCOPE,
                    'site_id' => null,
                    'site_scope_key' => self::SITE_SCOPE_KEY,
                    'confirmation_note' => (string)($definition['confirmation_note'] ?? ''),
                    'publish' => 1,
                    'soft_delete' => 0,
                    'modified' => $now,
                ];
                if ($aliasRow) {
                    Db::name('qms_position_aliases')
                        ->where('company_id', $companyId)
                        ->where('id', $aliasId)
                        ->update($aliasPayload);
                } else {
                    Db::name('qms_position_aliases')->insert(array_merge($aliasPayload, [
                        'id' => $aliasId,
                        'created' => $now,
                    ]));
                }
            }
        }

        return $positions;
    }

    public static function aliasCatalog(): array
    {
        self::seedCatalog();
        $companyId = (string)Config::get('qms.company_id');
        $rows = Db::name('qms_position_aliases')
            ->alias('a')
            ->join('qms_positions p', 'p.id = a.position_id')
            ->where('a.company_id', $companyId)
            ->where('a.source_scope', self::SOURCE_SCOPE)
            ->where('a.site_scope_key', self::SITE_SCOPE_KEY)
            ->where('a.soft_delete', 0)
            ->where('p.company_id', $companyId)
            ->where('p.soft_delete', 0)
            ->field('a.alias,a.confirmation_status,a.source_scope,a.site_scope_key,p.id position_id,p.code position_code,p.name position_name')
            ->order('p.code,a.alias')
            ->select()
            ->toArray();

        $catalog = [];
        foreach ($rows as $row) {
            $catalog[(string)$row['alias']] = $row;
        }

        return $catalog;
    }

    private static function definition(string $name, array $aliases): array
    {
        return [
            'name' => $name,
            'aliases' => $aliases,
        ];
    }
}
