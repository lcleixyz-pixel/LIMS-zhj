# 系统架构说明

## 1. 业务背景

珠宝检测实验室通常具备两类系统：

- **检测业务系统（LIMS）**：委托、样品、检测、报告、收费等业务流程，通常已独立运行。
- **质量管理系统（QMS）**：服务 CMA/CNAS 和 ISO/IEC 17025 的文件化质量体系，常见痛点是纸质、Word、Excel 分散管理。

本工作区目标是**仅信息化 QMS**，与既有 LIMS 松耦合，避免重复建设检测业务流程。

```text
实验室信息系统目标态
├── LIMS（已有，不纳入本仓库）
│   ├── 委托 / 样品 / 检测 / 报告
│   └── 财务 / 客户业务
└── Jewelry QMS（本仓库主交付）
    ├── 文件控制 / 审批 / 修订
    ├── 内审 / 管评 / CAPA
    ├── 设备校准 / 培训能力
    └── 供应商 / 投诉 / 不符合
```

后续可通过只读 API 或数据库视图同步人员、设备、客户、报告编号等主数据。

## 2. 当前技术架构

| 层级 | 当前选型 | 说明 |
|------|----------|------|
| 语言 | PHP 8.1+ | 主项目已迁移到现代 PHP 运行环境 |
| 框架 | ThinkPHP 8 | 服务端渲染，公开入口为 `public/` |
| ORM | think-orm 3.x / 4.x | 通过模型类访问业务表 |
| 数据库 | MySQL / MariaDB，InnoDB，utf8mb4 | 初始化脚本为 `database/jewelry_qms.sql` |
| 前端 | ThinkPHP 模板 + Bootstrap 风格页面 | 视图位于 `app/view` |
| 文件 | 上传/下载模式 | 文件写入 `public/uploads/`，后续可扩展 ONLYOFFICE |

`flinkiso/`、`flinkiso-lite-master/` 和 `jewelry-qms-legacy/` 均为参考或归档目录，不作为主项目运行栈。

## 3. Jewelry QMS 目录边界

```text
jewelry-qms/
├── app/
│   ├── controller/        # 控制器：登录、仪表盘、文件控制、业务模块
│   ├── Model/             # 领域模型
│   ├── middleware/        # Auth、RBAC、AuditLog
│   ├── service/           # 审批、流程、通知、导入、文件服务
│   └── view/              # 中文服务端模板
├── config/
│   ├── database.php       # 数据库连接，读取 .env
│   └── qms.php            # 业务参数、版本、角色、权限、审批规则
├── database/
│   └── jewelry_qms.sql    # 初始化数据库脚本
├── public/
│   ├── index.php          # Web 入口
│   └── uploads/           # 用户上传文件
├── route/
│   └── app.php            # 路由定义与中间件绑定
└── runtime/               # 缓存、日志、运行时文件
```

开发启动命令位于 `jewelry-qms` 目录内：

```bash
composer install
php think run
```

## 4. 核心业务模块

当前版本 `2.1.0` 覆盖以下能力：

| 模块 | 当前状态 |
|------|----------|
| 文件控制 | 四层级、模板、Word/PDF 等附件上传、提交审核、批准、发布、修订 |
| 体系策划 | 外部依据、条款库、无编号要素、质量手册章节、文件结构化、追溯矩阵 |
| 文件结构化 | 外部依据、质量手册、程序文件、记录表格 Markdown 化和块级追溯 |
| 记录表格 | 模板 schema、来源预览、字段复核、运行填写和证据输出 |
| 审批 | 按文件层级差异化审批，审批待办通知 |
| 内审 | 计划批准、日程、检查表、发现，发现可触发 CAPA |
| 管理评审 | 评审输入自动汇总，决议事项跟踪与验证 |
| CAPA | 来源关联、原因分析、措施实施、效果验证、关闭 |
| 不符合 / 投诉 | 严重程度、处置推进、闭环、CAPA 关联 |
| 设备 / 校准 | 台账、校准记录、到期提醒、校准后更新 |
| 培训 / 能力 | 培训完成标记、培训记录、能力确认 |
| 供应商 | 评价驱动状态、合格供应商名录 |
| 通知 / 导入 / 仪表盘 | 待办、校准到期、CAPA 超期、CSV 导入、聚合看板 |

