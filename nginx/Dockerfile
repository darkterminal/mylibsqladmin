FROM openresty/openresty:alpine

COPY ./nginx.conf /etc/nginx/conf.d/default.conf

RUN mv entrypoint.sh /usr/local/bin/proxy-entrypoint.sh
RUN chmod +x /usr/local/bin/proxy-entrypoint.sh

ENTRYPOINT ["sh", "-c", "/usr/local/bin/proxy-entrypoint.sh"]
