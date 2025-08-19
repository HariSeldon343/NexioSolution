#!/bin/bash

# OnlyOffice Health Monitor
# Runs every minute to ensure OnlyOffice is always available

SCRIPT_DIR="/mnt/c/xampp/htdocs/piattaforma-collaborativa/onlyoffice"
LOG_FILE="${SCRIPT_DIR}/monitor.log"
MAX_LOG_SIZE=10485760  # 10MB

# Function to rotate log if too large
rotate_log() {
    if [ -f "$LOG_FILE" ]; then
        size=$(stat -c%s "$LOG_FILE" 2>/dev/null || stat -f%z "$LOG_FILE" 2>/dev/null)
        if [ "$size" -gt "$MAX_LOG_SIZE" ]; then
            mv "$LOG_FILE" "${LOG_FILE}.old"
            echo "[$(date)] Log rotated" > "$LOG_FILE"
        fi
    fi
}

# Function to log with timestamp
log_message() {
    echo "[$(date)] $1" >> "$LOG_FILE"
}

# Rotate log if needed
rotate_log

# Check health
log_message "Checking OnlyOffice health..."

if ! curl -sf http://localhost:8082/web-apps/apps/api/documents/api.js > /dev/null 2>&1; then
    log_message "ERROR: OnlyOffice is not responding! Attempting auto-fix..."
    
    # Try to fix
    ${SCRIPT_DIR}/docker-onlyoffice-manager.sh auto-fix >> "$LOG_FILE" 2>&1
    
    # Check again
    sleep 10
    if curl -sf http://localhost:8082/web-apps/apps/api/documents/api.js > /dev/null 2>&1; then
        log_message "SUCCESS: OnlyOffice recovered successfully"
    else
        log_message "CRITICAL: OnlyOffice could not be recovered!"
        # Send alert (could integrate with email/notification system)
    fi
else
    log_message "OK: OnlyOffice is healthy"
fi