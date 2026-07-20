# fakeclock 使用说明 v0.2

用途：在 Linux 沙箱中让 PHP 进程“以为时间已经前进”，用于模拟到期提醒、内审周期、培训有效期、设备校准周期等场景。

## 编译

```bash
cd 沙箱排练工具/fakeclock
gcc -shared -fPIC -O2 -o fakeclock.so fakeclock.c -ldl
```

## 使用

```bash
cd source/jewelry-qms
LD_PRELOAD=../../沙箱排练工具/fakeclock/fakeclock.so \
FAKE_OFFSET_SECONDS=$((90*86400)) \
php think run -H 0.0.0.0 -p 8010
```

## 边界

- 该方法只影响加载了 `LD_PRELOAD` 的进程；
- 如果使用独立 MySQL 容器，MySQL 容器时间不会自动跟随；
- SQLite 与 PHP 同进程/同宿主时更容易保持时间一致；
- 浏览器、外部接口、异步队列、独立守护进程不一定被同一假时钟覆盖。

