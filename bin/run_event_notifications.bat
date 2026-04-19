@echo off
setlocal

set "PROJECT_DIR=C:\Users\hp\ArdhiWEB"
set "PHP_EXE=C:\xampp\php\php.exe"
set "LOG_FILE=C:\Users\hp\ArdhiWEB\var\log\evenement_notifications.log"

if not exist "C:\Users\hp\ArdhiWEB\var\log" (
    mkdir "C:\Users\hp\ArdhiWEB\var\log"
)

echo [%date% %time%] Running notifications... >> "%LOG_FILE%"

"%PHP_EXE%" "%PROJECT_DIR%\bin\console" app:evenement:send-notifications --env=dev >> "%LOG_FILE%" 2>&1

echo [%date% %time%] Done. >> "%LOG_FILE%"

endlocal
