@echo off
setlocal

set "PROJECT_DIR=%~dp0.."
set "PHP_EXE=C:\xampp\php\php.exe"
set "LOG_FILE=%PROJECT_DIR%\var\log\evenement_notifications.log"

if not exist "%PROJECT_DIR%\var\log" (
    mkdir "%PROJECT_DIR%\var\log"
)

"%PHP_EXE%" "%PROJECT_DIR%\bin\console" app:evenement:send-notifications --env=dev >> "%LOG_FILE%" 2>&1

endlocal
