@echo off
setlocal

set "PROJECT_DIR=C:\Users\mejsa\ArdhiWEB"
set "PHP_EXE=C:\xampp\php\php.exe"
set "LOG_FILE=C:\Users\mejsa\ArdhiWEB\var\log\evenement_notifications.log"

if not exist "C:\Users\mejsa\ArdhiWEB\var\log" (
    mkdir "C:\Users\mejsa\ArdhiWEB\var\log"
)

echo [%date% %time%] Running notifications... >> "%LOG_FILE%"

"%PHP_EXE%" "%PROJECT_DIR%\bin\console" app:evenement:send-notifications --env=dev >> "%LOG_FILE%" 2>&1

echo [%date% %time%] Done. >> "%LOG_FILE%"

endlocal
