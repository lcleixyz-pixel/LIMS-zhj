#!/usr/bin/env bash
# 启动 jewelry_qms 专用 MySQL 实例(用户态,无需管理员)
# ------------------------------------------------------------------
# 数据目录 : C:\Users\Martyr\mysql_data   (持久,跨重启保留数据)
# 端口     : 3306  仅绑 127.0.0.1(不对外)
# 账号     : root  无密码
# X 插件   : 已关(无 33060)
# 库       : jewelry_qms(74 表 + 37 现行程序种子)
# 平台默认 DB 配置(root/空/127.0.0.1/3306/jewelry_qms)正好匹配,无需 .env
# ------------------------------------------------------------------
MYSQLD="/c/Program Files/MySQL/MySQL Server 8.0/bin/mysqld.exe"
BASEDIR="C:\\Program Files\\MySQL\\MySQL Server 8.0"
DATADIR="C:\\Users\\Martyr\\mysql_data"

if netstat -ano 2>/dev/null | grep -q "127.0.0.1:3306 .*LISTENING"; then
  echo "MySQL 已在 127.0.0.1:3306 运行,无需重复启动。"
  exit 0
fi
if [ ! -d "$DATADIR" ]; then
  echo "数据目录不存在:$DATADIR"
  echo "首次需先初始化:mysqld --basedir=\"$BASEDIR\" --datadir=\"$DATADIR\" --port=3306 --initialize-insecure --console"
  exit 1
fi
echo "启动 mysqld(前台运行,保持本窗口打开;停止用 Ctrl+C 或另开窗口跑 mysqladmin shutdown)..."
"$MYSQLD" --basedir="$BASEDIR" --datadir="$DATADIR" --port=3306 --bind-address=127.0.0.1 --skip-mysqlx --console
