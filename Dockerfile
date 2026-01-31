# Используем официальный образ PHP с Apache
FROM php:8.2-apache

# Устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    gnupg \
    && rm -rf /var/lib/apt/lists/*

# Устанавливаем расширения PHP
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    session \
    zip

# Включаем необходимые модули Apache
RUN a2enmod rewrite headers expires

# Создаем директорию для логов
RUN mkdir -p /var/log/apache2 && \
    chown www-data:www-data /var/log/apache2

# Копируем файлы проекта
COPY . /var/www/html/

# Настраиваем безопасность Apache
RUN echo "ServerTokens Prod" >> /etc/apache2/apache2.conf && \
    echo "ServerSignature Off" >> /etc/apache2/apache2.conf && \
    echo "TraceEnable Off" >> /etc/apache2/apache2.conf

# Настраиваем php.ini для безопасности
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo "upload_max_filesize = 2M" >> "$PHP_INI_DIR/php.ini" && \
    echo "post_max_size = 4M" >> "$PHP_INI_DIR/php.ini" && \
    echo "max_execution_time = 120" >> "$PHP_INI_DIR/php.ini" && \
    echo "memory_limit = 128M" >> "$PHP_INI_DIR/php.ini" && \
    echo "display_errors = Off" >> "$PHP_INI_DIR/php.ini" && \
    echo "log_errors = On" >> "$PHP_INI_DIR/php.ini" && \
    echo "error_log = /var/log/apache2/php_errors.log" >> "$PHP_INI_DIR/php.ini"

# Меняем права для безопасности
RUN chown -R www-data:www-data /var/www/html && \
    find /var/www/html -type d -exec chmod 755 {} \; && \
    find /var/www/html -type f -exec chmod 644 {} \; && \
    chmod 600 /var/www/html/.env 2>/dev/null || true

# Удаляем опасные файлы если они случайно попали
RUN rm -f /var/www/html/.env.example 2>/dev/null || true

# Порт (Render использует $PORT переменную)
EXPOSE 8080

# Запускаем Apache с правильным портом
CMD sed -i "s/Listen 80/Listen 8080/g" /etc/apache2/ports.conf /etc/apache2/sites-enabled/*.conf && \
    apache2-foreground