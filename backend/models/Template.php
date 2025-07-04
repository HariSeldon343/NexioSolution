<?php
/**
 * Modello Template per gestione intestazioni e piè di pagina
 */
class Template {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Crea un nuovo template
     */
    public function create($data) {
        $sql = "INSERT INTO templates (
            nome, 
            descrizione,
            azienda_id,
            tipo_template,
            intestazione_config,
            pie_pagina_config,
            stili_css,
            attivo,
            creato_da,
            data_creazione
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['nome'],
            $data['descrizione'],
            $data['azienda_id'],
            $data['tipo_template'] ?? 'globale',
            json_encode($data['intestazione_config']),
            json_encode($data['pie_pagina_config']),
            $data['stili_css'],
            $data['attivo'] ?? 1,
            $data['creato_da']
        ]);
    }
    
    /**
     * Ottiene template per azienda
     */
    public function getByAzienda($azienda_id) {
        $sql = "SELECT * FROM templates WHERE azienda_id = ? AND attivo = 1 ORDER BY nome";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$azienda_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Aggiorna un elemento specifico di un template
     */
    public function updateElement($template_id, $section, $column_index, $row_index, $element_data) {
        try {
            $template = $this->getById($template_id);
            if (!$template) {
                throw new Exception("Template non trovato");
            }
            
            // Decodifica la configurazione esistente
            $config = json_decode($template[$section . '_config'], true);
            if (!$config) {
                $config = ['columns' => []];
            }
            
            // Assicurati che la struttura esista
            if (!isset($config['columns'][$column_index])) {
                $config['columns'][$column_index] = ['rows' => []];
            }
            
            if (!isset($config['columns'][$column_index]['rows'][$row_index])) {
                $config['columns'][$column_index]['rows'][$row_index] = [];
            }
            
            // Aggiorna l'elemento
            $config['columns'][$column_index]['rows'][$row_index] = array_merge(
                $config['columns'][$column_index]['rows'][$row_index],
                $element_data
            );
            
            // Salva la configurazione aggiornata
            $sql = "UPDATE templates SET {$section}_config = ?, ultima_modifica = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                json_encode($config),
                $template_id
            ]);
            
        } catch (Exception $e) {
            error_log("Errore aggiornamento elemento template: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rimuove un elemento specifico da un template
     */
    public function removeElement($template_id, $section, $column_index, $row_index) {
        try {
            $template = $this->getById($template_id);
            if (!$template) {
                throw new Exception("Template non trovato");
            }
            
            $config = json_decode($template[$section . '_config'], true);
            if (!$config || !isset($config['columns'][$column_index]['rows'][$row_index])) {
                return true; // Elemento già non presente
            }
            
            // Rimuovi l'elemento
            unset($config['columns'][$column_index]['rows'][$row_index]);
            
            // Riordina l'array per evitare buchi negli indici
            $config['columns'][$column_index]['rows'] = array_values($config['columns'][$column_index]['rows']);
            
            $sql = "UPDATE templates SET {$section}_config = ?, ultima_modifica = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                json_encode($config),
                $template_id
            ]);
            
        } catch (Exception $e) {
            error_log("Errore rimozione elemento template: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clona un template per un'altra azienda
     */
    public function cloneForAzienda($template_id, $new_azienda_id, $created_by) {
        try {
            $original = $this->getById($template_id);
            if (!$original) {
                throw new Exception("Template originale non trovato");
            }
            
            $data = [
                'nome' => $original['nome'] . ' (Copia)',
                'descrizione' => $original['descrizione'],
                'azienda_id' => $new_azienda_id,
                'intestazione_config' => json_decode($original['intestazione_config'], true),
                'pie_pagina_config' => json_decode($original['pie_pagina_config'], true),
                'stili_css' => $original['stili_css'],
                'attivo' => 1,
                'creato_da' => $created_by
            ];
            
            return $this->create($data);
            
        } catch (Exception $e) {
            error_log("Errore clonazione template: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottiene template disponibili per multiple aziende
     */
    public function getAvailableForAziende($aziende_ids) {
        if (empty($aziende_ids)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($aziende_ids) - 1) . '?';
        $sql = "SELECT t.*, a.nome as azienda_nome 
                FROM templates t 
                LEFT JOIN aziende a ON t.azienda_id = a.id 
                WHERE (t.azienda_id IN ($placeholders) OR t.azienda_id IS NULL) 
                AND t.attivo = 1 
                ORDER BY a.nome, t.nome";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($aziende_ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Ottiene template per ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM templates WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $result['intestazione_config'] = json_decode($result['intestazione_config'], true);
            $result['pie_pagina_config'] = json_decode($result['pie_pagina_config'], true);
        }
        
        return $result;
    }
    
    /**
     * Genera HTML per intestazione
     */
    public function generateHeader($template_id, $document_data = []) {
        $template = $this->getById($template_id);
        if (!$template) return '';
        
        $config = $template['intestazione_config'];
        return $this->generateSection($config, $document_data, 'header');
    }
    
    /**
     * Genera HTML per piè di pagina
     */
    public function generateFooter($template_id, $document_data = []) {
        $template = $this->getById($template_id);
        if (!$template) return '';
        
        $config = $template['pie_pagina_config'];
        return $this->generateSection($config, $document_data, 'footer');
    }
    
    /**
     * Genera HTML per sezione (header/footer)
     */
    private function generateSection($config, $document_data, $type) {
        if (!$config || !isset($config['columns'])) return '';
        
        $html = "<div class='template-{$type}'>";
        $html .= "<table class='template-table' style='width: 100%; border-collapse: collapse;'>";
        
        // Calcola larghezza colonne
        $columnCount = count($config['columns']);
        $columnWidth = 100 / $columnCount;
        
        // Trova il numero massimo di righe
        $maxRows = 0;
        foreach ($config['columns'] as $column) {
            $maxRows = max($maxRows, count($column['rows'] ?? []));
        }
        
        // Genera righe
        for ($row = 0; $row < $maxRows; $row++) {
            $html .= "<tr>";
            
            foreach ($config['columns'] as $colIndex => $column) {
                $cellData = $column['rows'][$row] ?? ['type' => 'empty', 'content' => ''];
                $cellContent = $this->generateCellContent($cellData, $document_data);
                
                $html .= "<td style='width: {$columnWidth}%; vertical-align: top; padding: 5px;'>";
                $html .= $cellContent;
                $html .= "</td>";
            }
            
            $html .= "</tr>";
        }
        
        $html .= "</table>";
        $html .= "</div>";
        
        return $html;
    }
    
    /**
     * Genera contenuto per cella
     */
    private function generateCellContent($cellData, $document_data) {
        if (!isset($cellData['type'])) return '';
        
        switch ($cellData['type']) {
            case 'logo':
                return $this->generateLogo($cellData, $document_data);
                
            case 'titolo_documento':
                return "<h2 style='margin: 0; font-size: 16px; font-weight: bold;'>" . 
                       ($document_data['titolo'] ?? 'Documento') . "</h2>";
                       
            case 'codice_documento':
                return "<div style='font-family: monospace; font-weight: bold; font-size: 14px;'>" . 
                       ($document_data['codice'] ?? 'DOC-001') . "</div>";
                       
            case 'copyright':
                return "<div style='font-size: 11px; color: #666;'>" . 
                       ($cellData['content'] ?? $this->getCompanyCopyright($document_data)) . "</div>";
                       
            case 'data_revisione':
                return "<div style='font-size: 12px;'>Rev: " . 
                       ($document_data['data_revisione'] ?? date('d/m/Y')) . "</div>";
                       
            case 'numero_versione':
                return "<div style='font-size: 12px;'>v" . 
                       ($document_data['versione'] ?? '1.0') . "</div>";
                       
            case 'numero_pagine':
                return "<div style='font-size: 12px;'>Pag. <span class='page-current'>1</span> di <span class='page-total'>1</span></div>";
                
            case 'testo_libero':
                return "<div style='font-size: 13px;'>" . ($cellData['content'] ?? '') . "</div>";
                
            case 'data_corrente':
                return "<div style='font-size: 12px;'>" . date('d/m/Y H:i') . "</div>";
                
            case 'autore_documento':
                return "<div style='font-size: 12px;'>Autore: " . 
                       ($document_data['autore_nome'] ?? 'N/A') . "</div>";
                       
            case 'azienda_nome':
                return "<div style='font-size: 14px; font-weight: bold;'>" . 
                       ($document_data['azienda_nome'] ?? 'Nome Azienda') . "</div>";
                       
            case 'azienda_indirizzo':
                return "<div style='font-size: 11px; color: #666;'>" . 
                       ($document_data['azienda_indirizzo'] ?? '') . "</div>";
                       
            case 'azienda_contatti':
                return "<div style='font-size: 11px; color: #666;'>" . 
                       $this->getCompanyContacts($document_data) . "</div>";
                       
            case 'stato_documento':
                return "<div style='font-size: 12px; padding: 2px 6px; border-radius: 3px; background: #e3f2fd; color: #1976d2;'>" . 
                       ($document_data['stato'] ?? 'Bozza') . "</div>";
                       
            case 'data_creazione':
                return "<div style='font-size: 12px;'>Creato: " . 
                       ($document_data['data_creazione'] ?? date('d/m/Y')) . "</div>";
                       
            case 'ultima_modifica':
                return "<div style='font-size: 12px;'>Modificato: " . 
                       ($document_data['ultima_modifica'] ?? date('d/m/Y H:i')) . "</div>";
                
            case 'empty':
            default:
                return "&nbsp;";
        }
    }
    
    /**
     * Ottiene copyright aziendale dal database
     */
    private function getCompanyCopyright($document_data) {
        if (isset($document_data['azienda_id'])) {
            try {
                $stmt = $this->db->prepare("SELECT nome FROM aziende WHERE id = ?");
                $stmt->execute([$document_data['azienda_id']]);
                $azienda = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($azienda) {
                    return "© " . date('Y') . " " . $azienda['nome'] . ". Tutti i diritti riservati.";
                }
            } catch (Exception $e) {
                // Fallback silenzioso
            }
        }
        return "© " . date('Y') . " Azienda. Tutti i diritti riservati.";
    }
    
    /**
     * Ottiene contatti aziendali dal database
     */
    private function getCompanyContacts($document_data) {
        if (isset($document_data['azienda_id'])) {
            try {
                $stmt = $this->db->prepare("SELECT email, telefono, sito_web FROM aziende WHERE id = ?");
                $stmt->execute([$document_data['azienda_id']]);
                $azienda = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($azienda) {
                    $contacts = [];
                    if ($azienda['email']) $contacts[] = $azienda['email'];
                    if ($azienda['telefono']) $contacts[] = "Tel: " . $azienda['telefono'];
                    if ($azienda['sito_web']) $contacts[] = $azienda['sito_web'];
                    return implode(' • ', $contacts);
                }
            } catch (Exception $e) {
                // Fallback silenzioso
            }
        }
        return "";
    }
    
    /**
     * Genera logo aziendale
     */
    private function generateLogo($cellData, $document_data) {
        $logoUrl = $cellData['logo_url'] ?? $document_data['logo_azienda'] ?? '';
        if (!$logoUrl) return '';
        
        $maxHeight = $cellData['max_height'] ?? '50px';
        return "<img src='{$logoUrl}' style='max-height: {$maxHeight}; max-width: 100%;' alt='Logo' />";
    }
    
    /**
     * Genera CSS per template
     */
    public function generateCSS($template_id) {
        $template = $this->getById($template_id);
        if (!$template) return '';
        
        $css = "
        .template-header, .template-footer {
            width: 100%;
            font-family: Arial, sans-serif;
        }
        
        .template-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .template-table td {
            vertical-align: top;
            padding: 5px;
        }
        
        .template-header {
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        
        .template-footer {
            border-top: 1px solid #ddd;
            margin-top: 20px;
            padding-top: 10px;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
        }
        
        @media print {
            .template-header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: white;
                z-index: 1000;
            }
            
            .template-footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                z-index: 1000;
            }
        }
        ";
        
        // Aggiungi CSS personalizzato dal template
        if (!empty($template['stili_css'])) {
            $css .= "\n" . $template['stili_css'];
        }
        
        return $css;
    }
    
    /**
     * Lista tutti i template
     */
    public function getAll($filters = []) {
        $sql = "SELECT t.*, a.nome as azienda_nome 
                FROM templates t 
                LEFT JOIN aziende a ON t.azienda_id = a.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['azienda_id'])) {
            $sql .= " AND t.azienda_id = ?";
            $params[] = $filters['azienda_id'];
        }
        
        if (!empty($filters['attivo'])) {
            $sql .= " AND t.attivo = ?";
            $params[] = $filters['attivo'];
        }
        
        $sql .= " ORDER BY t.nome";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Aggiorna template
     */
    public function update($id, $data) {
        $sql = "UPDATE templates SET 
                nome = ?, 
                descrizione = ?,
                azienda_id = ?,
                tipo_template = ?,
                intestazione_config = ?,
                pie_pagina_config = ?,
                stili_css = ?,
                attivo = ?,
                ultima_modifica = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['nome'],
            $data['descrizione'],
            $data['azienda_id'],
            $data['tipo_template'] ?? 'globale',
            json_encode($data['intestazione_config']),
            json_encode($data['pie_pagina_config']),
            $data['stili_css'],
            $data['attivo'] ?? 1,
            $id
        ]);
    }
    
    /**
     * Elimina template
     */
    public function delete($id) {
        $sql = "DELETE FROM templates WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Attiva un template
     */
    public function activate($id) {
        $sql = "UPDATE templates SET attivo = 1 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Disattiva un template
     */
    public function deactivate($id) {
        $sql = "UPDATE templates SET attivo = 0 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Cambia stato attivo/inattivo di un template
     */
    public function toggleStatus($id) {
        $sql = "UPDATE templates SET attivo = 1 - attivo WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
}
?>