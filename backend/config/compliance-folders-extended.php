<?php
/**
 * Strutture estese per schemi di conformità individuali
 */

function getSchemaStructure($schema_key) {
    $structures = [
        'ISO_9001' => [
            'ISO_9001_QUALITA' => [
                'color' => '#3b82f6',
                'description' => 'Sistema Gestione Qualità ISO 9001',
                'children' => [
                    'Manuale_Qualita' => ['color' => '#3b82f6'],
                    'Politiche' => ['color' => '#3b82f6'],
                    'Procedure' => [
                        'color' => '#3b82f6',
                        'children' => [
                            'Controllo_Documenti' => ['color' => '#3b82f6'],
                            'Controllo_Registrazioni' => ['color' => '#3b82f6'],
                            'Audit_Interni' => ['color' => '#3b82f6'],
                            'Non_Conformita' => ['color' => '#ef4444'],
                            'Azioni_Correttive' => ['color' => '#10b981'],
                            'Azioni_Preventive' => ['color' => '#10b981']
                        ]
                    ],
                    'Moduli_Registrazioni' => ['color' => '#3b82f6'],
                    'Audit' => [
                        'color' => '#3b82f6',
                        'children' => [
                            'Programma_Audit' => ['color' => '#3b82f6'],
                            'Rapporti_Audit' => ['color' => '#3b82f6'],
                            'Follow_Up' => ['color' => '#3b82f6']
                        ]
                    ],
                    'Non_Conformita' => ['color' => '#ef4444'],
                    'Azioni_Miglioramento' => ['color' => '#10b981'],
                    'Riesame_Direzione' => ['color' => '#3b82f6'],
                    'Formazione' => [
                        'color' => '#3b82f6',
                        'children' => [
                            'Piano_Formazione' => ['color' => '#3b82f6'],
                            'Registri_Formazione' => ['color' => '#3b82f6'],
                            'Competenze' => ['color' => '#3b82f6']
                        ]
                    ],
                    'Gestione_Fornitori' => ['color' => '#3b82f6'],
                    'Indicatori_KPI' => ['color' => '#3b82f6'],
                    'Customer_Satisfaction' => ['color' => '#3b82f6']
                ]
            ]
        ],
        
        'ISO_14001' => [
            'ISO_14001_AMBIENTE' => [
                'color' => '#10b981',
                'description' => 'Sistema Gestione Ambientale ISO 14001',
                'children' => [
                    'Analisi_Ambientale' => [
                        'color' => '#10b981',
                        'children' => [
                            'Analisi_Iniziale' => ['color' => '#10b981'],
                            'Aggiornamenti' => ['color' => '#10b981']
                        ]
                    ],
                    'Aspetti_Impatti' => [
                        'color' => '#10b981',
                        'children' => [
                            'Registro_Aspetti' => ['color' => '#10b981'],
                            'Valutazione_Significativita' => ['color' => '#10b981']
                        ]
                    ],
                    'Requisiti_Legali' => ['color' => '#10b981'],
                    'Obiettivi_Traguardi' => ['color' => '#10b981'],
                    'Procedure_Ambientali' => ['color' => '#10b981'],
                    'Registri_Ambientali' => [
                        'color' => '#10b981',
                        'children' => [
                            'Rifiuti' => ['color' => '#10b981'],
                            'Emissioni' => ['color' => '#10b981'],
                            'Scarichi' => ['color' => '#10b981'],
                            'Consumi' => ['color' => '#10b981']
                        ]
                    ],
                    'Emergenze_Ambientali' => ['color' => '#ef4444'],
                    'Audit_Ambientali' => ['color' => '#10b981'],
                    'Comunicazioni' => ['color' => '#10b981'],
                    'Monitoraggi_Misurazioni' => ['color' => '#10b981']
                ]
            ]
        ],
        
        'ISO_45001' => [
            'ISO_45001_SICUREZZA' => [
                'color' => '#f59e0b',
                'description' => 'Sistema Gestione Sicurezza ISO 45001',
                'children' => [
                    'DVR_Valutazione_Rischi' => [
                        'color' => '#f59e0b',
                        'children' => [
                            'DVR_Generale' => ['color' => '#f59e0b'],
                            'Valutazioni_Specifiche' => ['color' => '#f59e0b'],
                            'Aggiornamenti_DVR' => ['color' => '#f59e0b']
                        ]
                    ],
                    'Procedure_Sicurezza' => ['color' => '#f59e0b'],
                    'DPI_Dispositivi' => [
                        'color' => '#f59e0b',
                        'children' => [
                            'Consegna_DPI' => ['color' => '#f59e0b'],
                            'Schede_Tecniche' => ['color' => '#f59e0b']
                        ]
                    ],
                    'Formazione_Sicurezza' => [
                        'color' => '#f59e0b',
                        'children' => [
                            'Formazione_Generale' => ['color' => '#f59e0b'],
                            'Formazione_Specifica' => ['color' => '#f59e0b'],
                            'Addestramento' => ['color' => '#f59e0b']
                        ]
                    ],
                    'Sorveglianza_Sanitaria' => ['color' => '#f59e0b'],
                    'Incidenti_Infortuni' => ['color' => '#ef4444'],
                    'Emergenze' => [
                        'color' => '#ef4444',
                        'children' => [
                            'Piano_Emergenza' => ['color' => '#ef4444'],
                            'Prove_Evacuazione' => ['color' => '#ef4444']
                        ]
                    ],
                    'DUVRI' => ['color' => '#f59e0b'],
                    'Verbali_Riunioni' => ['color' => '#f59e0b'],
                    'Manutenzioni' => ['color' => '#f59e0b']
                ]
            ]
        ],
        
        'ISO_27001' => [
            'ISO_27001_SICUREZZA_INFO' => [
                'color' => '#dc2626',
                'description' => 'Sistema Gestione Sicurezza Informazioni ISO 27001',
                'children' => [
                    'Politiche_Sicurezza_IT' => ['color' => '#dc2626'],
                    'Risk_Assessment' => [
                        'color' => '#dc2626',
                        'children' => [
                            'Analisi_Rischi' => ['color' => '#dc2626'],
                            'Trattamento_Rischi' => ['color' => '#dc2626']
                        ]
                    ],
                    'Controlli_Sicurezza' => ['color' => '#dc2626'],
                    'Incident_Management' => ['color' => '#dc2626'],
                    'Business_Continuity' => [
                        'color' => '#dc2626',
                        'children' => [
                            'BCP' => ['color' => '#dc2626'],
                            'DRP' => ['color' => '#dc2626']
                        ]
                    ],
                    'Asset_Management' => ['color' => '#dc2626'],
                    'Access_Control' => ['color' => '#dc2626'],
                    'Audit_IT' => ['color' => '#dc2626'],
                    'Vulnerability_Management' => ['color' => '#dc2626'],
                    'Security_Awareness' => ['color' => '#dc2626']
                ]
            ]
        ],
        
        'GDPR' => [
            'GDPR_PRIVACY' => [
                'color' => '#7c3aed',
                'description' => 'Gestione Privacy GDPR',
                'children' => [
                    'Informative' => [
                        'color' => '#7c3aed',
                        'children' => [
                            'Clienti' => ['color' => '#7c3aed'],
                            'Dipendenti' => ['color' => '#7c3aed'],
                            'Fornitori' => ['color' => '#7c3aed'],
                            'Sito_Web' => ['color' => '#7c3aed']
                        ]
                    ],
                    'Consensi' => ['color' => '#7c3aed'],
                    'Registro_Trattamenti' => ['color' => '#7c3aed'],
                    'DPIA' => ['color' => '#7c3aed'],
                    'Data_Breach' => ['color' => '#ef4444'],
                    'Nomine' => [
                        'color' => '#7c3aed',
                        'children' => [
                            'DPO' => ['color' => '#7c3aed'],
                            'Responsabili' => ['color' => '#7c3aed'],
                            'Incaricati' => ['color' => '#7c3aed']
                        ]
                    ],
                    'Formazione_Privacy' => ['color' => '#7c3aed'],
                    'Diritti_Interessati' => ['color' => '#7c3aed'],
                    'Misure_Sicurezza' => ['color' => '#7c3aed']
                ]
            ]
        ]
    ];
    
    return isset($structures[$schema_key]) ? $structures[$schema_key] : null;
}

