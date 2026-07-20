<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\ImportService;
use think\facade\Session;
use think\facade\View;

class Import extends BaseController
{
    public function index()
    {
        return View::fetch('import/index');
    }

    public function upload()
    {
        $type = $this->request->post('type', 'document');
        $file = $this->request->file('file');
        if (!$file) {
            Session::flash('error', '请选择文件');

            return redirect('/import/index');
        }

        $ext = strtolower($file->extension());
        if (!in_array($ext, ['csv', 'xls', 'xlsx'], true)) {
            Session::flash('error', '仅支持 CSV/Excel 格式（Excel 请另存为 CSV）');

            return redirect('/import/index');
        }

        $savePath = runtime_path() . 'import_' . time() . '.csv';
        if ($ext !== 'csv') {
            Session::flash('warning', '当前版本请将 Excel 另存为 CSV 后上传');

            return redirect('/import/index');
        }
        $file->move(dirname($savePath), basename($savePath));
        $rows = ImportService::parseCsv($savePath);
        @unlink($savePath);

        $result = match ($type) {
            'equipment' => ImportService::importEquipments($rows),
            'employee' => ImportService::importEmployees($rows),
            default => ImportService::importDocuments($rows),
        };

        $msg = "成功导入 {$result['imported']} 条";
        if (!empty($result['errors'])) {
            $msg .= '，' . count($result['errors']) . ' 条失败';
            Session::flash('warning', implode('; ', array_slice($result['errors'], 0, 5)));
        }
        Session::flash('success', $msg);

        return redirect('/import/index');
    }
}
