# jewelry-qms 可运行系统说明

## 1. 系统定位

`jewelry-qms/` 是当前项目的主交付物：ThinkPHP 8 + PHP 8.1+ 的珠宝检测实验室质量管理系统。

它的定位不是替代检测业务 LIMS，不负责委托、样品、检测、报告、收费等业务主流程；它负责把 CMA/CNAS、ISO/IEC 17025 相关的质量体系文件、记录、审批、内审、管评、CAPA、设备、培训、供应商、投诉、不符合等工作信息化。

推荐理解：

```text
已有或未来的检测业务 LIMS
  负责：委托、样品、检测、报告、收费、客户业务

jewelry-qms
  负责：体系文件、质量记录、合规追溯、内审管评、设备培训、CAPA、通知、知识资产
```

## 2. 主目录说明

| 路径 | 作用 |
|---|---|
| `jewelry-qms/app/controller/` | 页面和接口控制器。 |
| `jewelry-qms/app/service/` | 核心业务服务，审批、导入、结构化、记录表格、AI、通知等逻辑集中在这里。 |
| `jewelry-qms/app/Model/` | 数据模型。 |
| `jewelry-qms/app/middleware/` | 登录、RBAC、页面上下文、审计等中间件。 |
| `jewelry-qms/app/view/` | 中文服务端模板。 |
| `jewelry-qms/app/record_form_print/` | 记录表格打印模板。 |
| `jewelry-qms/config/` | 数据库、控制台命令、QMS 角色权限和业务配置。 |
| `jewelry-qms/database/` | 初始化 SQL、迁移、记录表格 schema。 |
| `jewelry-qms/route/app.php` | 登录、QMS 模块、策划中心、AI、CRUD 模块路由。 |
| `jewelry-qms/tests/` | PHP smoke 测试。 |
| `jewelry-qms/runtime/` | 运行日志、系统包、归档、临时文件。默认不是 Git 资产。 |

## 3. 启动方式

### 3.1 本机 PHP 启动

```bash
cd /Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/jewelry-qms
composer install
cp .example.env .env
php think run -H 127.0.0.1 -p 8010
```

访问：

```text
http://127.0.0.1:8010
```

默认账号：

```text
admin / password
```

首次正式使用必须修改默认密码。

### 3.2 Docker 开发启动

```bash
cd /Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/jewelry-qms
docker compose up --build
```

Docker 会启动应用和 MySQL，并使用 `docker/.env.docker`。仓库父级的 `docs/`、`参考/`、`现用文件/` 会以只读方式挂入容器，供结构化文档流程读取。

## 4. 核心模块地图

### 4.1 登录、权限、审计和基础框架

可用内容：

- 登录、退出、修改密码。
- Auth、RBAC、PageContext、FormTokenCheck、AuditLog 中间件。
- 五类角色：系统管理员、质量负责人、内审员、部门负责人、一般人员。
- `CrudBase` 和 `BusinessBase` 提供通用增删改查、CSV 导出、表单校验基础。
- 字段审计服务 `FieldAuditService` 已用于特定关键对象。

主要文件：

- `app/controller/Login.php`
- `app/controller/CrudBase.php`
- `app/controller/BusinessBase.php`
- `app/middleware/Auth.php`
- `app/middleware/Rbac.php`
- `app/middleware/AuditLog.php`
- `app/service/RbacService.php`
- `app/service/FieldAuditService.php`
- `config/qms.php`

### 4.2 文件控制与审批

可用内容：

- 质量手册、程序文件、作业指导书、记录表格四层级。
- 文件模板、文件附件、发布、修订、废止。
- 分发记录、回收确认、评审入口。
- 受控打印记录入口。
- 按文件层级配置不同审批级数。

主要文件：

- `app/controller/Document.php`
- `app/controller/DocTemplate.php`
- `app/controller/DocCategory.php`
- `app/controller/Approval.php`
- `app/service/DocumentControlService.php`
- `app/service/ApprovalService.php`
- `app/service/WorkflowService.php`
- `app/service/ControlledPrintService.php`
- `app/Model/Document*.php`
- `app/Model/Approval.php`
- `app/Model/ControlledPrintLog.php`

注意边界：

- 当前是上传、下载和服务端页面模式。
- ONLYOFFICE 配置有预留，但在线编辑不应默认视为已完成能力。

