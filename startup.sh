#!/usr/bin/env bash

set -e

if ! php -m | grep -qi '^opentelemetry$'; then
    echo "Installing OpenTelemetry PHP extension..."
    pecl install opentelemetry
    printf '%s\n' 'extension=opentelemetry.so' > /usr/local/etc/php/conf.d/99-opentelemetry.ini
fi

echo "Starting PHP-FPM..."
php-fpm