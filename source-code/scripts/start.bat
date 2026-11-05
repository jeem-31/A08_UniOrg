@echo off
setlocal EnableExtensions

set "PROJECT_ROOT=%~dp0..\"
set "DRY_RUN="

if /I "%~1"=="--dry-run" set "DRY_RUN=1"

cd /d "%PROJECT_ROOT%"

if /I "%~1"=="--check-redis" (
    call :check_redis
    exit /b %errorlevel%
)

echo ========================================
echo   Starting UniOrg Local Services
echo ========================================
echo.

where php >nul 2>&1
if errorlevel 1 (
    echo ERROR: PHP was not found in PATH.
    goto :failure
)

if defined DRY_RUN (
    echo [DRY RUN] No service windows will be opened.
) else (
    call :check_redis
    if errorlevel 1 (
        goto :failure
    )
)

echo.
echo Launching services in separate windows...

call :launch "Queue Worker" "%PROJECT_ROOT%" "php artisan queue:work redis --queue=document-ai,emails,default --tries=3 --timeout=300"
call :launch "Laravel Scheduler" "%PROJECT_ROOT%" "php artisan schedule:work"
call :launch "Laravel Server" "%PROJECT_ROOT%public" "php -d max_file_uploads=100 -d upload_max_filesize=100M -d post_max_size=500M -d memory_limit=512M -S 0.0.0.0:8000 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"

echo.
echo ========================================
echo   All local services have been launched.
echo ========================================
echo Laravel:  http://localhost:8000
echo Queue:    Processing (document-ai, emails, default)
echo Scheduler: running in its own window
echo Pusher:   cloud-hosted WebSocket service
echo.
echo Close this window anytime; the service windows will keep running.
exit /b 0

:check_redis
echo Checking Redis connectivity...
php artisan tinker --execute="echo (string) Illuminate\Support\Facades\Redis::connection()->ping();" 2>nul | findstr /R /C:"^PONG$" >nul
if errorlevel 1 (
    echo WARNING: Redis is not reachable. Start Redis, then run this script again.
    exit /b 1
)
echo Redis is reachable.
exit /b 0

:launch
set "SERVICE_NAME=%~1"
set "WORKING_DIRECTORY=%~2"
set "SERVICE_COMMAND=%~3"

if defined DRY_RUN (
    echo [DRY RUN] %SERVICE_NAME%: %SERVICE_COMMAND%
) else (
    start "%SERVICE_NAME%" /D "%WORKING_DIRECTORY%" cmd /k "%SERVICE_COMMAND%"
)
exit /b 0

:failure
echo.
echo No services were launched.
if not defined DRY_RUN pause
exit /b 1
