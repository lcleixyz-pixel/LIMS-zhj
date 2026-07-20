<?php
declare(strict_types=1);

namespace app\service;

class RecordFormPdfLayoutAuditService
{
    public static function buildVisualReview(int $year = 2025, array $options = []): array
    {
        $year = max(2000, min(2100, $year));
        $batchId = trim((string)($options['batch_id'] ?? 'pdf-visual-review'));
        if ($batchId === '') {
            $batchId = 'pdf-visual-review';
        }
        $limit = max(0, (int)($options['limit'] ?? 0));

        $dashboard = RecordFormBatchReviewService::build($year, false);
        $sourceRows = (array)($dashboard['rows'] ?? []);
        $sourceRows = array_values(array_filter(
            $sourceRows,
            static fn (array $row): bool => (string)($row['preview_pdf_path'] ?? '') !== ''
        ));
        if ($limit > 0) {
            $sourceRows = array_slice($sourceRows, 0, $limit);
        }

        $dir = self::artifactDir($year, $batchId);
        $thumbDir = $dir . DIRECTORY_SEPARATOR . 'thumbs';
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        $rows = [];
        foreach ($sourceRows as $row) {
            $rows[] = self::visualReviewRow($row, $year, $batchId, $thumbDir);
        }

        $report = [
            'batch_id' => $batchId,
            'year' => $year,
            'created_at' => date('Y-m-d H:i:s'),
            'summary' => [
                'total' => count($rows),
                'with_thumbnail' => count(array_filter($rows, static fn (array $row): bool => (string)($row['thumbnail_path'] ?? '') !== '')),
                'thumbnail_failed' => count(array_filter($rows, static fn (array $row): bool => (string)($row['thumbnail_path'] ?? '') === '')),
            ],
            'rows' => $rows,
        ];
        $report['report'] = self::writeVisualReview($report);

        return $report;
    }

    public static function audit(array $options = []): array
    {
        $year = max(2000, min(2100, (int)($options['year'] ?? 2025)));
        $limit = max(0, (int)($options['limit'] ?? 0));
        $batchId = trim((string)($options['batch_id'] ?? 'pdf-layout-audit'));
        if ($batchId === '') {
            $batchId = 'pdf-layout-audit';
        }

        $dashboard = RecordFormBatchReviewService::build($year, false);
        $rows = [];
        $sourceRows = (array)($dashboard['rows'] ?? []);
        if ($limit > 0) {
            $sourceRows = array_slice($sourceRows, 0, $limit);
        }

        foreach ($sourceRows as $row) {
            $rows[] = self::auditRow($row);
        }

        $summary = self::summary($rows, $year, count($sourceRows));
        $report = [
            'batch_id' => $batchId,
            'year' => $year,
            'created_at' => date('Y-m-d H:i:s'),
            'summary' => $summary,
            'rows' => $rows,
        ];
        $report['report'] = self::writeReport($report);

        return $report;
    }

