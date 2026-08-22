<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

final class QualityWorkbenchService
{
    private const FORMAL_SYSTEM_NOTICE = '仅限 8021 测试环境内评审项目工作台；纸质体系仍是唯一正式体系，不构成 8010 发布、受控签署或真实运行记录迁移。';

    private const SYSTEM_PROJECTS = [
        'system-document-trace' => ['文件链路评审', 'document_trace', '65 份 GOV-TRIAL/0.3 制度文件、29 个质量要素、制度—要素链路与章节链路'],
        'system-record-forms' => ['记录表单评审', 'record_forms', '记录表单模板状态、字段结构、来源追溯和待复核状态'],
        'system-responsibility' => ['岗位职责评审', 'responsibility', '职责链版本、关键岗位和人员任命状态'],
        'system-equipment' => ['设备与计量评审', 'equipment', '设备台账、校准、期间核查、标准物质和能力路径缺口'],
        'system-personnel' => ['人员能力评审', 'personnel', '培训、能力确认、证书与关键岗位授权状态'],
        'system-audit' => ['内审评审', 'audit', '内审计划、日程、检查表、发现项和整改闭环'],
        'system-management-review' => ['管理评审', 'management_review', '管理评审输入、输出、措施和验证状态'],
        'system-improvement-risk' => ['改进与风险', 'capa', 'CAPA、不符合、投诉和外部变化事件'],
    ];

    public static function labels(): array
    {
        return [
            'project_status' => [
                'active' => '进行中',
                'blocked' => '被阻断',
                'ready_for_acceptance' => '可验收',
                'accepted' => '已验收',
                'archived' => '已归档',
            ],
            'task_status' => [
                'open' => '待处理',
                'in_progress' => '处理中',
                'review_required' => '待人工复核',
                'done' => '已完成',
                'waived' => '已保留/暂不处理',
                'blocked' => '被阻断',
            ],
            'severity' => [
                'blocker' => '阻断项',
                'warning' => '需关注',
                'info' => '提醒',
            ],
            'project_type' => [
                'document_trace' => '文件链路',
                'record_forms' => '记录表单',
                'responsibility' => '岗位职责',
                'equipment' => '设备与计量',
                'personnel' => '人员能力',
                'audit' => '内审',
                'management_review' => '管理评审',
                'capa' => '改进与风险',
                'complaint' => '投诉',
                'nonconformity' => '不符合',
                'external_change' => '外部变化',
                'custom' => '自定义',
            ],
        ];
    }

