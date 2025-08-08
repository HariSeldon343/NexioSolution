<?php
/**
 * Struttura predefinita cartelle per conformità normativa
 */

function getComplianceFoldersStructure() {
    return [
        'SISTEMA_GESTIONE_CONFORMITA' => [
            'icon' => 'fas fa-shield-alt',
            'color' => '#8b5cf6',
            'description' => 'Sistema di Gestione Conformità',
            'children' => [
                'ISO_9001_QUALITA' => [
                    'icon' => 'fas fa-certificate',
                    'color' => '#3b82f6',
                    'description' => 'Sistema Gestione Qualità ISO 9001',
                    'children' => [
                        'Manuale_Qualita' => ['icon' => 'fas fa-book', 'color' => '#3b82f6'],
                        'Politiche' => ['icon' => 'fas fa-gavel', 'color' => '#3b82f6'],
                        'Procedure' => ['icon' => 'fas fa-tasks', 'color' => '#3b82f6'],
                        'Moduli_Registrazioni' => ['icon' => 'fas fa-clipboard-list', 'color' => '#3b82f6'],
                        'Audit' => ['icon' => 'fas fa-search', 'color' => '#3b82f6'],
                        'Non_Conformita' => ['icon' => 'fas fa-exclamation-triangle', 'color' => '#ef4444'],
                        'Azioni_Miglioramento' => ['icon' => 'fas fa-chart-line', 'color' => '#10b981'],
                        'Riesame_Direzione' => ['icon' => 'fas fa-users', 'color' => '#3b82f6'],
                        'Formazione' => ['icon' => 'fas fa-graduation-cap', 'color' => '#3b82f6'],
                        'Gestione_Fornitori' => ['icon' => 'fas fa-truck', 'color' => '#3b82f6'],
                        'Indicatori_KPI' => ['icon' => 'fas fa-chart-bar', 'color' => '#3b82f6']
                    ]
                ],
                'ISO_14001_AMBIENTE' => [
                    'icon' => 'fas fa-leaf',
                    'color' => '#10b981',
                    'description' => 'Sistema Gestione Ambientale ISO 14001',
                    'children' => [
                        'Analisi_Ambientale' => ['icon' => 'fas fa-microscope', 'color' => '#10b981'],
                        'Aspetti_Impatti' => ['icon' => 'fas fa-project-diagram', 'color' => '#10b981'],
                        'Obiettivi_Traguardi' => ['icon' => 'fas fa-bullseye', 'color' => '#10b981'],
                        'Procedure_Ambientali' => ['icon' => 'fas fa-tasks', 'color' => '#10b981'],
                        'Registri_Ambientali' => ['icon' => 'fas fa-clipboard-list', 'color' => '#10b981'],
                        'Emergenze_Ambientali' => ['icon' => 'fas fa-exclamation-circle', 'color' => '#ef4444'],
                        'Audit_Ambientali' => ['icon' => 'fas fa-search', 'color' => '#10b981'],
                        'Comunicazioni' => ['icon' => 'fas fa-comments', 'color' => '#10b981'],
                        'Conformita_Legale' => ['icon' => 'fas fa-balance-scale', 'color' => '#10b981']
                    ]
                ],
                'ISO_45001_SICUREZZA' => [
                    'icon' => 'fas fa-hard-hat',
                    'color' => '#f59e0b',
                    'description' => 'Sistema Gestione Sicurezza ISO 45001',
                    'children' => [
                        'DVR_Valutazione_Rischi' => ['icon' => 'fas fa-file-medical-alt', 'color' => '#f59e0b'],
                        'Procedure_Sicurezza' => ['icon' => 'fas fa-shield-alt', 'color' => '#f59e0b'],
                        'DPI_Dispositivi' => ['icon' => 'fas fa-vest', 'color' => '#f59e0b'],
                        'Formazione_Sicurezza' => ['icon' => 'fas fa-user-graduate', 'color' => '#f59e0b'],
                        'Sorveglianza_Sanitaria' => ['icon' => 'fas fa-heartbeat', 'color' => '#f59e0b'],
                        'Incidenti_Infortuni' => ['icon' => 'fas fa-ambulance', 'color' => '#ef4444'],
                        'Emergenze' => ['icon' => 'fas fa-fire-extinguisher', 'color' => '#ef4444'],
                        'DUVRI' => ['icon' => 'fas fa-handshake', 'color' => '#f59e0b'],
                        'Verbali_Riunioni' => ['icon' => 'fas fa-file-signature', 'color' => '#f59e0b']
                    ]
                ],
                'ISO_27001_SICUREZZA_INFO' => [
                    'icon' => 'fas fa-lock',
                    'color' => '#dc2626',
                    'description' => 'Sistema Gestione Sicurezza Informazioni ISO 27001',
                    'children' => [
                        'Politiche_Sicurezza_IT' => ['icon' => 'fas fa-file-shield', 'color' => '#dc2626'],
                        'Risk_Assessment' => ['icon' => 'fas fa-exclamation-triangle', 'color' => '#dc2626'],
                        'Controlli_Sicurezza' => ['icon' => 'fas fa-user-lock', 'color' => '#dc2626'],
                        'Incident_Management' => ['icon' => 'fas fa-bug', 'color' => '#dc2626'],
                        'Business_Continuity' => ['icon' => 'fas fa-sync-alt', 'color' => '#dc2626'],
                        'Asset_Management' => ['icon' => 'fas fa-server', 'color' => '#dc2626'],
                        'Access_Control' => ['icon' => 'fas fa-key', 'color' => '#dc2626'],
                        'Audit_IT' => ['icon' => 'fas fa-search', 'color' => '#dc2626']
                    ]
                ],
                'GDPR_PRIVACY' => [
                    'icon' => 'fas fa-user-shield',
                    'color' => '#7c3aed',
                    'description' => 'Gestione Privacy GDPR',
                    'children' => [
                        'Informative' => ['icon' => 'fas fa-info-circle', 'color' => '#7c3aed'],
                        'Consensi' => ['icon' => 'fas fa-check-square', 'color' => '#7c3aed'],
                        'Registro_Trattamenti' => ['icon' => 'fas fa-clipboard-list', 'color' => '#7c3aed'],
                        'DPIA' => ['icon' => 'fas fa-shield-alt', 'color' => '#7c3aed'],
                        'Data_Breach' => ['icon' => 'fas fa-exclamation-triangle', 'color' => '#ef4444'],
                        'Nomine' => ['icon' => 'fas fa-id-badge', 'color' => '#7c3aed'],
                        'Formazione_Privacy' => ['icon' => 'fas fa-user-graduate', 'color' => '#7c3aed']
                    ]
                ],
                'DOCUMENTI_TRASVERSALI' => [
                    'icon' => 'fas fa-exchange-alt',
                    'color' => '#6b7280',
                    'description' => 'Documenti Trasversali',
                    'children' => [
                        'Organigramma' => ['icon' => 'fas fa-sitemap', 'color' => '#6b7280'],
                        'Mansionari' => ['icon' => 'fas fa-id-card', 'color' => '#6b7280'],
                        'Contesto_Organizzazione' => ['icon' => 'fas fa-building', 'color' => '#6b7280'],
                        'Stakeholder' => ['icon' => 'fas fa-users', 'color' => '#6b7280'],
                        'Comunicazioni_Generali' => ['icon' => 'fas fa-envelope', 'color' => '#6b7280']
                    ]
                ],
                'SISTEMA_INTEGRATO' => [
                    'icon' => 'fas fa-puzzle-piece',
                    'color' => '#06b6d4',
                    'description' => 'Sistema Integrato',
                    'children' => [
                        'Procedure_Comuni' => ['icon' => 'fas fa-tasks', 'color' => '#06b6d4'],
                        'Audit_Integrati' => ['icon' => 'fas fa-search-plus', 'color' => '#06b6d4'],
                        'Riesame_Integrato' => ['icon' => 'fas fa-sync', 'color' => '#06b6d4'],
                        'Obiettivi_Integrati' => ['icon' => 'fas fa-bullseye', 'color' => '#06b6d4']
                    ]
                ],
                'ARCHIVIO_GENERALE' => [
                    'icon' => 'fas fa-archive',
                    'color' => '#9ca3af',
                    'description' => 'Archivio Generale',
                    'children' => [
                        'Documenti_Obsoleti' => ['icon' => 'fas fa-history', 'color' => '#9ca3af'],
                        'Storico_Certificati' => ['icon' => 'fas fa-award', 'color' => '#9ca3af'],
                        'Versioni_Precedenti' => ['icon' => 'fas fa-code-branch', 'color' => '#9ca3af']
                    ]
                ]
            ]
        ]
    ];
}

