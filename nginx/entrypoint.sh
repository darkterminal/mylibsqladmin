#!/bin/sh
set -eu

# Render the nginx template once at startup. The mounted template stays
# read-only; only the generated /etc/nginx/conf.d/default.conf is writable.
# This replaces the old APP_ENV sed hack, which tried to edit a bind mount.

TEMPLATE=${NGINX_TEMPLATE:-/etc/nginx/templates/default.conf.template}
DEST=/etc/nginx/conf.d/default.conf

if [ ! -f "$TEMPLATE" ]; then
    echo "proxy: nginx template not found: $TEMPLATE" >&2
    exit 1
fi

PARENT_DOMAIN=${LIBSQL_PARENT_DOMAIN:-localhost}
VALIDATOR_UPSTREAM=${LIBSQL_VALIDATOR_UPSTREAM:-mylibsqladmin-webui-prod:8000}
DB_HTTP_UPSTREAM=${LIBSQL_DB_HTTP_UPSTREAM:-db:8080}
DB_GRPC_UPSTREAM=${LIBSQL_DB_GRPC_UPSTREAM:-db:5001}

# These values are interpolated into nginx directives by sed. Restrict them
# to the hostname/port alphabet so a stray separator, newline or backslash
# cannot inject or corrupt a directive. Fail closed at startup rather than
# render a proxy with an unintended upstream or parent domain.
validate_value() {
    name=$1
    value=$2
    case "$value" in
        *[!A-Za-z0-9._:-]* )
            echo "proxy: invalid $name: $value" >&2
            exit 1
            ;;
    esac
}

validate_value LIBSQL_PARENT_DOMAIN "$PARENT_DOMAIN"
validate_value LIBSQL_VALIDATOR_UPSTREAM "$VALIDATOR_UPSTREAM"
validate_value LIBSQL_DB_HTTP_UPSTREAM "$DB_HTTP_UPSTREAM"
validate_value LIBSQL_DB_GRPC_UPSTREAM "$DB_GRPC_UPSTREAM"

cp "$TEMPLATE" "$DEST"

sed -i \
    -e "s|__LIBSQL_PARENT_DOMAIN__|${PARENT_DOMAIN}|g" \
    -e "s|__VALIDATOR_UPSTREAM__|${VALIDATOR_UPSTREAM}|g" \
    -e "s|__DB_HTTP_UPSTREAM__|${DB_HTTP_UPSTREAM}|g" \
    -e "s|__DB_GRPC_UPSTREAM__|${DB_GRPC_UPSTREAM}|g" \
    "$DEST"

exec nginx -g 'daemon off;'
