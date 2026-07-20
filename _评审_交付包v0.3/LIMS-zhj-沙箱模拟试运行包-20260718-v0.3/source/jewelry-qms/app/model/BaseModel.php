<?php
declare(strict_types=1);

namespace app\model;

use app\service\FieldAuditService;
use think\Model;
use think\facade\Config;
use think\facade\Session;

class BaseModel extends Model
{
    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';

    protected $createTime = 'created';
    protected $updateTime = 'modified';

    protected $defaultCompanyField = 'company_id';

    public static function onBeforeInsert(Model $model): void
    {
        if (empty($model->id)) {
            $model->id = qms_uuid();
        }
        if ($model->defaultCompanyField && $model->hasColumn($model->defaultCompanyField)) {
            $model->setAttr($model->defaultCompanyField, Config::get('qms.company_id'));
        }
        if (Session::has('user.id')) {
            if ($model->hasColumn('created_by')) {
                $model->created_by = Session::get('user.id');
            }
            if ($model->hasColumn('modified_by')) {
                $model->modified_by = Session::get('user.id');
            }
        }
    }

    public static function onBeforeUpdate(Model $model): void
    {
        if (Session::has('user.id') && $model->hasColumn('modified_by')) {
            $model->modified_by = Session::get('user.id');
        }
        if (FieldAuditService::shouldAuditModel($model)) {
            FieldAuditService::capture($model);
        }
    }

    public function hasColumn(string $field): bool
    {
        static $cache = [];
        $class = static::class;
        if (!isset($cache[$class])) {
            try {
                $fields = $this->db()->getFields();
                $cache[$class] = is_array($fields) ? array_keys($fields) : [];
            } catch (\Throwable $e) {
                $cache[$class] = [];
            }
        }
        return in_array($field, $cache[$class]);
    }

    public function scopeActive($query)
    {
        $query->where('soft_delete', 0);
    }
}
