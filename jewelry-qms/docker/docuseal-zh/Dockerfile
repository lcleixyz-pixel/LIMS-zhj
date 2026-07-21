# DocuSeal 简体中文自建镜像（钉版本 + overlay）
# 构建：docker compose --profile signing build docuseal
ARG DOCUSEAL_GIT_SHA=5fe75c84ffc71d1e879884f453a7532b4dee049a
ARG DOCUSEAL_VERSION=3.1.5

FROM alpine:3.20 AS source
ARG DOCUSEAL_GIT_SHA
RUN apk add --no-cache git
WORKDIR /src
RUN git clone --filter=blob:none https://github.com/docusealco/docuseal.git . \
 && git checkout "${DOCUSEAL_GIT_SHA}"

# 覆盖汉化与 locale 接线
COPY overlay/ /src/

# 复用上游多阶段 Dockerfile
FROM source AS build-context
WORKDIR /src

# —— 以下阶段与上游 Dockerfile 对齐（钉 3.1.5）——
FROM ruby:4.0.5-alpine AS download
WORKDIR /fonts
RUN apk --no-cache add wget unzip && \
    wget https://github.com/satbyy/go-noto-universal/releases/download/v7.0/GoNotoKurrent-Regular.ttf && \
    wget https://github.com/satbyy/go-noto-universal/releases/download/v7.0/GoNotoKurrent-Bold.ttf && \
    wget https://github.com/impallari/DancingScript/raw/master/fonts/DancingScript-Regular.otf && \
    wget https://raw.githubusercontent.com/impallari/DancingScript/master/OFL.txt && \
    wget https://raw.githubusercontent.com/notofonts/noto-fonts/refs/heads/main/LICENSE && \
    wget -O /model.onnx "https://github.com/docusealco/fields-detection/releases/download/2.0.0/model_704_int8.onnx" && \
    wget -O pdfium-linux.zip "https://github.com/docusealco/pdfium-binaries/releases/download/20260613/pdfium-musl-$(uname -m).zip" && \
    case "$(uname -m)" in \
      x86_64)  echo "2c953ff72ee2dda07e7fc577e25841cc3d6464468a7c5adfaea574efcbc3b90b  pdfium-linux.zip" ;; \
      aarch64) echo "23bbe287d2753fdb05741c7660647eb0ef0d2e4da2ce0722bfa9d9d455bd64e2  pdfium-linux.zip" ;; \
    esac | sha256sum -c - && \
    mkdir -p /pdfium-linux && \
    unzip -q pdfium-linux.zip -d /pdfium-linux

FROM ruby:4.0.5-alpine AS webpack
ENV RAILS_ENV=production
ENV NODE_ENV=production
WORKDIR /app
RUN apk add --no-cache nodejs yarn git build-base && \
    gem install shakapacker
COPY --from=build-context /src/package.json /src/yarn.lock ./
RUN yarn install --network-timeout 1000000
COPY --from=build-context /src/bin/shakapacker ./bin/shakapacker
COPY --from=build-context /src/config/webpack ./config/webpack
COPY --from=build-context /src/config/shakapacker.yml ./config/shakapacker.yml
COPY --from=build-context /src/postcss.config.js ./postcss.config.js
COPY --from=build-context /src/tailwind.config.js ./tailwind.config.js
COPY --from=build-context /src/tailwind.form.config.js ./tailwind.form.config.js
COPY --from=build-context /src/tailwind.dynamic.config.js ./tailwind.dynamic.config.js
COPY --from=build-context /src/tailwind.application.config.js ./tailwind.application.config.js
COPY --from=build-context /src/app/javascript ./app/javascript
COPY --from=build-context /src/app/views ./app/views
RUN echo "gem 'shakapacker'" > Gemfile && ./bin/shakapacker

FROM ruby:4.0.5-alpine AS app
ARG DOCUSEAL_VERSION
ENV RAILS_ENV=production
ENV BUNDLE_WITHOUT="development:test"
ENV OPENSSL_CONF=/etc/openssl_legacy.cnf
WORKDIR /app
RUN apk add --no-cache libpq vips redis onnxruntime leptonica && \
    rm -f /usr/bin/onnx_test_runner /usr/bin/onnxruntime_test
