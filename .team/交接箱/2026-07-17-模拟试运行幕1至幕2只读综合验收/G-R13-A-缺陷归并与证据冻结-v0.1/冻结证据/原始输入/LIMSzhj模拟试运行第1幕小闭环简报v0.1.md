# LIMS-zhj 模拟试运行 · 第 1 幕小闭环简报

> 版本：v0.1　日期：2026-07-16　被测版本：G4-R12（沙箱模拟试运行包 v0.2）
> 环境：Claude 云沙箱（PHP 8.4 / SQLite 降真 / fakeclock v2 / Playwright+Chromium）
> 按你方《第 0 幕验证批注 v0.2》建议的小闭环顺序执行，判定采用四级口径

## 一句话结论

**排练场全面就绪：应用引导✅ → SQLite 建库✅ → 登录冒烟✅ → 假时钟整机穿越✅ → 存档读档✅ → 首场戏"一单一库"走查✅。拿到整季预算锚点：单角色只读场次 = 7.6 万 token / 15 分钟 / 14 条发现。**

## 1. 小闭环实测矩阵

| 步骤 | 结果 | 关键数据 |
|---|---|---|
| ① vendor 启动引导 | ✅ | 未建库先起服务，路由/模板正常，登录页可渲染（印证批注的分步建议） |
| ② SQLite 初始化转换 | ✅ | 79 表全建、7 条种子全入；唯一报错 `INSERT IGNORE`→改 `INSERT OR IGNORE`（判 sandbox-noise） |
| ③ 真时钟登录冒烟 | ✅ | admin 登录→仪表盘，SQLite 后端正常工作 |
| ④ 假时钟整机 +90 天 | ✅ | HTTP 头、登录、日历页全部显示 2026 年 10 月（截图留证） |
| ⑤ 存档/读档 | ✅ | 840K 库：存档 5ms、读档 6ms，污染数据回滚验证干净 |
| ⑥ 首场戏 Scene1 | ✅ | 见第 3 节 |

## 2. 排练工具事故记录（已修复/已勘误）

**坑一：假时钟下会话 8 小时即死。** 会话文件的 mtime 由内核用真实时间盖章；PHP 活在假 10 月，一算"文件年龄 90 天 > 8 小时有效期"，登录即被作废。**修复**：shim 升级 v2，把 stat/lstat/fstat/fstatat/statx 全家桶纳入拦截，文件时间戳同步平移——此后全流程通畅。这个坑对整季至关重要（一切"文件新旧比对"逻辑都受影响），v2 源码见附录，请收进《沙箱排练工具》下一版。

**勘误一：F-06"快一天"是导演口径错误。** 假时钟落在 UTC 10-14 深夜，Asia/Shanghai 已是 10-15 凌晨——系统显示正确，我给角色的剧本简报写错了。整季规矩：**所有系统内日期一律按上海时区口径下发，且偏移量取整到上午 9 点**，避免跨午夜歧义。F-06 结案，不计系统问题。

## 3. Scene1「一单一库」走查摘要（角色：质量负责人·赵姐）

完成 5/8 项；受阻 3 项全部因为**排练库是空库**（候选池 0 条、记录模板 0 条——道具组必须先行的实证）。发现 14 条：

| 判定 | 数量 | 代表 |
|---|---|---|
| sandbox-pass | 5 | 登录流畅；候选池"机器建议、人工定夺"边界文案清晰；变更事件状态机与空态引导好；一单一库链路互链自洽；假时钟月份一致 |
| sandbox-noise | 2 | CDN 失效致全站裸排版（F-01）；F-06 已勘误结案 |
| real-stack-required | 3 | 候选池/模板空库待种子后复核；页脚暴露调试耗时需核对生产配置 |
| **product-defect** | **4** | **F-02** 前端依赖公网 CDN 无本地回退（内网部署实验室将同样崩样式，修复点已定位：`app/view/layout/main.html`、`layout/login.html`、`compliance/index.html` 三处 jsdelivr 引用）；**F-05** 访问不存在的详情 URL 静默返回 200 渲染成列表、无"记录不存在"提示；**F-07** 菜单"2025 运行确认"疑似年份硬编码，不随系统时间走；**F-08** 无站内搜索＋行业用语与系统命名不对齐（法规监视→外部变化管理、溯源链→追溯矩阵） |

严重度分布：A 0｜B 3｜C 4｜D 2。原始台账与 12 张截图随附（原始记录不改动，导演意见单独批注——记录完整性规矩从模拟第一天就立起来）。

**已知问题盲测**：交付说明记载的"溯源链页面 1 个旧内容替换字符"本场未命中——追溯矩阵页当时是空库空态，疑似该内容块未渲染。判"未判定"，列入种子数据就绪后的复测项，不算漏检也不算通过。

