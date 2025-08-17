<?php
/**
 * Helper per gestire i colori degli eventi nel calendario
 * Assegna colori diversi in base all'azienda o stato
 */

class CalendarColorHelper {
    
    // Colori predefiniti per aziende
    private static $companyColors = [
        '#4299e1', // Blue
        '#48bb78', // Green
        '#ed8936', // Orange
        '#9f7aea', // Purple
        '#f56565', // Red
        '#38b2ac', // Teal
        '#ecc94b', // Yellow
        '#fc8181', // Light Red
        '#68d391', // Light Green
        '#90cdf4', // Light Blue
    ];
    
    // Colore per eventi senza azienda (neutro)
    private static $noCompanyColor = '#718096'; // Gray
    
    // Colore per eventi importati da ICS
    private static $icsImportColor = '#805ad5'; // Purple
    
    // Cache dei colori assegnati
    private static $colorMapping = [];
    
    /**
     * Inizializza il mapping dei colori per le aziende
     */
    public static function initializeColorMappings($eventi, $aziende = []) {
        self::$colorMapping = [];
        $colorIndex = 0;
        
        // Assegna colori alle aziende dall'array fornito
        foreach ($aziende as $azienda) {
            if (!isset(self::$colorMapping['company_' . $azienda['id']])) {
                self::$colorMapping['company_' . $azienda['id']] = self::$companyColors[$colorIndex % count(self::$companyColors)];
                $colorIndex++;
            }
        }
        
        // Assegna colori alle aziende trovate negli eventi
        foreach ($eventi as $evento) {
            if (!empty($evento['azienda_id']) && !isset(self::$colorMapping['company_' . $evento['azienda_id']])) {
                self::$colorMapping['company_' . $evento['azienda_id']] = self::$companyColors[$colorIndex % count(self::$companyColors)];
                $colorIndex++;
            }
        }
    }
    
    /**
     * Ottiene il colore per un evento
     */
    public static function getEventColor($evento) {
        // Se l'evento non ha azienda, usa il colore neutro
        if (empty($evento['azienda_id'])) {
            return self::$noCompanyColor;
        }
        
        // Se è un evento importato da ICS con UID
        if (!empty($evento['uid_import'])) {
            return self::$icsImportColor;
        }
        
        // Ottieni il colore dell'azienda
        $key = 'company_' . $evento['azienda_id'];
        if (isset(self::$colorMapping[$key])) {
            return self::$colorMapping[$key];
        }
        
        // Fallback: assegna un nuovo colore
        $colorIndex = count(self::$colorMapping) % count(self::$companyColors);
        self::$colorMapping[$key] = self::$companyColors[$colorIndex];
        return self::$colorMapping[$key];
    }
    
    /**
     * Ottiene la classe CSS per il colore dell'evento
     */
    public static function getEventColorClass($evento) {
        // Eventi senza azienda
        if (empty($evento['azienda_id'])) {
            return 'event-no-company';
        }
        
        // Eventi importati da ICS
        if (!empty($evento['uid_import'])) {
            return 'event-ics-import';
        }
        
        // Eventi con azienda
        return 'event-company-' . $evento['azienda_id'];
    }
    
    /**
     * Ottiene la classe per indicare la fonte dell'evento
     */
    public static function getSourceIndicatorClass($evento) {
        if (!empty($evento['uid_import'])) {
            return 'from-ics';
        }
        if (empty($evento['azienda_id'])) {
            return 'personal-calendar';
        }
        return '';
    }
    
    /**
     * Genera CSS dinamico per i colori delle aziende
     */
    public static function generateColorCSS() {
        $css = "<style>\n";
        
        // Stile per eventi senza azienda
        $css .= ".event-no-company {\n";
        $css .= "    background-color: #e2e8f0 !important;\n";
        $css .= "    color: #2d3748 !important;\n";
        $css .= "    border-left: 3px solid " . self::$noCompanyColor . " !important;\n";
        $css .= "}\n";
        
        // Stile per eventi importati da ICS
        $css .= ".event-ics-import {\n";
        $css .= "    background-color: #e9d8fd !important;\n";
        $css .= "    color: #44337a !important;\n";
        $css .= "    border-left: 3px solid " . self::$icsImportColor . " !important;\n";
        $css .= "}\n";
        
        // Indicatore ICS
        $css .= ".from-ics::before {\n";
        $css .= "    content: '📥';\n";
        $css .= "    margin-right: 4px;\n";
        $css .= "    font-size: 10px;\n";
        $css .= "}\n";
        
        // Indicatore calendario personale
        $css .= ".personal-calendar::before {\n";
        $css .= "    content: '👤';\n";
        $css .= "    margin-right: 4px;\n";
        $css .= "    font-size: 10px;\n";
        $css .= "}\n";
        
        // Stili per ogni azienda mappata
        foreach (self::$colorMapping as $key => $color) {
            if (strpos($key, 'company_') === 0) {
                $companyId = str_replace('company_', '', $key);
                $css .= ".event-company-{$companyId} {\n";
                $css .= "    background-color: " . self::hexToRgba($color, 0.15) . " !important;\n";
                $css .= "    color: " . self::darkenColor($color, 40) . " !important;\n";
                $css .= "    border-left: 3px solid {$color} !important;\n";
                $css .= "}\n";
            }
        }
        
        // Stili per giorni con eventi multipli
        $css .= ".calendar-day.has-multiple-calendars {\n";
        $css .= "    background: linear-gradient(135deg, #f0f9ff 0%, #fef3c7 50%, #f0fdf4 100%) !important;\n";
        $css .= "}\n";
        
        $css .= "</style>\n";
        
        return $css;
    }
    
    /**
     * Converte colore HEX in RGBA
     */
    private static function hexToRgba($hex, $alpha = 1) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba($r, $g, $b, $alpha)";
    }
    
    /**
     * Scurisce un colore HEX
     */
    private static function darkenColor($hex, $percent) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r - ($r * $percent / 100)));
        $g = max(0, min(255, $g - ($g * $percent / 100)));
        $b = max(0, min(255, $b - ($b * $percent / 100)));
        
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
    
    /**
     * Verifica se un giorno ha eventi da calendari diversi
     */
    public static function hasMultipleCalendars($dayEvents) {
        if (count($dayEvents) < 2) {
            return false;
        }
        
        $calendars = [];
        foreach ($dayEvents as $evento) {
            $calendarKey = $evento['azienda_id'] ?? 'personal';
            $calendars[$calendarKey] = true;
        }
        
        return count($calendars) > 1;
    }
    
    /**
     * Ottiene classe CSS per giorni con calendari multipli
     */
    public static function getMultiCalendarClass($dayEvents) {
        if (self::hasMultipleCalendars($dayEvents)) {
            return 'has-multiple-calendars';
        }
        return '';
    }
}
?>