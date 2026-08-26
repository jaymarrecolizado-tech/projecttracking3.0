# Free WiFi Monitoring app - one-shot dev setup
# Run: powershell -ExecutionPolicy Bypass -File setup.ps1
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

Write-Host "== [1/6] Composer install ==" -ForegroundColor Cyan
composer install --no-interaction --prefer-dist
if ($LASTEXITCODE -ne 0) { Write-Error "composer install failed"; exit 1 }

Write-Host "== [2/6] Environment file ==" -ForegroundColor Cyan
if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env"; Write-Host "  .env created" }
php artisan key:generate --force
if ($LASTEXITCODE -ne 0) { Write-Error "key:generate failed"; exit 1 }

Write-Host "== [3/6] SQLite database ==" -ForegroundColor Cyan
if (-not (Test-Path "database\database.sqlite")) { New-Item "database\database.sqlite" -ItemType File | Out-Null }

Write-Host "== [4/6] Migrate + seed ==" -ForegroundColor Cyan
php artisan migrate --seed --force
if ($LASTEXITCODE -ne 0) { Write-Error "migrate failed"; exit 1 }

Write-Host "== [5/6] Admin user ==" -ForegroundColor Cyan
# Generate a random 14-char alphanumeric password — never ship a default one.
$chars = (48..57) + (65..90) + (97..122)
$adminPassword = -join ($chars | Get-Random -Count 14 | ForEach-Object { [char]$_ })
$safePassword = $adminPassword.Replace("'", "\'").Replace("\", "\\")
$code = "App\Models\User::firstOrCreate(['email' => 'admin@example.com'], ['name' => 'Admin', 'password' => bcrypt('$safePassword')]); `" + "`$u = App\Models\User::where('email', 'admin@example.com')->first(); DB::table('role_user')->insertOrIgnore(['user_id' => `$u->id, 'role_id' => 1]);"
php artisan tinker "--execute=$code"
if ($LASTEXITCODE -ne 0) { Write-Error "admin user creation failed"; exit 1 }

Write-Host "== [6/6] Build frontend ==" -ForegroundColor Cyan
if (-not (Test-Path "node_modules")) { npm install }
npm run build
if ($LASTEXITCODE -ne 0) { Write-Error "vite build failed"; exit 1 }

Write-Host ""
Write-Host "DONE. Start the app with:" -ForegroundColor Green
Write-Host "   php artisan serve"
Write-Host "Then open http://127.0.0.1:8000"
Write-Host "Login: admin@example.com / $adminPassword" -ForegroundColor Yellow
