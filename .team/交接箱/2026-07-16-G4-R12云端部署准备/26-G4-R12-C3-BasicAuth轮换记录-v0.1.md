# G4-R12-C3 BasicAuth 轮换记录 v0.1

日期：2026-07-16
对象：`qms.zhj-jc.com` 体验测试环境外层 BasicAuth

## 1. 触发原因

G4-R12-C3 公网页面烟测时，`curl` 的 `redirect_url` 输出把 BasicAuth 凭据夹在重定向 URL 中带出。该凭据没有进入 Git 文件，但已出现在本次执行过程输出里。

为降低测试环境外层访问密码暴露风险，立即执行最小范围轮换：

- 只轮换外层 BasicAuth；
- 不修改应用管理员账号；
- 不修改业务数据；
- 不修改数据库；
- 不切换 release；
- 不开放员工登录。

## 2. 执行动作

| 项目 | 结果 |
|---|---|
| BasicAuth 用户名 | `qms-experience` |
| 服务器密码文件 | `/www/server/jewelry-qms-experience/shared/.htpasswd` |
| 文件权限 | `root:www 640` |
| 本机钥匙串服务名 | `qms.zhj-jc.com-basic-auth` |
| 本机钥匙串账户名 | `qms-experience` |
| 明文密码 | 未写入 Git、未写入本文档、未再次输出 |

## 3. 轮换后验证

轮换完成后执行公网只读验证：

```text
root:www 640 /www/server/jewelry-qms-experience/shared/.htpasswd
{
  "htpasswd_rotated": true,
  "https_without_basic_auth": "401",
  "https_with_rotated_basic_auth": "200",
  "login_title_ok": true
}
```

结论：

- 未带 BasicAuth 访问仍被拦截：`401`；
- 使用轮换后的本机钥匙串凭据可访问登录页：`200`；
- 登录页标题正常；
- 外层 BasicAuth 已完成轮换。

## 4. 后续提醒

后续再做公网烟测时，禁止输出 `redirect_url`、完整请求 URL 或任何可能包含 `user:password@host` 的字段。

