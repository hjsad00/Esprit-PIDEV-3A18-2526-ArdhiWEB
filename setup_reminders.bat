@echo off
setlocal

REM Setup Windows Task Scheduler for Event Reminders
REM This creates a task to run reminders automatically every day at 12:00 PM

set TASK_NAME=ArdhiEventReminders
set BATCH_FILE=%~dp0bin\run_event_notifications.bat
set TRIGGER_TIME=12:00

echo.
echo ============================================
echo Event Reminders - Task Scheduler Setup
echo ============================================
echo.
echo Creating scheduled task: %TASK_NAME%
echo Batch file: %BATCH_FILE%
echo Run time: Daily at %TRIGGER_TIME%
echo.

REM Remove existing task if it exists
echo Checking for existing task...
tasklist /FI "TASKNAME eq %TASK_NAME%" 2>NUL | find /I "%TASK_NAME%" >NUL
if "%ERRORLEVEL%"=="0" (
    echo Removing existing task...
    schtasks /delete /tn %TASK_NAME% /f >NUL 2>&1
)

REM Create new scheduled task
echo Creating new task...
schtasks /create /tn %TASK_NAME% /tr "%BATCH_FILE%" /sc daily /st %TRIGGER_TIME% /f

echo.
if %ERRORLEVEL% EQU 0 (
    echo [SUCCESS] Task created successfully!
    echo.
    echo The event reminders will now run automatically every day at %TRIGGER_TIME%
    echo.
    echo To view or edit the task:
    echo   1. Open Task Scheduler (press Win+R, type taskschd.msc, press Enter)
    echo   2. Look for task: %TASK_NAME%
    echo   3. Right-click to view properties or edit settings
    echo.
) else (
    echo [ERROR] Failed to create task. You may need Administrator privileges.
    echo Please run this file as Administrator.
    echo.
)

echo Press any key to close this window...
pause >NUL

endlocal
