<?php

/**
 * Helper functions for calendar views
 */
class CalendarHelper {
    
    /**
     * Format duration in minutes to human-readable Italian format
     * @param int $minutes Duration in minutes
     * @return string Formatted duration string
     */
    public static function formatDuration($minutes) {
        if ($minutes <= 0) {
            return '';
        }
        
        // Handle special cases
        if ($minutes >= 10080) { // 7 days or more (week)
            $weeks = floor($minutes / 10080);
            $days = floor(($minutes % 10080) / 1440);
            
            if ($weeks == 1 && $days == 0) {
                return '1 settimana';
            } elseif ($weeks > 1 && $days == 0) {
                return $weeks . ' settimane';
            } elseif ($weeks == 1 && $days > 0) {
                return '1 settimana ' . ($days == 1 ? '1 giorno' : $days . ' giorni');
            } else {
                return $weeks . ' settimane ' . ($days == 1 ? '1 giorno' : $days . ' giorni');
            }
        } elseif ($minutes >= 1440) { // 1 day or more
            $days = floor($minutes / 1440);
            $hours = floor(($minutes % 1440) / 60);
            $remainingMinutes = $minutes % 60;
            
            $result = $days . ($days == 1 ? ' giorno' : ' giorni');
            
            if ($hours > 0) {
                $result .= ' ' . $hours . ($hours == 1 ? ' ora' : ' ore');
            }
            
            if ($remainingMinutes > 0 && $days < 2) { // Only show minutes for short durations
                $result .= ' ' . $remainingMinutes . ' min';
            }
            
            return $result;
        } elseif ($minutes >= 60) { // 1 hour or more
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            
            $result = $hours . ($hours == 1 ? ' ora' : ' ore');
            
            if ($remainingMinutes > 0) {
                $result .= ' ' . $remainingMinutes . ' min';
            }
            
            return $result;
        } else { // Less than an hour
            return $minutes . ' min';
        }
    }
    
    /**
     * Calculate duration in minutes between two dates
     * @param string $startDate Start date/time
     * @param string $endDate End date/time (optional)
     * @return int Duration in minutes
     */
    public static function calculateDurationMinutes($startDate, $endDate = null) {
        if (empty($endDate)) {
            return 60; // Default 1 hour if no end date
        }
        
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        
        if ($start === false || $end === false) {
            return 60; // Default if parsing fails
        }
        
        $diff = $end - $start;
        
        if ($diff <= 0) {
            return 60; // Default if end is before or same as start
        }
        
        return floor($diff / 60);
    }
    
    /**
     * Check if an event is all-day (24 hours or more)
     * @param int $durationMinutes Duration in minutes
     * @return bool True if all-day event
     */
    public static function isAllDayEvent($durationMinutes) {
        return $durationMinutes >= 1440;
    }
    
    /**
     * Format event time range
     * @param string $startDate Start date/time
     * @param string $endDate End date/time (optional)
     * @param bool $includeDate Include date in format
     * @return string Formatted time range
     */
    public static function formatTimeRange($startDate, $endDate = null, $includeDate = false) {
        $startTime = date('H:i', strtotime($startDate));
        
        if (empty($endDate) || $startDate === $endDate) {
            if ($includeDate) {
                return date('d/m/Y', strtotime($startDate)) . ' alle ' . $startTime;
            }
            return $startTime;
        }
        
        $endTime = date('H:i', strtotime($endDate));
        $startDateOnly = date('Y-m-d', strtotime($startDate));
        $endDateOnly = date('Y-m-d', strtotime($endDate));
        
        if ($startDateOnly === $endDateOnly) {
            // Same day
            if ($includeDate) {
                return date('d/m/Y', strtotime($startDate)) . ' ' . $startTime . ' - ' . $endTime;
            }
            return $startTime . ' - ' . $endTime;
        } else {
            // Different days
            if ($includeDate) {
                return date('d/m/Y H:i', strtotime($startDate)) . ' - ' . date('d/m/Y H:i', strtotime($endDate));
            }
            return date('d/m H:i', strtotime($startDate)) . ' - ' . date('d/m H:i', strtotime($endDate));
        }
    }
    
    /**
     * Get event type badge color class
     * @param string $type Event type
     * @return string CSS class for badge color
     */
    public static function getEventTypeClass($type) {
        $typeClasses = [
            'meeting' => 'event-type-meeting',
            'riunione' => 'event-type-meeting',
            'presentation' => 'event-type-presentation',
            'presentazione' => 'event-type-presentation',
            'training' => 'event-type-training',
            'formazione' => 'event-type-training',
            'workshop' => 'event-type-workshop',
            'conference' => 'event-type-conference',
            'conferenza' => 'event-type-conference',
            'social' => 'event-type-social',
            'sociale' => 'event-type-social',
            'task' => 'event-type-task',
            'altro' => 'event-type-other',
            'other' => 'event-type-other'
        ];
        
        return $typeClasses[strtolower($type)] ?? 'event-type-other';
    }
    
    /**
     * Get event type label
     * @param string $type Event type
     * @return string Formatted type label
     */
    public static function getEventTypeLabel($type) {
        $typeLabels = [
            'meeting' => 'Riunione',
            'riunione' => 'Riunione',
            'presentation' => 'Presentazione',
            'presentazione' => 'Presentazione',
            'training' => 'Formazione',
            'formazione' => 'Formazione',
            'workshop' => 'Workshop',
            'conference' => 'Conferenza',
            'conferenza' => 'Conferenza',
            'social' => 'Sociale',
            'sociale' => 'Sociale',
            'task' => 'Task',
            'altro' => 'Altro',
            'other' => 'Altro'
        ];
        
        return $typeLabels[strtolower($type)] ?? ucfirst($type);
    }
    
    /**
     * Format task duration in days
     * @param float $days Number of days
     * @return string Formatted duration
     */
    public static function formatTaskDuration($days) {
        if ($days == 1) {
            return '1 giorno';
        } elseif ($days == (int)$days) {
            return (int)$days . ' giorni';
        } else {
            return number_format($days, 1, ',', '.') . ' giorni';
        }
    }
}