### 4.3 体系策划中心与追溯

可用内容：

- 外部依据登记、上传、查新、条款抽取。
- 条款库、条款原文、无编号体系要素。
- 质量方针、质量目标。
- 质量手册章节、结构化文件、内容块、块级链接。
- 追溯矩阵和系统包渲染入口。
- 外部变更事件登记、附件、状态流转、字段审计。

主要文件：

- `app/controller/PlanningDashboard.php`
- `app/controller/PlanningSource.php`
- `app/controller/PlanningClause.php`
- `app/controller/PlanningElement.php`
- `app/controller/PlanningStructure.php`
- `app/controller/PlanningTraceability.php`
- `app/controller/PlanningChangeEvent.php`
- `app/controller/PlanningObjective.php`
- `app/service/QmsElementService.php`
- `app/service/QmsPlanningImportService.php`
- `app/service/QmsDocumentStructureService.php`
- `app/service/ExternalChangeEventService.php`
- `app/Model/Qms*.php`

关键数据表：

- `qms_sources`
- `qms_clauses`
- `qms_clause_texts`
- `qms_elements`
- `qms_element_clause_links`
- `qms_manual_sections`
- `qms_document_assets`
- `qms_structured_documents`
- `qms_document_blocks`
- `qms_document_block_links`
- `qms_document_change_logs`
- `qms_external_change_events`
- `qms_quality_policies`
- `qms_quality_objectives`

### 4.4 记录表格

可用内容：

- 记录表格模板和 schema。
- 模板来源预览、字段复核、样例 seed、批量 seed、缺口 seed。
- 记录实例创建、编辑、查看、打印、PDF 导出入口。
- 记录表格重构审查和 schema 重建命令。
- 大量专用打印模板已经生成到 `app/record_form_print/`。

主要文件：

- `app/controller/RecordFormTemplate.php`
- `app/controller/RecordFormInstance.php`
- `app/service/RecordFormSchemaService.php`
- `app/service/RecordFormBatchTemplateService.php`
- `app/service/RecordFormBatchReviewService.php`
- `app/service/RecordFormPrintService.php`
- `app/service/RecordFormPdfLayoutAuditService.php`
- `app/service/RecordFormReconstructionReviewService.php`
- `database/schemas/record_form_schemas.json`
- `app/record_form_print/*.php`

注意边界：

- 记录表格 schema 应来自程序文件记录要求和现用表格原件的共同确认。
- 后续不要只按表名猜测字段。

### 4.5 内审、管评、CAPA、投诉、不符合

可用内容：

- 内审计划审批、审核日程、检查表、审核发现。
- 审核发现、不符合、投诉、管评行动可转 CAPA。
- CAPA 来源、原因分析、措施实施、有效性验证、关闭。
- 管理评审记录和后续行动验证。

主要文件：

- `app/controller/AuditPlan.php`
- `app/controller/AuditSchedule.php`
- `app/controller/AuditChecklist.php`
- `app/controller/AuditFinding.php`
- `app/controller/ManagementReview.php`
- `app/controller/ReviewAction.php`
- `app/controller/Capa.php`
- `app/controller/Complaint.php`
- `app/controller/Nonconformity.php`
- `app/service/FileAttachmentService.php`
- `app/Model/Audit*.php`
- `app/Model/ManagementReview.php`
- `app/Model/ReviewAction.php`
- `app/Model/Capa*.php`
- `app/Model/CustomerComplaint.php`
- `app/Model/Nonconformity.php`

### 4.6 设备、校准、标准物质、培训、能力

可用内容：

- 设备台账、设备维护、设备授权、设备调拨。
- 校准记录和证书附件。
- 标准物质管理。
- 培训计划、培训记录、培训完成。
- 能力确认、员工证书附件。
- 到期提醒可通过命令统一检查。

主要文件：

- `app/controller/Equipment.php`
- `app/controller/EquipmentMaintenance.php`
- `app/controller/EquipmentAuthorization.php`
- `app/controller/EquipmentTransfer.php`
- `app/controller/Calibration.php`
- `app/controller/ReferenceMaterial.php`
- `app/controller/TrainingPlan.php`
- `app/controller/Training.php`
- `app/controller/TrainingRecord.php`
- `app/controller/CompetencyRecord.php`
- `app/controller/EmployeeCertificate.php`
- `app/service/EquipmentEvidenceService.php`
- `app/service/TrainingEvidenceService.php`

