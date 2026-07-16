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

## 4. 轮换后收口复验

轮换并更新本机钥匙串后，重新执行公网与登录后页面烟测：

```text
{
  "http_root_code": "301",
  "https_without_basic_auth_code": "401",
  "login_get_code": "200",
  "admin_login_post_code": "302",
  "dashboard_code": "200",
  "candidates_code": "200",
  "candidate_detail_code": "200",
  "change_events_code": "200",
  "traceability_code": "200",
  "template_review_code": "200",
  "login_title_ok": true,
  "dashboard_env_banner_ok": true,
  "candidate_pool_entry_ok": true,
  "month_todo_wording_ok": true,
  "no_empty_template_create_link_on_dashboard": true,
  "candidates_yidanyiku_ok": true,
  "candidate_detail_impact_terms_ok": true,
  "candidate_no_auto_write_warning_ok": true,
  "change_events_page_ok": true,
  "traceability_page_ok": true,
  "template_review_has_uuid_fill_links": true,
  "replacement_char_counts": {
    "login.html": 0,
    "dashboard.html": 0,
    "candidates.html": 0,
    "candidate_detail.html": 0,
    "change_events.html": 0,
    "traceability.html": 1,
    "template_review.html": 0
  }
}
```

收口判断：

- 访问链路、登录链路、法规候选池、候选详情、变更事件、模板试填入口均可打开；
- 首页不再出现空 `template_id` 的新建记录入口；
- 溯源链页面仍有 1 个旧内容替换字符，继续作为 C4/试运行问题处理，不触发本次回退。

## 5. 后续提醒

后续再做公网烟测时，禁止输出 `redirect_url`、完整请求 URL 或任何可能包含 `user:password@host` 的字段。
