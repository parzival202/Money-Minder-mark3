# Staggered Alert System Implementation

## Overview
The alert system has been modified to send alerts in a staggered manner to avoid overwhelming the user with multiple notifications at once. Hourly alerts are now sent in a 2-day cycle with specific active hours.

## Alert Types

### Hourly Alerts (sent in rotation, every 2 days during active hours)
These alerts are sent in rotation, one per hour during active periods:
1. **large_expense** - Large expenses (>10,000 FCFA)
2. **budget_warning** - Budget warnings (80-99% usage)
3. **budget_exceeded** - Budget exceeded (≥100% usage)
4. **global_budget** - Global monthly budget exceeded

**Schedule:**
- Active days: Every 2 days (even day of year: 0, 2, 4, etc.)
- Active hours: 00h, 01h, 02h, 03h, 06h, 07h, 08h, 09h
- Sequence: Full rotation twice per active day (8 alerts total)
- Inactive days: No hourly alerts sent

### Immediate Alerts (sent right away)
These alerts are sent immediately when conditions are met:
- **daily_limit** - Daily spending limit exceeded (>10,000 FCFA)
- **daily_warning** - Daily spending warning (>8,000 FCFA)
- **goal_achieved** - Savings goal achieved (≥100%)
- **goal_progress** - Savings goal progress (≥75%)
- **inactivity** - No expenses for 7+ days
- **low_spending** - Low spending encouragement (early month, <5,000 FCFA/week)

## Implementation Details

### Rotation System
- Uses `current_alert_rotation` meta key to track current position in rotation
- Cycles through: large_expense → budget_warning → budget_exceeded → global_budget → repeat
- Only one hourly alert is sent per execution during active periods
- Rotation advances after each execution
- Active day check: `date('z') % 2 == 0` (even day of year)
- Active hour check: current hour in [0,1,2,3,6,7,8,9]

### Files Modified
- `telegram_bot.php` - Modified `checkAndSendAlerts()` function
- `send_alerts.php` - Standalone script for hourly execution
- `send_alerts.bat` - Batch file for Windows Task Scheduler

### Windows Task Scheduler Setup
1. Open Task Scheduler (`taskschd.msc`)
2. Create new task: "MoneyMinder Hourly Alerts"
3. Set to run daily, repeat every 1 hour
4. Program: `C:\xampp\htdocs\moneyminder\send_alerts.bat`

## Testing
- Script tested with multiple executions to verify rotation
- Each run advances to the next alert type in sequence
- Immediate alerts are sent alongside hourly alerts when conditions are met

## Benefits
- Reduces notification spam
- Maintains awareness of all important financial events
- Balances immediate alerts for urgent issues with spaced alerts for monitoring
