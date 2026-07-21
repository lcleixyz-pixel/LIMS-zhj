# Phase 4 · DocuSeal embed 联调记录 v0.1

> 日期：2026-07-21  
> 范围：启 signing profile + 写 env + 原生 webhook 联调 + **免 SMTP embed**  
> 现用 8010：已开 `DOCUSEAL_SIGNING_ENABLED=1`（secrets 在 `jewelry-qms/docker/.env.signing`，不入库）

## 结论

| 项 | 结果 |
|---|---|
| DocuSeal 容器 | `docker compose --profile signing up -d docuseal` · `127.0.0.1:3100` |
| embed（`send_email=false`） | **通**：提审建签返回 Reviewer/Approver `embed_src` |
| 开源限制 | `/api/submissions/pdf` 与 HTML one-off 为 **Pro**；现用路径 = **template_id=1**「QMS Controlled Document Sign」签批单 |
| 原生 webhook | DocuSeal `SendWebhookRequest` → `http://app:8000/docuseal/webhook` → **HTTP 200** |
| smoke | `d4` / `d35` / `g3` **PASS** |

## 代码改动

- `DocuSealService`：`send_email` 默认关；`POST /api/submissions` + `template_id`；note JSON 存 embeds；原生验签 `timestamp.sig`
- `DocuSealWebhook`：归一 `event_type`/`data`；按提交人邮箱推进审批
- 文控详情页：展示 embed 链接 + iframe
- `compose.yaml`：`env_file: docker/.env.signing`
- 配置项：`DOCUSEAL_TEMPLATE_ID` / `DOCUSEAL_PUBLIC_BASE_URL` / `DOCUSEAL_SEND_EMAIL`

## 运行时配置（本机已写入）

文件：`jewelry-qms/docker/.env.signing`（gitignored）

```
DOCUSEAL_SIGNING_ENABLED=1
DOCUSEAL_BASE_URL=http://docuseal:3000
DOCUSEAL_PUBLIC_BASE_URL=http://127.0.0.1:3100
DOCUSEAL_API_KEY=<控制台/rails 生成>
DOCUSEAL_WEBHOOK_SECRET=whsec_…
DOCUSEAL_TEMPLATE_ID=1
DOCUSEAL_SEND_EMAIL=0
```

DocuSeal 侧：

- Webhook URL：`http://app:8000/docuseal/webhook`
- 事件：`form.completed` / `form.declined` / `submission.completed`
- 模板 external_id：`qms-controlled-document-sign`（字段：doc_number/title/version/change_reason/content_sha256 + 双角色签字）

## P1-B1 怎么用（embed）

1. 8010 对 CX-21：**发起修订** → 上传正文 → 确认审核人/批准人邮箱唯一且绑定  
2. **提交审核** → 详情页出现「DocuSeal 签批（embed）」卡片  
3. Reviewer / Approver 点「打开签署页」或页内 iframe 签署（**不用邮件**）  
4. webhook 推进审批 → 分发 → 接收确认 → 证据台账

## 注意

- 签的是**受控签批单模板**（含文件号/版本/修订原因/正文哈希），不是把整份 Word 丢进 DocuSeal（开源无 PDF one-off）  
- QM/TM 必须用真实唯一邮箱（与 `users.email` 一致），否则 webhook 无法反查用户  
- 关签批：把 `.env.signing` 里 `DOCUSEAL_SIGNING_ENABLED=0` 后 `docker compose up -d app`

## 验证摘录

```
signing_enabled=1 send_email=0
embeds: http://127.0.0.1:3100/s/…
webhook_http=200 decision=approved processed=1
DocuSeal SendWebhookRequest → QMS STATUS=200
qms_wave1_d4 / d35 / g3 smoke passed
```
