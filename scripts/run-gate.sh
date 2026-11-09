#!/usr/bin/env bash
# Reproducible gate for remote-test-2026-10-07 (digest-pinned PHP + Selenium).
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"

PHP_IMAGE='php@sha256:76e563191d1ade120313a8736df24154d21da5155c0756f147c0b01bd19d9087'
UBUNTU_IMAGE='ubuntu:24.04@sha256:fe81560990fae86dc4743202b2cb4d3df98d0bcf949173cecef15ce1127ef852'
SEL_IMAGE='selenium/standalone-chrome@sha256:be55620222a49b5ed58787573c6ae864ed86833668b5a54a792f5419befb1dfd'
PHP_PKG_VER='8.1.34-9+ubuntu24.04.1+deb.sury.org+1'
PHP_TREE_DEBIAN="${GATE_PHP_TREE_DEBIAN:-/tmp/module-webdriver-gate-php-debian}"
PHP_TREE="${GATE_PHP_TREE:-/tmp/module-webdriver-gate-php}"
COMPOSER_TIMEOUT="${COMPOSER_TIMEOUT:-600}"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"

if [[ "$USE_SUDO_DOCKER" == "1" ]]; then
  DOCKER=(sudo docker)
else
  DOCKER=(docker)
fi

if ! command -v docker >/dev/null 2>&1 && [[ "$USE_SUDO_DOCKER" == "0" ]]; then
  if command -v sudo >/dev/null 2>&1 && sudo docker version >/dev/null 2>&1; then
    DOCKER=(sudo docker)
  else
    echo "docker not available" >&2
    exit 1
  fi
fi

ARTIFACT_DIR="${ARTIFACT_DIR:-/opt/cursor/artifacts}"
mkdir -p "$ARTIFACT_DIR" 2>/dev/null || sudo mkdir -p "$ARTIFACT_DIR"
RESULTS="${ARTIFACT_DIR}/suite-results.md"
: >"$RESULTS"

log_section() {
  echo "## $1" | tee -a "$RESULTS"
}

rm_tree() {
  local dir="$1"
  if [[ -e "$dir" ]]; then
    if [[ "$USE_SUDO_DOCKER" == "1" ]]; then
      sudo rm -rf "$dir"
    else
      rm -rf "$dir" || sudo rm -rf "$dir"
    fi
  fi
}

# PHP for PHPUnit inside Selenium (glibc matches Ubuntu 24.04 / Chrome image).
selenium_php_bin() {
  echo 'env LD_LIBRARY_PATH=/opt/php/lib/php-gate-deps TZ=UTC PHP_INI_SCAN_DIR=/opt/php/etc/conf.d /opt/php/bin/php'
}

