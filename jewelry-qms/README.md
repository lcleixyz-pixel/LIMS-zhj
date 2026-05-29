# Jewelry QMS

珠宝检测实验室质量管理系统，面向 CMA/CNAS 与 ISO/IEC 17025 质量体系信息化。当前目录是仓库主交付物，技术栈为 ThinkPHP 8 + PHP 8.1+，服务端渲染页面位于 `app/view`，公开入口为 `public/`。

CakePHP 2.x 旧版归档位于仓库根目录 `jewelry-qms-legacy/`，不再作为主运行版本。

## 功能模块

| 模块 | 当前能力 |
|------|----------|
| 文件控制 | 四层级、模板管理、附件上传、审批、发布、修订 |
| 体系策划 | 外部依据、条款库、无编号要素、文件结构化、追溯矩阵、质量方针目标 |
| 文件结构化 | 外部依据、质量手册、程序文件、记录表格 Markdown 化和块级追溯 |
| 记录表格 | 表格模板、schema 复核、来源预览、运行填写与证据输出 |
| 审批 | 按文件层级差异化审批，审批待办通知 |
| 内部审核 | 计划批准、日程、检查表、发现，发现可触发 CAPA |
| 管理评审 | 输入汇总、决议事项跟踪与验证 |
| CAPA | 来源关联、原因分析、措施实施、效果验证、关闭 |
| 设备与校准 | 设备台账、校准记录、到期提醒 |
| 培训与能力 | 培训记录、完成标记、能力确认 |
| 供应商 | 评价驱动状态、合格供应商名录 |
| 客户投诉 | 受理、调查、处置、回复、关闭 |
| 不符合工作 | 评估、纠正、验证、关闭 |
| 通知 / 导入 / 仪表盘 | 待办聚合、CSV 导入、校准到期、CAPA 超期提醒 |

## 环境要求

- PHP 8.1+
- Composer 2.x
- MySQL 5.7+ 或 MariaDB 10.3+
- PHP 扩展：`mbstring`, `pdo_mysql`, `json`, `openssl`, `fileinfo`

## 快速启动

```bash
cd jewelry-qms
composer install
cp .example.env .env
php think run
```

浏览器访问命令输出的地址，默认通常为：

```text
http://127.0.0.1:8000
```

本机固定使用 8010 端口时可执行：

```bash
php think run -H 127.0.0.1 -p 8010
```

## 数据库初始化

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS jewelry_qms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p jewelry_qms < database/jewelry_qms.sql
```

默认账号：

```text
admin / password
```

首次登录后必须修改默认密码。

## 配置

数据库连接通过 `.env` 配置，并由 `config/database.php` 读取：

```ini
APP_DEBUG = false

DB_TYPE = mysql
DB_HOST = 127.0.0.1
DB_NAME = jewelry_qms
DB_USER = your_user
DB_PASS = your_password
DB_PORT = 3306
DB_CHARSET = utf8mb4
```

业务配置位于 `config/qms.php`，包括：

- 系统标题与版本
- 默认 `company_id`
- 文件层级 `docLevels`
- 审批规则 `approvalRules`
- 五角色权限矩阵
- 上传扩展名和大小限制
- 通知提醒参数

## 目录结构

```text
jewelry-qms/
├── app/
│   ├── controller/        # 控制器
│   ├── Model/             # 模型
│   ├── middleware/        # Auth / Rbac / AuditLog
│   ├── service/           # 审批、流程、通知、文件、导入服务
│   └── view/              # 服务端模板
├── config/                # 应用与业务配置
├── database/              # jewelry_qms.sql
├── public/                # Web 入口与 uploads
├── route/                 # 路由
└── runtime/               # 缓存、日志、运行时文件
```

生产环境 Web 根目录必须指向：

```text
jewelry-qms/public
```

## 生产安全提醒

- `.env` 中关闭 `APP_DEBUG`
- 修改默认管理员密码
- 配置 HTTPS
- 确保 `runtime/` 和 `public/uploads/` 可写
- 禁止外部访问 `.env`、`.git`、源码目录和备份文件
- 定期备份数据库与 `public/uploads/`

## 更多文档

- 仓库总览：`../README.md`
- 架构说明：`../docs/ARCHITECTURE.md`
- 部署指南：`../docs/DEPLOYMENT.md`
- 使用与体系文件适配：`../docs/JEWELRY_QMS_GUIDE.md`
- 文档总览：`../docs/DOCUMENTATION_INDEX.md`
- v2.2 开发路线图：`../docs/QMS_V2_2_ROADMAP.md`
- 体系策划中心：`../docs/QMS_PLANNING_CENTER_GUIDE.md`
- 追溯数据模型：`../docs/QMS_TRACEABILITY_DATA_MODEL.md`
- 文件结构化：`../docs/QMS_DOCUMENT_STRUCTURING_GUIDE.md`
- 记录表格：`../docs/QMS_RECORD_FORMS_GUIDE.md`
- 运行维护：`../docs/QMS_OPERATIONS_RUNBOOK.md`
