# 变更记录

本文件遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.0.0/)，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [1.0.0] - 2026-05-19

### 新增

- 工作区 Monorepo：纳入 FlinkISO On-Premise、FlinkISO Lite 参考项目
- **jewelry-qms** 珠宝检测实验室质量管理系统初版
  - 九模块数据库 Schema（`jewelry_qms.sql`）
  - 中文界面、导航、工作台
  - 文件控制：四层级、Word 上传、差异化审批、版本修订、模板管理
  - 其余模块 CRUD 骨架（内审、管评、CAPA、设备、培训、供应商、投诉、不符合）
- 文档：`README`、`docs/*`、`jewelry-qms/README`
- Git 版本管理配置（`.gitignore`、`VERSIONING.md`）

### 说明

- 默认账号 `admin` / `password` 仅限首次部署，生产环境必须修改
- 参考项目版权归原权利人，本仓库仅作技术参考

[1.0.0]: https://github.com/your-org/jewelry-lab-qms/releases/tag/v1.0.0
