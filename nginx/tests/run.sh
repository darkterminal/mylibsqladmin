#!/bin/sh
# Run the proxy Lua unit tests in an OpenResty container. Kiri by default,
# Docker as fallback. No stack or network is required.
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
ENGINE=${CONTAINER_ENGINE:-kiri}
if ! command -v "$ENGINE" >/dev/null 2>&1; then
    ENGINE=docker
fi

exec "$ENGINE" run --rm \
    -v "$ROOT/nginx/lua:/etc/nginx/lua:ro" \
    -v "$ROOT/nginx/tests/proxy_lua_test.lua:/tmp/proxy_lua_test.lua:ro" \
    openresty/openresty:alpine \
    /usr/local/openresty/luajit/bin/luajit /tmp/proxy_lua_test.lua
