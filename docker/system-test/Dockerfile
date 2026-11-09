FROM php:7.2.34-cli@sha256:42ffbc0798e4449bbd1e14fc4dcb87774aa1ad1900a09ef6a965bc0880aa2161

RUN set -eux \
 && sed -i \
      -e 's|http://deb.debian.org/debian|https://archive.debian.org/debian|g' \
      -e 's|http://security.debian.org/debian-security|https://archive.debian.org/debian-security|g' \
      /etc/apt/sources.list \
 && printf 'Acquire::https::archive.debian.org::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/99archive-check-valid \
 && apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      ca-certificates \
      curl \
      git \
      unzip \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app
