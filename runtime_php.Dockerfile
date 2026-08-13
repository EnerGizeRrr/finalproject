FROM ubuntu:24.04

LABEL maintainer="Taylor Otwell"

ARG WWWGROUP
ARG NODE_VERSION="22"
ARG POSTGRES_VERSION="16"

COPY start-container /usr/local/bin/start-container
COPY install-composer /usr/local/bin/install-composer
COPY install-php-extensions /usr/local/bin/install-php-extensions

RUN ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

RUN apt-get update \
    && apt-get install gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin libpng-dev python3 python3-dev python3-pip python3-setuptools gcc g++ pkg-config make nano vim jq \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14aa40ec0831756756d7f66c4f4ea0aae5267a6c' | gpg --dearmor | tee /etc/apt/trusted.gpg.d/remi.gpg > /dev/null \
    && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor | tee /etc/apt/trusted.gpg.d/yarn.gpg > /dev/null \
    && curl -sS https://www.dotdeb.org/dotdeb.gpg | gpg --dearmor | tee /etc/apt/trusted.gpg.d/dotdeb.gpg > /dev/null \
    && echo "deb https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ondrej-php.list \
    && echo "deb https://dl.yarnpkg.com/debian/ stable main" > /etc/apt/sources.list.d/yarn.list \
    && apt-get update \
    && apt-get install -y php8.4-cli php8.4-dev \
       php8.4-pgsql php8.4-redis php8.4-memcached php8.4-imap php8.4-mysql php8.4-mbstring \
       php8.4-xml php8.4-zip php8.4-bcmath php8.4-soap php8.4-intl php8.4-readline \
       php8.4-ldap \
       php8.4-msgpack php8.4-igbinary php8.4-sockets php8.4-xdebug \
       php8.4-fpm \
    && php -r "readfile('https://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -sS https://deb.nodesource.com/setup_$NODE_VERSION.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm \
    && curl -sSL -o postgresql.deb "https://ftp.postgresql.org/pub/repos/apt/pool/main/p/postgresql-common/postgresql-common_265.pgdg24.04+1_all.deb" \
    && apt-get install -y /postgresql.deb \
    && rm postgresql.deb \
    && apt-get install -y "postgresql-client-$POSTGRES_VERSION" \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN set -eux; \
    if [ -n "$WWWGROUP" ] && [ "$WWWGROUP" -eq "$WWWGROUP" ] 2>/dev/null; then \
        groupadd --force -g $WWWGROUP sail; \
    else \
        # Если переменная пуста или не число, создаем группу с произвольным ID или используем существующую подходящую
        groupadd --force sail; \
    fi \
    && useradd -ms /bin/bash --no-user-group -g sail -u 1337 sail \
    && usermod -a -G adm www-data

RUN set -eux; \
    if [ -n "$WWWGROUP" ] && [ "$WWWGROUP" -eq "$WWWGROUP" ] 2>/dev/null; then \
        export WWWGROUP_NAME=$(getent group $WWWGROUP | cut -d: -f1); \
        if [ -n "$WWWGROUP_NAME" ]; then \
            usermod -a -G $WWWGROUP_NAME sail; \
        fi; \
    fi

COPY start-supervisor.conf /etc/supervisor/conf.d/

RUN mkdir -p /var/www/html \
    && chown sail:sail /var/www/html \
    && chmod 755 /usr/local/bin/start-container \
    && chmod 755 /usr/local/bin/install-composer \
    && chmod 755 /usr/local/bin/install-php-extensions

EXPOSE 8000

ENTRYPOINT ["start-container"]