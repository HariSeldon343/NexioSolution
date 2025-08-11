/**
 * Duration Formatter Utility
 * Formats durations in minutes to human-readable Italian format
 */

class DurationFormatter {
    /**
     * Format minutes to human-readable Italian duration string
     * @param {number} minutes - Duration in minutes
     * @returns {string} Formatted duration string
     */
    static formatMinutes(minutes) {
        if (!minutes || minutes <= 0) {
            return '0 minuti';
        }

        // Convert to integer
        minutes = Math.floor(minutes);

        // Special cases
        if (minutes >= 10080) { // 7+ days (1 week)
            const weeks = Math.floor(minutes / 10080);
            const days = Math.floor((minutes % 10080) / 1440);
            if (weeks === 1 && days === 0) {
                return '1 settimana';
            } else if (weeks === 1 && days > 0) {
                return `1 settimana e ${days} ${days === 1 ? 'giorno' : 'giorni'}`;
            } else if (days === 0) {
                return `${weeks} settimane`;
            } else {
                return `${weeks} settimane e ${days} ${days === 1 ? 'giorno' : 'giorni'}`;
            }
        }

        if (minutes >= 1440) { // 1+ days
            const days = Math.floor(minutes / 1440);
            const hours = Math.floor((minutes % 1440) / 60);
            const mins = minutes % 60;
            
            let result = [];
            
            if (days > 0) {
                result.push(`${days} ${days === 1 ? 'giorno' : 'giorni'}`);
            }
            
            if (hours > 0) {
                result.push(`${hours} ${hours === 1 ? 'ora' : 'ore'}`);
            }
            
            if (mins > 0 && days === 0) { // Only show minutes if less than a day
                result.push(`${mins} ${mins === 1 ? 'minuto' : 'minuti'}`);
            }
            
            return result.join(' e ');
        }

        if (minutes >= 60) { // 1+ hours
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            
            let result = [];
            
            if (hours > 0) {
                result.push(`${hours} ${hours === 1 ? 'ora' : 'ore'}`);
            }
            
            if (mins > 0) {
                result.push(`${mins} ${mins === 1 ? 'minuto' : 'minuti'}`);
            }
            
            return result.join(' e ');
        }

        // Less than an hour
        return `${minutes} ${minutes === 1 ? 'minuto' : 'minuti'}`;
    }

    /**
     * Format minutes to short Italian duration string
     * @param {number} minutes - Duration in minutes
     * @returns {string} Short formatted duration string
     */
    static formatMinutesShort(minutes) {
        if (!minutes || minutes <= 0) {
            return '0m';
        }

        minutes = Math.floor(minutes);

        if (minutes >= 10080) { // 7+ days (1 week)
            const weeks = Math.floor(minutes / 10080);
            const days = Math.floor((minutes % 10080) / 1440);
            return days > 0 ? `${weeks}set ${days}gg` : `${weeks}set`;
        }

        if (minutes >= 1440) { // 1+ days
            const days = Math.floor(minutes / 1440);
            const hours = Math.floor((minutes % 1440) / 60);
            return hours > 0 ? `${days}gg ${hours}h` : `${days}gg`;
        }

        if (minutes >= 60) { // 1+ hours
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
        }

        return `${minutes}m`;
    }

    /**
     * Format duration between two dates
     * @param {Date|string} startDate - Start date
     * @param {Date|string} endDate - End date
     * @returns {string} Formatted duration string
     */
    static formatDateRange(startDate, endDate) {
        if (!startDate || !endDate) {
            return '';
        }

        const start = new Date(startDate);
        const end = new Date(endDate);
        const diffMinutes = Math.floor((end - start) / 60000);

        return this.formatMinutes(diffMinutes);
    }

    /**
     * Format working days to readable string
     * @param {number} days - Number of working days
     * @returns {string} Formatted string
     */
    static formatWorkDays(days) {
        if (!days || days <= 0) {
            return '0 giorni';
        }

        if (days === 1) {
            return '1 giorno';
        }

        if (days >= 5 && days < 10) {
            return `1 settimana lavorativa`;
        }

        if (days >= 10 && days < 15) {
            return `2 settimane lavorative`;
        }

        if (days >= 20 && days < 25) {
            return `1 mese lavorativo`;
        }

        return `${days} giorni lavorativi`;
    }
}

// Auto-format all elements with data-duration attribute on page load
document.addEventListener('DOMContentLoaded', function() {
    formatAllDurations();
});

/**
 * Format all duration elements on the page
 */
function formatAllDurations() {
    // Format elements with data-duration-minutes attribute
    document.querySelectorAll('[data-duration-minutes]').forEach(element => {
        const minutes = parseInt(element.getAttribute('data-duration-minutes'));
        if (!isNaN(minutes)) {
            const formatted = DurationFormatter.formatMinutes(minutes);
            element.textContent = formatted;
        }
    });

    // Format elements with class 'duration-minutes'
    document.querySelectorAll('.duration-minutes').forEach(element => {
        const text = element.textContent.trim();
        const match = text.match(/(\d+)\s*min/i);
        if (match) {
            const minutes = parseInt(match[1]);
            const formatted = DurationFormatter.formatMinutes(minutes);
            element.textContent = `(${formatted})`;
        }
    });

    // Format task durations (work days)
    document.querySelectorAll('.task-duration').forEach(element => {
        const text = element.textContent.trim();
        const match = text.match(/(\d+)\s*gg/i);
        if (match) {
            const days = parseInt(match[1]);
            const formatted = DurationFormatter.formatWorkDays(days);
            element.textContent = formatted;
        }
    });
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DurationFormatter;
}