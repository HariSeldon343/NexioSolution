<?php
/**
 * Template email unificato per tutte le notifiche di sistema
 * 
 * NOTA: Questa classe ora utilizza automaticamente EmailTemplateOutlook
 * per garantire la compatibilità con tutti i client email, incluso Outlook
 */

require_once __DIR__ . '/EmailTemplateOutlook.php';

class EmailTemplate extends EmailTemplateOutlook {
    // Tutti i metodi sono ereditati da EmailTemplateOutlook per garantire
    // la compatibilità con Outlook e tutti i client email
}
?>