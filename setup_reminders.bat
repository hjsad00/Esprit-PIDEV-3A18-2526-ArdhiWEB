@echo off
setlocal

set TASK_NAME=ArdhiEventReminders
set BATCH_FILE=C:\Users\hp\ArdhiWEB\bin\run_event_notifications.bat
set TRIGGER_TIME=08:00

echo ============================================
echo Event Reminders - Task Scheduler Setup
echo ============================================

REM Remove existing task if it exists
schtasks /query /tn "%TASK_NAME%" >NUL 2>&1
if %ERRORLEVEL% EQU 0 (
    echo Removing existing task...
    schtasks /delete /tn "%TASK_NAME%" /f
)

REM Create new scheduled task (runs every day at 8:00 AM)
echo Creating new task...
schtasks /create /tn "%TASK_NAME%" /tr "\"%BATCH_FILE%\"" /sc daily /st %TRIGGER_TIME% /ru SYSTEM /f

if %ERRORLEVEL% EQU 0 (
    echo [SUCCESS] Task created successfully!
    echo Every day at %TRIGGER_TIME%, reminders will be sent.
    echo Logs can be found in var/log/evenement_notifications.log
) else (
    echo [ERROR] Please run this file as Administrator!
)

pause
endlocal
