FROM composer:2.8 AS vendor

WORKDIR /app
COPY . .
RUN composer install --no-interaction --prefer-dist

FROM composer:2.8

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 \
    PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/usr/bin/chromium-browser

# Node + system Chromium：记录表格 PDF 由 app 内 PdfRenderService 直接调用 node 脚本
RUN apk add --no-cache \
      tzdata \
      nodejs \
      npm \
      chromium \
      nss \
      freetype \
      harfbuzz \
      ca-certificates \
      ttf-freefont \
      font-noto-cjk \
    && docker-php-ext-install pdo_mysql \
    && ln -snf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime \
    && echo "Asia/Shanghai" > /etc/timezone \
    && printf 'date.timezone=Asia/Shanghai\n' > /usr/local/etc/php/conf.d/timezone.ini

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor /app/vendor

COPY docker/entrypoint.sh /usr/local/bin/jewelry-qms-entrypoint
RUN chmod +x /usr/local/bin/jewelry-qms-entrypoint

ENTRYPOINT ["jewelry-qms-entrypoint"]
CMD ["php", "think", "run", "-H", "0.0.0.0", "-p", "8000"]
