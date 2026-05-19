#!/bin/bash
set -e

echo "🚀 Harpos Data Service başlatılıyor..."

# Veritabanı hazır olana kadar bekle
echo "⏳ Veritabanı bağlantısı bekleniyor..."
until php artisan db:show --no-ansi 2>/dev/null || php -r "
\$retries = 0;
while (\$retries < 30) {
    try {
        \$pdo = new PDO(
            'pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
        echo 'DB OK';
        exit(0);
    } catch (Exception \$e) {
        sleep(2);
        \$retries++;
    }
}
exit(1);
" 2>/dev/null; do
    echo "   Veritabanı hazır değil, 3 saniye bekleniyor..."
    sleep 3
done

echo "✅ Veritabanı bağlantısı kuruldu"

# Migration çalıştır
echo "🔄 Migrationlar çalıştırılıyor..."
php artisan migrate --force --no-interaction

echo "✅ Migrationlar tamamlandı"

# Cache temizle
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true

echo "🌐 Sunucu başlatılıyor..."
exec php artisan serve --host=0.0.0.0 --port=80
