FROM openresty/openresty:alpine

COPY ./nginx.conf /etc/nginx/conf.d/default.conf
COPY ./entrypoint.sh /usr/local/bin/proxy-entrypoint.sh

RUN chmod +x /usr/local/bin/proxy-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/proxy-entrypoint.sh"]