    public static function schemaSql(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS `quality_review_projects` (
                `id` varchar(64) NOT NULL,
                `company_id` varchar(64) NOT NULL DEFAULT '',
                `project_code` varchar(120) NOT NULL,
                `title` varchar(255) NOT NULL,
                `project_type` varchar(64) NOT NULL,
                `scope_label` varchar(255) NOT NULL DEFAULT '',
                `status` varchar(40) NOT NULL DEFAULT 'active',
                `conclusion` text NULL,
                `owner_role` varchar(64) NOT NULL DEFAULT '',
                `owner_user_id` varchar(64) NOT NULL DEFAULT '',
                `due_date` date NULL,
                `summary_json` mediumtext NULL,
                `accepted_at` datetime NULL,
                `accepted_by` varchar(64) NOT NULL DEFAULT '',
                `publish` tinyint(1) NOT NULL DEFAULT 1,
                `soft_delete` tinyint(1) NOT NULL DEFAULT 0,
                `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_quality_review_project_code` (`company_id`, `project_code`, `soft_delete`),
                KEY `idx_quality_review_project_status` (`status`, `project_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `quality_review_tasks` (
                `id` varchar(64) NOT NULL,
                `project_id` varchar(64) NOT NULL,
                `title` varchar(255) NOT NULL,
                `task_type` varchar(80) NOT NULL,
                `severity` varchar(20) NOT NULL DEFAULT 'warning',
                `status` varchar(40) NOT NULL DEFAULT 'open',
                `source_model` varchar(120) NOT NULL DEFAULT '',
                `source_id` varchar(120) NOT NULL DEFAULT '',
                `assigned_role` varchar(64) NOT NULL DEFAULT '',
                `assigned_user_id` varchar(64) NOT NULL DEFAULT '',
                `due_date` date NULL,
                `action_url` varchar(512) NOT NULL DEFAULT '',
                `evidence_summary` text NULL,
                `payload_json` mediumtext NULL,
                `completed_at` datetime NULL,
                `completed_by` varchar(64) NOT NULL DEFAULT '',
                `publish` tinyint(1) NOT NULL DEFAULT 1,
                `soft_delete` tinyint(1) NOT NULL DEFAULT 0,
                `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_quality_review_task_source` (`project_id`, `task_type`, `source_model`, `source_id`, `soft_delete`),
                KEY `idx_quality_review_task_status` (`status`, `severity`),
                KEY `idx_quality_review_task_assignee` (`assigned_role`, `assigned_user_id`, `due_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `quality_review_events` (
                `id` varchar(64) NOT NULL,
                `project_id` varchar(64) NOT NULL,
                `task_id` varchar(64) NOT NULL DEFAULT '',
                `event_type` varchar(60) NOT NULL,
                `old_status` varchar(40) NOT NULL DEFAULT '',
                `new_status` varchar(40) NOT NULL DEFAULT '',
                `note` text NULL,
                `created_by` varchar(64) NOT NULL DEFAULT '',
                `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_quality_review_event_project` (`project_id`, `created`),
                KEY `idx_quality_review_event_task` (`task_id`, `created`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }

    public function ensureSchema(): void
    {
        foreach (self::schemaSql() as $sql) {
            Db::execute($sql);
        }
    }

    public function schemaReady(): bool
    {
        return self::tableExists('quality_review_projects')
            && self::tableExists('quality_review_tasks')
            && self::tableExists('quality_review_events');
    }

    public function previewSystemProjects(): array
    {
        $projects = [
            $this->documentTraceProject(),
            $this->recordFormsProject(),
            $this->responsibilityProject(),
            $this->equipmentProject(),
            $this->personnelProject(),
            $this->auditProject(),
            $this->managementReviewProject(),
            $this->improvementRiskProject(),
        ];

        return [
            'mode' => 'dry_run',
            'project_count' => count($projects),
            'task_count' => array_sum(array_map(static fn(array $project): int => count($project['tasks'] ?? []), $projects)),
            'projects' => $projects,
            'formal_system_notice' => self::FORMAL_SYSTEM_NOTICE,
        ];
    }

    public function refreshSystemProjects(bool $apply = false, bool $notify = false): array
    {
        $preview = $this->previewSystemProjects();
        if (!$apply) {
            return $preview;
        }

        $this->assertWritableTrialEnvironment();
        $this->ensureSchema();

        $summary = [
            'created_projects' => 0,
            'updated_projects' => 0,
            'created_tasks' => 0,
            'updated_tasks' => 0,
            'soft_deleted_tasks' => 0,
            'events' => 0,
        ];

        Db::transaction(function () use ($preview, &$summary, $notify): void {
            foreach ($preview['projects'] as $projectPlan) {
                $project = $this->upsertProject($projectPlan, $summary);
                $liveTaskIds = [];
                foreach ($projectPlan['tasks'] as $taskPlan) {
                    $task = $this->upsertTask((string)$project['id'], $taskPlan, $summary);
                    $liveTaskIds[] = (string)$task['id'];
                    if ($notify && ($taskPlan['severity'] ?? '') === 'blocker') {
                        $this->notifyQualityManager((string)$project['id'], (string)$taskPlan['title'], (string)($taskPlan['evidence_summary'] ?? ''));
                    }
                }

                $query = Db::name('quality_review_tasks')
                    ->where('project_id', (string)$project['id'])
                    ->where('soft_delete', 0);
                if ($liveTaskIds !== []) {
                    $query->whereNotIn('id', $liveTaskIds);
                }
                $summary['soft_deleted_tasks'] += (int)$query->update([
                    'soft_delete' => 1,
                    'modified' => date('Y-m-d H:i:s'),
                ]);

                $this->recalculateProjectStatus((string)$project['id'], $summary);
            }
        });

        return [
            'mode' => 'apply',
            'summary' => $summary,
            'projects' => $preview['projects'],
            'validation' => $this->validationSummary(),
            'formal_system_notice' => self::FORMAL_SYSTEM_NOTICE,
        ];
    }

    public function dashboardSummary(?string $userId = null): array
    {
        if (!$this->schemaReady()) {
            return [
                'needs_setup' => true,
                'preview' => $this->previewSystemProjects(),
                'labels' => self::labels(),
                'formal_system_notice' => self::FORMAL_SYSTEM_NOTICE,
            ];
        }

        $userId = trim((string)$userId);
        $projects = Db::name('quality_review_projects')
            ->where('soft_delete', 0)
            ->orderRaw("FIELD(status,'blocked','ready_for_acceptance','active','accepted','archived')")
            ->order('modified', 'desc')
            ->select()
            ->toArray();
        foreach ($projects as &$project) {
            $project = $this->decorateProject($project);
        }
        unset($project);

        $role = (string)Session::get('user.role', '');
        $todoQuery = Db::name('quality_review_tasks')
            ->where('soft_delete', 0)
            ->whereNotIn('status', ['done', 'waived']);
        if ($userId !== '') {
            $todoQuery->where(function ($query) use ($userId, $role): void {
                $query->where('assigned_user_id', $userId);
                if ($role !== '') {
                    $query->whereOr('assigned_role', $role);
                }
            });
        }

        $tasks = $todoQuery->orderRaw("FIELD(severity,'blocker','warning','info')")
            ->order('due_date', 'asc')
            ->limit(12)
            ->select()
            ->toArray();
        foreach ($tasks as &$task) {
            $task = $this->decorateTask($task);
        }
        unset($task);

        $blockers = Db::name('quality_review_tasks')
            ->where('soft_delete', 0)
            ->where('severity', 'blocker')
            ->whereNotIn('status', ['done', 'waived'])
            ->order('due_date', 'asc')
            ->limit(12)
            ->select()
            ->toArray();
        foreach ($blockers as &$task) {
            $task = $this->decorateTask($task);
        }
        unset($task);

        $events = Db::name('quality_review_events')
            ->alias('event')
            ->leftJoin('quality_review_projects project', 'project.id=event.project_id')
            ->field('event.*, project.title as project_title')
            ->order('event.created', 'desc')
            ->limit(10)
            ->select()
            ->toArray();
        foreach ($events as &$event) {
            $event = $this->decorateEvent($event);
        }
        unset($event);

        return [
            'needs_setup' => false,
            'labels' => self::labels(),
            'formal_system_notice' => self::FORMAL_SYSTEM_NOTICE,
            'projects' => $projects,
            'project_status_counts' => $this->statusCounts('quality_review_projects', 'status'),
            'today_todos' => $tasks,
            'blockers' => $blockers,
            'ready_projects' => array_values(array_filter($projects, static fn(array $row): bool => ($row['status'] ?? '') === 'ready_for_acceptance')),
            'risk_tasks' => $this->riskTasks(),
            'recent_events' => $events,
            'next_action' => $this->nextAction($tasks, $blockers, $projects),
        ];
    }

    public function listProjects(?string $status = null): array
    {
        if (!$this->schemaReady()) {
            return ['needs_setup' => true, 'projects' => [], 'labels' => self::labels()];
        }

        $query = Db::name('quality_review_projects')->where('soft_delete', 0);
        $status = trim((string)$status);
        if ($status !== '') {
            $query->where('status', $status);
        }

        $projects = $query->orderRaw("FIELD(status,'blocked','ready_for_acceptance','active','accepted','archived')")
            ->order('modified', 'desc')
            ->select()
            ->toArray();

        foreach ($projects as &$project) {
            $project['summary'] = $this->decodeJson((string)($project['summary_json'] ?? ''));
            $project['task_counts'] = $this->taskCounts((string)$project['id']);
            $project = $this->decorateProject($project);
        }
        unset($project);

        return [
            'needs_setup' => false,
            'projects' => $projects,
            'labels' => self::labels(),
            'selected_status' => $status,
            'formal_system_notice' => self::FORMAL_SYSTEM_NOTICE,
        ];
    }

    public function projectDetail(string $projectId): array
    {
        if (!$this->schemaReady()) {
            throw new RuntimeException('质量工作台表尚未初始化，请先运行 qms:refresh-quality-workbench --apply --ack-quality-workbench。');
        }

        $project = Db::name('quality_review_projects')
            ->where('id', $projectId)
            ->where('soft_delete', 0)
            ->find();
        if (!is_array($project)) {
            throw new RuntimeException('评审项目不存在或已删除。');
        }

        $tasks = Db::name('quality_review_tasks')
            ->where('project_id', $projectId)
            ->where('soft_delete', 0)
            ->orderRaw("FIELD(severity,'blocker','warning','info')")
            ->orderRaw("FIELD(status,'blocked','open','in_progress','review_required','done','waived')")
            ->order('created', 'asc')
            ->select()
            ->toArray();

        $events = Db::name('quality_review_events')
            ->where('project_id', $projectId)
            ->order('created', 'desc')
            ->limit(50)
            ->select()
            ->toArray();

        $project['summary'] = $this->decodeJson((string)($project['summary_json'] ?? ''));
        $project['summary_text'] = $this->encodeJson($project['summary']);
        $project = $this->decorateProject($project);
        foreach ($tasks as &$task) {
            $task = $this->decorateTask($task);
        }
        unset($task);
        foreach ($events as &$event) {
            $event = $this->decorateEvent($event);
        }
        unset($event);

        return [
            'project' => $project,
            'tasks' => $tasks,
            'events' => $events,
            'task_counts' => $this->taskCounts($projectId),
            'labels' => self::labels(),
            'formal_system_notice' => self::FORMAL_SYSTEM_NOTICE,
            'page_answers' => [
                'what' => '这页在评：' . (string)$project['title'] . '。',
                'conclusion' => '当前结论：' . ((string)($project['conclusion'] ?? '') !== '' ? (string)$project['conclusion'] : '尚未形成最终验收结论。'),
                'next' => $this->projectNextAction($tasks),
            ],
        ];
    }

    public function transitionTask(string $taskId, string $action, string $note): array
    {
        $this->ensureSchema();
        $task = Db::name('quality_review_tasks')->where('id', $taskId)->where('soft_delete', 0)->find();
        if (!is_array($task)) {
            throw new RuntimeException('任务不存在或已删除。');
        }

        $action = trim($action);
        $note = trim($note);
        $target = match ($action) {
            'start' => 'in_progress',
            'done' => 'done',
            'waive' => 'waived',
            'block' => 'blocked',
            'reopen' => 'open',
            default => throw new RuntimeException('任务动作无效。'),
        };
        if ($target === 'waived' && $note === '') {
            throw new RuntimeException('保留/暂不处理必须填写说明。');
        }

        $old = (string)$task['status'];
        Db::name('quality_review_tasks')->where('id', $taskId)->update([
            'status' => $target,
            'completed_at' => in_array($target, ['done', 'waived'], true) ? date('Y-m-d H:i:s') : null,
            'completed_by' => in_array($target, ['done', 'waived'], true) ? (string)Session::get('user.id', '') : '',
            'modified' => date('Y-m-d H:i:s'),
        ]);
        $this->recordEvent((string)$task['project_id'], $taskId, 'task_transition', $old, $target, $note);

        $summary = ['events' => 0];
        $this->recalculateProjectStatus((string)$task['project_id'], $summary);

        return ['project_id' => (string)$task['project_id'], 'old_status' => $old, 'new_status' => $target];
    }

    public function acceptProject(string $projectId, string $decision, string $note): array
    {
        $this->ensureSchema();
        $project = Db::name('quality_review_projects')->where('id', $projectId)->where('soft_delete', 0)->find();
        if (!is_array($project)) {
            throw new RuntimeException('评审项目不存在或已删除。');
        }

        $decision = trim($decision);
        $note = trim($note);
        $activeBlockers = Db::name('quality_review_tasks')
            ->where('project_id', $projectId)
            ->where('soft_delete', 0)
            ->where('severity', 'blocker')
            ->whereNotIn('status', ['done', 'waived'])
            ->count();

        $old = (string)$project['status'];
        $conclusion = $note !== '' ? $note : (string)($project['conclusion'] ?? '');
        $target = match ($decision) {
            'accept' => 'accepted',
            'retain' => 'accepted',
            'block' => 'blocked',
            'return' => 'active',
            default => throw new RuntimeException('项目验收动作无效。'),
        };
        if ($decision === 'accept' && $activeBlockers > 0) {
            throw new RuntimeException('仍有阻断项未关闭，不能验收通过。');
        }
        if (in_array($decision, ['retain', 'block', 'return'], true) && $note === '') {
            throw new RuntimeException('该验收动作必须填写说明。');
        }
        if ($decision === 'retain') {
            $conclusion = '保留待复核：' . $note;
        } elseif ($decision === 'block') {
            $conclusion = '标记阻断：' . $note;
        } elseif ($decision === 'return') {
            $conclusion = '退回补充：' . $note;
        } elseif ($conclusion === '') {
            $conclusion = '验收通过：工作台内本评审项目已处理到当前状态。';
        }

        Db::name('quality_review_projects')->where('id', $projectId)->update([
            'status' => $target,
            'conclusion' => $conclusion,
            'accepted_at' => in_array($decision, ['accept', 'retain'], true) ? date('Y-m-d H:i:s') : null,
            'accepted_by' => in_array($decision, ['accept', 'retain'], true) ? (string)Session::get('user.id', '') : '',
            'modified' => date('Y-m-d H:i:s'),
        ]);
        $this->recordEvent($projectId, '', 'project_acceptance', $old, $target, $conclusion);

        return ['project_id' => $projectId, 'old_status' => $old, 'new_status' => $target, 'conclusion' => $conclusion];
    }

    public function validationSummary(): array
    {
        if (!$this->schemaReady()) {
            return ['ok' => false, 'errors' => ['质量工作台表尚未初始化']];
        }

        $projectCount = (int)Db::name('quality_review_projects')->where('soft_delete', 0)->count();
        $taskCount = (int)Db::name('quality_review_tasks')->where('soft_delete', 0)->count();
        $duplicateTasks = (int)Db::query(
            "SELECT COUNT(*) AS c FROM (
                SELECT project_id, task_type, source_model, source_id, COUNT(*) AS n
                FROM quality_review_tasks
                WHERE soft_delete=0
                GROUP BY project_id, task_type, source_model, source_id
                HAVING n > 1
            ) t"
        )[0]['c'];

        $errors = [];
        if ($projectCount !== 8) {
            $errors[] = '系统评审项目应为8个，当前为' . $projectCount;
        }
        if ($duplicateTasks !== 0) {
            $errors[] = '存在重复任务键：' . $duplicateTasks;
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'project_count' => $projectCount,
            'task_count' => $taskCount,
            'duplicate_task_keys' => $duplicateTasks,
        ];
    }

    private function documentTraceProject(): array
    {
        [$title, $type, $scope] = self::SYSTEM_PROJECTS['system-document-trace'];
        $tasks = [];
        $summary = [];
        $conclusion = '文件链路待读取 8021 测试正式视图。';

        try {
            $verification = FinalCandidateTraceSyncService::verifyFormalTrace();
            $counts = $verification['counts'] ?? [];
            $summary = ['counts' => $counts, 'verification_errors' => $verification['errors'] ?? []];
            $expected = [
                'candidate_documents' => 65,
                'candidate_published_documents' => 65,
                'candidate_structures' => 65,
                'candidate_blocks' => 315,
                'active_elements' => 29,
                'candidate_element_documents' => 119,
                'candidate_block_links' => 254,
                'old_active_element_documents' => 0,
                'old_active_block_links' => 0,
                'active_non_candidate_documents' => 0,
                'active_non_candidate_structures' => 0,
                'candidate_template_block_links' => 0,
            ];
            foreach ($expected as $key => $value) {
                if ((int)($counts[$key] ?? -1) !== $value) {
                    $tasks[] = $this->task(
                        'trace_count_' . $key,
                        '核对文件链路指标：' . $key,
                        'blocker',
                        'blocked',
                        'FinalCandidateTraceSyncService',
                        $key,
                        '/planning/traceability',
                        '期望 ' . $value . '，当前 ' . (string)($counts[$key] ?? '未读取')
                    );
                }
            }
            foreach (($verification['errors'] ?? []) as $index => $error) {
                $tasks[] = $this->task('trace_verify_error', '处理文件链路验证错误', 'blocker', 'blocked', 'FinalCandidateTraceSyncService', (string)$index, '/planning/traceability', (string)$error);
            }
            $tasks[] = $this->task('trace_review_required', '人工复核手册、程序、作业指导书链路语义是否贴合', 'warning', 'review_required', 'QmsDocumentBlockLink', 'semantic_review', '/planning/traceability', '系统已形成章节链路，但“满足链路”仍需要质量负责人确认语义是否够用。');
            $tasks[] = $this->task('trace_record_boundary', '记录表单不在本轮文件链路中自动闭合', 'info', 'review_required', 'RecordFormTemplate', 'boundary', '/record_form_template/review', '本轮不自动把记录表单伪造成运行证据；记录表单由单独评审项目处理。');
            $conclusion = empty(array_filter($tasks, static fn(array $task): bool => ($task['severity'] ?? '') === 'blocker'))
                ? '文件链路基本满足：65份制度、29个要素、制度—要素链路和章节链路已形成；语义贴合仍保留人工复核。'
                : '文件链路存在阻断项，需先处理数量或旧版本混入问题。';
        } catch (\Throwable $exception) {
            $tasks[] = $this->task('trace_unavailable', '文件链路验证服务不可用', 'blocker', 'blocked', 'FinalCandidateTraceSyncService', 'exception', '/planning/traceability', $exception->getMessage());
            $summary = ['error' => $exception->getMessage()];
            $conclusion = '文件链路无法自动验证，需先恢复 8021 链路服务。';
        }

        return $this->project('system-document-trace', $title, $type, $scope, $conclusion, $summary, $tasks);
    }

    private function recordFormsProject(): array
    {
        [$title, $type, $scope] = self::SYSTEM_PROJECTS['system-record-forms'];
        $summary = ['table_ready' => self::tableExists('record_form_templates')];
        $tasks = [];
        if (!$summary['table_ready']) {
            $tasks[] = $this->task('record_forms_table_missing', '记录表单模板表未接入', 'blocker', 'blocked', 'RecordFormTemplate', 'schema', '/record_form_template/index', '无法读取 record_form_templates。');
            return $this->project('system-record-forms', $title, $type, $scope, '记录表单无法读取，需先恢复模板表。', $summary, $tasks);
        }

        $summary['templates'] = (int)Db::name('record_form_templates')->where('soft_delete', 0)->count();
        $summary['trial_ready'] = (int)Db::name('record_form_templates')->where('soft_delete', 0)->where('status', 'trial_ready')->count();
        $summary['published'] = (int)Db::name('record_form_templates')->where('soft_delete', 0)->where('status', 'published')->count();
        if (self::columnExists('record_form_templates', 'review_status')) {
            $summary['review_required'] = (int)Db::name('record_form_templates')
                ->where('soft_delete', 0)
                ->whereIn('review_status', ['pending', 'needs_fidelity', 'needs_review', 'deferred', 'review_required'])
                ->count();
        }
        if (self::columnExists('record_form_templates', 'schema_json')) {
            $summary['empty_schema'] = (int)Db::name('record_form_templates')
                ->where('soft_delete', 0)
                ->where(function ($query): void {
                    $query->whereNull('schema_json')->whereOr('schema_json', '');
                })
                ->count();
        } elseif (self::columnExists('record_form_templates', 'field_schema')) {
            $summary['empty_schema'] = (int)Db::name('record_form_templates')
                ->where('soft_delete', 0)
                ->where(function ($query): void {
                    $query->whereNull('field_schema')->whereOr('field_schema', '');
                })
                ->count();
        }

        if (($summary['empty_schema'] ?? 0) > 0) {
            $tasks[] = $this->task('record_empty_schema', '补齐字段结构为空的记录表单模板', 'blocker', 'open', 'RecordFormTemplate', 'empty_schema', '/record_form_template/review', '字段结构为空数量：' . $summary['empty_schema']);
        }
        if (($summary['review_required'] ?? 0) > 0) {
            $tasks[] = $this->task('record_review_required', '处理待复核记录表单模板', 'warning', 'review_required', 'RecordFormTemplate', 'review_required', '/record_form_template/review', '待复核模板数量：' . $summary['review_required']);
        }
        $tasks[] = $this->task('record_runtime_boundary', '确认记录表单仅纳入模板评审，不伪造运行记录闭环', 'info', 'review_required', 'RecordFormInstance', 'boundary', '/record_form_instance/index', '本工作台只显示模板和缺口，不自动生成真实运行证据。');

        return $this->project('system-record-forms', $title, $type, $scope, '记录表单已纳入工作台；运行记录闭合不在本轮自动判定。', $summary, $tasks);
    }

    private function responsibilityProject(): array
    {
        [$title, $type, $scope] = self::SYSTEM_PROJECTS['system-responsibility'];
        $summary = [];
        $tasks = [];
        if (!self::tableExists('qms_responsibility_chain_versions')) {
            $tasks[] = $this->task('responsibility_table_missing', '职责链版本表未接入', 'warning', 'review_required', 'QmsResponsibilityChainVersion', 'schema', '/planning/responsibilities', '无法读取职责链版本。');
        } else {
            $summary['versions'] = (int)Db::name('qms_responsibility_chain_versions')->where('soft_delete', 0)->count();
            $summary['published_or_effective'] = (int)Db::name('qms_responsibility_chain_versions')->where('soft_delete', 0)->whereIn('status', ['published', 'effective', 'active'])->count();
            if ($summary['published_or_effective'] <= 0) {
                $tasks[] = $this->task('responsibility_no_effective_version', '确认职责链有效版本', 'blocker', 'open', 'QmsResponsibilityChainVersion', 'effective', '/planning/responsibilities', '未读取到 published/effective/active 状态的职责链版本。');
            }
        }
        foreach (['质量负责人', '技术负责人', '授权签字人'] as $roleName) {
            $tasks[] = $this->task('responsibility_key_role_review', '复核关键岗位责任：' . $roleName, 'warning', 'review_required', 'QmsResponsibilityAssignment', $roleName, '/planning/responsibilities', '关键岗位责任需要人工确认岗位名、任命与职责链一致。');
        }

        return $this->project('system-responsibility', $title, $type, $scope, '岗位职责链已纳入工作台；关键岗位仍需人工确认任命与名称不漂移。', $summary, $tasks);
    }

    private function equipmentProject(): array
    {
        [$title, $type, $scope] = self::SYSTEM_PROJECTS['system-equipment'];
        $summary = ['equipment_table_ready' => self::tableExists('equipments')];
        $tasks = [];
        if ($summary['equipment_table_ready']) {
            $summary['equipment_count'] = (int)Db::name('equipments')->where('soft_delete', 0)->count();
            if (self::columnExists('equipments', 'next_calibration_date')) {
                $summary['calibration_overdue'] = (int)Db::name('equipments')
                    ->where('soft_delete', 0)->where('calibration_required', 1)
                    ->whereNotNull('next_calibration_date')->where('next_calibration_date', '<', date('Y-m-d'))->count();
                $summary['calibration_due_30d'] = (int)Db::name('equipments')
                    ->where('soft_delete', 0)->where('calibration_required', 1)
                    ->whereNotNull('next_calibration_date')->where('next_calibration_date', '<=', date('Y-m-d', strtotime('+30 days')))->count();
                if ($summary['calibration_overdue'] > 0) {
                    $tasks[] = $this->task('equipment_calibration_overdue', '处理超期校准设备', 'blocker', 'open', 'Equipment', 'calibration_overdue', '/equipment/index', '超期校准设备数量：' . $summary['calibration_overdue']);
                } elseif ($summary['calibration_due_30d'] > 0) {
                    $tasks[] = $this->task('equipment_calibration_due', '关注30天内到期校准设备', 'warning', 'open', 'Equipment', 'calibration_due_30d', '/equipment/index', '30天内到期数量：' . $summary['calibration_due_30d']);
                }
            }
        } else {
            $tasks[] = $this->task('equipment_table_missing', '设备台账表未接入', 'warning', 'review_required', 'Equipment', 'schema', '/equipment/index', '无法读取 equipments。');
        }
        $tasks[] = $this->task('equipment_capability_path', '复核关键设备能力路径是否可见', 'warning', 'review_required', 'EquipmentAuthorization', 'capability_path', '/equipment_authorization/index', '校准状态可读不等于能力路径已完全闭合，需要人工复核关键设备。');

        return $this->project('system-equipment', $title, $type, $scope, '设备与计量风险已汇总；关键设备能力路径仍需质量负责人复核。', $summary, $tasks);
    }

    private function personnelProject(): array
    {
        [$title, $type, $scope] = self::SYSTEM_PROJECTS['system-personnel'];
        $summary = [];
        $tasks = [];
        foreach (['training_records', 'competency_records', 'employee_certificates'] as $table) {
            $summary[$table . '_ready'] = self::tableExists($table);
        }
        if (self::tableExists('competency_records') && self::columnExists('competency_records', 'valid_until')) {
            $summary['competency_expired'] = (int)Db::name('competency_records')->where('soft_delete', 0)->whereNotNull('valid_until')->where('valid_until', '<', date('Y-m-d'))->count();
            if ($summary['competency_expired'] > 0) {
                $tasks[] = $this->task('personnel_competency_expired', '处理已到期能力确认', 'blocker', 'open', 'CompetencyRecord', 'expired', '/competency_record/index', '已到期能力确认数量：' . $summary['competency_expired']);
            }
        }
        if (self::tableExists('employee_certificates') && self::columnExists('employee_certificates', 'valid_until')) {
            $summary['certificate_expired'] = (int)Db::name('employee_certificates')->where('soft_delete', 0)->whereNotNull('valid_until')->where('valid_until', '<', date('Y-m-d'))->count();
            if ($summary['certificate_expired'] > 0) {
                $tasks[] = $this->task('personnel_certificate_expired', '处理已到期人员证书', 'blocker', 'open', 'EmployeeCertificate', 'expired', '/employee_certificate/index', '已到期证书数量：' . $summary['certificate_expired']);
            }
        }
        $tasks[] = $this->task('personnel_authorization_review', '复核授权岗位能力证据', 'warning', 'review_required', 'CompetencyRecord', 'key_roles', '/competency_record/index', '授权签字人、监督员、关键检测岗位的能力证据需要人工确认。');

        return $this->project('system-personnel', $title, $type, $scope, '人员能力状态已汇总；授权岗位仍需人工复核证据充分性。', $summary, $tasks);
    }

    private function auditProject(): array
    {
        [$title, $type, $scope] = self::SYSTEM_PROJECTS['system-audit'];
        $summary = [];
        $tasks = [];
        if (self::tableExists('audit_findings')) {
            $summary['open_findings'] = (int)Db::name('audit_findings')->where('soft_delete', 0)->whereNotIn('status', ['closed', 'verified'])->count();
            if (self::columnExists('audit_findings', 'due_date')) {
                $summary['overdue_findings'] = (int)Db::name('audit_findings')->where('soft_delete', 0)->whereNotIn('status', ['closed', 'verified'])->whereNotNull('due_date')->where('due_date', '<', date('Y-m-d'))->count();
            }
            if (($summary['overdue_findings'] ?? 0) > 0) {
                $tasks[] = $this->task('audit_finding_overdue', '关闭或重新评审超期内审发现', 'blocker', 'open', 'AuditFinding', 'overdue', '/audit_finding/index', '超期内审发现数量：' . $summary['overdue_findings']);
            } elseif ($summary['open_findings'] > 0) {
                $tasks[] = $this->task('audit_finding_open', '跟踪未关闭内审发现', 'warning', 'open', 'AuditFinding', 'open', '/audit_finding/index', '未关闭发现数量：' . $summary['open_findings']);
            }
        } else {
            $tasks[] = $this->task('audit_table_missing', '内审发现表未接入', 'warning', 'review_required', 'AuditFinding', 'schema', '/audit_finding/index', '无法读取 audit_findings。');
        }

        return $this->project('system-audit', $title, $type, $scope, '内审计划与发现项进入工作台；整改闭环按发现项状态跟踪。', $summary, $tasks);
    }

    private function managementReviewProject(): array
    {
        [$title, $type, $scope] = self::SYSTEM_PROJECTS['system-management-review'];
        $summary = [];
        $tasks = [];
        if (self::tableExists('management_reviews')) {
            $summary['planned_reviews'] = (int)Db::name('management_reviews')->where('soft_delete', 0)->where('status', 'planned')->count();
        }
        if (self::tableExists('review_actions')) {
            $summary['open_actions'] = (int)Db::name('review_actions')->where('soft_delete', 0)->whereNotIn('status', ['completed', 'closed', 'verified'])->count();
            if (self::columnExists('review_actions', 'due_date')) {
                $summary['overdue_actions'] = (int)Db::name('review_actions')->where('soft_delete', 0)->whereNotIn('status', ['completed', 'closed', 'verified'])->whereNotNull('due_date')->where('due_date', '<', date('Y-m-d'))->count();
            } else {
                $summary['overdue_actions'] = (int)Db::name('review_actions')->where('soft_delete', 0)->where('status', 'overdue')->count();
            }
            if ($summary['overdue_actions'] > 0) {
                $tasks[] = $this->task('management_action_overdue', '处理超期管理评审措施', 'blocker', 'open', 'ReviewAction', 'overdue', '/review_action/index', '超期措施数量：' . $summary['overdue_actions']);
            } elseif ($summary['open_actions'] > 0) {
                $tasks[] = $this->task('management_action_open', '跟踪未关闭管理评审措施', 'warning', 'open', 'ReviewAction', 'open', '/review_action/index', '未关闭措施数量：' . $summary['open_actions']);
            }
        }
        $tasks[] = $this->task('management_input_review', '复核管理评审输入是否完整', 'warning', 'review_required', 'ManagementReview', 'input_review', '/management_review/index', '输入完整性需要人工判断，系统只显示状态和措施。');

        return $this->project('system-management-review', $title, $type, $scope, '管理评审输入、输出与措施进入工作台；输入完整性保留人工复核。', $summary, $tasks);
    }

    private function improvementRiskProject(): array
    {
        [$title, $type, $scope] = self::SYSTEM_PROJECTS['system-improvement-risk'];
        $summary = [];
        $tasks = [];
        foreach ([
            ['capas', 'Capa', '/capa/index', 'CAPA'],
            ['nonconformities', 'Nonconformity', '/nonconformity/index', '不符合工作'],
            ['customer_complaints', 'CustomerComplaint', '/complaint/index', '投诉'],
            ['qms_external_change_events', 'QmsExternalChangeEvent', '/planning/change-events', '外部变化事件'],
        ] as [$table, $model, $url, $label]) {
            if (!self::tableExists($table)) {
                $summary[$table . '_ready'] = false;
                continue;
            }
            $summary[$table . '_ready'] = true;
            $open = (int)Db::name($table)->where('soft_delete', 0)->whereNotIn('status', ['closed', 'completed', 'verified', 'cancelled'])->count();
            $summary[$table . '_open'] = $open;
            $overdue = 0;
            if (self::columnExists($table, 'due_date')) {
                $overdue = (int)Db::name($table)->where('soft_delete', 0)->whereNotIn('status', ['closed', 'completed', 'verified', 'cancelled'])->whereNotNull('due_date')->where('due_date', '<', date('Y-m-d'))->count();
            }
            $summary[$table . '_overdue'] = $overdue;
            if ($overdue > 0) {
                $tasks[] = $this->task('risk_overdue_' . $table, '处理超期' . $label, 'blocker', 'open', $model, 'overdue', $url, '超期未关闭数量：' . $overdue);
            } elseif ($open > 0) {
                $tasks[] = $this->task('risk_open_' . $table, '跟踪未关闭' . $label, 'warning', 'open', $model, 'open', $url, '未关闭数量：' . $open);
            }
        }
        $tasks[] = $this->task('risk_verification_review', '复核风险与改进措施验证结论', 'warning', 'review_required', 'QualityRisk', 'verification', '/capa/index', '来源、责任人和措施可见后，验证结论仍需人工确认。');

        return $this->project('system-improvement-risk', $title, $type, $scope, '改进与风险事项已汇总；验证充分性保留人工复核。', $summary, $tasks);
    }

    private function project(string $code, string $title, string $type, string $scope, string $conclusion, array $summary, array $tasks): array
    {
        $hasBlocker = false;
        foreach ($tasks as $task) {
            if (($task['severity'] ?? '') === 'blocker' && !in_array(($task['status'] ?? ''), ['done', 'waived'], true)) {
                $hasBlocker = true;
                break;
            }
        }

        return [
            'project_code' => $code,
            'title' => $title,
            'project_type' => $type,
            'scope_label' => $scope,
            'status' => $hasBlocker ? 'blocked' : 'ready_for_acceptance',
            'conclusion' => $conclusion,
            'owner_role' => 'quality_manager',
            'owner_user_id' => '',
            'due_date' => null,
            'summary' => $summary,
            'tasks' => $tasks,
        ];
    }

    private function task(string $taskType, string $title, string $severity, string $status, string $sourceModel, string $sourceId, string $actionUrl, string $evidence, array $payload = []): array
    {
        return [
            'task_type' => $taskType,
            'title' => $title,
            'severity' => $severity,
            'status' => $status,
            'source_model' => $sourceModel,
            'source_id' => $sourceId,
            'assigned_role' => 'quality_manager',
            'assigned_user_id' => '',
            'due_date' => null,
            'action_url' => $actionUrl,
            'evidence_summary' => $evidence,
            'payload' => $payload,
        ];
    }

    private function upsertProject(array $plan, array &$summary): array
    {
        $companyId = (string)Config::get('qms.company_id', '');
        $existing = Db::name('quality_review_projects')
            ->where('company_id', $companyId)
            ->where('project_code', (string)$plan['project_code'])
            ->where('soft_delete', 0)
            ->find();
        $payload = [
            'company_id' => $companyId,
            'project_code' => (string)$plan['project_code'],
            'title' => (string)$plan['title'],
            'project_type' => (string)$plan['project_type'],
            'scope_label' => (string)$plan['scope_label'],
            'status' => (string)$plan['status'],
            'conclusion' => (string)$plan['conclusion'],
            'owner_role' => (string)$plan['owner_role'],
            'owner_user_id' => (string)$plan['owner_user_id'],
            'due_date' => $plan['due_date'] ?? null,
            'summary_json' => $this->encodeJson($plan['summary'] ?? []),
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => date('Y-m-d H:i:s'),
        ];

        if (is_array($existing)) {
            $statusBefore = (string)$existing['status'];
            Db::name('quality_review_projects')->where('id', (string)$existing['id'])->update($payload);
            $summary['updated_projects']++;
            if ($statusBefore !== (string)$payload['status']) {
                $this->recordEvent((string)$existing['id'], '', 'project_refresh', $statusBefore, (string)$payload['status'], '系统刷新评审项目状态');
                $summary['events']++;
            }
            return array_merge($existing, $payload);
        }

        $payload['id'] = qms_uuid();
        $payload['created'] = date('Y-m-d H:i:s');
        Db::name('quality_review_projects')->insert($payload);
        $summary['created_projects']++;
        $this->recordEvent((string)$payload['id'], '', 'project_created', '', (string)$payload['status'], '系统生成评审项目');
        $summary['events']++;

        return $payload;
    }

    private function upsertTask(string $projectId, array $plan, array &$summary): array
    {
        $sourceId = trim((string)($plan['source_id'] ?? ''));
        $existing = Db::name('quality_review_tasks')
            ->where('project_id', $projectId)
            ->where('task_type', (string)$plan['task_type'])
            ->where('source_model', (string)$plan['source_model'])
            ->where('source_id', $sourceId)
            ->where('soft_delete', 0)
            ->find();
        $payload = [
            'project_id' => $projectId,
            'title' => (string)$plan['title'],
            'task_type' => (string)$plan['task_type'],
            'severity' => (string)$plan['severity'],
            'status' => (string)$plan['status'],
            'source_model' => (string)$plan['source_model'],
            'source_id' => $sourceId,
            'assigned_role' => (string)($plan['assigned_role'] ?? 'quality_manager'),
            'assigned_user_id' => (string)($plan['assigned_user_id'] ?? ''),
            'due_date' => $plan['due_date'] ?? null,
            'action_url' => (string)($plan['action_url'] ?? ''),
            'evidence_summary' => (string)($plan['evidence_summary'] ?? ''),
            'payload_json' => $this->encodeJson($plan['payload'] ?? []),
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => date('Y-m-d H:i:s'),
        ];

        if (is_array($existing)) {
            if (in_array((string)$existing['status'], ['done', 'waived'], true)
                && (string)$existing['severity'] === (string)$payload['severity']
            ) {
                $payload['status'] = (string)$existing['status'];
                $payload['completed_at'] = $existing['completed_at'] ?? null;
                $payload['completed_by'] = (string)($existing['completed_by'] ?? '');
            }
            Db::name('quality_review_tasks')->where('id', (string)$existing['id'])->update($payload);
            $summary['updated_tasks']++;
            return array_merge($existing, $payload);
        }

        $payload['id'] = qms_uuid();
        $payload['created'] = date('Y-m-d H:i:s');
        Db::name('quality_review_tasks')->insert($payload);
        $summary['created_tasks']++;
        $this->recordEvent($projectId, (string)$payload['id'], 'task_created', '', (string)$payload['status'], (string)$payload['title']);
        $summary['events']++;

        return $payload;
    }

    private function recalculateProjectStatus(string $projectId, array &$summary): void
    {
        $project = Db::name('quality_review_projects')->where('id', $projectId)->where('soft_delete', 0)->find();
        if (!is_array($project) || in_array((string)$project['status'], ['accepted', 'archived'], true)) {
            return;
        }

        $blockers = (int)Db::name('quality_review_tasks')
            ->where('project_id', $projectId)->where('soft_delete', 0)
            ->where('severity', 'blocker')->whereNotIn('status', ['done', 'waived'])->count();
        $open = (int)Db::name('quality_review_tasks')
            ->where('project_id', $projectId)->where('soft_delete', 0)
            ->whereNotIn('status', ['done', 'waived', 'review_required'])->count();
        $target = $blockers > 0 ? 'blocked' : ($open === 0 ? 'ready_for_acceptance' : 'active');
        if ((string)$project['status'] !== $target) {
            Db::name('quality_review_projects')->where('id', $projectId)->update([
                'status' => $target,
                'modified' => date('Y-m-d H:i:s'),
            ]);
            $this->recordEvent($projectId, '', 'project_status_recalculated', (string)$project['status'], $target, '任务状态变化后自动重算项目状态');
            $summary['events'] = ($summary['events'] ?? 0) + 1;
        }
    }

    private function taskCounts(string $projectId): array
    {
        $rows = Db::name('quality_review_tasks')
            ->field('status, severity, COUNT(*) as count')
            ->where('project_id', $projectId)
            ->where('soft_delete', 0)
            ->group('status, severity')
            ->select()
            ->toArray();
        $counts = ['total' => 0, 'done' => 0, 'review_required' => 0, 'blocker' => 0, 'open' => 0];
        foreach ($rows as $row) {
            $count = (int)$row['count'];
            $counts['total'] += $count;
            if (($row['status'] ?? '') === 'done') {
                $counts['done'] += $count;
            }
            if (($row['status'] ?? '') === 'review_required') {
                $counts['review_required'] += $count;
            }
            if (($row['severity'] ?? '') === 'blocker' && !in_array(($row['status'] ?? ''), ['done', 'waived'], true)) {
                $counts['blocker'] += $count;
            }
            if (!in_array(($row['status'] ?? ''), ['done', 'waived'], true)) {
                $counts['open'] += $count;
            }
        }
        $counts['percent_done'] = $counts['total'] > 0 ? (int)round(($counts['done'] / $counts['total']) * 100) : 0;

        return $counts;
    }

    private function statusCounts(string $table, string $field): array
    {
        $rows = Db::name($table)->field($field . ', COUNT(*) AS count')->where('soft_delete', 0)->group($field)->select()->toArray();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string)$row[$field]] = (int)$row['count'];
        }

        return $counts;
    }

