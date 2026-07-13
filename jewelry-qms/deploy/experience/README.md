# Jewelry QMS 云端体验测试环境

本目录用于把 `jewelry-qms` 部署为**体验和测试环境**。它不是正式生产环境，只允许录入明确标记为“模拟”的测试数据，禁止录入正式检测业务数据、客户资料、身份证明、受控体系原件或其他敏感信息。

## 1. 固定边界

- 应用只发布到 `127.0.0.1:18010`，公网入口由宝塔 Nginx 提供。
- MySQL 不发布任何宿主机端口，只加入 `qms_db_internal` 封闭网络。
- 应用同时加入数据库内网和普通 `qms_edge` 网络，避免内部网络阻断宿主机回环访问。
- 宝塔入口必须启用 HTTPS 和独立 BasicAuth；BasicAuth 凭据与应用账号分开。
- 服务器只接收本机完成 `linux/amd64` 验证的代码包和镜像包，不在服务器重建镜像。
- 密码只在服务器生成，不写入 Git、交接文档或聊天。

## 2. 服务器目录

```text
/www/server/jewelry-qms-experience/
├── current -> releases/<release-id>/
├── releases/
└── shared/
    ├── .env
    ├── .htpasswd
    └── snapshots/
```

每个 release 都是只读发布副本。禁止在 `current` 或 `releases/<release-id>` 内直接编辑业务代码；所有修订在本机完成验证后产生新 release。

## 3. 首次部署（仅在 G2 服务器变更获批后）

以下命令是后续执行参考，G2-B 本机阶段不在服务器运行。

### 3.1 创建目录并解压代码包

```bash
set -euo pipefail
BASE=/www/server/jewelry-qms-experience
RELEASE_ID=<已验证的-release-id>

install -d -m 750 "$BASE/releases/$RELEASE_ID" "$BASE/shared/snapshots"
tar -xzf "/root/LIMS-zhj-experience-${RELEASE_ID}.tar.gz" -C "$BASE/releases/$RELEASE_ID"
ln -sfn "$BASE/releases/$RELEASE_ID" "$BASE/current"
```

### 3.2 在服务器生成 `.env`

```bash
BASE=/www/server/jewelry-qms-experience
cp "$BASE/current/jewelry-qms/deploy/experience/.env.example" "$BASE/shared/.env"
chmod 600 "$BASE/shared/.env"
openssl rand -base64 36
openssl rand -base64 36
```

把两次随机结果分别填入 `MYSQL_PASSWORD` 和 `MYSQL_ROOT_PASSWORD`。不要复制到聊天或提交到 Git。`QMS_IMAGE_TAG` 必须改为本地验证记录中的实际 amd64 tag。

### 3.3 加载本机验证过的镜像包

```bash
sha256sum -c /root/LIMS-zhj-experience-SHA256SUMS.txt
gzip -dc "/root/LIMS-zhj-experience-images-${RELEASE_ID}.tar.gz" | docker load
docker image inspect \
  "jewelry-qms-experience:<已验证-tag>" \
  jewelry-qms-mysql:8.4-amd64 \
  --format '{{.RepoTags}} architecture={{.Architecture}} os={{.Os}}'
```

两张镜像都必须显示 `architecture=amd64 os=linux`。

### 3.4 启动与验证

```bash
BASE=/www/server/jewelry-qms-experience
cd "$BASE/current/jewelry-qms"
docker compose --env-file "$BASE/shared/.env" \
  -f deploy/experience/compose.yaml up -d --pull never
bash deploy/experience/verify.sh "$BASE/shared/.env"
```

## 4. 宝塔 Nginx、HTTPS 与 BasicAuth

在宝塔为测试子域名建立独立站点并申请 HTTPS 证书，再采用 `nginx.conf.example` 中的反向代理片段。代理目标固定为 `http://127.0.0.1:18010`。

BasicAuth 密码在服务器交互生成：

```bash
htpasswd -c /www/server/jewelry-qms-experience/shared/.htpasswd qms-experience
chmod 640 /www/server/jewelry-qms-experience/shared/.htpasswd
```

不要把 `.htpasswd`、应用密码或数据库密码放进 release、Git 或聊天。未通过 BasicAuth 的公网访问应返回 `401`。

## 5. 日常命令

```bash
BASE=/www/server/jewelry-qms-experience
cd "$BASE/current/jewelry-qms"

# 状态和只读验证
docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml ps
bash deploy/experience/verify.sh "$BASE/shared/.env"

# 日志（只查看最近 200 行）
docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml logs --tail 200 app db

# 停止和启动；不删除 volume
docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml stop
docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml start
```

不要使用 `down -v` 处理服务器体验环境，因为它会删除持久化数据卷。

## 6. 创建测试快照

快照只保存模拟数据。先建立时间戳目录：

