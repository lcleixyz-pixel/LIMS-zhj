<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\QmsSource;
use app\service\QmsElementService;
use app\service\QmsPlanningImportService;
use think\exception\HttpException;
use think\facade\Session;
use think\facade\View;

class PlanningSource extends BaseController
{
    public function index()
    {
        $items = QmsSource::where('soft_delete', 0)
            ->order('status', 'asc')
            ->order('source_code', 'asc')
            ->paginate(20);

        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('manifest', QmsPlanningImportService::officialSourceManifest());
        View::assign('processingRows', QmsElementService::externalSourceProcessingRows());

        return View::fetch('planning_source/index');
    }

    public function seed()
    {
        $summary = QmsElementService::seedExternalSources();
        Session::flash('success', '外部依据已登记并抽取到正式条款库：依据 ' . (int)$summary['sources'] . '，条款 ' . (int)$summary['clauses'] . '。');

        return redirect('/planning/sources');
    }

    public function upload()
    {
        $file = $this->request->file('source_file');
        if (!$file) {
            Session::flash('error', '请选择外部依据文件');

            return redirect('/planning/sources');
        }

        $ext = strtolower($file->extension());
        if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
            Session::flash('error', '外部依据仅支持 PDF、Word 文件');

            return redirect('/planning/sources');
        }

        $originalName = method_exists($file, 'getOriginalName') ? (string)$file->getOriginalName() : (string)$file->getFilename();
        if ($originalName === '') {
            $originalName = 'external-source.' . $ext;
        }

        $tempPath = runtime_path() . 'qms_source_upload_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $file->move(dirname($tempPath), basename($tempPath));

        try {
            $summary = QmsElementService::registerExternalSourceFile($tempPath, $originalName, [
                'freshness_checked_at' => (string)$this->request->post('freshness_checked_at', ''),
                'freshness_result' => (string)$this->request->post('freshness_result', ''),
                'freshness_evidence' => (string)$this->request->post('freshness_evidence', ''),
                'next_freshness_due' => (string)$this->request->post('next_freshness_due', ''),
                'freshness_status' => (string)$this->request->post('freshness_status', 'current'),
            ]);
            Session::flash('success', '外部依据已归档并抽取条款：'
                . (string)$summary['source']->source_code
                . '，条款 ' . (int)$summary['clauses']
                . '，Markdown结构 ' . (string)($summary['structured_rendered_path'] ?? '') . '。');
        } catch (\Throwable $exception) {
            Session::flash('error', '外部依据处理失败：' . $exception->getMessage());
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

        return redirect('/planning/sources');
    }

    public function freshness()
    {
        $source = $this->findSource();
        try {
            QmsElementService::updateSourceFreshness((string)$source->id, [
                'freshness_checked_at' => (string)$this->request->post('freshness_checked_at', ''),
                'freshness_result' => (string)$this->request->post('freshness_result', ''),
                'freshness_evidence' => (string)$this->request->post('freshness_evidence', ''),
                'next_freshness_due' => (string)$this->request->post('next_freshness_due', ''),
                'freshness_status' => (string)$this->request->post('freshness_status', 'unknown'),
            ]);
            Session::flash('success', '外部依据查新记录已更新。');
        } catch (\Throwable $exception) {
            Session::flash('error', '查新记录更新失败：' . $exception->getMessage());
        }

        return redirect('/planning/sources');
    }

    public function extractClauses()
    {
        $source = $this->findSource();
        $count = QmsElementService::upsertExternalClauses($source);
        Session::flash('success', '已从该外部依据更新正式条款库：' . $count . ' 条。');

        return redirect('/planning/clauses?source_id=' . $source->id);
    }

    public function obsolete()
    {
        $source = $this->findSource();
        $source->save([
            'status' => 'obsolete',
            'publish' => 0,
            'freshness_status' => 'obsolete',
        ]);
        Session::flash('success', '外部依据已标记废止，历史条款保留供追溯。');

        return redirect('/planning/sources');
    }

    private function findSource(): QmsSource
    {
        $source = QmsSource::where('soft_delete', 0)->find($this->request->param('id'));
        if (!$source) {
            throw new HttpException(404, '外部依据不存在');
        }

        return $source;
    }
}