    private function riskTasks(): array
    {
        $tasks = Db::name('quality_review_tasks')
            ->where('soft_delete', 0)
            ->whereIn('source_model', ['Capa', 'Nonconformity', 'CustomerComplaint', 'QmsExternalChangeEvent', 'Equipment', 'CompetencyRecord', 'EmployeeCertificate'])
            ->whereNotIn('status', ['done', 'waived'])
            ->orderRaw("FIELD(severity,'blocker','warning','info')")
            ->limit(10)
            ->select()
            ->toArray();
        foreach ($tasks as &$task) {
            $task = $this->decorateTask($task);
        }
        unset($task);

        return $tasks;
    }

    private function decorateProject(array $project): array
    {
        $labels = self::labels();
        $project['status_label'] = $labels['project_status'][(string)($project['status'] ?? '')] ?? (string)($project['status'] ?? '');
        $project['project_type_label'] = $labels['project_type'][(string)($project['project_type'] ?? '')] ?? (string)($project['project_type'] ?? '');

        return $project;
    }

    private function decorateTask(array $task): array
    {
        $labels = self::labels();
        $task['status_label'] = $labels['task_status'][(string)($task['status'] ?? '')] ?? (string)($task['status'] ?? '');
        $task['severity_label'] = $labels['severity'][(string)($task['severity'] ?? '')] ?? (string)($task['severity'] ?? '');

        return $task;
    }