## 4. 成本锚点（整季预算的地基）

| 指标 | 实测值 |
|---|---|
| Scene1 总消耗 | **76,031 token / 27 次工具调用 / 15.3 分钟** |
| 场次画像 | 单角色、只读为主、14 次浏览器操作、12 截图、2 份文件产出 |
| 推导规则 | 写入型场次按 1.5~2.5 倍估：约 12~19 万/角色场；多角色协作关键帧场约 30~60 万 |

## 5. 给你的 v0.3 包请求清单

1. **bootstrap 5.3.0 本地化**（上面三个文件改指 `/static/`，CDN 文件放进 `public/static/`）——这同时就是 F-02 的产品修复，一举两得；修完沙箱截图恢复正常样式，UI 类发现不再失真。
2. **种子数据的授权口径**：A. 你们出一份排练种子库；或 B. 授权我在沙箱用 SQL/界面造数（全部 SIM- 前缀）。二选一即可，B 更快。
3. **fakeclock v2 回传**：附录源码替换《沙箱排练工具/fakeclock》，注明"必须含 stat 拦截，否则会话必死"。

## 6. 附录：fakeclock v2 源码（含 stat 全家桶拦截）

```c
#define _GNU_SOURCE
#include <time.h>
#include <sys/time.h>
#include <sys/stat.h>
#include <stdlib.h>
#include <limits.h>
#include <dlfcn.h>
static long off(void){
    static long o = LONG_MIN;
    if (o == LONG_MIN) { const char *e = getenv("FAKE_OFFSET_SECONDS"); o = e ? atol(e) : 0; }
    return o;
}
time_t time(time_t *t){
    static time_t (*r)(time_t*) = 0; if (!r) r = dlsym(RTLD_NEXT, "time");
    time_t v = r(0) + off(); if (t) *t = v; return v;
}
int gettimeofday(struct timeval *tv, void *tz){
    static int (*r)(struct timeval*, void*) = 0; if (!r) r = dlsym(RTLD_NEXT, "gettimeofday");
    int rc = r(tv, tz); if (!rc && tv) tv->tv_sec += off(); return rc;
}
int clock_gettime(clockid_t id, struct timespec *ts){
    static int (*r)(clockid_t, struct timespec*) = 0; if (!r) r = dlsym(RTLD_NEXT, "clock_gettime");
    int rc = r(id, ts);
    if (!rc && ts && (id == CLOCK_REALTIME || id == CLOCK_REALTIME_COARSE)) ts->tv_sec += off();
    return rc;
}
static void shift_stat(struct stat *st){ long o = off(); st->st_atime += o; st->st_mtime += o; st->st_ctime += o; }
int stat(const char *p, struct stat *st){
    static int (*r)(const char*, struct stat*) = 0; if (!r) r = dlsym(RTLD_NEXT, "stat");
    int rc = r(p, st); if (!rc) shift_stat(st); return rc;
}
int lstat(const char *p, struct stat *st){
    static int (*r)(const char*, struct stat*) = 0; if (!r) r = dlsym(RTLD_NEXT, "lstat");
    int rc = r(p, st); if (!rc) shift_stat(st); return rc;
}
int fstat(int fd, struct stat *st){
    static int (*r)(int, struct stat*) = 0; if (!r) r = dlsym(RTLD_NEXT, "fstat");
    int rc = r(fd, st); if (!rc) shift_stat(st); return rc;
}
int fstatat(int dfd, const char *p, struct stat *st, int flags){
    static int (*r)(int, const char*, struct stat*, int) = 0; if (!r) r = dlsym(RTLD_NEXT, "fstatat");
    int rc = r(dfd, p, st, flags); if (!rc) shift_stat(st); return rc;
}
#include <linux/stat.h>
int statx(int dfd, const char *p, int flags, unsigned mask, struct statx *sx){
    static int (*r)(int, const char*, int, unsigned, struct statx*) = 0; if (!r) r = dlsym(RTLD_NEXT, "statx");
    if (!r) return -1;
    int rc = r(dfd, p, flags, mask, sx);
    if (!rc && sx){ long o = off(); sx->stx_atime.tv_sec += o; sx->stx_mtime.tv_sec += o; sx->stx_ctime.tv_sec += o; sx->stx_btime.tv_sec += o; }
    return rc;
}
```

编译：`gcc -shared -fPIC -O2 -o fakeclock2.so fakeclock2.c -ldl`
使用：`LD_PRELOAD=./fakeclock2.so FAKE_OFFSET_SECONDS=$((90*86400)) php think run -p 8010`

## 7. 版本记录

| 版本 | 日期 | 变更 | 状态 |
|---|---|---|---|
| v0.1 | 2026-07-16 | 第 1 幕小闭环全通＋Scene1 首演＋成本锚点＋v0.3 请求清单 | 现行 |