/**
 * Crea la struttura delle cartelle per un'azienda
 */
function createComplianceFoldersForCompany($azienda_id, $parent_id = null, $structure = null) {
    if ($structure === null) {
        $structure = getComplianceFoldersStructure();
    }
    
    foreach ($structure as $folder_name => $folder_data) {
        // Crea la cartella
        $stmt = db_query(
            "INSERT INTO cartelle (nome, parent_id, azienda_id, colore, icona, descrizione) 
             VALUES (?, ?, ?, ?, ?, ?)", 
            [
                $folder_name,
                $parent_id,
                $azienda_id,
                $folder_data['color'] ?? '#fbbf24',
                $folder_data['icon'] ?? 'fas fa-folder',
                $folder_data['description'] ?? null
            ]
        );
        
        $folder_id = db_connection()->lastInsertId();
        
        // Se ci sono sottocartelle, creale ricorsivamente
        if (isset($folder_data['children']) && is_array($folder_data['children'])) {
            createComplianceFoldersForCompany($azienda_id, $folder_id, $folder_data['children']);
        }
    }
}

/**
 * Verifica se un'azienda ha già la struttura di conformità
 */
function hasComplianceStructure($azienda_id) {
    $stmt = db_query(
        "SELECT COUNT(*) as count FROM cartelle 
         WHERE azienda_id = ? AND nome = 'SISTEMA_GESTIONE_CONFORMITA'", 
        [$azienda_id]
    );
    return $stmt->fetch()['count'] > 0;
}
?>