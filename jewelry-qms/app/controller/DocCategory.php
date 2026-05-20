<?php
declare(strict_types=1);

namespace app\controller;

use app\model\DocCategory as DocCategoryModel;

class DocCategory extends CrudBase
{
    protected string $modelClass = DocCategoryModel::class;
    protected string $viewPrefix = 'doc_category';
    protected string $pageTitle = '文件分类';
}
