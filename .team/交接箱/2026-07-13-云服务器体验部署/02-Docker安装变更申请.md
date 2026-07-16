# G2 前置变更申请：安装 Docker 运行环境

> 日期：2026-07-13
> 目标：`101.200.41.200`（Alibaba Cloud Linux 3.2104 U12，x86_64）
> 当前状态：**用户已批准 G2-A；已执行并验证通过，详见 `03-G2A-Docker安装验证记录.md`**

## 一、为什么需要这项变更

G1 已确认目标服务器没有 Docker Engine、Docker Compose 和 Docker Buildx。`jewelry-qms` 体验环境采用 Docker Compose 隔离部署，因此这三项是进入后续部署的前置条件。

## 二、本次建议授权的最小范围

建议把本次授权限定为“G2-A：安装并验证 Docker 运行环境”，只包含：

1. 添加阿里云内网 Docker CE 软件源文件；
2. 安装 Alibaba Cloud Linux 3 的 DNF 源兼容插件；
3. 安装 `docker-ce`、`docker-ce-cli`、`containerd.io`、`docker-buildx-plugin`、`docker-compose-plugin`；
4. 启动 Docker 服务并设置开机自启；
5. 只读验证版本、服务状态、端口和现有网站。

明确不包含：

- 不上传或加载 QMS/MySQL 镜像；
- 不创建 QMS 容器、网络、volume 或部署目录；
- 不修改宝塔、Nginx、安全组、DNS 或 HTTPS；
- 不拉取 Docker Hub 的 `hello-world`；
- 不删除现有文件、网站或系统服务。

## 三、依据与路线选择

- 采用阿里云官方针对 Alibaba Cloud Linux 3 的 Docker CE 安装路线，使用 `mirrors.cloud.aliyuncs.com` 内网镜像源和 `dnf-plugin-releasever-adapter`。
- 安装 Compose/Buildx 插件包，不使用旧式独立 `docker-compose`。
- 不使用 `curl | sh` 便利脚本；所有拟安装包名称明确可审计。
- 后续业务镜像仍按 v0.2 采用本机生成 amd64 镜像包、校验后上传、服务器 `docker load` 的路线。

## 四、批准后拟执行的变更命令

以下命令已在用户批准 G2-A 后执行：

```bash
set -euo pipefail

wget -O /etc/yum.repos.d/docker-ce.repo \
  http://mirrors.cloud.aliyuncs.com/docker-ce/linux/centos/docker-ce.repo

sed -i \
  's|https://mirrors.aliyun.com|http://mirrors.cloud.aliyuncs.com|g' \
  /etc/yum.repos.d/docker-ce.repo

dnf -y install dnf-plugin-releasever-adapter --repo alinux3-plus

dnf -y install \
  docker-ce \
  docker-ce-cli \
  containerd.io \
  docker-buildx-plugin \
  docker-compose-plugin

systemctl enable --now docker
```

## 五、安装后验证

不通过拉取公网测试镜像验证，改用以下本机只读命令：

```bash
systemctl is-active docker
systemctl is-enabled docker
docker version
docker compose version
docker buildx version
docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Ports}}\t{{.Status}}'
ss -lntup
df -h /
curl -fsSIL https://zhj-jc.com | sed -n '1,12p'
```

通过标准：Docker 服务为 `active/enabled`；Engine、Compose、Buildx 均返回版本；没有未知容器；现有网站仍返回正常 `200`/重定向链；`80/443` 仍由现有 Nginx 服务；磁盘无异常增长。

## 六、风险与控制

| 风险 | 控制 |
|---|---|
| 软件源或包与 Alibaba Cloud Linux 3 不兼容 | 使用阿里云官方适配路线；任何 DNF 失败立即停止，不改用未知脚本 |
| Docker 启动建立 iptables/nftables 规则 | 启动后立即复核监听端口与现有网站；不创建业务容器 |
| 国内访问 Docker Hub 不稳定 | 不运行 `hello-world`，业务镜像后续离线交付 |
| 系统盘被镜像缓存逐步占满 | 当前可用 39 GiB；后续执行镜像定额和范围化清理，不运行全局破坏性 prune |
| 现有网页受影响 | 变更前基线已记录；安装后立即复核 `https://zhj-jc.com` |

## 七、回退边界

若安装完成但 Docker 服务异常，先执行：

```bash
systemctl disable --now docker
```

如确认必须卸载，只在用户再次批准后移除本次新增的软件包和软件源。**不自动删除** `/var/lib/docker` 或 `/var/lib/containerd`，避免未来存在数据时误删。

## 八、安全观察项（不混入本次变更）

G1 发现宝塔 `8888` 端口公网可达。建议后续单独检查阿里云安全组和宝塔访问限制，但本次 Docker 安装不调整该端口，避免把部署前置与安全收口混成一次难回退变更。

## 九、授权口径

如用户同意执行，请明确回复：

> 批准 G2-A，仅安装并验证 Docker 运行环境。

用户已通过所选授权语句明确批准 G2-A。本次实际执行严格限定在本文件第二节范围内，结果见 `03-G2A-Docker安装验证记录.md`；该授权已经消费完毕，不自动延伸到后续部署操作。
