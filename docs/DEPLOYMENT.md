# 部署指南

## 1. 环境要求（通用）

| 组件 | 要求 |
|------|------|
| PHP | 5.6+，推荐 7.4；扩展 `mbstring`, `pdo_mysql`, `json`, `openssl` |
| MySQL | 5.7+ 或 MariaDB 10.3+ |
| Web 服务器 | Apache 2.4 + `mod_rewrite`，或 Nginx + PHP-FPM |
| 操作系统 | Windows Server / Linux 均可 |

## 2. 部署 Jewelry QMS（推荐生产）

### 2.1 目录

将 `jewelry-qms` 放到 Web 可访问路径，例如：

- Windows：`C:\xampp\htdocs\jewelry-qms\`
- Linux：`/var/www/jewelry-qms/`

**DocumentRoot** 应指向 `jewelry-qms/app/webroot`（或使用根目录 `.htaccess` 重写到 webroot）。

### 2.2 数据库

```bash
mysql -u root -p -e "CREATE DATABASE jewelry_qms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p jewelry_qms < jewelry-qms/app/webroot/schema/jewelry_qms.sql
```

编辑 `jewelry-qms/app/Config/database.php`：

```php
'host' => 'localhost',
'login' => 'your_user',
'password' => 'your_password',
'database' => 'jewelry_qms',
```

### 2.3 目录权限

确保可写：

```
jewelry-qms/tmp/cache/
jewelry-qms/tmp/logs/
jewelry-qms/tmp/sessions/
jewelry-qms/app/webroot/files/
```

Windows：IIS/IUSR 或 Apache 运行用户对上述目录有修改权。

### 2.4 生产检查清单

- [ ] `app/Config/core.php` 中 `Configure::write('debug', 0);`
- [ ] 修改默认用户 `admin` 密码
- [ ] 配置 HTTPS
- [ ] 禁止对外暴露 `phpinfo`、`.git`
- [ ] 定期备份数据库与 `app/webroot/files/`

### 2.5 访问

- URL 示例：`http://your-server/jewelry-qms/`
- 默认账号：`admin` / `password`

---

## 3. 参考项目（可选本地对比）

### 3.1 FlinkISO Lite

路径：`flinkiso-lite-master/flinkiso-lite-master/`

1. 复制 `app/Config/database.php.default` 为 `database.php`（若存在）并配置
2. 按项目内安装向导或导入 `app/Config/Schema/` 下 SQL
3. 访问站点根目录，完成 Web 安装

### 3.2 FlinkISO On-Premise

路径：`flinkiso/flinkiso-ver-2x-on-premise/`

1. 导入 `app/webroot/schema/flinkiso-on-premise.sql`
2. 配置 `app/Config/database.php`
3. 需 ONLYOFFICE Document Server（若使用在线编辑）

参考项目**勿与 jewelry-qms 共用同一数据库名**。

---

## 4. 与检测业务系统同机部署

| 项目 | 建议 |
|------|------|
| 端口/虚拟主机 | LIMS 与 QMS 分 vhost，如 `lims.lab.com` / `qms.lab.com` |
| 数据库 | 分库：`lims_db` / `jewelry_qms` |
| 集成 | 只读 API 或 MySQL 视图同步人员、设备、报告号 |

---

## 5. 故障排查

| 现象 | 处理 |
|------|------|
| 404 除首页外均失败 | 检查 `mod_rewrite`、`.htaccess` |
| 空白页 | 临时 `debug=2` 查看错误；查 `tmp/logs/error.log` |
| 无法登录 | 确认 SQL 已导入；用户表存在 `admin` |
| 上传失败 | 检查 `webroot/files` 权限与 PHP `upload_max_filesize` |
