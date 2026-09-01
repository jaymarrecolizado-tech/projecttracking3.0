#!/bin/bash
# ==========================================
# DICT-MRIS Hostinger CloudPanel Deploy Script
# ==========================================

echo "🚀 Starting Valkyrie Deployment Protocol..."

# Ensure we are in the script's directory
cd "$(dirname "$0")"

# 0. Preflight — the 02:15 spatie/laravel-backup dump shells out to mysqldump.
if ! command -v mysqldump >/dev/null 2>&1; then
    echo "⚠️  mysqldump is not on PATH — nightly DB backups will fail until it is available."
fi

# 1. Pull latest code (Uncomment if using Git)
# echo "📥 Pulling latest code..."
# git pull origin main

# 2. Install PHP Dependencies (No Dev)
echo "📦 Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# 3. Build Frontend Assets
echo "🎨 Building Vue frontend assets..."
npm ci
npm run build

# 4. Run Database Migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# 5. Clear and Cache Laravel Data
echo "🧹 Clearing and caching application state..."
php artisan optimize:clear
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

# 6. Fix File Permissions (CloudPanel standard)
# Note: CloudPanel typically uses the site user for ownership (e.g., your FTP username)
echo "🔐 Securing file permissions..."
chown -R $USER:$USER .
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 775 storage bootstrap/cache

# 7. Restart Queue Workers (If using Supervisor)
echo "⚙️ Restarting queue workers..."
php artisan queue:restart

echo "✅ Deployment successful. DICT-MRIS is online."