    private static function auditRow(array $row): array
    {
        $path = (string)($row['preview_pdf_path'] ?? '');
        $fullPath = $path !== '' ? root_path() . $path : '';
        $issues = [];
        $info = [
            'pages' => null,
            'page_size' => '',
            'file_size_bytes' => 0,
            'text_chars' => 0,
            'text_lines' => 0,
        ];

        if ($fullPath === '' || !is_file($fullPath)) {
            $issues[] = 'missing_pdf';
        } else {
            $info['file_size_bytes'] = filesize($fullPath) ?: 0;
            if ($info['file_size_bytes'] < 50000) {
                $issues[] = 'small_pdf_file';
            }

            $pdfInfo = self::pdfInfo($fullPath);
            if ($pdfInfo['ok']) {
                $info['pages'] = $pdfInfo['pages'];
                $info['page_size'] = $pdfInfo['page_size'];
                if ((int)$pdfInfo['pages'] < 1) {
                    $issues[] = 'no_pages';
                }
                if ((int)$pdfInfo['pages'] > 6) {
                    $issues[] = 'many_pages';
                }
                if ($pdfInfo['page_size'] !== '' && !str_contains($pdfInfo['page_size'], 'A4')) {
                    $issues[] = 'non_a4_page_size';
                }
            } else {
                $issues[] = 'pdfinfo_failed';
            }

            $text = self::pdfText($fullPath);
            if ($text['ok']) {
                $info['text_chars'] = mb_strlen(trim($text['text']));
                $info['text_lines'] = substr_count(trim($text['text']), "\n") + (trim($text['text']) === '' ? 0 : 1);
                if ($info['text_chars'] < 20) {
                    $issues[] = 'low_extractable_text';
                }
            } else {
                $issues[] = 'pdftotext_failed';
            }
        }

        return [
            'instance_id' => (string)($row['id'] ?? ''),
            'doc_number' => (string)($row['doc_number'] ?? ''),
            'module' => (string)($row['module'] ?? ''),
            'name' => (string)($row['name'] ?? ''),
            'instance_url' => (string)($row['instance_url'] ?? ''),
            'print_url' => (string)($row['print_url'] ?? ''),
            'preview_pdf_url' => (string)($row['preview_pdf_url'] ?? ''),
            'preview_pdf_path' => $path,
            'pages' => $info['pages'],
            'page_size' => $info['page_size'],
            'file_size_bytes' => $info['file_size_bytes'],
            'text_chars' => $info['text_chars'],
            'text_lines' => $info['text_lines'],
            'blank_required_count' => (int)($row['blank_required_count'] ?? 0),
            'low_confidence_count' => (int)($row['low_confidence_count'] ?? 0),
            'ai_candidate_count' => (int)($row['ai_candidate_count'] ?? 0),
            'issues' => array_values(array_unique($issues)),
            'status' => $issues === [] ? 'ok' : 'attention',
        ];
    }

    private static function visualReviewRow(array $row, int $year, string $batchId, string $thumbDir): array
    {
        $id = (string)($row['id'] ?? '');
        $path = (string)($row['preview_pdf_path'] ?? '');
        $fullPath = $path !== '' ? root_path() . $path : '';
        $thumbName = self::safeFile((string)($row['doc_number'] ?? '') . '-' . $id) . '.png';
        $thumbPath = $thumbDir . DIRECTORY_SEPARATOR . $thumbName;
        $issues = [];

        if ($fullPath === '' || !is_file($fullPath)) {
            $issues[] = 'missing_pdf';
            $thumbPath = '';
        } elseif (!self::renderFirstPageThumbnail($fullPath, $thumbPath)) {
            $issues[] = 'thumbnail_failed';
            $thumbPath = '';
        }

        return [
            'instance_id' => $id,
            'doc_number' => (string)($row['doc_number'] ?? ''),
            'module' => (string)($row['module'] ?? ''),
            'name' => (string)($row['name'] ?? ''),
            'record_title' => (string)($row['record_title'] ?? ''),
            'instance_url' => (string)($row['instance_url'] ?? ''),
            'edit_url' => (string)($row['edit_url'] ?? ''),
            'print_url' => (string)($row['print_url'] ?? ''),
            'preview_pdf_url' => (string)($row['preview_pdf_url'] ?? ''),
            'thumbnail_path' => $thumbPath !== '' ? str_replace(root_path(), '', $thumbPath) : '',
            'thumbnail_url' => $thumbPath !== ''
                ? '/record_form_instance/reviewArtifact?year=' . $year . '&batch=' . rawurlencode($batchId) . '&file=' . rawurlencode('thumbs/' . $thumbName)
                : '',
            'blank_required_count' => (int)($row['blank_required_count'] ?? 0),
            'low_confidence_count' => (int)($row['low_confidence_count'] ?? 0),
            'ai_candidate_count' => (int)($row['ai_candidate_count'] ?? 0),
            'manual_layout_status' => (string)($row['manual_layout_status'] ?? 'pending'),
            'issues' => $issues,
        ];
    }

