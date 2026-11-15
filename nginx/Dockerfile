FROM openresty/openresty:alpine

# Pick the config flavor at build time: prod ships nginx.conf (pointing at
# mylibsqladmin-webui-prod:8000); dev flavor points at mylibsqladmin-webui-dev:8000.
ARG NGINX_CONF=nginx.conf
COPY ./${NGINX_CONF} /etc/nginx/conf.d/default.conf
COPY ./entrypoint.sh /usr/local/bin/proxy-entrypoint.sh

RUN chmod +x /usr/local/bin/proxy-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/proxy-entrypoint.sh"]
