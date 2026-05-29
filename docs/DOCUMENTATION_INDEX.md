# 文档总览

本文档是 `LIMS-zhj` 工作区的说明文档入口。主交付物为 `jewelry-qms`，即 ThinkPHP 8 版珠宝检测实验室质量管理系统。

## 1. 项目与部署

| 文档 | 适用对象 | 内容 |
|------|----------|------|
| [../README.md](../README.md) | 全体成员 | 仓库结构、快速开始、主项目边界 |
| [ARCHITECTURE.md](ARCHITECTURE.md) | 开发者、质量负责人 | 系统边界、技术架构、模块和审批逻辑 |
| [DEPLOYMENT.md](DEPLOYMENT.md) | 运维、部署人员 | ThinkPHP 8 部署、数据库、Web 服务器和故障排查 |
| [VERSIONING.md](VERSIONING.md) | 开发者、协作者 | Git 分支、提交、标签和禁提交内容 |
| [REMOTE_UPLOAD.md](REMOTE_UPLOAD.md) | 协作者 | 远程仓库上传和协作说明 |

## 2. 体系策划与追溯

| 文档 | 适用对象 | 内容 |
|------|----------|------|
| [QMS_V2_2_ROADMAP.md](QMS_V2_2_ROADMAP.md) | 项目负责人、开发者 | v2.2 分阶段开发路线图、执行顺序和验收标准 |
| [QMS_PLANNING_CENTER_GUIDE.md](QMS_PLANNING_CENTER_GUIDE.md) | 质量负责人、体系管理员 | 策划中心入口、流程、无编号要素、条款库和追溯矩阵 |
| [QMS_TRACEABILITY_DATA_MODEL.md](QMS_TRACEABILITY_DATA_MODEL.md) | 开发者、数据维护人员 | 外部依据、条款、要素、结构化文件和运行证据的数据模型 |
| [QMS_DOCUMENT_STRUCTURING_GUIDE.md](QMS_DOCUMENT_STRUCTURING_GUIDE.md) | 体系文件管理员、开发者 | 文件归档、Markdown 结构化、块级追溯和系统包输出 |
| [QMS_RECORD_FORMS_GUIDE.md](QMS_RECORD_FORMS_GUIDE.md) | 质量负责人、表格维护人员 | 记录表格 schema、程序记录要求和运行证据 |
| [QMS_OPERATIONS_RUNBOOK.md](QMS_OPERATIONS_RUNBOOK.md) | 开发者、运维 | 本机服务、初始化、smoke 测试、运行产物清理 |

## 3. 使用与界面

| 文档 | 适用对象 | 内容 |
|------|----------|------|
| [JEWELRY_QMS_GUIDE.md](JEWELRY_QMS_GUIDE.md) | 实验室质量负责人 | 体系文件适配、模块使用、权限和常见问题 |
| [UI_DESIGN_GUIDELINES.md](UI_DESIGN_GUIDELINES.md) | 前端和模板维护人员 | 中文界面、布局、组件、状态和交互规范 |
| [import-preview/record-forms-import-preview.md](import-preview/record-forms-import-preview.md) | 表格维护人员 | 记录表格导入预览 |

## 4. 推荐阅读顺序

如果是第一次接触项目，按以下顺序阅读：

1. [../README.md](../README.md)
2. [ARCHITECTURE.md](ARCHITECTURE.md)
3. [JEWELRY_QMS_GUIDE.md](JEWELRY_QMS_GUIDE.md)
4. [QMS_V2_2_ROADMAP.md](QMS_V2_2_ROADMAP.md)
5. [QMS_PLANNING_CENTER_GUIDE.md](QMS_PLANNING_CENTER_GUIDE.md)
6. [QMS_TRACEABILITY_DATA_MODEL.md](QMS_TRACEABILITY_DATA_MODEL.md)
7. [QMS_DOCUMENT_STRUCTURING_GUIDE.md](QMS_DOCUMENT_STRUCTURING_GUIDE.md)
8. [QMS_RECORD_FORMS_GUIDE.md](QMS_RECORD_FORMS_GUIDE.md)
9. [QMS_OPERATIONS_RUNBOOK.md](QMS_OPERATIONS_RUNBOOK.md)

如果只是本机运行或排查问题，直接看 [QMS_OPERATIONS_RUNBOOK.md](QMS_OPERATIONS_RUNBOOK.md) 和 [DEPLOYMENT.md](DEPLOYMENT.md)。