    private static function renderFirstPageThumbnail(string $pdfPath, string $thumbPath): bool
    {
        if (is_file($thumbPath) && filemtime($thumbPath) >= filemtime($pdfPath)) {
            return true;
        }

        $prefix = substr($thumbPath, 0, -4);
        $command = implode(' ', [
            'pdftoppm',
            '-f 1',
            '-l 1',
            '-singlefile',
            '-png',
            '-scale-to 420',
            escapeshellarg($pdfPath),
            escapeshellarg($prefix),
            '2>&1',
        ]);
        exec($command, $output, $code);

        return $code === 0 && is_file($thumbPath) && filesize($thumbPath) > 0;
    }

    private static function pdfInfo(string $path): array
    {
        $command = 'pdfinfo ' . escapeshellarg($path) . ' 2>&1';
        exec($command, $output, $code);
        $text = implode("\n", $output);
        if ($code !== 0) {
            return ['ok' => false, 'pages' => null, 'page_size' => '', 'raw' => $text];
        }

        $pages = null;
        if (preg_match('/^Pages:\s+(\d+)/mi', $text, $match) === 1) {
            $pages = (int)$match[1];
        }
        $pageSize = '';
        if (preg_match('/^Page size:\s+(.+)$/mi', $text, $match) === 1) {
            $pageSize = trim($match[1]);
        }

        return ['ok' => true, 'pages' => $pages, 'page_size' => $pageSize, 'raw' => $text];
    }

    private static function pdfText(string $path): array
    {
        $command = 'pdftotext -layout ' . escapeshellarg($path) . ' - 2>&1';
        exec($command, $output, $code);
        $text = implode("\n", $output);

        return ['ok' => $code === 0, 'text' => $code === 0 ? $text : '', 'raw' => $text];
    }

    private static function summary(array $rows, int $year, int $total): array
    {
        $attention = array_values(array_filter($rows, static fn (array $row): bool => ($row['status'] ?? '') !== 'ok'));
        $issueCounts = [];
        foreach ($rows as $row) {
            foreach ((array)($row['issues'] ?? []) as $issue) {
                $issueCounts[$issue] = ($issueCounts[$issue] ?? 0) + 1;
            }
        }
        ksort($issueCounts);

        return [
            'year' => $year,
            'total' => $total,
            'ok' => $total - count($attention),
            'attention' => count($attention),
            'missing_pdf' => $issueCounts['missing_pdf'] ?? 0,
            'issue_counts' => $issueCounts,
        ];
    }

