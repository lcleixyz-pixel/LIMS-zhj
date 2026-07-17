<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\ExternalEvidenceReferenceService;
use think\facade\Config;
use think\facade\Db;

if (
    !str_contains((string)Config::get('qms.environment_label', ''), '8011')
    && getenv('QMS_ALLOW_DESTRUCTIVE_SMOKE') !== '1'
) {
    fwrite(STDERR, "拒绝运行：外部证据安全回归仅允许在 8011 候选环境执行。\n");
    exit(2);
}

$subjectId = (string)Db::name('capas')
    ->where('company_id', (string)Config::get('qms.company_id'))
    ->where('soft_delete', 0)
    ->value('id');
if ($subjectId === '') {
    fwrite(STDERR, "[FAIL] 缺少可用于只读校验的 CAPA\n");
    exit(1);
}

$base = [
    'source_system' => 'G-R14 安全测试',
    'object_type' => '只读证据',
    'external_number' => 'SIM-EXT-SECURITY',
    'display_name' => '不会写入的非法链接测试',
];
$invalidUrls = [
    'https://example.test/t/token/abc123',
    'https://example.test/view?id=sk_live_example',
    'https://example.test/view?id=sk-proj-example',
    'https://example.test/view?id=xoxb-example-token',
    'https://example.test/view?id=AIzaExampleKeyValue',
    'https://example.test/view?id=eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.signature',
];

foreach ($invalidUrls as $index => $url) {
    $rejected = false;
    try {
        ExternalEvidenceReferenceService::create(
            'capa',
            $subjectId,
            $base + ['readonly_url' => $url]
        );
    } catch (\InvalidArgumentException $exception) {
        $rejected = str_contains($exception->getMessage(), '凭据');
    }
    if (!$rejected) {
        fwrite(STDERR, '[FAIL] EES' . ($index + 1) . " 疑似凭据链接未被拒绝\n");
        exit(1);
    }
    fwrite(STDOUT, '[PASS] EES' . ($index + 1) . " 疑似凭据链接被拒绝\n");
}

fwrite(STDOUT, "qms_gr14_external_evidence_security_runtime_smoke passed\n");
