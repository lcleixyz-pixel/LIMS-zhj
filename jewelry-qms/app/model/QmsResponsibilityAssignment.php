<?php
declare(strict_types=1);

namespace app\model;

class QmsResponsibilityAssignment extends BaseModel
{
    protected $name = 'qms_responsibility_assignments';

    protected $type = [
        'competence_snapshot' => 'json',
        'validation_details' => 'json',
    ];

    public function responsibility()
    {
        return $this->belongsTo(QmsActivityResponsibility::class, 'responsibility_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
