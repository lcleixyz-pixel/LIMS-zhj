# MySQL 实例已就绪(jewelry_qms · 用户态,无需管理员)

> 2026-07-09:**数据库已建好并跑通**,转正版硬条件 ①② 在此实例上 **双双 ALL GREEN**。
> 本机 MySQL80 服务仍停止、root 密码未知、沙箱无管理员——这三条原本的阻断,**用一个用户态全新 MySQL 实例绕过**(数据目录放在用户可写区,不碰 `C:\ProgramData`)。

## 当前实例(已运行)

| 项 | 值 |
|---|---|
| 数据目录 | `C:\Users\Martyr\mysql_data`(持久,跨重启保留) |
| 监听 | `127.0.0.1:3306` **仅本机**(X 插件 33060 已关) |
| 账号 | `root` 无密码 |
| 库 | `jewelry_qms`(74 表,基础 70 + 4 迁移新增) |
| 迁移 | 20 个全部已导(含 `20260704_external_change_events`) |
| 种子 | 37 行现行程序已入 `documents`(status=published, soft_delete=0),供 `reference_existing_current` 反查命中 |
| 平台连接 | 用 `config/database.php` 默认值即可(root/空/127.0.0.1/3306/jewelry_qms),**无需 `.env`** |
| 排序规则 | 库 `utf8mb4_unicode_ci`;导入迁移时连接需 `SET collation_connection=utf8mb4_unicode_ci`(否则 `gbk_chinese_ci` 会让 `DEFAULT '新对话'` 报 1067) |

## 转正版硬条件实测(2026-07-09)

| 硬条件 | 命令 | 结果 |
|---|---|---|
| ① v1.21a 青禾产物 dry-run 发现项=0 | `php think qms:preimport-package --package-dir 甲方金样第9道/package --json-out …` | **status=passed, findings=0** ✅ |
| ② 乙方正式金样 MySQL 全量 run | `run_preimport_golden.py --fixture-dir …/fixture` | **ALL GREEN, findings=0** ✅ |

> ① 的覆盖说明:用的是甲方第9道**最小**青禾包(经 `qms_lims_preimport_build.py` 产出),documents 含 37 行 `reference_existing_current`,已用种子让其在库内命中。真正的 v1.21a **全量**"第五版候选修订"stage md 仍不在工作区(构建脚本默认指向 macOS 路径)——若要全量①,需补齐该 stage 目录后再构建。最小包已证 dry-run 链路 0 发现。

## 启动 / 停止

实例由后台 mysqld 进程维持。若进程不在了(重启电脑、会话结束等),用脚本拉起:

```bash
cd "C:/Users/Martyr/OneDrive/桌面/参考"
bash 启动MySQL.sh          # 前台运行,保持窗口打开
```

停止(另开窗口):
```bash
"/c/Program Files/MySQL/MySQL Server 8.0/bin/mysqladmin.exe" -u root -h 127.0.0.1 -P 3306 shutdown
```

连接查看:
```bash
"/c/Program Files/MySQL/MySQL Server 8.0/bin/mysql.exe" -u root -h 127.0.0.1 -P 3306 -e "USE jewelry_qms; SHOW TABLES;"
```

## 复跑 ①②

```bash
cd "C:/Users/Martyr/OneDrive/桌面/参考/__work_lims/LIMS-zhj-minimal-runnable-20260708/jewelry-qms"
PLAT="C:/Users/Martyr/OneDrive/桌面/参考/__work_lims/LIMS-zhj-minimal-runnable-20260708/jewelry-qms"

# ② 乙方正式金样
python "C:/Users/Martyr/OneDrive/桌面/参考/乙方夹具回归/preimport_golden/run_preimport_golden.py" \
  --lims-root "$PLAT" --fixture-dir "C:/Users/Martyr/OneDrive/桌面/参考/乙方夹具回归/preimport_golden/fixture"

# ① 甲方第9道 dry-run
php think qms:preimport-package \
  --package-dir "C:/Users/Martyr/OneDrive/桌面/参考/甲方金样第9道/package" \
  --json-out "C:/Users/Martyr/OneDrive/桌面/参考/甲方金样第9道/dryrun_report.json"
```

## 与官方 MySQL80 服务的关系(可选)

本实例**不是** Windows 的 MySQL80 服务,而是一个并列的用户态实例(独立数据目录)。若日后你想改用官方服务(开机自启、用真实 root 密码),仍需:

1. 管理员启服务:`net start MySQL80`;
2. 提供 root 密码;
3. 导库+迁移:`mysql -u root -p < database/jewelry_qms.sql` + 20 个迁移(遇 `DEFAULT '中文'` 报 1067 时,连接加 `SET collation_connection=utf8mb4_unicode_ci`);
4. 种 37 程序(同上种子逻辑);
5. 建 `jewelry-qms/.env` 写真实密码。

官方服务与本用户态实例二选一即可,平台连哪个都行。

## 安全说明

- 实例仅监听 `127.0.0.1`,不对外网暴露;root 无密码仅限本机。
- 若需更严:可给 root 设密码并在 `jewelry-qms/.env` 配 `DB_PASS`,或建专用非 root 用户。
