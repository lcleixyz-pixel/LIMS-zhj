<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Employee;
use app\model\ManagementReview;
use app\model\ReviewAction as ReviewActionModel;
use think\facade\View;

class ReviewAction extends CrudBase
{
    protected string $modelClass = ReviewActionModel::class;
    protected string $viewPrefix = 'review_action';
    protected string $pageTitle = '评审决议与措施';

    public function index()
    {
        $prototype = new ReviewActionModel();
        $query = ReviewActionModel::with(['managementReview']);
        if ($prototype->hasColumn('soft_delete')) {
            $query->where('soft_delete', 0);
        }
        $items = $query->order('created', 'desc')->paginate(20);
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('pageTitle', $this->pageTitle);

        return View::fetch($this->viewPrefix . '/index');
    }

    protected function assignFormContext(): void
    {
        View::assign(
            'reviews',
            ManagementReview::where('soft_delete', 0)->order('review_date', 'desc')->select()
        );
        View::assign('employees', Employee::where('soft_delete', 0)->select());
    }
}
