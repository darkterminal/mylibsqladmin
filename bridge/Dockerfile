FROM php:8.3-bookworm

# Install Turso CLI
RUN curl --proto '=https' --tlsv1.2 -LsSf https://github.com/tursodatabase/libsql/releases/download/libsql-server-v0.24.32/libsql-server-installer.sh | sh && \
    mv /root/.cargo/bin/sqld /usr/local/bin/sqld && \
    sqld --version

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    chmod +x /usr/local/bin/composer

WORKDIR /app
COPY . .

# Create a startup script
RUN echo '#!/bin/sh' > /entrypoint.sh && \
    echo 'mkdir -p /app/data/libsql/data.sqld/dbs' >> /entrypoint.sh && \
    echo 'chown -R www-data:www-data /app/data' >> /entrypoint.sh && \
    echo 'chmod -R 777 /app/data' >> /entrypoint.sh && \
    echo 'exec "$@"' >> /entrypoint.sh && \
    chmod +x /entrypoint.sh

USER root
EXPOSE 4500
HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost:4500/health || exit 1
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:4500", "index.php"]
