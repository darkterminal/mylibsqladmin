FROM openresty/openresty:alpine

# Pick the config flavor at build time: both shipped templates are identical;
# upstreams and parent domain are selected by environment variables at start.
ARG NGINX_CONF=nginx.conf
COPY ./${NGINX_CONF} /etc/nginx/templates/default.conf.template
COPY ./lua /etc/nginx/lua
COPY ./entrypoint.sh /usr/local/bin/proxy-entrypoint.sh

RUN chmod +x /usr/local/bin/proxy-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/proxy-entrypoint.sh"]
