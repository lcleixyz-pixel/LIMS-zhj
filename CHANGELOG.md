# 变更记录

本文件遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.0.0/)，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

> **台账分工（2026-07-11 拍板）**：日常变更逐条记录见 [docs/变更台账.md](docs/变更台账.md)，本文件仅在发布新版本号时按主题汇总。

## [Unreleased]

尚未发布后续候选。

## [2.2.0] - 2026-07-20

### 新增

- DocuSeal 自托管签署（compose profile `signing`）与 Webhook 验签落库
- 文档状态写入守卫（非授权路径写 approved/effective 阻断）
- 登录限流与强制改密（`must_change_password`）
- 期间核查计划机制入口、员工唯一性与校准合格必填校验
- 记录模板空 schema 拒存、子表列展示；记录实例字段留痕

### 变更

- 批准/推进类写操作统一 POST + CSRF；Cookie httponly + SameSite=Lax
- 仿真环境可选 `sim-` UUID 前缀

### 修复

- 承接 main 已合入的编号年份叠加、表单字段契约、管评校准合格率枚举等 P0 修复

### 安全

- 清除残余 GET 写路由；Webhook 防重放/验签/哈希比对；AuditLog 覆盖关键写动作

## [2.1.0] - 2026-05-21

### 新增

- P1 业务深化：CAPA 状态流转、来源关联、效果验证与关闭
- 内审串联：计划批准、日程回避检查、检查表、发现触发 CAPA
- 管理评审：输入自动汇总、决议跟踪验证
- 不符合/投诉：严重程度、处置决定、闭环推进、CAPA 关联
- 设备校准：到期提醒、校准后自动更新台账
- 培训完成标记、供应商评价驱动状态、合格供应商名录
- RBAC 五角色权限矩阵中间件
- 通知系统：审批待办、校准到期、CAPA 超期
- CSV 批量导入（文件台账、设备、人员）
- 仪表盘：待办聚合、校准到期看板

### 修复

- 修复 Login/Approval 控制器中文乱码
- 完成 ThinkPHP 8 迁移后工作树提交

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
