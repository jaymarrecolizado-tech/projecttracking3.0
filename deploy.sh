#!/bin/bash
# ==========================================
# DICT-MRIS Hostinger CloudPanel Deploy Script
# ==========================================
# Usage: ./deploy.sh [--pull]
#   --pull  also run `git pull origin main` first (default: deploy the
#           working tree as-is, so prod never lags behind main by accident)

set -euo pipefail

echo "🚀 Starting Valkyrie Deployment Protocol..."

# Ensure we are in the script's directory
cd "$(dirname "$0")"

# 0. Preflight — the 02:15 spatie/laravel-backup dump shells out to mysqldump.
if ! command -v mysqldump >/dev/null 2>&1; then
    echo "⚠️  mysqldump is not on PATH — nightly DB backups will fail until it is available."
fi

# 1. Pull latest code (beh a flag — see Plan_revision §Phase 4.4; the old
# commented-out pull is why production silently fell a release behind)
if [[ "${1:-}" == "--pull" ]]; then
    echo "📥 Pulling latest code..."
    git pull origin main
fi

# 2. Install PHP Dependencies (No Dev)
echo "📦 Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# 3. Build Frontend Assets
echo "🎨 Building Vue frontend assets..."
npm ci
npm run build

# 4. Maintenance window: migrations + cutover happen with requests parked,
#    so a half-migrated schema is never served (Plan_revision §Phase 4.2).
echo "🛠️  Opening maintenance window..."
php artisan down --retry=15

# 5. Run Database Migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# 6. Clear and Cache Laravel Data
echo "🧹 Clearing and caching application state..."
php artisan optimize:clear
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

restore() {
    php artisan up || true
}
trap restore EXIT

# 7. Fix File Permissions (CloudPanel standard)
# Own only what the app writes; never blanket-chmod — a 644 sweep strips the
# executable bit from artisan and deploy.sh and hits .env readability
# (Plan_revision §Phase 4.4).
echo "🔐 Securing file permissions..."
chmod +x artisan deploy.sh
find storage bootstrap/cache -type d -exec chmod 775 {} \; 2>/dev/null || true
find storage bootstrap/cache -type f -exec chmod 664 {} \; 2>/dev/null || true

# 8. Restart Queue Workers (If using Supervisor)
echo "⚙️ Restarting queue workers..."
php artisan queue:restart

# 9. Close the maintenance window (trap also covers a mid-deploy failure)
echo "🌐 Bringing the site back up..."
php artisan up
trap - EXIT

echo "✅ Deployment successful. DICT-MRIS is online."
echo "↩️  Rollback: git checkout <previous-tag> && ./deploy.sh && php artisan migrate:rollback --step=1 --force"