    private static function writeReport(array $report): array
    {
        $year = (string)($report['year'] ?? date('Y'));
        $batchId = (string)($report['batch_id'] ?? 'pdf-layout-audit');
        $relativeDir = 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $batchId;
        $dir = root_path() . $relativeDir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $jsonPath = $dir . DIRECTORY_SEPARATOR . 'report.json';
        $markdownPath = $dir . DIRECTORY_SEPARATOR . 'report.md';
        file_put_contents($jsonPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($markdownPath, self::markdown($report));

        return [
            'json_path' => str_replace(root_path(), '', $jsonPath),
            'markdown_path' => str_replace(root_path(), '', $markdownPath),
        ];
    }

    private static function writeVisualReview(array $report): array
    {
        $year = (string)($report['year'] ?? date('Y'));
        $batchId = (string)($report['batch_id'] ?? 'pdf-visual-review');
        $dir = self::artifactDir((int)$year, $batchId);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $jsonPath = $dir . DIRECTORY_SEPARATOR . 'report.json';
        $markdownPath = $dir . DIRECTORY_SEPARATOR . 'report.md';
        $htmlPath = $dir . DIRECTORY_SEPARATOR . 'index.html';
        file_put_contents($jsonPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($markdownPath, self::visualMarkdown($report));
        file_put_contents($htmlPath, self::visualHtml($report));

        return [
            'json_path' => str_replace(root_path(), '', $jsonPath),
            'markdown_path' => str_replace(root_path(), '', $markdownPath),
            'html_path' => str_replace(root_path(), '', $htmlPath),
            'html_url' => '/record_form_instance/reviewArtifact?year=' . $year . '&batch=' . rawurlencode($batchId) . '&file=index.html',
        ];
    }

    private static function markdown(array $report): string
    {
        $summary = $report['summary'] ?? [];
        $lines = [
            '# 2025运行记录临时PDF版式巡检',
            '',
            '- 创建时间：' . (string)($report['created_at'] ?? ''),
            '- 巡检PDF：' . (int)($summary['total'] ?? 0),
            '- 正常：' . (int)($summary['ok'] ?? 0),
            '- 需关注：' . (int)($summary['attention'] ?? 0),
            '- 缺PDF：' . (int)($summary['missing_pdf'] ?? 0),
            '',
            '## 异常分类',
            '',
        ];
        if (($summary['issue_counts'] ?? []) === []) {
            $lines[] = '- 无自动巡检异常';
        } else {
            foreach ((array)$summary['issue_counts'] as $issue => $count) {
                $lines[] = '- ' . self::md((string)$issue) . '：' . (int)$count;
            }
        }

        $lines[] = '';
        $lines[] = '## 逐表巡检';
        $lines[] = '';
        $lines[] = '| 编号 | 表格 | 状态 | 页数 | 页面尺寸 | 文件KB | 文本字符 | 问题 | 实例 | PDF |';
        $lines[] = '| --- | --- | --- | ---: | --- | ---: | ---: | --- | --- | --- |';
        foreach ((array)($report['rows'] ?? []) as $row) {
            $lines[] = '| ' . implode(' | ', [
                self::md((string)($row['doc_number'] ?? '')),
                self::md((string)($row['name'] ?? '')),
                self::md((string)($row['status'] ?? '')),
                (string)($row['pages'] ?? '-'),
                self::md((string)($row['page_size'] ?? '-')),
                (string)round(((int)($row['file_size_bytes'] ?? 0)) / 1024, 1),
                (string)(int)($row['text_chars'] ?? 0),
                self::md(implode(', ', (array)($row['issues'] ?? [])) ?: '-'),
                ($row['instance_url'] ?? '') !== '' ? '[查看](' . (string)$row['instance_url'] . ')' : '-',
                ($row['preview_pdf_url'] ?? '') !== '' ? '[下载](' . (string)$row['preview_pdf_url'] . ')' : '-',
            ]) . ' |';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private static function visualMarkdown(array $report): string
    {
        $summary = $report['summary'] ?? [];
        $lines = [
            '# 2025运行记录PDF视觉索引',
            '',
            '- 创建时间：' . (string)($report['created_at'] ?? ''),
            '- 表格数：' . (int)($summary['total'] ?? 0),
            '- 已生成缩略图：' . (int)($summary['with_thumbnail'] ?? 0),
            '- 缩略图失败：' . (int)($summary['thumbnail_failed'] ?? 0),
            '',
            '| 编号 | 表格 | 缩略图 | 实例 | 打印 | PDF |',
            '| --- | --- | --- | --- | --- | --- |',
        ];
        foreach ((array)($report['rows'] ?? []) as $row) {
            $lines[] = '| ' . implode(' | ', [
                self::md((string)($row['doc_number'] ?? '')),
                self::md((string)($row['name'] ?? '')),
                ($row['thumbnail_url'] ?? '') !== '' ? '[预览](' . (string)$row['thumbnail_url'] . ')' : '-',
                ($row['instance_url'] ?? '') !== '' ? '[查看](' . (string)$row['instance_url'] . ')' : '-',
                ($row['print_url'] ?? '') !== '' ? '[打印](' . (string)$row['print_url'] . ')' : '-',
                ($row['preview_pdf_url'] ?? '') !== '' ? '[下载](' . (string)$row['preview_pdf_url'] . ')' : '-',
            ]) . ' |';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private static function visualHtml(array $report): string
    {
        $summary = $report['summary'] ?? [];
        $cards = [];
        foreach ((array)($report['rows'] ?? []) as $row) {
            $thumb = (string)($row['thumbnail_url'] ?? '');
            $badges = [];
            if ((int)($row['low_confidence_count'] ?? 0) > 0) {
                $badges[] = '<span>低置信 ' . (int)$row['low_confidence_count'] . '</span>';
            }
            if ((int)($row['ai_candidate_count'] ?? 0) > 0) {
                $badges[] = '<span>AI候选 ' . (int)$row['ai_candidate_count'] . '</span>';
            }
            if ((array)($row['issues'] ?? []) !== []) {
                $badges[] = '<span class="warn">' . self::e(implode(', ', (array)$row['issues'])) . '</span>';
            }
            $cards[] = '<article class="card">'
                . '<a class="thumb" href="' . self::e((string)($row['preview_pdf_url'] ?? '#')) . '" target="_blank">'
                . ($thumb !== '' ? '<img src="' . self::e($thumb) . '" alt="' . self::e((string)($row['doc_number'] ?? '')) . '">' : '<div class="missing">无缩略图</div>')
                . '</a>'
                . '<div class="meta">'
                . '<div class="doc">' . self::e((string)($row['doc_number'] ?? '')) . '</div>'
                . '<div class="name">' . self::e((string)($row['name'] ?? '')) . '</div>'
                . '<div class="module">' . self::e((string)($row['module'] ?? '')) . '</div>'
                . '<div class="badges">' . implode('', $badges) . '</div>'
                . '<div class="links">'
                . '<a href="' . self::e((string)($row['instance_url'] ?? '#')) . '">查看</a>'
                . '<a href="' . self::e((string)($row['edit_url'] ?? '#')) . '">编辑</a>'
                . '<a href="' . self::e((string)($row['print_url'] ?? '#')) . '" target="_blank">打印</a>'
                . '<a href="' . self::e((string)($row['preview_pdf_url'] ?? '#')) . '" target="_blank">PDF</a>'
                . '</div>'
                . '</div>'
                . '</article>';
        }

        return '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>2025运行记录PDF视觉索引</title>'
            . '<style>body{margin:0;background:#f6f7f9;color:#1f2937;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}'
            . 'header{position:sticky;top:0;background:#fff;border-bottom:1px solid #e5e7eb;padding:14px 20px;z-index:2}'
            . 'h1{font-size:18px;margin:0 0 4px}.summary{color:#6b7280;font-size:13px}'
            . '.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;padding:16px 20px}'
            . '.card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden}'
            . '.thumb{display:block;background:#eef2f7;aspect-ratio:1/1.42;overflow:hidden}.thumb img{width:100%;height:100%;object-fit:contain;display:block}'
            . '.missing{height:100%;display:grid;place-items:center;color:#9ca3af}.meta{padding:10px}.doc{font-weight:700;font-size:13px}.name{font-size:13px;margin-top:3px}.module{font-size:12px;color:#6b7280;margin-top:3px}'
            . '.badges{display:flex;flex-wrap:wrap;gap:4px;margin-top:8px}.badges span{font-size:11px;background:#e0f2fe;color:#075985;border-radius:999px;padding:2px 7px}.badges .warn{background:#fef3c7;color:#92400e}'
            . '.links{display:flex;flex-wrap:wrap;gap:8px;margin-top:9px}.links a{font-size:12px;color:#2563eb;text-decoration:none}</style>'
            . '</head><body><header><h1>2025运行记录PDF视觉索引</h1>'
            . '<div class="summary">表格 ' . (int)($summary['total'] ?? 0)
            . ' / 缩略图 ' . (int)($summary['with_thumbnail'] ?? 0)
            . ' / 失败 ' . (int)($summary['thumbnail_failed'] ?? 0)
            . ' / 创建时间 ' . self::e((string)($report['created_at'] ?? '')) . '</div></header>'
            . '<main class="grid">' . implode('', $cards) . '</main></body></html>';
    }

    private static function artifactDir(int $year, string $batchId): string
    {
        return root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-batches' . DIRECTORY_SEPARATOR
            . (string)$year . DIRECTORY_SEPARATOR . self::safeBatch($batchId);
    }

    private static function safeBatch(string $batchId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '-', $batchId) ?: 'pdf-visual-review';

        return trim($safe, '.-') ?: 'pdf-visual-review';
    }

    private static function safeFile(string $value): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $value) ?: 'record';

        return trim($safe, '.-') ?: 'record';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function md(string $value): string
    {
        return str_replace(["\n", "\r", '|'], [' ', ' ', '/'], $value);
    }
}
