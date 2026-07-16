# G4-R11-B1 关闭本地 debug 验证记录 v0.1

日期：2026-07-16

## 本轮目标

治理试运行放行前 P0 硬伤：现用本地 8010 不应暴露 ThinkPHP trace 面板、SQL、SESSION_ID、服务器路径等调试信息。

## 执行边界

- 只处理本地 debug 配置。
- 不修改账号。
- 不写业务数据库。
- 不部署云端。
- 不处理孤儿提醒、演示记录、记录锁定和菜单权限过滤。

## 修改内容

将以下本地配置从 debug 开启改为关闭：

- `jewelry-qms/compose.yaml`
- `jewelry-qms/docker/.env.docker`
- `jewelry-qms/.env`

配置目标：

```text
APP_DEBUG=false
```

## 执行动作

重建本地 app 容器，使配置生效：

```bash
docker compose -f jewelry-qms/compose.yaml up -d --force-recreate app
```

说明：数据库容器保持运行，未重建。

## 验证证据

运行态配置：

```text
env APP_DEBUG=false
/app/.env: APP_DEBUG = false
app_debug=false
db_trigger_sql=false
```

HTTP 页面验证：

- 请求：`GET http://localhost:8010/login/index`
- 响应 HTML 大小由约 26 KB 降为约 5.9 KB。
- 未检出以下调试标识：
  - `ShowPageTrace`
  - `SESSION_ID=`
  - `运行时间`
  - `/app/vendor`

关键烟测：

```text
qms_g4r7_trial_readiness_smoke passed
qms_g4r9_stale_link_guard_smoke passed
```

## 结论

G4-R11-B1 已完成。本地 8010 已关闭 debug，登录页不再暴露 ThinkPHP trace 面板。

## 剩余硬伤

仍未处理：

- B2：测试账号/弱口令收口。
- B3：孤儿期间核查提醒与演示培训记录归档。
- C：`generated` 记录编辑控制。
- E：菜单权限过滤。
