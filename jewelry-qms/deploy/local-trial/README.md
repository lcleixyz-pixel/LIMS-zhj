# 本机 8010 不可变镜像发布与回退

本目录用于 G‑R14 最终人工闸门之后的本机 8010 切换。它只替换 `app` 容器，复用现有
`jewelry-qms_default` 网络和数据库容器；不得部署云端，也不得在切换时临时构建镜像。

## 为什么需要这一层

现用 8010 由开发 Compose 启动，并把主工作区直接挂载到 `/app`。因此本机当前并不存在
云端使用的 `releases/current` 发布结构，直接写“切换 current”并不成立。本方案先把现用版
和候选版都制成不可变本机镜像，再通过本机发布根目录中的 `current` 符号链接选择 Compose
清单；应用容器据此重建，数据库不重建。

## 最终闸门前不得执行的动作

- 不得停止、删除或重建 `jewelry-qms-app-1`。
- 不得对现用数据库运行 G‑R14 迁移。
- 不得把 `current` 指向候选版后运行 `docker compose up`。
- 不得部署云端。

镜像构建、镜像检查和发布目录预生成不改变 8010 运行态，但仍须记录镜像 ID 和 SHA。

## 发布目录

建议把 Git 外归档根目录设为：

```text
.../05-Codex输出归档/LIMS-zhj/G-R14/local-releases/
├── current -> releases/<release-id>/
├── releases/
│   ├── pre-gr14-<id>/
│   └── g-r14-<id>/
├── shared/
│   ├── runtime/
│   ├── uploads/
│   └── release.env
└── snapshots/
```

`release.env` 只保存本机绝对路径和镜像标签，不写数据库密码。数据库连接继续由服务器端
环境和受控 `.env` 提供。

## 升级前快照

获得用户最终批准后，必须先完成：

1. 导出 `jewelry_qms` 完整数据库并校验 SHA256；
2. 归档现有 `public/uploads/`，记录空目录也要生成有效压缩包；
3. 保存现用容器 inspect、镜像 ID、Compose 配置和主工作区状态；
4. 将现用代码构建为不可变回退镜像，并在隔离端口验证能启动；
5. 将现有上传目录和必要运行证据复制到 `shared/`，不得移动或删除原目录；
6. 在恢复临时库中完成一次数据库恢复核对。

任一项失败都停止，不进入切换。

## `current` 切换

所有环境变量必须使用绝对路径。候选镜像必须是已在 8011 通过验收的同一镜像 ID：

```bash
ln -sfn "releases/g-r14-<id>" "$LOCAL_RELEASE_ROOT/current"
docker compose \
  --env-file "$LOCAL_RELEASE_ROOT/shared/release.env" \
  -p jewelry-qms \
  -f "$LOCAL_RELEASE_ROOT/current/compose.yaml" \
  up -d --no-deps --force-recreate app
```

切换后先验证登录、数据库迁移、核心保存、`SIM-` 强制编号、岗位越权、15 项模板和上传/
打印，再决定是否保持候选版。不得使用 `--remove-orphans`，避免误删数据库容器。

## 立即回退

出现登录不可用、迁移失败、核心保存错误、岗位越权、非 `SIM-` 误写或上传目录异常时：

1. 导出切换后产生的试运行证据；
2. 把 `current` 指回 `pre-gr14-<id>`；
3. 用同一命令重建 `app`，确认旧版登录可用；
4. 若结构或内容受损，恢复升级前数据库快照；
5. 核对上传目录 SHA 和关键数据计数。

不得用 Git 工作区回退替代不可变镜像回退，也不得删除原快照和候选证据。