    private function decorateEvent(array $event): array
    {
        $labels = self::labels();
        $event['old_status_label'] = $labels['project_status'][(string)($event['old_status'] ?? '')]
            ?? $labels['task_status'][(string)($event['old_status'] ?? '')]
            ?? (string)($event['old_status'] ?? '');
        $event['new_status_label'] = $labels['project_status'][(string)($event['new_status'] ?? '')]
            ?? $labels['task_status'][(string)($event['new_status'] ?? '')]
            ?? (string)($event['new_status'] ?? '');

        return $event;
    }

    private function nextAction(array $tasks, array $blockers, array $projects): string
    {
        if ($blockers !== []) {
            return '先处理阻断项：' . (string)$blockers[0]['title'];
        }
        if ($tasks !== []) {
            return '今天先处理：' . (string)$tasks[0]['title'];
        }
        foreach ($projects as $project) {
            if (($project['status'] ?? '') === 'ready_for_acceptance') {
                return '可以验收：' . (string)$project['title'];
            }
        }

        return '暂无紧急动作，保持例行复核。';
    }

    private function projectNextAction(array $tasks): string
    {
        foreach ($tasks as $task) {
            if (($task['severity'] ?? '') === 'blocker' && !in_array(($task['status'] ?? ''), ['done', 'waived'], true)) {
                return '下一步：质量负责人先处理阻断项“' . (string)$task['title'] . '”。';
            }
        }
        foreach ($tasks as $task) {
            if (!in_array(($task['status'] ?? ''), ['done', 'waived'], true)) {
                return '下一步：质量负责人处理“' . (string)$task['title'] . '”。';
            }
        }

        return '下一步：项目可进入验收判断。';
    }

