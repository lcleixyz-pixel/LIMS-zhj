<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\ComplianceCheckService;
use think\facade\Config;
use think\facade\Session;
use think\facade\View;

class Compliance extends BaseController
{
    public function index()
    {
        $companyId = (string)Config::get('qms.company_id');
        $scorecard = ComplianceCheckService::getLatestScorecard($companyId);
        $gaps = ComplianceCheckService::getAllGaps($companyId);
        $trend = ComplianceCheckService::scoreTrend($companyId, 10);
        $dimensions = ComplianceCheckService::dimensionLabels();
        $dimensionCheckCounts = array_fill_keys(array_keys($dimensions), 0);
        foreach ($scorecard['results'] ?? [] as $result) {
            $dimension = (string)($result['dimension'] ?? '');
            if (array_key_exists($dimension, $dimensionCheckCounts)) {
                $dimensionCheckCounts[$dimension]++;
            }
        }

        View::assign('scorecard', $scorecard);
        View::assign('summary', $scorecard['summary'] ?? []);
        View::assign('dimensionScores', $scorecard['dimension_scores'] ?? []);
        View::assign('dimensionCheckCounts', $dimensionCheckCounts);
        View::assign('gaps', $gaps);
        View::assign('trendJson', json_encode($trend, JSON_UNESCAPED_UNICODE));
        View::assign('dimensions', $dimensions);
        View::assign('hasSnapshot', $scorecard !== null);

        return View::fetch('compliance/index');
    }

    public function refresh()
    {
        $companyId = (string)Config::get('qms.company_id');
        $userId = Session::get('user.id');
        $result = ComplianceCheckService::runFullAssessment($companyId, 'manual', $userId ? (string)$userId : null);

        Session::flash('success', sprintf(
            '评估完成：总评分 %.1f，%d项通过，%d项不合规，%d项数据不足。',
            (float)$result['total_score'],
            (int)($result['summary']['pass'] ?? 0),
            (int)($result['summary']['fail'] ?? 0),
            (int)($result['summary']['insufficient_data'] ?? 0)
        ));

        return redirect('/compliance/index');
    }

    public function dimension()
    {
        $companyId = (string)Config::get('qms.company_id');
        $dimensions = ComplianceCheckService::dimensionLabels();
        $dimension = (string)$this->request->get('dim', 'equipment');
        if (!array_key_exists($dimension, $dimensions)) {
            $dimension = 'equipment';
        }

        $scorecard = ComplianceCheckService::getLatestScorecard($companyId);
        $results = [];
        if ($scorecard) {
            $results = array_values(array_filter(
                $scorecard['results'],
                static fn (array $row): bool => (string)$row['dimension'] === $dimension
            ));
        }

        View::assign('scorecard', $scorecard);
        View::assign('dimension', $dimension);
        View::assign('dimensionLabel', $dimensions[$dimension]);
        View::assign('dimensionScore', $scorecard['dimension_scores'][$dimension] ?? null);
        View::assign('results', $results);

        return View::fetch('compliance/dimension');
    }

    public function seed()
    {
        $companyId = (string)Config::get('qms.company_id');
        $count = ComplianceCheckService::seedDefaultChecks($companyId);
        Session::flash('success', "已初始化 {$count} 条判定规则。");

        return redirect('/compliance/index');
    }
}