```bash
set -euo pipefail
BASE=/www/server/jewelry-qms-experience
STAMP="$(date +%Y%m%d-%H%M%S)"
SNAPSHOT="$BASE/shared/snapshots/$STAMP"
install -d -m 750 "$SNAPSHOT"
cd "$BASE/current/jewelry-qms"

docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml \
  exec -T db sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' \
  > "$SNAPSHOT/database.sql"

docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml \
  exec -T app tar czf - -C /app/public uploads \
  > "$SNAPSHOT/uploads.tar.gz"

sha256sum "$SNAPSHOT/database.sql" "$SNAPSHOT/uploads.tar.gz" \
  > "$SNAPSHOT/SHA256SUMS"
chmod 600 "$SNAPSHOT/database.sql" "$SNAPSHOT/uploads.tar.gz" "$SNAPSHOT/SHA256SUMS"
```

## 7. 数据库恢复演练

恢复演练必须使用独立临时容器和独立 volume，不能覆盖当前 `db_data`：

```bash
set -euo pipefail
SNAPSHOT=/www/server/jewelry-qms-experience/shared/snapshots/<时间戳>
DRILL_NAME="qms-restore-drill-$(date +%s)"
DRILL_VOLUME="${DRILL_NAME}-data"
DRILL_ROOT_PASSWORD="$(openssl rand -base64 36)"

cd "$SNAPSHOT"
sha256sum -c SHA256SUMS
docker volume create "$DRILL_VOLUME"
docker run -d --name "$DRILL_NAME" --network none \
  -e MYSQL_ROOT_PASSWORD="$DRILL_ROOT_PASSWORD" \
  -e MYSQL_DATABASE=jewelry_qms_restore_drill \
  -v "$DRILL_VOLUME:/var/lib/mysql" \
  jewelry-qms-mysql:8.4-amd64

until docker exec -e MYSQL_PWD="$DRILL_ROOT_PASSWORD" "$DRILL_NAME" \
  mysqladmin ping -uroot --silent; do sleep 2; done

docker exec -e MYSQL_PWD="$DRILL_ROOT_PASSWORD" -i "$DRILL_NAME" \
  mysql -uroot jewelry_qms_restore_drill < database.sql

docker exec -e MYSQL_PWD="$DRILL_ROOT_PASSWORD" "$DRILL_NAME" \
  mysql -uroot -Nse 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema="jewelry_qms_restore_drill";'
```

记录表数量和演练结论后，只删除明确命名的临时资源：

```bash
docker rm -f "$DRILL_NAME"
docker volume rm "$DRILL_VOLUME"
unset DRILL_ROOT_PASSWORD
```

## 8. 升级与切换 `current`

1. 校验并解压新的代码包到全新 `releases/<release-id>`。
2. 加载同一验证记录中的 amd64 镜像包。
3. 在新 release 目录执行 `docker compose config`。
4. 原子切换 `current`，再执行 `up -d --pull never`。
5. 运行 `verify.sh`，复核登录、填报、上传、PDF 和持久化。

```bash
BASE=/www/server/jewelry-qms-experience
NEW_RELEASE=<新-release-id>
ln -sfn "$BASE/releases/$NEW_RELEASE" "$BASE/current"
cd "$BASE/current/jewelry-qms"
docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml up -d --pull never

if docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml \
  exec -T app php think list --raw | grep -qx 'clear'; then
  docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml \
    exec -T app php think clear
fi
```

不得为了清缓存删除整个 `runtime_data` 卷。

## 9. 回退

若新版本验证失败，把 `current` 切回上一个已验证 release，并把 `.env` 中 `QMS_IMAGE_TAG` 改回对应已验证 tag，然后重新执行 `up -d --pull never`：

```bash
BASE=/www/server/jewelry-qms-experience
PREVIOUS_RELEASE=<上一已验证-release-id>
ln -sfn "$BASE/releases/$PREVIOUS_RELEASE" "$BASE/current"
cd "$BASE/current/jewelry-qms"
docker compose --env-file "$BASE/shared/.env" -f deploy/experience/compose.yaml up -d --pull never
bash deploy/experience/verify.sh "$BASE/shared/.env"
```

版本回退与数据库恢复是两个动作。数据库只有在确认发生了不兼容写入且用户单独批准后，才从已验证快照恢复。

## 10. 磁盘与日志维护

```bash
docker system df
docker image prune -f
find /www/server/jewelry-qms-experience/releases -mindepth 1 -maxdepth 1 -type d -print
```

先列出 release，由人工确认后只保留当前版、上一版和一个已验证版本。Compose 已把容器日志限制为 `max-size=10m`、`max-file=3`。

共享服务器禁止直接执行：

```text
docker system prune -a
docker volume prune
不带明确目录范围的 rm -rf
```

这些命令可能误删其他应用镜像、缓存或持久化数据。
