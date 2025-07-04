<?php
/**
 * ICS Generator - Classe per generare file calendario ICS
 * Supporta esportazione di eventi singoli e calendari completi
 */

class ICSGenerator {
    private $timezone = 'Europe/Rome';
    private $prodId = '-//Nexio Solution//Piattaforma Collaborativa//IT';
    
    /**
     * Genera file ICS per un singolo evento
     */
    public function generateEventICS($evento, $organizer = null) {
        $ics = $this->getICSHeader();
        $ics .= $this->generateVEvent($evento, $organizer);
        $ics .= $this->getICSFooter();
        
        return $ics;
    }
    
    /**
     * Genera file ICS per un array di eventi
     */
    public function generateCalendarICS($eventi, $calendarName = 'Calendario Eventi') {
        $ics = $this->getICSHeader($calendarName);
        
        foreach ($eventi as $evento) {
            $ics .= $this->generateVEvent($evento);
        }
        
        $ics .= $this->getICSFooter();
        
        return $ics;
    }
    
    /**
     * Header del file ICS
     */
    private function getICSHeader($calendarName = null) {
        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:" . $this->prodId . "\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        
        if ($calendarName) {
            $ics .= "X-WR-CALNAME:" . $this->escapeText($calendarName) . "\r\n";
            $ics .= "X-WR-CALDESC:Calendario esportato dalla Piattaforma Collaborativa Nexio\r\n";
        }
        
        $ics .= "X-WR-TIMEZONE:" . $this->timezone . "\r\n";
        
        // Definizione timezone
        $ics .= "BEGIN:VTIMEZONE\r\n";
        $ics .= "TZID:" . $this->timezone . "\r\n";
        $ics .= "BEGIN:STANDARD\r\n";
        $ics .= "DTSTART:20071028T030000\r\n";
        $ics .= "RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\n";
        $ics .= "TZNAME:CET\r\n";
        $ics .= "TZOFFSETFROM:+0200\r\n";
        $ics .= "TZOFFSETTO:+0100\r\n";
        $ics .= "END:STANDARD\r\n";
        $ics .= "BEGIN:DAYLIGHT\r\n";
        $ics .= "DTSTART:20070325T020000\r\n";
        $ics .= "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\n";
        $ics .= "TZNAME:CEST\r\n";
        $ics .= "TZOFFSETFROM:+0100\r\n";
        $ics .= "TZOFFSETTO:+0200\r\n";
        $ics .= "END:DAYLIGHT\r\n";
        $ics .= "END:VTIMEZONE\r\n";
        
        return $ics;
    }
    
    /**
     * Footer del file ICS
     */
    private function getICSFooter() {
        return "END:VCALENDAR\r\n";
    }
    
    /**
     * Genera un singolo evento VEVENT
     */
    private function generateVEvent($evento, $organizer = null) {
        $ics = "BEGIN:VEVENT\r\n";
        
        // UID univoco per l'evento
        $uid = $this->generateUID($evento['id']);
        $ics .= "UID:" . $uid . "\r\n";
        
        // DTSTAMP (quando è stato creato/modificato)
        $dtstamp = $this->formatDateTime($evento['creato_il'] ?? date('Y-m-d H:i:s'));
        $ics .= "DTSTAMP:" . $dtstamp . "\r\n";
        
        // Data/ora inizio
        $dtstart = $this->formatDateTime($evento['data_inizio']);
        $ics .= "DTSTART;TZID=" . $this->timezone . ":" . $dtstart . "\r\n";
        
        // Data/ora fine
        if (!empty($evento['data_fine']) && $evento['data_fine'] !== $evento['data_inizio']) {
            $dtend = $this->formatDateTime($evento['data_fine']);
            $ics .= "DTEND;TZID=" . $this->timezone . ":" . $dtend . "\r\n";
        } else {
            // Se non c'è data fine, imposta durata di 1 ora
            $dtend = $this->formatDateTime(date('Y-m-d H:i:s', strtotime($evento['data_inizio'] . ' +1 hour')));
            $ics .= "DTEND;TZID=" . $this->timezone . ":" . $dtend . "\r\n";
        }
        
        // Titolo dell'evento
        $ics .= "SUMMARY:" . $this->escapeText($evento['titolo']) . "\r\n";
        
        // Descrizione
        if (!empty($evento['descrizione'])) {
            $ics .= "DESCRIPTION:" . $this->escapeText($evento['descrizione']) . "\r\n";
        }
        
        // Luogo
        if (!empty($evento['luogo'])) {
            $ics .= "LOCATION:" . $this->escapeText($evento['luogo']) . "\r\n";
        }
        
        // Organizer
        if ($organizer && !empty($organizer['email'])) {
            $organizerName = $organizer['nome'] . ' ' . $organizer['cognome'];
            $ics .= "ORGANIZER;CN=" . $this->escapeText($organizerName) . ":MAILTO:" . $organizer['email'] . "\r\n";
        }
        
        // Status
        $ics .= "STATUS:CONFIRMED\r\n";
        
        // Sequenza (per aggiornamenti)
        $ics .= "SEQUENCE:0\r\n";
        
        // Priorità basata sul tipo di evento
        $priority = $this->getEventPriority($evento['tipo'] ?? 'meeting');
        $ics .= "PRIORITY:" . $priority . "\r\n";
        
        // Classificazione
        $ics .= "CLASS:PUBLIC\r\n";
        
        // Creato da
        if (!empty($evento['creato_il'])) {
            $created = $this->formatDateTime($evento['creato_il']);
            $ics .= "CREATED:" . $created . "\r\n";
        }
        
        // Ultima modifica
        $lastModified = $this->formatDateTime($evento['aggiornato_il'] ?? $evento['creato_il'] ?? date('Y-m-d H:i:s'));
        $ics .= "LAST-MODIFIED:" . $lastModified . "\r\n";
        
        // URL evento (se disponibile)
        $baseUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it';
        $eventUrl = $baseUrl . '/piattaforma-collaborativa/calendario-eventi.php?action=view&id=' . $evento['id'];
        $ics .= "URL:" . $eventUrl . "\r\n";
        
        // Categoria basata sul tipo
        $categoria = $this->getEventCategory($evento['tipo'] ?? 'meeting');
        $ics .= "CATEGORIES:" . $categoria . "\r\n";
        
        // Alarm/Reminder - 15 minuti prima
        $ics .= "BEGIN:VALARM\r\n";
        $ics .= "TRIGGER:-PT15M\r\n";
        $ics .= "ACTION:DISPLAY\r\n";
        $ics .= "DESCRIPTION:Promemoria: " . $this->escapeText($evento['titolo']) . "\r\n";
        $ics .= "END:VALARM\r\n";
        
        $ics .= "END:VEVENT\r\n";
        
        return $ics;
    }
    
