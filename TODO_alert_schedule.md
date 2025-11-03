# Alert Schedule Modification TODO

## Overview
Modify the hourly alert system to send the 4-alert sequence twice per active day (at 00h,01h,02h,03h,06h,07h,08h,09h), with active days every 2 days (even day of year), and skip on inactive days.

## Changes Needed

### 1. telegram_bot.php
- [x] Add condition in checkAndSendAlerts() before hourly alert sending:
  - Check if day_of_year % 2 == 0 (active day)
  - Check if current_hour in [0,1,2,3,6,7,8,9] (active hours)
  - If not, skip hourly alert sending
- [x] Immediate alerts unchanged (sent every run if conditions met)

### 2. ALERT_SYSTEM_README.md
- [x] Update Implementation Details section with new 2-day cycle and active hours
- [x] Update Testing section if needed

## Testing
- [ ] Run send_alerts.php multiple times to simulate different hours/days
- [ ] Confirm alerts sent only during active periods
- [ ] Verify two full sequences per active day
