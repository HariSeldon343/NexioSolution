#!/bin/bash

# Setup cron job for OnlyOffice monitoring

CRON_JOB="* * * * * /mnt/c/xampp/htdocs/piattaforma-collaborativa/onlyoffice/monitor-onlyoffice.sh >/dev/null 2>&1"
CRON_COMMENT="# OnlyOffice Health Monitor"

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "monitor-onlyoffice.sh"; then
    echo "Cron job already exists"
else
    # Add the cron job
    (crontab -l 2>/dev/null; echo "$CRON_COMMENT"; echo "$CRON_JOB") | crontab -
    echo "Cron job added successfully"
    echo "OnlyOffice will be monitored every minute"
fi

# Show current crontab
echo ""
echo "Current crontab:"
crontab -l | grep -A1 -B1 "OnlyOffice"