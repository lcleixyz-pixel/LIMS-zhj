# LIMS-zhj · 珠宝检测实验室 QMS

本仓库的主交付物是 `jewelry-qms`：面向珠宝/宝玉石检测实验室的中文质量管理系统，技术栈为 ThinkPHP 8、MySQL 8 和 Docker。系统用于体系文件、记录填报、内审、管理评审、CAPA、设备、培训、供应商、投诉及质量体系证据管理。

当前治理试运行候选版本为 **2.3.0-rc.1**（本地候选标签，2026-07-28）；当前稳定版本仍为 **2.2.0**。

## 当前边界

- `jewelry-qms/` 是唯一现行应用，只增强、不替换。
- 生产/现用数据库默认不直接写；迁移、演练和批量操作必须先在测试环境验证并取得授权。
- `knowledge/internal/` 是系统生成的导出层，不作为手工编辑正式体系文件的入口。
- 早期 FlinkISO、FlinkISO Lite 和 CakePHP legacy 参考代码已从主仓拆出。历史与版权边界见 [REFERENCE_ARCHIVE.md](REFERENCE_ARCHIVE.md) 和 [REFERENCE_DOCS_NOTICE.md](REFERENCE_DOCS_NOTICE.md)。
- 第五版质量手册治理演练仍冻结，待一次真实内审后再继续，不属于当前运行系统发布范围。

## 仓库结构

```text
LIMS-zhj/
├── jewelry-qms/        # ThinkPHP 8 现行应用
├── docs/               # 架构、部署、验证、版本与操作说明
├── knowledge/          # 知识索引和系统生成导出
├── 现用文件/            # 机构现用文件，只按受控边界处理
├── 整合/                # 契约、技能套装和整合材料
├── .team/              # 作战室：当前战役、任务、日志与交接箱
├── CHANGELOG.md        # 发布版本汇总
└── README.md
```

## 本机快速启动

需要先启动 Docker Desktop，然后执行：

```bash
cd "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/jewelry-qms"
docker compose up --build
```

浏览器访问 `http://127.0.0.1:8010`。

默认管理员账号仅用于首次本机初始化：`admin / password`。首次登录后应立即修改密码，不能用于生产环境。

停止服务：

```bash
docker compose down
```

`docker compose down -v` 会删除本地数据库卷，不属于日常停止命令，执行前必须确认。

## 常用验证

```bash
cd jewelry-qms
docker compose exec app php tests/rbac_controller_normalization_smoke.php
docker compose exec app php tests/qms_ui_navigation_template_smoke.php
```

较大变更按 [系统变更控制规定](docs/jewelry-qms系统变更控制规定-v0.1.md) 执行，并把逐项结果写入 [变更台账](docs/变更台账.md)。`CHANGELOG.md` 仅在发布版本号时汇总。

## 文档入口

| 文档 | 用途 |
|---|---|
| [文档总览](docs/DOCUMENTATION_INDEX.md) | 推荐阅读顺序与全部入口 |
| [应用 README](jewelry-qms/README.md) | 模块、配置、目录与生产安全提醒 |
| [部署说明](docs/DEPLOYMENT.md) | 部署、数据库和 Web 服务配置 |
| [运行手册](docs/QMS_OPERATIONS_RUNBOOK.md) | 本机运行、验证、维护与系统失效应急 |
| [计算机化系统验证方案](docs/jewelry-qms计算机化系统验证方案-v1.0.md) | CL01 7.11 验证证据与结论 |
| [版本管理](docs/VERSIONING.md) | 分支、版本、台账和标签规则 |
| [远端分支治理](docs/BRANCH_GOVERNANCE.md) | 远端分支状态、合并记录和清理建议 |
| [当前战役](.team/当前战役.md) | 作战室唯一进度指挥文件 |

## 版本状态

| 对象 | 当前状态 |
|---|---|
| 治理试运行候选版本 | 2.3.0-rc.1 |
| 稳定版本 | 2.2.0 |
| 本地候选标签 | v2.3.0-rc.1（2026-07-28，未推送） |
| 8021 | 装配候选版本，用于治理试运行与集中验收 |
| 8010 | 保持稳定版本 2.2.0；本轮不部署 |
| 日常变更真值 | `docs/变更台账.md` |
| 发布汇总 | `CHANGELOG.md` |

发布新版本前，必须同时校准 `jewelry-qms/config/qms.php`、README、`docs/VERSIONING.md`、`CHANGELOG.md` 和 Git 标签，不能只改其中一个文件。候选版通过验收并取得发布授权后，才能转为稳定版并部署到 8010。
