<?php
declare(strict_types=1);

namespace app\model;

class Company extends BaseModel
{
    protected $name = 'companies';

    protected $displayField = 'name';
}