### 4.7 供应商、组织、人员和导入

可用内容：

- 公司、部门、岗位、员工、用户。
- 场所管理。
- 供应商和供应商评价。
- CSV 导入入口。
- API 读取人员、设备、客户基础信息的轻量接口。

主要文件：

- `app/controller/Site.php`
- `app/controller/Department.php`
- `app/controller/Employee.php`
- `app/controller/User.php`
- `app/controller/Supplier.php`
- `app/controller/SupplierEvaluation.php`
- `app/controller/Import.php`
- `app/controller/Api.php`
- `app/service/ImportService.php`

### 4.8 通知、仪表盘、合规看板

可用内容：

- 仪表盘聚合。
- 通知列表、阅读、全部已读。
- 校准、CAPA、文件评审、能力到期提醒命令。
- 合规就绪度评估命令和页面入口。

主要文件：

- `app/controller/Dashboard.php`
- `app/controller/Notification.php`
- `app/controller/Compliance.php`
- `app/service/DashboardMetricService.php`
- `app/service/NotificationService.php`
- `app/service/ComplianceCheckService.php`
- `app/command/CheckReminders.php`
- `app/command/ComplianceAssess.php`

### 4.9 AI 助理和聊天

可用内容：

- AI 文档助理：抽取、确认、拒绝、预览、历史。
- AI 设置：保存和测试。
- AI 聊天：会话、消息、清理。
- 页面上下文构建和只读上下文服务。

主要文件：

- `app/controller/AiAssistant.php`
- `app/controller/AiSettings.php`
- `app/controller/AiChat.php`
- `app/service/AiAssistantService.php`
- `app/service/AiSettingsService.php`
- `app/service/AiChatService.php`
- `app/service/AiContextToolService.php`
- `app/service/CopilotReadService.php`
- `app/service/PageContextBuilder.php`
- `app/service/DeepSeekService.php`
- `app/service/SettingsCipher.php`

注意边界：

- AI 生成内容只应作为草稿或建议。
- 涉及外部 API、敏感体系文件、标准文本全文时，需要先确认保密和版权边界。
- 不能绕过文件控制审批，把 AI 草稿直接变成受控文件。

## 5. 可用命令

在 `jewelry-qms/` 目录执行：

```bash
php think check:reminders
php think check:reminders --type=calibration
php think check:reminders --type=capa
php think check:reminders --type=doc_review
php think check:reminders --type=competency
```

用途：检查校准、CAPA、文件评审、能力到期提醒。

```bash
php think compliance:assess
```

用途：执行审核准备驾驶舱合规就绪度评估。

```bash
php think qms:seed-current-files --enumerate-procedures
php think qms:seed-current-files --export-knowledge-internal
php think qms:seed-current-files --refresh-structures --export-knowledge-internal
```

用途：枚举 2022 程序文件、刷新结构化库、导出 `knowledge/internal/`。

```bash
php think record_form:rebuild_schema
php think record_form:reconstruction_review
php think record_form:seed_source_instances
```

用途：记录表格 schema 重建、重构审查、从现用记录表格生成运行记录草稿。

```bash
php think ai:purge-chat
```

用途：清理超过保留期的 AI 聊天会话。

## 6. 推荐验证入口

不涉及生产库前，优先使用 smoke 测试和静态检查：

```bash
cd /Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/jewelry-qms
php tests/qms_validation_smoke.php
php tests/qms_current_files_enumeration_smoke.php
git diff --check
```

需要数据库或 Docker 环境的测试，先确认当前连接的是开发库或容器库，不要误写生产库。

## 7. 接手原则

1. 改系统功能时，以 `jewelry-qms/` 为主，不从两个 FlinkISO 参考项目复制架构。
2. 改体系文件结构化时，优先复用 `QmsDocumentStructureService` 和 `CurrentFilesSeedService`。
3. 改记录表格时，优先复用 `RecordForm*` 服务和已有 schema/打印模板。
4. 改合规扫描时，优先复用 `ComplianceCheckService`。
5. 改 AI 时，保持“AI 出草稿，人审确认，审批发布”的边界。

