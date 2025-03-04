FROM php:8.3-alpine

WORKDIR /app

COPY . .

USER www-data

ENV BRIDGE_HTTP_PASSWORD=libsql

EXPOSE 4500

HEALTHCHECK --interval=30s --timeout=3s \
    CMD wget --quiet --tries=1 --spider http://localhost:4500/health || exit 1

CMD ["php", "-S", "0.0.0.0:4500", "index.php"]
