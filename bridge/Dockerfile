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

# Cleanup database directories
RUN find data/libsql/data.sqld/dbs/ -mindepth 1 -maxdepth 1 -type d ! -name 'default' -exec rm -rf {} +

# Set permissions
RUN chown -R www-data:www-data /app

USER www-data

EXPOSE 4500

HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost:4500/health || exit 1

CMD ["php", "-S", "0.0.0.0:4500", "index.php"]
