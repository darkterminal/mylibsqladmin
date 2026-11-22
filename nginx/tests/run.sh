#!/bin/sh
# Run the proxy Lua unit tests in an OpenResty container. Docker by default;
# Kiri is opt-in via CONTAINER_ENGINE=kiri. No stack or network is required.
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
ENGINE=${CONTAINER_ENGINE:-docker}
if ! command -v "$ENGINE" >/dev/null 2>&1; then
    echo "proxy/test: '$ENGINE' not found. Install Docker or set CONTAINER_ENGINE=kiri." >&2
    exit 1
fi

exec "$ENGINE" run --rm \
    -v "$ROOT/nginx/lua:/etc/nginx/lua:ro" \
    -v "$ROOT/nginx/tests/proxy_lua_test.lua:/tmp/proxy_lua_test.lua:ro" \
    openresty/openresty:alpine \
    /usr/local/openresty/luajit/bin/luajit /tmp/proxy_lua_test.lua
