# FlinkISO 珠宝检测实验室 QMS 工作区

本仓库为单体工作区（Monorepo），包含两个 FlinkISO 参考项目，以及基于对比分析后定制的珠宝检测实验室质量管理系统 `jewelry-qms`。

适用于：在已有独立检测业务系统（LIMS）的前提下，为满足 CMA/CNAS 与 ISO/IEC 17025 要求建设信息化质量管理体系。

## 仓库结构

```text
LIMS-zhj/
├── README.md
├── CHANGELOG.md
├── docs/
│   ├── ARCHITECTURE.md
│   ├── PROJECT_COMPARISON.md
│   ├── DEPLOYMENT.md
│   ├── VERSIONING.md
│   ├── JEWELRY_QMS_GUIDE.md
│   ├── QMS_V2_2_ROADMAP.md
│   ├── QMS_PLANNING_CENTER_GUIDE.md
│   ├── QMS_TRACEABILITY_DATA_MODEL.md
│   ├── QMS_DOCUMENT_STRUCTURING_GUIDE.md
│   ├── QMS_RECORD_FORMS_GUIDE.md
│   ├── QMS_OPERATIONS_RUNBOOK.md
│   └── REMOTE_UPLOAD.md
├── flinkiso/                          # 参考项目 A：FlinkISO On-Premise 2.2.42
│   └── flinkiso-ver-2x-on-premise/
├── flinkiso-lite-master/              # 参考项目 B：FlinkISO Lite
│   └── flinkiso-lite-master/
├── jewelry-qms/                       # 主交付物：ThinkPHP 8 珠宝检测实验室 QMS
└── jewelry-qms-legacy/                # CakePHP 2.x 旧版归档
```

| 子项目 | 角色 | 技术栈 | 说明 |
|--------|------|--------|------|
| `jewelry-qms` | 生产定制主项目 | ThinkPHP 8 + PHP 8.1+ | 中文 17025 QMS，SSR 模板，Web 入口 `public/` |
| `jewelry-qms-legacy` | 归档对照 | CakePHP 2.x | 迁移前代码备份，不再作为主运行版本 |
| `flinkiso/.../on-premise` | 参考快照 | CakePHP 2.10.24 | 企业本地版，ONLYOFFICE、PDF、动态表单、计费等参考能力 |
| `flinkiso-lite-master/...` | 参考快照 | CakePHP 2.3.6 | CAPA、培训、供应商、内审等模块参考 |

## 快速开始（Jewelry QMS）

1. 准备 PHP 8.1+、Composer、MySQL。
2. 进入 `jewelry-qms` 执行 `composer install`。
3. 创建数据库并导入 `jewelry-qms/database/jewelry_qms.sql`。
4. 复制 `jewelry-qms/.example.env` 为 `.env`，配置数据库连接；业务参数位于 `config/qms.php`。
5. 开发启动：`php think run`；生产部署时 Web 根目录指向 `jewelry-qms/public`。
6. 默认账号：`admin` / `password`，首次登录后必须修改。

详细步骤见 [jewelry-qms/README.md](jewelry-qms/README.md) 与 [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)。

## 文档索引

| 文档 | 内容 |
|------|------|
| [docs/DOCUMENTATION_INDEX.md](docs/DOCUMENTATION_INDEX.md) | 全部说明文档入口和推荐阅读顺序 |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | 系统边界、技术架构、模块与审批实现 |
| [docs/PROJECT_COMPARISON.md](docs/PROJECT_COMPARISON.md) | FlinkISO 与 FlinkISO Lite 功能对比 |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | ThinkPHP 8 主项目部署指南 |
| [docs/VERSIONING.md](docs/VERSIONING.md) | Git 分支、标签、提交规范 |
| [docs/JEWELRY_QMS_GUIDE.md](docs/JEWELRY_QMS_GUIDE.md) | 体系文件适配、模板、审批流与模块使用 |
| [docs/QMS_V2_2_ROADMAP.md](docs/QMS_V2_2_ROADMAP.md) | Jewelry QMS v2.2 分阶段开发路线图 |
| [docs/QMS_PLANNING_CENTER_GUIDE.md](docs/QMS_PLANNING_CENTER_GUIDE.md) | 体系策划中心、无编号要素、条款库与追溯矩阵 |
| [docs/QMS_TRACEABILITY_DATA_MODEL.md](docs/QMS_TRACEABILITY_DATA_MODEL.md) | 体系策划数据模型和追溯关系 |
| [docs/QMS_DOCUMENT_STRUCTURING_GUIDE.md](docs/QMS_DOCUMENT_STRUCTURING_GUIDE.md) | 文件 Markdown 结构化、块级追溯与系统包输出 |
| [docs/QMS_RECORD_FORMS_GUIDE.md](docs/QMS_RECORD_FORMS_GUIDE.md) | 记录表格 schema、程序记录要求和运行证据 |
| [docs/QMS_OPERATIONS_RUNBOOK.md](docs/QMS_OPERATIONS_RUNBOOK.md) | 本机运行、初始化、验证和运行产物清理 |
| [docs/REMOTE_UPLOAD.md](docs/REMOTE_UPLOAD.md) | 远程仓库与协作说明 |

## 版权与参考项目声明

- FlinkISO / FlinkISO Lite：版权归 Techmentis Global Services Pvt Ltd，本仓库中的副本仅作技术参考与对比。
- `jewelry-qms`：为本工作区定制成果，可按实验室内部许可使用与二次开发。

## 版本

| 组件 | 当前版本 | 说明 |
|------|----------|------|
| 工作区 | 2.1.0 | ThinkPHP 8 主项目 + P1 业务深化 |
| jewelry-qms | 2.1.0 | CAPA、内审、管评、RBAC、通知、导入、仪表盘等 P1 能力 |

历史初始标签 `v1.0.0` 表示首次纳入参考项目和 Jewelry QMS 初版。版本记录见 [CHANGELOG.md](CHANGELOG.md) 与 [docs/VERSIONING.md](docs/VERSIONING.md)。