build_php_trees() {
  log_section "build-php-tree"
  rm_tree "$PHP_TREE_DEBIAN"
  rm_tree "$PHP_TREE"
  mkdir -p "$PHP_TREE_DEBIAN" "$PHP_TREE"

  log_section "build-php-tree-digest-php-image"
  local cid
  cid="$("${DOCKER[@]}" create "$PHP_IMAGE")"
  "${DOCKER[@]}" cp "$cid:/usr/local/." "$PHP_TREE_DEBIAN/"
  "${DOCKER[@]}" rm -f "$cid" >/dev/null
  "${DOCKER[@]}" run --rm -v "$PHP_TREE_DEBIAN:/usr/local" -w /usr/local "$PHP_IMAGE" bash -lc 'set -e
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq git unzip libzip-dev curl
    docker-php-ext-install zip >/dev/null
    curl -fsSL -o /tmp/composer-setup.php https://getcomposer.org/installer
    expected="$(curl -fsSL https://composer.github.io/installer.sig)"
    actual="$(php -r "echo hash_file(\"sha384\", \"/tmp/composer-setup.php\");")"
    test "$expected" = "$actual"
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
    ext_dir="$(/usr/local/bin/php-config --extension-dir)"
    deps_dir=/usr/local/lib/php-gate-deps
    mkdir -p "$deps_dir"
    zip_so="$ext_dir/zip.so"
    ldd "$zip_so" | awk '\''/=> \// {print $3}'\'' | while read -r lib; do
      cp -L "$lib" "$deps_dir/" 2>/dev/null || true
    done
    test "$(php -r "echo PHP_VERSION;")" = "8.1.34"
    php -v | head -1
    php -m | grep -x zip >/dev/null
    /usr/local/bin/composer --version | head -1
  ' | tee -a "$RESULTS"

  log_section "build-php-tree-selenium-glibc"
  "${DOCKER[@]}" run --rm -v "$PHP_TREE:/out" "$UBUNTU_IMAGE" bash -lc "set -e
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq software-properties-common curl ca-certificates
    add-apt-repository -y ppa:ondrej/php
    apt-get update -qq
    apt-get install -y -qq \\
      php8.1-cli=${PHP_PKG_VER} \\
      php8.1-zip=${PHP_PKG_VER} \\
      php8.1-mbstring=${PHP_PKG_VER} \\
      php8.1-curl=${PHP_PKG_VER} \\
      php8.1-xml=${PHP_PKG_VER}
    mkdir -p /out/bin /out/lib/php/extensions/no-debug-non-zts-20210902 /out/lib/php-gate-deps /out/etc/conf.d
    cp /usr/bin/php8.1 /out/bin/php
    cat > /out/etc/conf.d/gate.ini <<'INI'
date.timezone=UTC
extension_dir=/opt/php/lib/php/extensions/no-debug-non-zts-20210902
extension=phar
extension=zip
extension=mbstring
extension=curl
extension=dom
extension=xml
extension=xmlwriter
extension=xmlreader
extension=simplexml
extension=tokenizer
INI
    cp -a /usr/lib/php/20210902/. /out/lib/php/extensions/no-debug-non-zts-20210902/
    deps_dir=/out/lib/php-gate-deps
    for obj in /out/bin/php /out/lib/php/extensions/no-debug-non-zts-20210902/*.so; do
      ldd \"\$obj\" | awk '/=> \\// {print \$3}' | while read -r lib; do
        cp -L \"\$lib\" \"\$deps_dir/\" 2>/dev/null || true
      done
    done
    curl -fsSL -o /tmp/composer-setup.php https://getcomposer.org/installer
    expected=\"\$(curl -fsSL https://composer.github.io/installer.sig)\"
    actual=\"\$(php8.1 -r \"echo hash_file('sha384', '/tmp/composer-setup.php');\")\"
    test \"\$expected\" = \"\$actual\"
    php8.1 /tmp/composer-setup.php --install-dir=/out/bin --filename=composer
    rm -f /tmp/composer-setup.php
    test \"\$(php8.1 -r 'echo PHP_VERSION;')\" = \"8.1.34\"
    php8.1 -m | grep -x zip >/dev/null
    /out/bin/composer --version | head -1
  " | tee -a "$RESULTS"
}

composer_update_repo() {
  log_section "composer-update"
  if ! timeout "$COMPOSER_TIMEOUT" "${DOCKER[@]}" run --rm \
    -v "$REPO_ROOT:/app" \
    -v "$PHP_TREE_DEBIAN:/usr/local:ro" \
    -w /app \
    "$PHP_IMAGE" bash -lc 'set -e
      export DEBIAN_FRONTEND=noninteractive
      apt-get update -qq
      apt-get install -y -qq git
      export PATH=/usr/local/bin:$PATH
      export LD_LIBRARY_PATH=/usr/local/lib/php-gate-deps:${LD_LIBRARY_PATH:-}
      export GATE_COMPOSER_BIN=/usr/local/bin/composer
      git config --global --add safe.directory /app
      php -m | grep -x zip >/dev/null
      COMPOSER_PLATFORM_PHP=8.1.34 composer update --no-interaction --no-progress
    ' 2>&1 | tee -a "$RESULTS"; then
    echo "composer update failed or timed out after ${COMPOSER_TIMEOUT}s (see log above)" | tee -a "$RESULTS"
    exit 1
  fi
  rm -f "$REPO_ROOT/composer.lock"
  if [[ -d "$REPO_ROOT/vendor" ]]; then
    sudo chown -R "$(id -u):$(id -g)" "$REPO_ROOT/vendor" 2>/dev/null || true
  fi
}

selenium_env_script() {
  cat <<'EOS'
set -euo pipefail
export PATH=/opt/php/bin:/usr/bin:/bin:$PATH
export WEBDRIVER_TEST_CHROME_BINARY=/usr/bin/google-chrome
export WEBDRIVER_TEST_CHROMEDRIVER_BINARY=/usr/bin/chromedriver
export GATE_COMPOSER_BIN=/opt/php/bin/composer
EXT_DIR=/opt/php/lib/php/extensions/no-debug-non-zts-20210902
PHP_LIB_DEPS=/opt/php/lib/php-gate-deps
export LD_LIBRARY_PATH="${PHP_LIB_DEPS}"
export TZ=UTC
export PHP_INI_SCAN_DIR=/opt/php/etc/conf.d
PHP_VER="$(/opt/php/bin/php -r "echo PHP_VERSION;")"
CHROME_VER="$(/usr/bin/google-chrome --version 2>&1 | tr -d "\n")"
DRIVER_VER="$(/usr/bin/chromedriver --version 2>&1 | tr -d "\n")"
echo "PHP_VERSION=${PHP_VER}"
echo "CHROME_VERSION=${CHROME_VER}"
echo "CHROMEDRIVER_VERSION=${DRIVER_VER}"
test "${PHP_VER}" = "8.1.34"
echo "${CHROME_VER}" | grep -q "140.0.7339.207"
echo "${DRIVER_VER}" | grep -q "140.0.7339.207"
if command -v git >/dev/null 2>&1; then
  git config --global --add safe.directory /app
fi
if [[ -e tests/data/app/db && ! -d tests/data/app/db ]]; then
  rm -f tests/data/app/db
fi
mkdir -p tests/output tests/support/_generated .phpunit.cache tests/data/app/db
chmod -R 777 tests/output tests/support/_generated .phpunit.cache tests/data/app/db 2>/dev/null || true
EOS
}

run_phpunit_in_selenium() {
  local label="$1"
  shift
  log_section "$label"
  local inner="$1"
  shift || true
  "${DOCKER[@]}" run --rm --shm-size=2g -u root \
    -v "$REPO_ROOT:/app" \
    -v "$PHP_TREE:/opt/php:ro" \
    -w /app \
    --entrypoint bash \
    "$SEL_IMAGE" -lc "$(selenium_env_script)
${inner}" 2>&1 | tee -a "$RESULTS"
  local ec=${PIPESTATUS[0]}
  echo "EXIT:${ec}" | tee -a "$RESULTS"
  test "$ec" -eq 0
}

run_gate_phpunit() {
  local php_inv
  php_inv="$(selenium_php_bin)"
  run_phpunit_in_selenium "phpunit-default-1" \
    "${php_inv} vendor/bin/phpunit -c phpunit.xml.dist"
  run_phpunit_in_selenium "phpunit-default-2" \
    "${php_inv} vendor/bin/phpunit -c phpunit.xml.dist"
  run_phpunit_in_selenium "phpunit-random-1" \
    "${php_inv} vendor/bin/phpunit -c phpunit.xml.dist --order-by=random --random-order-seed=20261008"
  run_phpunit_in_selenium "phpunit-random-2" \
    "${php_inv} vendor/bin/phpunit -c phpunit.xml.dist --order-by=random --random-order-seed=20261008"
  run_phpunit_in_selenium "consumer-web" \
    "${php_inv} vendor/bin/phpunit -c phpunit.consumer-web.xml"
}

composer_audit() {
  log_section "composer-audit"
  "${DOCKER[@]}" run --rm \
    -v "$REPO_ROOT:/app" \
    -v "$PHP_TREE_DEBIAN:/usr/local:ro" \
    -w /app \
    "$PHP_IMAGE" bash -lc 'set -e
      export PATH=/usr/local/bin:$PATH
      export LD_LIBRARY_PATH=/usr/local/lib/php-gate-deps:${LD_LIBRARY_PATH:-}
      composer audit
    ' 2>&1 | tee -a "$RESULTS"
  echo "AUDIT_EXIT:${PIPESTATUS[0]}" | tee -a "$RESULTS"
  test "${PIPESTATUS[0]}" -eq 0
}

static_analysis() {
  log_section "phpcs-tests"
  "${DOCKER[@]}" run --rm \
    -v "$REPO_ROOT:/app:ro" \
    -v "$PHP_TREE_DEBIAN:/usr/local:ro" \
    -w /app \
    "$PHP_IMAGE" bash -lc 'set -e
      export PATH=/usr/local/bin:$PATH
      export LD_LIBRARY_PATH=/usr/local/lib/php-gate-deps:${LD_LIBRARY_PATH:-}
      curl -fsSL -o /tmp/phpcs.phar https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar
      php /tmp/phpcs.phar -p tests --standard=tests/phpcs.xml
    ' 2>&1 | tee -a "$RESULTS"
  echo "PHPCS_EXIT:${PIPESTATUS[0]}" | tee -a "$RESULTS"
  test "${PIPESTATUS[0]}" -eq 0

  log_section "phpstan-tests"
  "${DOCKER[@]}" run --rm \
    -v "$REPO_ROOT:/app" \
    -v "$PHP_TREE_DEBIAN:/usr/local:ro" \
    -w /app \
    "$PHP_IMAGE" bash -lc 'set -e
      export DEBIAN_FRONTEND=noninteractive
      apt-get update -qq
      apt-get install -y -qq git
      export PATH=/usr/local/bin:$PATH
      export LD_LIBRARY_PATH=/usr/local/lib/php-gate-deps:${LD_LIBRARY_PATH:-}
      git config --global --add safe.directory /app
      php vendor/bin/codecept build -q
      curl -fsSL -o /tmp/phpstan.phar https://github.com/phpstan/phpstan/releases/download/2.1.22/phpstan.phar
      php /tmp/phpstan.phar analyse tests/ --level=1 --memory-limit=512M
    ' 2>&1 | tee -a "$RESULTS"
  echo "PHPSTAN_EXIT:${PIPESTATUS[0]}" | tee -a "$RESULTS"
  test "${PIPESTATUS[0]}" -eq 0
}

main() {
  build_php_trees
  composer_update_repo
  run_gate_phpunit
  composer_audit
  static_analysis
  echo "GATE_COMPLETE" | tee -a "$RESULTS"
}

main "$@"
