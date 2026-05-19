# FlinkISO 珠宝检测实验室 QMS 工作区

本仓库为**单体工作区（Monorepo）**，包含两个开源/参考版 FlinkISO 项目，以及基于对比分析后定制的**珠宝检测实验室质量管理系统（Jewelry QMS）**。

适用于：在已有独立检测业务管理系统的前提下，为满足 **CMA / CNAS（ISO/IEC 17025）** 要求建设信息化质量管理体系。

---

## 仓库结构

```
flinkiso-ver-2/
├── README.md                          # 本文件：工作区总览
├── docs/                              # 详细文档
│   ├── ARCHITECTURE.md                # 架构与选型说明
│   ├── PROJECT_COMPARISON.md          # 两参考项目对比
│   ├── DEPLOYMENT.md                  # 部署指南
│   ├── VERSIONING.md                  # 版本管理与发布规范
│   └── JEWELRY_QMS_GUIDE.md           # Jewelry QMS 使用与定制指南
├── flinkiso/                          # 参考项目 A：FlinkISO On-Premise 2.2.42
│   └── flinkiso-ver-2x-on-premise/
├── flinkiso-lite-master/              # 参考项目 B：FlinkISO Lite（CakePHP 2.3.6）
│   └── flinkiso-lite-master/
└── jewelry-qms/                       # 定制项目：珠宝检测实验室 QMS（主交付物）
```

| 子项目 | 角色 | 技术栈 | 说明 |
|--------|------|--------|------|
| `flinkiso/.../on-premise` | 参考 | CakePHP 2.10.24 | 企业本地版，ONLYOFFICE、PDF、动态表单、计费 |
| `flinkiso-lite-master/...` | 参考 + 基础框架来源 | CakePHP 2.3.6 | 模块广、CAPA/培训/供应商/内审等 |
| `jewelry-qms` | **生产定制** | CakePHP 2.x | 中文 17025 QMS，九模块，四层级文件控制 |

---

## 快速开始（Jewelry QMS）

1. 安装 PHP、MySQL、Apache（见 [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)）
2. 导入数据库：`jewelry-qms/app/webroot/schema/jewelry_qms.sql`
3. 配置 `jewelry-qms/app/Config/database.php`
4. 浏览器访问 `jewelry-qms` 对应 URL
5. 默认账号：`admin` / `password`（**首次登录后务必修改**）

详细步骤见 [jewelry-qms/README.md](jewelry-qms/README.md) 与 [docs/JEWELRY_QMS_GUIDE.md](docs/JEWELRY_QMS_GUIDE.md)。

---

## 文档索引

| 文档 | 内容 |
|------|------|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | 系统边界、与 LIMS 关系、技术选型 |
| [docs/PROJECT_COMPARISON.md](docs/PROJECT_COMPARISON.md) | FlinkISO vs FlinkISO Lite 功能对比 |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | 三项目部署与环境要求 |
| [docs/VERSIONING.md](docs/VERSIONING.md) | Git 分支、标签、提交规范 |
| [docs/JEWELRY_QMS_GUIDE.md](docs/JEWELRY_QMS_GUIDE.md) | 体系文件适配、模板、审批流 |

---

## 版权与参考项目声明

- **FlinkISO / FlinkISO Lite**：版权归 Techmentis Global Services Pvt Ltd，本仓库中的副本仅作**技术参考与对比**，定制开发以 `jewelry-qms` 为主。
- **jewelry-qms**：为本工作区定制成果，可按实验室内部许可使用与二次开发。

---

## 版本

| 组件 | 当前版本 | 说明 |
|------|----------|------|
| 工作区 | 1.0.0 | 初始纳入三项目 + 文档 |
| jewelry-qms | 1.0.0 | 九模块骨架 + 文件控制完整实现 |

版本记录见 [docs/VERSIONING.md](docs/VERSIONING.md) 与 Git 标签 `v1.0.0`。
