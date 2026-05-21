# 部署指南

本文档以当前主交付物 `jewelry-qms` 为准：ThinkPHP 8、PHP 8.1+、公开入口 `public/`、数据库脚本 `database/jewelry_qms.sql`。

两个 FlinkISO 目录仅作参考快照，`jewelry-qms-legacy/` 是 CakePHP 旧版归档，不作为生产部署入口。

## 1. Jewelry QMS 环境要求

| 组件 | 要求 |
|------|------|
| PHP | 8.1+，扩展 `mbstring`, `pdo_mysql`, `json`, `openssl`, `fileinfo` |
| Composer | 2.x |
| MySQL | 5.7+ 或 MariaDB 10.3+，推荐 utf8mb4 |
| Web 服务器 | Nginx + PHP-FPM，或 Apache 2.4 + `mod_rewrite` |
| 操作系统 | Linux / Windows Server 均可 |

## 2. 部署 Jewelry QMS

### 2.1 代码目录

将 `jewelry-qms` 放到 Web 可访问路径，例如：

- Linux：`/var/www/jewelry-qms/`
- Windows：`C:\xampp\htdocs\jewelry-qms\`

生产环境的 **DocumentRoot / root 必须指向**：

```text
jewelry-qms/public
```

不要把 Web 根目录指向仓库根目录或 `jewelry-qms/app`，否则会暴露配置、源码或运行时目录。

### 2.2 安装依赖

```bash
cd jewelry-qms
composer install --no-dev --optimize-autoloader
```

开发环境可直接执行：

```bash
composer install
php think run
```

默认开发访问地址通常为 `http://127.0.0.1:8000`，以命令输出为准。

### 2.3 数据库

创建数据库并导入初始化脚本：

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS jewelry_qms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p jewelry_qms < jewelry-qms/database/jewelry_qms.sql
```

脚本内包含默认公司、部门、管理员账号和基础分类数据。

### 2.4 配置 `.env`

复制示例配置：

```bash
cd jewelry-qms
cp .example.env .env
```

至少修改以下项：

```ini
APP_DEBUG = false

DB_TYPE = mysql
DB_HOST = 127.0.0.1
DB_NAME = jewelry_qms
DB_USER = your_user
DB_PASS = your_password
DB_PORT = 3306
DB_CHARSET = utf8mb4

DEFAULT_LANG = zh-cn
```

数据库连接由 `config/database.php` 读取 `.env`；业务参数、文档层级、审批级数、角色权限由 `config/qms.php` 管理。

### 2.5 可写目录

确保 Web/PHP 运行用户可写：

```text
jewelry-qms/runtime/
jewelry-qms/public/uploads/
```

上传文件由系统写入 `public/uploads/<module>/<record_id>/`，运行日志和缓存写入 `runtime/`。

### 2.6 Web 服务器示例

Nginx 示例：

```nginx
server {
    listen 80;
    server_name qms.example.com;
    root /var/www/jewelry-qms/public;
    index index.php index.html;

    location / {
        if (!-e $request_filename) {
            rewrite ^(.*)$ /index.php?s=$1 last;
            break;
        }
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(env|git) {
        deny all;
    }
}
```

Apache 可将虚拟主机 `DocumentRoot` 指向 `jewelry-qms/public`，并启用 `mod_rewrite`。`public/.htaccess` 已包含 ThinkPHP 路由重写规则。

### 2.7 生产检查清单

- [ ] `.env` 中 `APP_DEBUG = false`
- [ ] Web 根目录指向 `jewelry-qms/public`
- [ ] 已修改默认账号 `admin` / `password`
- [ ] 已配置 HTTPS
- [ ] 禁止对外访问 `.env`、`.git`、源码目录和备份文件
- [ ] 定期备份数据库与 `jewelry-qms/public/uploads/`
- [ ] `runtime/` 与 `public/uploads/` 权限正确

## 3. 参考项目部署（可选）

参考项目仅用于本地对比和功能借鉴，不建议与生产 Jewelry QMS 共用数据库或虚拟主机。

| 项目 | 路径 | 说明 |
|------|------|------|
| FlinkISO Lite | `flinkiso-lite-master/flinkiso-lite-master/` | CakePHP 2.3.6 参考项目 |
| FlinkISO On-Premise | `flinkiso/flinkiso-ver-2x-on-premise/` | CakePHP 2.10.24，包含 ONLYOFFICE/PDF 等参考能力 |
| Jewelry QMS Legacy | `jewelry-qms-legacy/` | CakePHP 旧版归档，仅用于迁移对照 |

这些项目若需运行，应按其自身 README 或原项目说明单独配置。

## 4. 与检测业务系统同机部署

| 项目 | 建议 |
|------|------|
| 端口/虚拟主机 | LIMS 与 QMS 分 vhost，如 `lims.lab.com` / `qms.lab.com` |
| 数据库 | 分库：`lims_db` / `jewelry_qms` |
| 集成 | 通过只读 API 或 MySQL 视图同步人员、设备、客户、报告编号 |

## 5. 故障排查

| 现象 | 处理 |
|------|------|
| 首页可访问但其他路径 404 | 检查 Nginx/Apache 重写规则是否指向 `public/index.php` |
| 空白页或页面错误 | 临时在受控环境开启 `APP_DEBUG = true`，查看 `runtime/log/` |
| 无法连接数据库 | 检查 `.env` 中 `DB_HOST`、`DB_NAME`、`DB_USER`、`DB_PASS` |
| 无法登录 | 确认已导入 `database/jewelry_qms.sql`，默认账号为 `admin` / `password` |
| 上传失败 | 检查 `public/uploads/` 权限与 PHP `upload_max_filesize`、`post_max_size` |
