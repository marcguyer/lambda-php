# Multi-stage build for PHP 8.4 Lambda runtime on ARM64
# Based on Amazon Linux 2023 for binary compatibility with Lambda execution environment

FROM public.ecr.aws/amazonlinux/amazonlinux:2023 AS builder

# Install build dependencies
RUN dnf install -y \
    gcc gcc-c++ make autoconf automake libtool \
    bison re2c pkgconfig \
    tar gzip \
    libxml2-devel openssl-devel curl-devel \
    oniguruma-devel \
    sqlite-devel zlib-devel \
    readline-devel \
    && dnf clean all

# Download PHP 8.4.15 source
WORKDIR /build
RUN curl -fsSL https://www.php.net/distributions/php-8.4.15.tar.gz -o php.tar.gz \
    && tar -xzf php.tar.gz \
    && mv php-8.4.15 php-src

# Compile PHP with optimizations for Lambda
WORKDIR /build/php-src
RUN ./configure \
    --prefix=/opt \
    --enable-shared=no \
    --enable-static=yes \
    --disable-cgi \
    --disable-fpm \
    --disable-phpdbg \
    --enable-cli \
    --enable-opcache \
    --enable-mbstring \
    --enable-bcmath \
    --enable-sockets \
    --enable-pcntl \
    --with-openssl \
    --with-curl \
    --with-zlib \
    --with-pdo-sqlite \
    CFLAGS="-O3 -march=armv8-a -mtune=neoverse-n1" \
    CXXFLAGS="-O3 -march=armv8-a -mtune=neoverse-n1" \
    LDFLAGS="-Wl,-O1 -Wl,--strip-all"

# Build and install PHP
RUN make -j$(nproc) && make install

# Strip debug symbols to reduce size
RUN strip --strip-all /opt/bin/php

# Configure opcache for Lambda
RUN mkdir -p /opt/etc/php.d && cat > /opt/etc/php.d/opcache.ini <<EOF
[opcache]
zend_extension=opcache.so
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.save_comments=1
EOF

# Export stage for extracting files
FROM builder AS exporter
RUN mkdir -p /layer/bin /layer/etc /layer/lib
RUN cp /opt/bin/php /layer/bin/
RUN cp -r /opt/etc/php.d /layer/etc/
RUN cp -r /opt/lib/php /layer/lib/

# Bundle only libraries not available in Lambda's AL2023
RUN cp /usr/lib64/libonig.so* /layer/lib/

# Verify build
RUN /opt/bin/php -v
RUN /opt/bin/php -m