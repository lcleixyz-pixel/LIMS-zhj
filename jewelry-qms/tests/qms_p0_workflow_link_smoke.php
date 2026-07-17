<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\model\CustomerComplaint;
use app\service\WorkflowService;
use think\facade\Config;
use think\facade\Db;

$passes = [];
$failures = [];

function p0_case(bool $condition, string $id, string $message): void
{
    global $passes, $failures;
    if ($condition) {
        $passes[] = $id . ' ' . $message;
    } else {
        $failures[] = $id . ' ' . $message;
    }
}

function p0_uuid(string $suffix): string
{
    return 'b2000000-0000-4000-8000-' . str_pad($suffix, 12, '0', STR_PAD_LEFT);
}

function p0_insert_complaint(string $id, string $number): void
{
    Db::name('customer_complaints')->insert([
        'id' => $id,
        'company_id' => (string)Config::get('qms.company_id'),
        'complaint_number' => $number,
        'customer_name' => 'P0R13B2 测试客户',
        'received_date' => '2026-07-17',
        'description' => 'P0R13B2 字段链测试',
        'status' => 'received',
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 0,
        'created' => '2026-07-17 00:00:00',
        'modified' => '2026-07-17 00:00:00',
    ]);
}

function p0_cleanup(): void
{
    Db::execute('DROP TRIGGER IF EXISTS p0_r13b2_force_link_failure');
    Db::name('capas')->whereLike('capa_number', 'P0R13B2%')->delete();
    Db::name('capas')->whereLike('source_record_id', 'b2000000-%')->delete();
    Db::name('customer_complaints')->whereLike('complaint_number', 'P0R13B2%')->delete();
    Db::name('customer_complaints')->whereLike('complaint_number', 'CP2026%')->delete();
    Db::name('nonconformities')->whereLike('nc_number', 'P0R13B2%')->delete();
}

function p0_has_unique_index(string $table, string $index): bool
{
    $rows = Db::query(
        'SELECT COUNT(*) AS c FROM information_schema.STATISTICS'
        . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
        [$table, $index]
    );

    return (int)($rows[0]['c'] ?? 0) > 0;
}

function p0_duplicate_rejected(string $table, array $row, string $id2): bool
{
    Db::name($table)->insert($row);
    $row['id'] = $id2;
    try {
        Db::name($table)->insert($row);
    } catch (Throwable) {
        return true;
    }

    return false;
}

$companyId = (string)Config::get('qms.company_id');
$complaintId = p0_uuid('401');
$ncId = p0_uuid('601');
$rollbackId = p0_uuid('701');
$invalidId = 'not-a-valid-uuid';
$firstCapa = null;

