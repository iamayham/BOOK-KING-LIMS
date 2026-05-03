FROM php:8.2-cli

WORKDIR /app

# PDO + MySQL + curl (Brevo transactional API over HTTPS)
RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_mysql curl \
    && rm -rf /var/lib/apt/lists/*

COPY . /app

# Railway provides PORT at runtime
CMD ["sh", "-c", "php -d auto_prepend_file=/app/helpers/request_guard.php -S 0.0.0.0:${PORT:-8080} -t /app"]
