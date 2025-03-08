FROM php:8.3-alpine

WORKDIR /app

COPY . .

RUN find data/libsql/data.sqld/dbs/ -mindepth 1 -maxdepth 1 -type d ! -name 'default' -exec rm -rf {} +

USER www-data

EXPOSE 4500

HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost:4500/health || exit 1

CMD ["php", "-S", "0.0.0.0:4500", "index.php"]