## 5. 文件控制审批逻辑

业务配置位于 `jewelry-qms/config/qms.php`：

| 配置 | 说明 |
|------|------|
| `docLevels` | 质量手册、程序文件、作业指导书、记录表格 |
| `approvalRules` | 各层级审批级数 |
| `roles` / `permissions` | 五角色权限矩阵 |
| `upload` | 允许上传的扩展名和大小限制 |
| `notification` | 到期与超期提醒参数 |

审批规则：

| 层级 | level 值 | 审批级数 | 流程 |
|------|----------|----------|------|
| 质量手册 | 1 | 3 | 编制 -> 审核 -> 批准 |
| 程序文件 | 2 | 3 | 编制 -> 审核 -> 批准 |
| 作业指导书 | 3 | 2 | 编制 -> 批准 |
| 记录表格 | 4 | 2 | 编制 -> 批准 |

主要实现位于 `ApprovalService`、`WorkflowService`、`NotificationService`。路由层统一绑定 `Auth`、`Rbac`、`AuditLog` 中间件。

## 6. 体系策划与追溯架构

体系策划中心建立从外部依据到运行证据的追溯骨架：

```text
外部依据 -> 条款库 -> 无编号体系要素 -> 手册章节 / 程序文件
        -> 记录表格 / 运行模块 -> 岗位职责 / 运行证据
```

关键建模边界：

- `qms_sources` 登记外部依据和查新信息。
- `qms_clauses` 承载条款编号，`qms_clause_texts` 保存条款原文。
- `qms_elements` 只保存无编号要素，用户界面显示中文名称，不显示系统 key。
- `qms_element_clause_links` 映射要素与条款，并通过 `is_primary` 标记主 27025 条款。
- `qms_manual_sections` 独立承载质量手册章节编号和标题。
- `qms_document_assets`、`qms_structured_documents`、`qms_document_blocks` 和 `qms_document_block_links` 承载文件 Markdown 结构化和块级追溯。
- `record_form_templates` 和 `record_form_instances` 分别承载记录表格 schema 和运行证据。
- `qms_agent_suggestions` 只保存建议和缺口，不自动修改正式体系数据。

详细说明见：

- [QMS_PLANNING_CENTER_GUIDE.md](QMS_PLANNING_CENTER_GUIDE.md)
- [QMS_TRACEABILITY_DATA_MODEL.md](QMS_TRACEABILITY_DATA_MODEL.md)
- [QMS_DOCUMENT_STRUCTURING_GUIDE.md](QMS_DOCUMENT_STRUCTURING_GUIDE.md)
- [QMS_RECORD_FORMS_GUIDE.md](QMS_RECORD_FORMS_GUIDE.md)

## 7. 安全与部署边界

- 当前面向单实验室部署，默认 `company_id` 配置在 `config/qms.php`。
- 用户密码使用 bcrypt 哈希，初始化账号为 `admin` / `password`，生产首次登录后必须修改。
- 生产环境必须关闭 `APP_DEBUG`，Web 根目录必须指向 `jewelry-qms/public`。
- 上传文件位于 `public/uploads/`，需配合 Web 服务器限制脚本执行。
- `.env`、`.git`、源码目录、备份文件不得对外暴露。

## 8. 参考项目在架构中的位置

| 项目 | 位置 | 用途 |
|------|------|------|
| FlinkISO Lite | `flinkiso-lite-master/` | CAPA、培训、供应商、内审等业务思路参考 |
| FlinkISO On-Premise | `flinkiso/` | ONLYOFFICE、PDF、记录锁定等后续能力参考 |
| Jewelry QMS Legacy | `jewelry-qms-legacy/` | 迁移前 CakePHP 旧版归档 |

参考项目不直接作为生产 QMS 运行，避免维护多套框架和数据库结构。

## 9. 扩展路线图

| 阶段 | 内容 |
|------|------|
| P0 | 文件控制与九模块基础能力 |
| P1（当前） | CAPA、内审、管评、通知、导入、仪表盘等业务深化 |
| P2（当前扩展） | 体系策划中心、条款库、无编号要素、结构化文件、记录表格 schema 和追溯矩阵 |
| P3 | LIMS 主数据同步 API 或只读视图 |
| P4 | ONLYOFFICE 在线编辑、PDF 受控打印、记录锁定 |
