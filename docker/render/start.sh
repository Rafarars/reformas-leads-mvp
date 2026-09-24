#!/bin/sh
# Arranque para plataformas que solo dan UN servicio (Render gratuito): el mismo contenedor
# sirve la web, entrega el outbox y ejecuta el planificador.
set -e

# Falla en cerrado: con configuración inválida no se sirve tráfico.
php artisan app:check-config
php artisan migrate --force
# Idempotente: crea o actualiza al administrador a partir de ADMIN_EMAIL y ADMIN_PASSWORD.
php artisan app:admin:ensure
# Idempotente: crea o actualiza el cliente API del bot de Telegram a partir de BOT_API_KEY.
php artisan app:api-client:ensure-bot

# Cada proceso en su bucle: si muere, vuelve a arrancar solo en vez de dejar la cola parada
# sin que nadie se entere.
while true; do php artisan queue:work --tries=1 --timeout=90 --sleep=3; sleep 2; done &
while true; do php artisan schedule:work; sleep 2; done &

# La plataforma indica el puerto en PORT; FrankenPHP lo toma de SERVER_NAME.
export SERVER_NAME=":${PORT:-8010}"

exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile
