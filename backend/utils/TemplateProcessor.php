<?php
class TemplateProcessor {
    
    /**
     * Mappa degli elementi disponibili con i loro placeholder
     */
    private static $elementMap = [
        // Elementi base
        'logo_azienda' => '{logo_azienda}',
        'info_azienda' => '{info_azienda}',
        'titolo_documento' => '{titolo_documento}',
        'codice_documento' => '{codice_documento}',
        'versione_documento' => '{versione_documento}',
        'numero_pagina' => '{numero_pagina}',
        'totale_pagine' => '{totale_pagine}',
        'data_stampa' => '{data_stampa}',
        'copyright' => '{copyright}',
        'contatti' => '{contatti}',
        
        // Placeholder supportati legacy (per compatibilità)
        'documento_titolo' => '{documento_titolo}',
        'documento_codice' => '{documento_codice}',
        'azienda_nome' => '{azienda_nome}',
        'azienda_logo' => '{azienda_logo}',
        'data_corrente' => '{data_corrente}',
    ];
    
    /**
     * Processa i placeholder in un template
     */
    public static function processTemplate($template, $data) {
        if (empty($template)) {
            return '';
        }
        
        // Prepara i dati azienda
        $azienda = $data['azienda'] ?? null;
        $documento = $data['documento'] ?? null;
        
        // Definisci i replacement per ogni elemento
        $replacements = [
            // Logo Azienda
            '{logo_azienda}' => self::getAziendaLogo($azienda),
            '{azienda_logo}' => self::getAziendaLogo($azienda), // legacy
            
            // Info Azienda (nome, indirizzo, telefono, email in blocco)
            '{info_azienda}' => self::getInfoAzienda($azienda),
            '{azienda_nome}' => htmlspecialchars($azienda['nome'] ?? ''), // legacy
            
            // Titolo e Codice Documento
            '{titolo_documento}' => htmlspecialchars($documento['titolo'] ?? ''),
            '{documento_titolo}' => htmlspecialchars($documento['titolo'] ?? ''), // legacy
            '{codice_documento}' => htmlspecialchars($documento['codice'] ?? ''),
            '{documento_codice}' => htmlspecialchars($documento['codice'] ?? ''), // legacy
            
            // Versione Documento
            '{versione_documento}' => htmlspecialchars($documento['versione_corrente'] ?? '1'),
            
            // Numero Pagina e Totale
            '{numero_pagina}' => '<span class="page-number"></span>',
            '{totale_pagine}' => '<span class="total-pages"></span>',
            
            // Data Stampa
            '{data_stampa}' => date('d/m/Y H:i'),
            '{data_corrente}' => date('d/m/Y'), // legacy
            
            // Copyright
            '{copyright}' => self::getCopyright($azienda),
            
            // Contatti
            '{contatti}' => self::getContatti($azienda),
        ];
        
        // Sostituisci i placeholder
        $processed = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        // Rimuovi placeholder non utilizzati
        $processed = preg_replace('/\{[a-z_]+\}/', '', $processed);
        
        return $processed;
    }
    
    /**
     * Ottiene il logo dell'azienda
     */
    private static function getAziendaLogo($azienda) {
        if (empty($azienda) || empty($azienda['logo'])) {
            return '<div class="logo-placeholder" style="width: 150px; height: 60px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px;">Logo</div>';
        }
        
        $logoPath = 'uploads/loghi/' . $azienda['logo'];
        if (file_exists($logoPath)) {
            return '<img src="' . $logoPath . '" alt="Logo" style="max-height: 60px; max-width: 150px;">';
        }
        
        return '<div class="logo-placeholder" style="width: 150px; height: 60px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px;">Logo</div>';
    }
    
    /**
     * Ottiene le informazioni complete dell'azienda
     */
    private static function getInfoAzienda($azienda) {
        if (empty($azienda)) {
            return '';
        }
        
        $html = '<div class="info-azienda" style="font-size: 12px; line-height: 1.5;">';
        
        if (!empty($azienda['nome'])) {
            $html .= '<strong>' . htmlspecialchars($azienda['nome']) . '</strong><br>';
        }
        
        if (!empty($azienda['indirizzo'])) {
            $html .= htmlspecialchars($azienda['indirizzo']) . '<br>';
        }
        
        $contacts = [];
        if (!empty($azienda['telefono'])) {
            $contacts[] = 'Tel: ' . htmlspecialchars($azienda['telefono']);
        }
        if (!empty($azienda['email'])) {
            $contacts[] = 'Email: ' . htmlspecialchars($azienda['email']);
        }
        if (!empty($contacts)) {
            $html .= implode(' - ', $contacts) . '<br>';
        }
        
        if (!empty($azienda['partita_iva'])) {
            $html .= 'P.IVA: ' . htmlspecialchars($azienda['partita_iva']);
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Ottiene il copyright
     */
    private static function getCopyright($azienda) {
        $year = date('Y');
        $company = !empty($azienda['nome']) ? htmlspecialchars($azienda['nome']) : 'Azienda';
        return "&copy; $year $company - Tutti i diritti riservati";
    }
    
    /**
     * Ottiene i contatti formattati
     */
    private static function getContatti($azienda) {
        if (empty($azienda)) {
            return '';
        }
        
        $contacts = [];
        
        if (!empty($azienda['telefono'])) {
            $contacts[] = '<i class="fas fa-phone"></i> ' . htmlspecialchars($azienda['telefono']);
        }
        
        if (!empty($azienda['email'])) {
            $contacts[] = '<i class="fas fa-envelope"></i> ' . htmlspecialchars($azienda['email']);
        }
        
        if (!empty($azienda['sito_web'])) {
            $contacts[] = '<i class="fas fa-globe"></i> ' . htmlspecialchars($azienda['sito_web']);
        }
        
        return implode(' | ', $contacts);
    }
    
    /**
     * Genera l'header del documento
     */
    public static function generateDocumentHeader($template, $data) {
        if (empty($template)) {
            // Header di default
            $template = '
            <table style="width: 100%; border-bottom: 2px solid #333;">
                <tr>
                    <td style="width: 30%; text-align: left;">{logo_azienda}</td>
                    <td style="width: 40%; text-align: center;">{info_azienda}</td>
                    <td style="width: 30%; text-align: right;">
                        <div style="font-size: 12px;">
                            <strong>{titolo_documento}</strong><br>
                            Codice: {codice_documento}<br>
                            Versione: {versione_documento}
                        </div>
                    </td>
                </tr>
            </table>';
        }
        
        return self::processTemplate($template, $data);
    }
    
    /**
     * Genera il footer del documento
     */
    public static function generateDocumentFooter($template, $data) {
        if (empty($template)) {
            // Footer di default
            $template = '
            <table style="width: 100%; border-top: 1px solid #333; margin-top: 20px;">
                <tr>
                    <td style="width: 33%; text-align: left; font-size: 10px;">
                        {copyright}
                    </td>
                    <td style="width: 34%; text-align: center; font-size: 10px;">
                        {contatti}
                    </td>
                    <td style="width: 33%; text-align: right; font-size: 10px;">
                        Pagina {numero_pagina} di {totale_pagine}
                    </td>
                </tr>
            </table>';
        }
        
        return self::processTemplate($template, $data);
    }
    
    /**
     * Ottiene la lista degli elementi disponibili
     */
    public static function getAvailableElements() {
        return [
            'text' => ['label' => 'Testo', 'icon' => 'fas fa-font'],
            'logo_azienda' => ['label' => 'Logo Azienda', 'icon' => 'fas fa-building', 'placeholder' => '{logo_azienda}'],
            'info_azienda' => ['label' => 'Info Azienda', 'icon' => 'fas fa-info-circle', 'placeholder' => '{info_azienda}'],
            'titolo_documento' => ['label' => 'Titolo Documento', 'icon' => 'fas fa-heading', 'placeholder' => '{titolo_documento}'],
            'codice_documento' => ['label' => 'Codice Documento', 'icon' => 'fas fa-barcode', 'placeholder' => '{codice_documento}'],
            'versione_documento' => ['label' => 'Versione Documento', 'icon' => 'fas fa-code-branch', 'placeholder' => '{versione_documento}'],
            'numero_pagina' => ['label' => 'Numero Pagina', 'icon' => 'fas fa-hashtag', 'placeholder' => '{numero_pagina}'],
            'data_stampa' => ['label' => 'Data Stampa', 'icon' => 'fas fa-calendar', 'placeholder' => '{data_stampa}'],
            'copyright' => ['label' => 'Copyright', 'icon' => 'fas fa-copyright', 'placeholder' => '{copyright}'],
            'contatti' => ['label' => 'Contatti', 'icon' => 'fas fa-phone', 'placeholder' => '{contatti}'],
            'line' => ['label' => 'Linea', 'icon' => 'fas fa-minus']
        ];
    }
} 