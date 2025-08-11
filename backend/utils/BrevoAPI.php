<?php
/**
 * BrevoAPI - Invio email tramite API HTTP di Brevo (Sendinblue)
 * 
 * Questo metodo consente di avere più controllo sul tracking dei link
 */

class BrevoAPI {
    private static $instance = null;
    private $apiKey = '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';
    private $apiUrl = 'https://api.brevo.com/v3/smtp/email';
    
    private function __construct() {
        // Costruttore privato per singleton
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Invia email tramite API HTTP di Brevo con tracking disabilitato
     */
    public function send($to, $subject, $body, $isHtml = true) {
        try {
            error_log('BrevoAPI::send() chiamato');
            error_log('To: ' . (is_array($to) ? implode(',', $to) : $to));
            error_log('Subject: ' . $subject);
            
            // Prepara i destinatari
            $recipients = is_array($to) ? $to : [$to];
            $toArray = [];
            foreach ($recipients as $recipient) {
                $toArray[] = ['email' => $recipient];
            }
            
            // Prepara il payload per l'API
            $payload = [
                'sender' => [
                    'name' => 'Nexio Platform',
                    'email' => 'info@nexiosolution.it'
                ],
                'to' => $toArray,
                'subject' => $subject,
                'htmlContent' => $body,
                // IMPORTANTE: Disabilita il tracking dei link e delle aperture
                'params' => [
                    'TRACKING' => [
                        'OPENS' => false,
                        'CLICKS' => false
                    ]
                ],
                'headers' => [
                    'X-Mailin-track-links' => '0',
                    'X-Mailin-track-opens' => '0'
                ]
            ];
            
            // Se non è HTML, usa textContent invece
            if (!$isHtml) {
                $payload['textContent'] = $body;
                unset($payload['htmlContent']);
            }
            
            // Invia la richiesta all'API
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . $this->apiKey
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception('CURL Error: ' . $error);
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                error_log('BrevoAPI: Email inviata con successo a ' . implode(',', $recipients));
                error_log('BrevoAPI Response: ' . $response);
                $this->saveToDatabase($to, $subject, $body, 'sent');
                return true;
            } else {
                $errorMsg = 'HTTP Error ' . $httpCode . ': ' . $response;
                error_log('BrevoAPI Error: ' . $errorMsg);
                $this->saveToDatabase($to, $subject, $body, 'failed', $errorMsg);
                return false;
            }
            
        } catch (Exception $e) {
            error_log('BrevoAPI Exception: ' . $e->getMessage());
            $this->saveToDatabase($to, $subject, $body, 'failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salva nel database (opzionale)
     */
    private function saveToDatabase($to, $subject, $body, $status, $error = null) {
        try {
            // Controlla se siamo in una transazione
            if (!db_connection()->inTransaction()) {
                // Verifica se la tabella notifiche_email esiste
                $tableExists = db_query("
                    SELECT COUNT(*) as count 
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = 'notifiche_email'
                ")->fetch();
                
                if ($tableExists['count'] > 0) {
                    // Inserisci il record
                    db_insert('notifiche_email', [
                        'destinatario_email' => is_array($to) ? implode(',', $to) : $to,
                        'oggetto' => $subject,
                        'contenuto' => $body,
                        'stato' => $status === 'sent' ? 'sent' : 'failed',
                        'inviato_il' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
                        'ultimo_errore' => $error
                    ]);
                }
            }
        } catch (Exception $e) {
            // Non bloccare l'invio email per errori di database
            error_log('BrevoAPI: Errore salvataggio database - ' . $e->getMessage());
        }
    }
}
?>