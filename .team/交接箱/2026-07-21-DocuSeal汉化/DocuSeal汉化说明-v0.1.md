# DocuSeal 汉化说明 v0.1

> 日期：2026-07-21  
> 范围：自托管 DocuSeal **签署页 + 管理台**简体中文（钉上游 3.1.5）  
> 方式：源码级 overlay + 自建镜像，**不**使用第三方汉化 fork

## 结论

| 项 | 结果 |
|---|---|
| 上游钉版本 | DocuSeal **3.1.5**（`GIT_SHA=5fe75c84ffc71d1e879884f453a7532b4dee049a`） |
| 镜像 | `lims/docuseal-zh:3.1.5-zh` |
| compose | [`jewelry-qms/compose.yaml`](../../../jewelry-qms/compose.yaml) `docuseal` 服务改为 `build: ./docker/docuseal-zh` |
| 容器版本文件 | `/app/.version` = `3.1.5-zh`；3100 HTTP 200 |
| Rails `zh-CN` | `I18n.t(:templates)` →「模板」；`I18n.t(:settings)` →「设置」 |
| 签署页打包 | `public/packs/js/form-*.js` 含第 15 套 `sign_and_complete` →「签名并完成」 |
| QMS smoke | `qms_wave1_d4_webhook_smoke` **PASS**；`qms_wave1_d35_signing_loop_smoke` **PASS** |
| API/webhook | 未改契约；`.env.signing` 七键不变 |

## 目录

```
jewelry-qms/docker/docuseal-zh/
  VERSION / GIT_SHA / Dockerfile / README.md
  overlay/
    app/javascript/submission_form/i18n.js      # +zh
    app/javascript/template_builder/i18n.js     # +zh
    config/locales/i18n.yml                     # +zh-CN / zh
    config/application.rb                       # available_locales
    app/controllers/accounts_controller.rb      # 语言下拉
  scripts/generate_zh_overlay.py                # 重生成词条
```

## 使用

```bash
cd jewelry-qms
docker compose --profile signing build docuseal
docker compose --profile signing up -d docuseal
```

- **管理台中文**：打开 http://127.0.0.1:3100 → Account → Language → **中文（简体）**（写入 `docuseal_data`，一次即可）。
- **签署页中文**：浏览器语言为 `zh-CN` 时，`/s/{slug}` 与 QMS iframe 自动用 `zh` 词条（`navigator.language` → `zh`）。

## 验收清单（本机已做）

1. 镜像 Built 且容器 Up，端口 `127.0.0.1:3100`
2. 容器内 `available_locales` 含 `zh-CN` / `zh`；LOCALE_OPTIONS 含「中文（简体）」
3. form pack 含中文「签名并完成」
4. d4 / d35 smoke PASS

**人工一眼确认（建议你做一次）**：3100 切语言后菜单为中文；任一文控提审后 iframe 签署按钮为「签名并完成」。

## 升级上游

1. 改 `VERSION` / `GIT_SHA` 与 compose `build.args`
2. 重新拉取 `scripts/upstream_*` 后跑 `python3 scripts/generate_zh_overlay.py`
3. `docker compose --profile signing build --no-cache docuseal && up -d docuseal`

## 明确不做

- 不改 QMS 签批 PHP 逻辑与 webhook 验签
- 不把 API Key 入库
- 不向上游强推 PR（本仓自建镜像为准）