/**
 * Crea schema personalizzato dalla struttura definita dall'utente
 */
function createCustomSchema($schema_name, $folder_structure) {
    // Questa funzione verrà utilizzata per processare schemi personalizzati
    // creati attraverso il builder drag & drop
    $custom_structure = [];
    
    // Logica per convertire la struttura dal frontend
    // al formato utilizzato dal sistema
    
    return $custom_structure;
}

/**
 * Ottiene combinazioni predefinite di schemi
 */
function getCombinedSchemas($schema_keys) {
    $combined = [];
    
    // Combinazioni comuni
    $combinations = [
        ['ISO_9001', 'ISO_14001'] => 'Sistema Integrato Qualità e Ambiente',
        ['ISO_9001', 'ISO_45001'] => 'Sistema Integrato Qualità e Sicurezza',
        ['ISO_14001', 'ISO_45001'] => 'Sistema Integrato Ambiente e Sicurezza',
        ['ISO_9001', 'ISO_14001', 'ISO_45001'] => 'Sistema Integrato QSA',
        ['ISO_9001', 'ISO_14001', 'ISO_45001', 'ISO_27001'] => 'Sistema Integrato QSAI'
    ];
    
    sort($schema_keys);
    $key = implode(',', $schema_keys);
    
    foreach ($combinations as $combo => $name) {
        $combo_keys = explode(',', $combo);
        sort($combo_keys);
        if (implode(',', $combo_keys) === $key) {
            return $name;
        }
    }
    
    return 'Sistema Integrato Personalizzato';
}
?>