    /**
     * Genera UID univoco per l'evento
     */
    private function generateUID($eventoId) {
        $domain = 'nexiosolution.it';
        return "evento-{$eventoId}-" . date('Ymd-His') . "@{$domain}";
    }
    
    /**
     * Formatta data/ora per ICS
     */
    private function formatDateTime($datetime) {
        if (empty($datetime)) {
            $datetime = date('Y-m-d H:i:s');
        }
        
        try {
            $dt = new DateTime($datetime, new DateTimeZone($this->timezone));
            return $dt->format('Ymd\THis');
        } catch (Exception $e) {
            // Fallback
            return date('Ymd\THis', strtotime($datetime));
        }
    }
    
    /**
     * Escape del testo per formato ICS
     */
    private function escapeText($text) {
        if (empty($text)) {
            return '';
        }
        
        // Rimuovi caratteri non validi
        $text = strip_tags($text);
        
        // Escape caratteri speciali ICS
        $text = str_replace(['\\', ',', ';', "\n", "\r"], ['\\\\', '\\,', '\\;', '\\n', ''], $text);
        
        // Tronca se troppo lungo (limite ICS)
        if (strlen($text) > 75) {
            $text = substr($text, 0, 72) . '...';
        }
        
        return $text;
    }
    
    /**
     * Determina la priorità dell'evento
     */
    private function getEventPriority($tipo) {
        $priorities = [
            'meeting' => 5,      // Normale
            'deadline' => 1,     // Alta
            'reminder' => 7,     // Bassa
            'evento' => 5,       // Normale
            'formazione' => 4,   // Medio-alta
            'altro' => 5         // Normale
        ];
        
        return $priorities[$tipo] ?? 5;
    }
    
    /**
     * Determina la categoria dell'evento
     */
    private function getEventCategory($tipo) {
        $categories = [
            'meeting' => 'MEETING',
            'deadline' => 'WORK',
            'reminder' => 'REMINDER',
            'evento' => 'EVENT',
            'formazione' => 'EDUCATION',
            'altro' => 'MISCELLANEOUS'
        ];
        
        return $categories[$tipo] ?? 'EVENT';
    }
    
    /**
     * Genera e forza il download di un file ICS
     */
    public function downloadICS($content, $filename = 'calendario.ics') {
        // Assicurati che il filename abbia estensione .ics
        if (!str_ends_with($filename, '.ics')) {
            $filename .= '.ics';
        }
        
        // Headers per il download
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Output del contenuto
        echo $content;
        exit;
    }
    
    /**
     * Salva contenuto ICS in un file temporaneo
     */
    public function saveToTempFile($content, $prefix = 'calendar_') {
        $tempDir = sys_get_temp_dir();
        $filename = $prefix . uniqid() . '.ics';
        $filepath = $tempDir . DIRECTORY_SEPARATOR . $filename;
        
        if (file_put_contents($filepath, $content) !== false) {
            return $filepath;
        }
        
        throw new Exception('Impossibile salvare il file ICS temporaneo');
    }
    
    /**
     * Valida e pulisce i dati dell'evento
     */
    public function validateEventData($evento) {
        $required = ['id', 'titolo', 'data_inizio'];
        
        foreach ($required as $field) {
            if (empty($evento[$field])) {
                throw new Exception("Campo obbligatorio mancante: $field");
            }
        }
        
        // Valida formato data
        if (!strtotime($evento['data_inizio'])) {
            throw new Exception("Formato data_inizio non valido");
        }
        
        if (!empty($evento['data_fine']) && !strtotime($evento['data_fine'])) {
            throw new Exception("Formato data_fine non valido");
        }
        
        return true;
    }
}
?>