try {
    p0_cleanup();

    p0_insert_complaint(p0_uuid('101'), 'CP2026001');
    p0_case(
        qms_next_number('CP', CustomerComplaint::class, 'complaint_number') === 'CP2026002',
        'L01',
        '合法 001 后生成 002，不重复年份'
    );

    p0_insert_complaint(p0_uuid('102'), 'CP20262026002');
    p0_case(
        qms_next_number('CP', CustomerComplaint::class, 'complaint_number') === 'CP2026002',
        'L02',
        '异常旧编号不参与当年序号计算'
    );
    Db::name('customer_complaints')->whereLike('complaint_number', 'CP2026%')->delete();

    $requiredIndexes = [
        ['customer_complaints', 'uq_complaint_company_number'],
        ['capas', 'uq_capa_company_number'],
        ['nonconformities', 'uq_nc_company_number'],
        ['capas', 'uq_capa_company_source_record'],
    ];
    $allIndexesPresent = true;
    foreach ($requiredIndexes as [$table, $index]) {
        $allIndexesPresent = $allIndexesPresent && p0_has_unique_index($table, $index);
    }
    $duplicatesRejected = false;
    if ($allIndexesPresent) {
        $now = '2026-07-17 00:00:00';
        $duplicatesRejected =
            p0_duplicate_rejected('customer_complaints', [
                'id' => p0_uuid('301'),
                'company_id' => $companyId,
                'complaint_number' => 'P0R13B2-DUP-CP',
                'customer_name' => '重复编号测试',
                'received_date' => '2026-07-17',
                'description' => '重复编号测试',
                'status' => 'received',
                'publish' => 1,
                'soft_delete' => 0,
                'record_status' => 0,
                'created' => $now,
                'modified' => $now,
            ], p0_uuid('302'))
            && p0_duplicate_rejected('capas', [
                'id' => p0_uuid('303'),
                'company_id' => $companyId,
                'capa_number' => 'P0R13B2-DUP-CAPA',
                'description' => '重复编号测试',
                'status' => 'open',
                'publish' => 1,
                'soft_delete' => 0,
                'record_status' => 0,
                'created' => $now,
                'modified' => $now,
            ], p0_uuid('304'))
            && p0_duplicate_rejected('nonconformities', [
                'id' => p0_uuid('305'),
                'company_id' => $companyId,
                'nc_number' => 'P0R13B2-DUP-NC',
                'description' => '重复编号测试',
                'identified_date' => '2026-07-17',
                'status' => 'open',
                'publish' => 1,
                'soft_delete' => 0,
                'record_status' => 0,
                'created' => $now,
                'modified' => $now,
            ], p0_uuid('306'));
    }
    p0_case(
        $allIndexesPresent && $duplicatesRejected,
        'L03',
        '投诉、CAPA、不符合编号和 CAPA 来源组合由数据库唯一约束保护'
    );

    p0_insert_complaint($complaintId, 'P0R13B2-CP-001');
    $firstCapa = WorkflowService::createCapaFromSource(
        'complaint',
        $complaintId,
        'P0R13B2 投诉转 CAPA'
    );
    $linkedComplaintCapa = (string)Db::name('customer_complaints')->where('id', $complaintId)->value('capa_id');
    p0_case(
        preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string)$firstCapa->id) === 1
        && $linkedComplaintCapa === (string)$firstCapa->id,
        'L04',
        '投诉和新建 CAPA 保存同一 36 位 UUID'
    );

    $secondCapa = WorkflowService::createCapaFromSource(
        'complaint',
        $complaintId,
        '重复点击不应再建'
    );
    $complaintCapaCount = (int)Db::name('capas')
        ->where('source_type', 'complaint')
        ->where('source_record_id', $complaintId)
        ->where('soft_delete', 0)
        ->count();
    p0_case(
        (string)$secondCapa->id === (string)$firstCapa->id && $complaintCapaCount === 1,
        'L05',
        '同一来源重复创建返回既有 CAPA，总数仍为 1'
    );

    Db::name('nonconformities')->insert([
        'id' => $ncId,
        'company_id' => $companyId,
        'nc_number' => 'P0R13B2-NC-001',
        'description' => 'P0R13B2 不符合转 CAPA',
        'identified_date' => '2026-07-17',
        'severity' => 'major',
        'status' => 'open',
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 0,
        'created' => '2026-07-17 00:00:00',
        'modified' => '2026-07-17 00:00:00',
    ]);
    $ncCapa = WorkflowService::createCapaFromSource('nc', $ncId, 'P0R13B2 不符合转 CAPA');
    $linkedNcCapa = (string)Db::name('nonconformities')->where('id', $ncId)->value('capa_id');
    p0_case(
        $linkedNcCapa === (string)$ncCapa->id
        && (string)$ncCapa->source_record_id === $ncId
        && (string)$ncCapa->source_type === 'nc',
        'L06',
        '不符合与 CAPA 双向关联一致'
    );

    p0_insert_complaint($rollbackId, 'P0R13B2-CP-ROLLBACK');
    Db::execute(
        "CREATE TRIGGER p0_r13b2_force_link_failure BEFORE UPDATE ON customer_complaints"
        . " FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced backlink failure'"
    );
    try {
        WorkflowService::createCapaFromSource('complaint', $rollbackId, '必须整体回滚');
    } catch (Throwable) {
        // 预期：来源回写失败。
    } finally {
        Db::execute('DROP TRIGGER IF EXISTS p0_r13b2_force_link_failure');
    }
    $rollbackOrphans = (int)Db::name('capas')
        ->where('source_type', 'complaint')
        ->where('source_record_id', $rollbackId)
        ->count();
    p0_case($rollbackOrphans === 0, 'L07', '来源回写失败时 CAPA 创建整体回滚');

    $invalidBefore = (int)Db::name('capas')->count();
    $invalidRejected = false;
    try {
        WorkflowService::createCapaFromSource('complaint', $invalidId, '无效来源不得建 CAPA');
    } catch (InvalidArgumentException|RuntimeException) {
        $invalidRejected = true;
    }
    $invalidAfter = (int)Db::name('capas')->count();
    p0_case(
        $invalidRejected && $invalidBefore === $invalidAfter,
        'L08',
        '无效来源 UUID 返回领域错误且 0 写入'
    );

    $complaintController = (string)file_get_contents(dirname(__DIR__) . '/app/controller/Complaint.php');
    p0_case(
        $firstCapa !== null
        && str_contains($complaintController, "redirect('/capa/view?id=' . \$capa->id)")
        && strlen((string)$firstCapa->id) === 36,
        'L09',
        '创建成功跳转使用真实 CAPA UUID'
    );
} finally {
    p0_cleanup();
}

foreach ($passes as $pass) {
    echo "PASS {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}

if ($failures !== []) {
    fwrite(STDERR, sprintf(
        "qms_p0_workflow_link_smoke failed: %d passed, %d failed\n",
        count($passes),
        count($failures)
    ));
    exit(1);
}

echo "qms_p0_workflow_link_smoke passed: L01-L09\n";
