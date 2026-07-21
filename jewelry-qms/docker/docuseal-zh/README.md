# DocuSeal 简体中文自建镜像

钉版本：见 `VERSION` / `GIT_SHA`（当前 3.1.5 / `5fe75c84…`）。

## 构建与启动

在 `jewelry-qms/` 下：

```bash
docker compose --profile signing build docuseal
docker compose --profile signing up -d docuseal
```

镜像名：`lims/docuseal-zh:3.1.5-zh`

## 覆盖层

| 路径 | 作用 |
|---|---|
| `overlay/app/javascript/submission_form/i18n.js` | 签署页 zh |
| `overlay/app/javascript/template_builder/i18n.js` | 模板设计器 zh |
| `overlay/config/locales/i18n.yml` | 管理台 zh-CN / zh |
| `overlay/config/application.rb` | `available_locales` 含 zh / zh-CN |
| `overlay/app/controllers/accounts_controller.rb` | 语言下拉含「中文（简体）」 |

## 重新生成词条

```bash
cd docker/docuseal-zh
# 若升版本：更新 VERSION/GIT_SHA，重新下载 scripts/upstream_*
PYTHONUNBUFFERED=1 python3 scripts/generate_zh_overlay.py
```

## 管理台切中文

打开 http://127.0.0.1:3100 → Settings / Account → Language → **中文（简体）**。

签署页：浏览器语言为 `zh-CN` 时自动走 `zh` 词条。
