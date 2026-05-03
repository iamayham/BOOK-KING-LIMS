FROM php:8.2-cli

WORKDIR /app

# Required PHP extensions for this project
RUN docker-php-ext-install pdo pdo_mysql

COPY . /app

# Railway provides PORT at runtime
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /app"]
