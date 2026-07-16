# G2-A Docker 安装验证记录

> 日期：2026-07-13
> 目标服务器：`101.200.41.200`
> 执行范围：仅安装并验证 Docker 运行环境
> 结论：**G2-A 通过；停止在后续 G2 操作授权前**

## 一、授权与边界

用户明确选择并批准：

> 批准 G2-A，仅安装并验证 Docker 运行环境。

本轮实际执行内容：

- 添加 Alibaba Cloud Linux 3 适用的 Docker CE 软件源；
- 安装 Docker Engine、CLI、containerd、Buildx 和 Compose 插件；
- 启动 Docker 并设置开机自启；
- 只读复核服务、版本、容器、磁盘、端口和现有网站。

本轮没有执行：

- 没有上传或加载 QMS/MySQL 镜像；
- 没有拉取 `hello-world` 或任何 Docker Hub 镜像；
- 没有创建容器、网络、volume 或 QMS 部署目录；
- 没有修改宝塔、Nginx、安全组、DNS 或 HTTPS；
- 没有停止、重启或改写现有网站。

## 二、安装前快照

| 检查项 | 安装前结果 |
|---|---|
| Docker CE 软件源 | `/etc/yum.repos.d/docker-ce.repo` 不存在 |
| Docker 相关包 | Docker Engine、CLI、containerd、Buildx、Compose 均未安装 |
| 冲突包 | 未发现 Docker、Podman、containerd、Moby 冲突包 |
| Alibaba Linux 适配插件 | `dnf-plugin-releasever-adapter-1.0-3.al8.noarch` 已安装 |
| Docker 服务 | 不存在；`inactive` |
| 系统盘 | 49 GiB，总使用 7.7 GiB，可用 39 GiB，17% |
| 现有网站 | `https://zhj-jc.com` 返回 `HTTP/2 200` |

## 三、实际变更

### 3.1 软件源

- 新增：`/etc/yum.repos.d/docker-ce.repo`
- 来源：`http://mirrors.cloud.aliyuncs.com/docker-ce/linux/centos/docker-ce.repo`
- SHA-256：`d46f8f7c661694e356be6026f6ed84ed4d7deedeee63fac899efa94ee0e008f4`
- 启用仓库：`docker-ce-stable`（x86_64）
- 导入的 Docker 发布 GPG 指纹：`060A 61C5 1B55 8A7F 742B 77AA C52F EB6B 621E 9F35`

### 3.2 安装包

主要包：

| 包 | 版本 |
|---|---|
| `docker-ce` | `26.1.3-1.el8.x86_64` |
| `docker-ce-cli` | `26.1.3-1.el8.x86_64` |
| `containerd.io` | `1.6.32-3.1.el8.x86_64` |
| `docker-buildx-plugin` | `0.14.0-1.el8.x86_64` |
| `docker-compose-plugin` | `2.27.0-1.el8.x86_64` |

同时由 DNF 安装 7 个依赖/弱依赖包：`fuse-overlayfs`、`fuse3`、`fuse3-libs`、`libcgroup`、`libslirp`、`slirp4netns`、`docker-ce-rootless-extras`。DNF 事务编号为 `2`，共变更 12 个包。

## 四、安装后验证

| 检查项 | 结果 |
|---|---|
| Docker 服务 | `active` / `running` |
| 开机自启 | `enabled` |
| containerd | `active` |
| Docker Engine | 26.1.3，API 1.45，`linux/amd64` |
| Docker Compose | v2.27.0 |
| Docker Buildx | v0.14.0 |
| 存储驱动 | `overlay2` |
| Cgroup | `cgroupfs`，version 1 |
| 容器 | 0 |
| 镜像 | 0 |
| Local volumes | 0 |
| Build cache | 0 B |
| RPM 完整性 | 5 个主要包执行 `rpm -V` 无输出，校验通过 |

验证命令均正常返回，没有通过拉取公网镜像制造额外依赖。

## 五、现有服务回归

| 项目 | 安装后结果 | 判定 |
|---|---|---|
| 服务器内访问 `https://zhj-jc.com` | `HTTP/2 200` | 通过 |
| 本机公网访问 `https://zhj-jc.com` | `HTTP/2 200` | 通过 |
| Nginx `80/443` | 仍由原 Nginx 监听 | 未受影响 |
| QMS 预留端口 `18010` | 未监听 | 保持可用 |
| Docker TCP 端口 | 未新增监听 | 通过 |
| 系统盘 | 总使用 8.1 GiB，可用 39 GiB，18% | 正常；约增加 0.4 GiB |
| 内存 | 已用约 675 MiB，可用约 2.8 GiB | 正常 |

## 六、安全观察项

G1 发现的宝塔 `8888` 公网可达状态没有因 Docker 安装发生变化。本次未触碰该端口；建议后续作为单独的宝塔/安全组收口任务处理。

## 七、结论与停点

**G2-A 验证通过。** Docker Engine、Compose 和 Buildx 已具备，服务已设置开机自启，现有网站未受影响，服务器上仍没有任何业务容器或镜像。

本次 G2-A 授权到此结束。未经新的明确授权，不执行以下动作：

- 不上传发布包或镜像；
- 不执行 `docker load`；
- 不创建 QMS 部署目录、配置、网络、volume 或容器；
- 不修改宝塔 Nginx、测试子域名 HTTPS 或访问认证。
