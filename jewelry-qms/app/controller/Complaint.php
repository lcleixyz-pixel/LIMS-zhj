<?php
declare(strict_types=1);

namespace app\controller;

use app\model\CustomerComplaint;

class Complaint extends CrudBase
{
    protected string $modelClass = CustomerComplaint::class;
    protected string $viewPrefix = 'complaint';
    protected string $pageTitle = '客户投诉';
}