    private function assertWritableTrialEnvironment(): void
    {
        if (!TrialModeService::isEnabled()) {
            throw new RuntimeException('质量工作台拒绝写入：QMS_TRIAL_MODE 未启用，本轮只允许 8021 测试环境。');
        }
    }

    private function notifyQualityManager(string $projectId, string $title, string $message): void
    {
        try {
            NotificationService::notifyRole(
                'quality_manager',
                '质量工作台阻断项',
                $title . '：' . $message,
                'general',
                'quality_workbench',
                $projectId,
                null,
                'quality_workbench_blocker:' . $projectId . ':' . sha1($title)
            );
        } catch (\Throwable) {
            // 通知失败不阻断工作台刷新；页面和事件仍是主证据。
        }
    }

    private function recordEvent(string $projectId, string $taskId, string $eventType, string $oldStatus, string $newStatus, string $note): void
    {
        Db::name('quality_review_events')->insert([
            'id' => qms_uuid(),
            'project_id' => $projectId,
            'task_id' => $taskId,
            'event_type' => $eventType,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'created_by' => (string)Session::get('user.id', ''),
            'created' => date('Y-m-d H:i:s'),
        ]);
    }

    private function encodeJson(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function decodeJson(string $payload): array
    {
        if (trim($payload) === '') {
            return [];
        }
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function tableExists(string $table): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        try {
            return Db::query("SHOW TABLES LIKE '" . addslashes($table) . "'") !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    private static function columnExists(string $table, string $column): bool
    {
        if (!self::tableExists($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        try {
            foreach (Db::query('SHOW COLUMNS FROM `' . $table . '`') as $row) {
                if ((string)$row['Field'] === $column) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
