#!/usr/bin/env bash

set -Eeuo pipefail

SOURCE_DIR="$(pwd -P)"
CPANEL_USER="${USER:-$(id -un)}"
HOME_DIR="${HOME:-/home/${CPANEL_USER}}"
APP_DIR="${SAFELMS_APP_DIR:-${HOME_DIR}/safelms_app}"
PUBLIC_DIR="${SAFELMS_PUBLIC_DIR:-${HOME_DIR}/public_html}"

fail() {
    echo "SafeLMS deployment stopped: $1" >&2
    exit 1
}

[[ -f "${SOURCE_DIR}/artisan" && -f "${SOURCE_DIR}/composer.json" ]] || fail "the deployment source is not the Laravel project root"
[[ "${APP_DIR}" == "${HOME_DIR}"/* ]] || fail "the application path must stay inside the cPanel home directory"
[[ "${PUBLIC_DIR}" == "${HOME_DIR}"/* ]] || fail "the public path must stay inside the cPanel home directory"

mkdir -p "${APP_DIR}" "${PUBLIC_DIR}"

if [[ "${SOURCE_DIR}" != "${APP_DIR}" ]]; then
    command -v rsync >/dev/null 2>&1 || fail "rsync is required when the Git repository is not the live application directory"
    [[ -f "${APP_DIR}/.env" ]] || fail "${APP_DIR}/.env is missing; create the production environment file before deploying"

    rsync -a --delete \
        --exclude='.git/' \
        --exclude='.env' \
        --exclude='auth.json' \
        --exclude='bootstrap/cache/*.php' \
        --exclude='dist/' \
        --exclude='node_modules/' \
        --exclude='public/hot' \
        --exclude='public/storage' \
        --exclude='storage/' \
        --exclude='vendor/' \
        "${SOURCE_DIR}/" "${APP_DIR}/"
fi

cd "${APP_DIR}"
[[ -f .env ]] || fail "the live application .env file is missing"
grep -Eq '^APP_ENV=production([[:space:]]*)$' .env || fail "APP_ENV must be production"
grep -Eq '^APP_DEBUG=false([[:space:]]*)$' .env || fail "APP_DEBUG must be false"
grep -Eq '^DB_CONNECTION=(mysql|mariadb)([[:space:]]*)$' .env || fail "the cPanel database must use MySQL or MariaDB"

PHP_BIN="${CPANEL_PHP_BIN:-}"
if [[ -z "${PHP_BIN}" ]]; then
    for candidate in \
        /opt/cpanel/ea-php84/root/usr/bin/php \
        /opt/cpanel/ea-php83/root/usr/bin/php \
        /opt/cpanel/ea-php82/root/usr/bin/php \
        "$(command -v php 2>/dev/null || true)"; do
        if [[ -n "${candidate}" && -x "${candidate}" ]]; then
            PHP_BIN="${candidate}"
            break
        fi
    done
fi
[[ -x "${PHP_BIN}" ]] || fail "PHP 8.2 or newer was not found"

PHP_VERSION="$(${PHP_BIN} -r 'echo PHP_VERSION_ID;')"
[[ "${PHP_VERSION}" -ge 80200 ]] || fail "PHP 8.2 or newer is required"

COMPOSER_BIN="$(command -v composer 2>/dev/null || true)"
if [[ -z "${COMPOSER_BIN}" && -x /opt/cpanel/composer/bin/composer ]]; then
    COMPOSER_BIN=/opt/cpanel/composer/bin/composer
fi

if [[ -n "${COMPOSER_BIN}" ]]; then
    "${COMPOSER_BIN}" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
elif [[ ! -f vendor/autoload.php ]]; then
    fail "Composer is unavailable and vendor/autoload.php is missing"
else
    echo "Composer is unavailable; preserving the existing vendor directory."
fi

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

find storage bootstrap/cache -type d -exec chmod 775 {} +
find storage bootstrap/cache -type f -exec chmod 664 {} +

APP_WAS_LIVE=false
if "${PHP_BIN}" artisan about >/dev/null 2>&1; then
    "${PHP_BIN}" artisan down --retry=60 || true
    APP_WAS_LIVE=true
fi

restore_application() {
    if [[ "${APP_WAS_LIVE}" == true ]]; then
        "${PHP_BIN}" artisan up >/dev/null 2>&1 || true
    fi
}
trap restore_application EXIT

"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan migrate --force
"${PHP_BIN}" artisan db:seed --class='Database\Seeders\RolePermissionSeeder' --force
"${PHP_BIN}" artisan files:migrate-protected
"${PHP_BIN}" artisan optimize
"${PHP_BIN}" artisan queue:restart || true

command -v rsync >/dev/null 2>&1 || fail "rsync is required to publish web assets"
[[ -f public/.htaccess ]] || fail "public/.htaccess is missing from the repository"
grep -q 'RewriteRule.*index.php' public/.htaccess || fail "public/.htaccess does not contain the Laravel front-controller rewrite"
rsync -a \
    --exclude='.well-known/' \
    --exclude='cgi-bin/' \
    --exclude='index.php' \
    --exclude='hot' \
    --exclude='storage' \
    public/ "${PUBLIC_DIR}/"

# Dotfiles are easy to miss in File Manager and archive workflows. Install the
# rewrite file explicitly so LiteSpeed sends clean Laravel URLs to index.php.
install -m 644 public/.htaccess "${PUBLIC_DIR}/.htaccess"

APP_PATH_ESCAPED="$(printf '%s' "${APP_DIR}" | sed 's/[&]/\\&/g')"
sed \
    -e "s#__DIR__.'/../storage#'${APP_PATH_ESCAPED}/storage#g" \
    -e "s#__DIR__.'/../vendor#'${APP_PATH_ESCAPED}/vendor#g" \
    -e "s#__DIR__.'/../bootstrap#'${APP_PATH_ESCAPED}/bootstrap#g" \
    public/index.php > "${PUBLIC_DIR}/index.php.tmp"
mv "${PUBLIC_DIR}/index.php.tmp" "${PUBLIC_DIR}/index.php"
chmod 644 "${PUBLIC_DIR}/index.php" "${PUBLIC_DIR}/.htaccess"

if [[ ! -e "${PUBLIC_DIR}/storage" ]]; then
    ln -s "${APP_DIR}/storage/app/public" "${PUBLIC_DIR}/storage" || true
fi

restore_application
APP_WAS_LIVE=false
trap - EXIT

echo "SafeLMS deployed with $(${PHP_BIN} -r 'echo PHP_VERSION;') and the configured cPanel MySQL database."
