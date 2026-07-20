#define _GNU_SOURCE
#include <time.h>
#include <sys/time.h>
#include <stdlib.h>
#include <limits.h>
#include <dlfcn.h>

static long fake_off(void) {
    static long o = LONG_MIN;
    if (o == LONG_MIN) {
        const char *e = getenv("FAKE_OFFSET_SECONDS");
        o = e ? atol(e) : 0;
    }
    return o;
}

time_t time(time_t *t) {
    static time_t (*real)(time_t*) = 0;
    if (!real) real = dlsym(RTLD_NEXT, "time");
    time_t v = real(0) + fake_off();
    if (t) *t = v;
    return v;
}

int gettimeofday(struct timeval *tv, void *tz) {
    static int (*real)(struct timeval*, void*) = 0;
    if (!real) real = dlsym(RTLD_NEXT, "gettimeofday");
    int rc = real(tv, tz);
    if (!rc && tv) tv->tv_sec += fake_off();
    return rc;
}

int clock_gettime(clockid_t id, struct timespec *ts) {
    static int (*real)(clockid_t, struct timespec*) = 0;
    if (!real) real = dlsym(RTLD_NEXT, "clock_gettime");
    int rc = real(id, ts);
    if (!rc && ts && (id == CLOCK_REALTIME || id == CLOCK_REALTIME_COARSE)) {
        ts->tv_sec += fake_off();
    }
    return rc;
}

