<?php
declare(strict_types=1);

namespace app\service;

use think\Model;
use think\facade\Config;

final class GovernedChangePolicyService
{
    private static ?array $subjects = null;

    public static function normalizeSubjectType(string $subjectType): string
    {
        $subjectType = trim($subjectType);
        if ($subjectType === '') {
            return '';
        }

        $subjectType = preg_replace('/(?<!^)[A-Z]/', '_$0', $subjectType) ?? $subjectType;

        $normalized = strtolower(str_replace(['-', '\\', '/'], '_', $subjectType));
        $compact = str_replace('_', '', $normalized);
        foreach (array_keys(self::subjects()) as $configured) {
            if (str_replace('_', '', (string)$configured) === $compact) {
                return (string)$configured;
            }
        }

        return $normalized;
    }

    public static function subjects(): array
    {
        if (self::$subjects !== null) {
            return self::$subjects;
        }

        try {
            $subjects = Config::get('governed_changes.subjects', null);
        } catch (\Throwable) {
            $subjects = null;
        }
        if (!is_array($subjects)) {
            $config = require dirname(__DIR__, 2) . '/config/governed_changes.php';
            $subjects = $config['subjects'] ?? [];
        }

        self::$subjects = is_array($subjects) ? $subjects : [];

        return self::$subjects;
    }

    public static function supports(string $subjectType): bool
    {
        return self::policy($subjectType) !== [];
    }

    public static function policy(string $subjectType): array
    {
        return self::subjects()[self::normalizeSubjectType($subjectType)] ?? [];
    }

    public static function strategy(string $subjectType): string
    {
        return (string)(self::policy($subjectType)['strategy'] ?? 'direct');
    }

    public static function modelClass(string $subjectType): ?string
    {
        $modelClass = self::policy($subjectType)['model'] ?? null;

        return is_string($modelClass) && is_a($modelClass, Model::class, true)
            ? $modelClass
            : null;
    }

    public static function findSubject(string $subjectType, string $subjectId): ?Model
    {
        $modelClass = self::modelClass($subjectType);
        if ($modelClass === null || trim($subjectId) === '') {
            return null;
        }

        /** @var Model $prototype */
        $prototype = new $modelClass();
        $query = $modelClass::where('id', $subjectId);
        if (method_exists($prototype, 'hasColumn') && $prototype->hasColumn('soft_delete')) {
            $query->where('soft_delete', 0);
        }
        if (method_exists($prototype, 'hasColumn') && $prototype->hasColumn('company_id')) {
            $companyId = (string)Config::get('qms.company_id', '');
            if ($companyId !== '') {
                $query->where('company_id', $companyId);
            }
        }

        $record = $query->find();

        return $record instanceof Model ? $record : null;
    }

    public static function isFrozen(string $subjectType, Model $record): bool
    {
        $policy = self::policy($subjectType);
        if ($policy === []) {
            return false;
        }
        if (($policy['always_frozen'] ?? false) === true) {
            return true;
        }

        $status = self::attribute($record, 'status');
        $frozenStatuses = is_array($policy['frozen_statuses'] ?? null)
            ? $policy['frozen_statuses']
            : [];
        if ($status !== null && in_array((string)$status, $frozenStatuses, true)) {
            return true;
        }

        $parent = $policy['parent'] ?? null;
        if (!is_array($parent)) {
            return false;
        }

        $foreignKey = (string)($parent['foreign_key'] ?? '');
        $parentId = $foreignKey !== '' ? trim((string)(self::attribute($record, $foreignKey) ?? '')) : '';
        $parentClass = $parent['model'] ?? null;
        if ($parentId === '' || !is_string($parentClass) || !is_a($parentClass, Model::class, true)) {
            return false;
        }

        try {
            /** @var Model|null $parentRecord */
            $parentRecord = $parentClass::where('id', $parentId)->find();
            if (!$parentRecord) {
                return false;
            }

            return in_array(
                (string)(self::attribute($parentRecord, 'status') ?? ''),
                is_array($parent['frozen_statuses'] ?? null) ? $parent['frozen_statuses'] : [],
                true
            );
        } catch (\Throwable) {
            return false;
        }
    }

    public static function directUpdateViolation(string $subjectType, Model $record, array $changes): ?string
    {
        $policy = self::policy($subjectType);
        $strategy = (string)($policy['strategy'] ?? 'direct');
        if ($strategy === 'revision' || $strategy === 'specialized') {
            return null;
        }
        if ($strategy === 'correction' && self::isFrozen($subjectType, $record)) {
            return '该记录已形成受控证据，不能直接覆盖原值；请从详情页提交字段更正申请。';
        }
        if ($strategy === 'event') {
            $protected = is_array($policy['protected_fields'] ?? null) ? $policy['protected_fields'] : [];
            if (array_intersect(array_keys($changes), $protected) !== []) {
                return '状态、归属或有效期等受控字段不能在普通编辑中覆盖；请通过对应业务事件办理。';
            }
        }

        return null;
    }

    public static function deleteViolation(string $subjectType, Model $record): ?string
    {
        $strategy = self::strategy($subjectType);
        if ($strategy === 'revision' || $strategy === 'specialized') {
            return null;
        }
        if ($strategy === 'correction' && self::isFrozen($subjectType, $record)) {
            return '该记录已形成受控证据，不能删除；如内容有误，请提交更正并保留原始记录。';
        }
        if ($strategy === 'event') {
            return '该对象已纳入生命周期管理，不能直接删除；请通过停用、撤销、报废或调拨事件处理。';
        }

        return null;
    }

    private static function attribute(Model $record, string $field): mixed
    {
        try {
            return $record->getAttr($field);
        } catch (\Throwable) {
            return $record->{$field} ?? null;
        }
    }
}