RUN addgroup -g 2000 docuseal && adduser -u 2000 -G docuseal -s /bin/sh -D -h /home/docuseal docuseal
RUN echo $'.include = /etc/ssl/openssl.cnf\n\
\n\
[provider_sect]\n\
default = default_sect\n\
legacy = legacy_sect\n\
\n\
[default_sect]\n\
activate = 1\n\
\n\
[legacy_sect]\n\
activate = 1' >> /etc/openssl_legacy.cnf
COPY --chown=docuseal:docuseal --from=build-context /src/Gemfile /src/Gemfile.lock ./
RUN apk add --no-cache build-base git libpq-dev yaml-dev && bundle install && apk del --no-cache build-base git libpq-dev yaml-dev && rm -rf ~/.bundle /usr/local/bundle/cache && ruby -e "puts Dir['/usr/local/bundle/**/{spec,rdoc,resources/shared,resources/collation,resources/locales,resources/unicode_data/properties}'] + Dir['/usr/local/bundle/gems/*/{test,tests,examples,sample,misc,doc,docs}'] + Dir['/usr/local/bundle/gems/*/ext/**/*.{c,h,o,S}']" | xargs rm -rf && ln -sf /usr/lib/libonnxruntime.so.1 $(ruby -e "print Dir[Gem::Specification.find_by_name('onnxruntime').gem_dir + '/vendor/*.so'].first")
COPY --chown=docuseal:docuseal --from=build-context /src/bin ./bin
COPY --chown=docuseal:docuseal --from=build-context /src/app ./app
COPY --chown=docuseal:docuseal --from=build-context /src/config ./config
COPY --chown=docuseal:docuseal --from=build-context /src/db/migrate ./db/migrate
COPY --chown=docuseal:docuseal --from=build-context /src/log ./log
COPY --chown=docuseal:docuseal --from=build-context /src/lib ./lib
COPY --chown=docuseal:docuseal --from=build-context /src/public ./public
COPY --chown=docuseal:docuseal --from=build-context /src/tmp ./tmp
COPY --chown=docuseal:docuseal --from=build-context /src/LICENSE /src/LICENSE_ADDITIONAL_TERMS /src/README.md /src/Rakefile /src/config.ru /src/.version ./
COPY --chown=docuseal:docuseal --from=build-context /src/.version ./public/version
COPY --chown=docuseal:docuseal --from=download /fonts/GoNotoKurrent-Regular.ttf /fonts/GoNotoKurrent-Bold.ttf /fonts/DancingScript-Regular.otf /fonts/OFL.txt /fonts/LICENSE /fonts/
COPY --from=download /pdfium-linux/lib/libpdfium.so /usr/lib/libpdfium.so
COPY --from=download /pdfium-linux/licenses/ /usr/lib/libpdfium-licenses/
COPY --chown=docuseal:docuseal --from=download /model.onnx /app/tmp/model.onnx
COPY --chown=docuseal:docuseal --from=webpack /app/public/packs ./public/packs
RUN mkdir -p /app/public/fonts && ln -s /fonts/DancingScript-Regular.otf /app/public/fonts/ && \
    mkdir -p /usr/share/fonts/noto && ln -s /fonts/GoNotoKurrent-Regular.ttf /usr/share/fonts/noto/ && ln -s /fonts/GoNotoKurrent-Bold.ttf /usr/share/fonts/noto/ && fc-cache -f && \
    bundle exec bootsnap precompile -j 1 --gemfile app/ lib/ && \
    chown -R docuseal:docuseal /app/tmp/cache && \
    echo "${DOCUSEAL_VERSION}-zh" > /app/.version && \
    echo "${DOCUSEAL_VERSION}-zh" > /app/public/version
WORKDIR /data/docuseal
ENV HOME=/home/docuseal
ENV WORKDIR=/data/docuseal
ENV VIPS_MAX_COORD=17000
ENV VIPS_BLOCK_UNTRUSTED=1
LABEL org.opencontainers.image.title="lims/docuseal-zh"
LABEL org.opencontainers.image.version="${DOCUSEAL_VERSION}-zh"
EXPOSE 3000
CMD ["/app/bin/bundle", "exec", "puma", "-C", "/app/config/puma.rb", "--dir", "/app"]
