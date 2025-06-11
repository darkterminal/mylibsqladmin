#!/bin/sh

DEV_PATTERN="proxy_pass http:\/\/mylibsqladmin-webui-dev:8000\/validate-subdomain;"
PROD_PATTERN="proxy_pass http:\/\/mylibsqladmin-webui-prod:8000\/validate-subdomain;"

if [ "$APP_ENV" = "production" ]; then
    sed -i "s|$DEV_PATTERN|$PROD_PATTERN|g" /etc/nginx/conf.d/default.conf
else
    sed -i "s|$PROD_PATTERN|$DEV_PATTERN|g" /etc/nginx/conf.d/default.conf
fi

exec nginx -g 'daemon off;'
