<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$pageTitle = 'Conformità Normativa';
include 'components/header.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Requisiti Autorizzazione e Accreditamento Sanitario - Regione Calabria</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background-color: #ffffff;
            color: #212529;
            line-height: 1.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #0066cc 0%, #004499 100%);
            color: white;
            padding: 32px;
            border-radius: 4px;
            margin-bottom: 32px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .header p {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 400;
        }

        .form-section {
            background: white;
            border: 1px solid #e9ecef;
            padding: 24px;
            border-radius: 4px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .form-section h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e9ecef;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #495057;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9375rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            background-color: white;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #0066cc;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }

        .btn {
            background: #0066cc;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn:hover {
            background: #0052a3;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }

        .requirements-section {
            background: white;
            border: 1px solid #e9ecef;
            padding: 32px;
            border-radius: 4px;
            margin-top: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .requirements-section h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e9ecef;
        }

        .alert-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            color: #0d47a1;
            padding: 16px 20px;
            border-radius: 4px;
            margin-bottom: 24px;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 16px 20px;
            border-radius: 4px;
            margin-bottom: 24px;
        }

        .requirement-category {
            margin-bottom: 32px;
        }

        .requirement-category h4 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #0066cc;
            margin-bottom: 16px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .requirement-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .requirement-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .requirement-table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .requirement-table tr:last-child td {
            border-bottom: none;
        }

        .requirement-table tr:hover {
            background-color: #f8f9fa;
        }

        .summary {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 20px;
            border-radius: 4px;
            margin-top: 20px;
        }

        .summary h4 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 16px;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .stat-box {
            background: #fff;
            padding: 16px;
            border-radius: 4px;
            text-align: center;
            border: 1px solid #e9ecef;
        }

        .stat-box .number {
            font-size: 1.5rem;
            font-weight: 600;
            color: #0066cc;
        }

        .stat-box .label {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .hidden {
            display: none;
        }

        .requirement-item {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 4px;
            margin-bottom: 12px;
            border: 1px solid #e9ecef;
        }

        .requirement-item h4 {
            color: #212529;
            margin-bottom: 8px;
            font-size: 1rem;
            font-weight: 600;
        }

        .requirement-item p {
            color: #6c757d;
            line-height: 1.6;
            font-size: 0.875rem;
        }

        .req-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .req-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 20px;
        }

        .req-card h4 {
            color: #212529;
            margin-bottom: 12px;
            font-size: 1rem;
            font-weight: 600;
        }

        .req-card ul {
            list-style: none;
            padding: 0;
        }

        .req-card li {
            padding: 6px 0;
            padding-left: 20px;
            position: relative;
            color: #495057;
            font-size: 0.875rem;
        }

        .req-card li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: #0066cc;
            font-weight: bold;
        }

        .intensity-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
        }

        .intensity-base {
            background: #e3f2fd;
            color: #1976d2;
        }

        .intensity-media {
            background: #fff3e0;
            color: #f57c00;
        }

        .intensity-elevata {
            background: #fce4ec;
            color: #c2185b;
        }

        .percentage-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
            padding: 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.875rem;
        }

        .note-box {
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
            color: #2e7d32;
            padding: 12px 16px;
            border-radius: 4px;
            margin-top: 12px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Dashboard Requisiti Autorizzazione e Accreditamento Sanitario</h1>
            <p>Sistema di verifica requisiti per strutture sanitarie - Regione Calabria (DCA 81/2016 e s.m.i.)</p>
        </div>

        <div class="form-section">
            <h2>Configurazione Struttura Sanitaria</h2>
            <div class="form-group">
                <label for="structureType">Tipologia di Struttura</label>
                <select id="structureType" onchange="updateForm()">
                    <option value="">Seleziona tipologia...</option>
                    <optgroup label="Strutture Ospedaliere">
                        <option value="hospital">Struttura Ospedaliera</option>
                        <option value="riab-intensiva-ospedaliera">Riabilitazione Intensiva Ospedaliera</option>
                    </optgroup>
                    <optgroup label="Strutture Territoriali">
                        <option value="dialysis">Centro Dialisi</option>
                        <option value="laboratory">Laboratorio Analisi</option>
                    </optgroup>
                    <optgroup label="Riabilitazione Extraospedaliera">
                        <option value="riab-estensiva">Riabilitazione Estensiva Extraospedaliera</option>
                    </optgroup>
                    <optgroup label="Neuropsichiatria">
                        <option value="neuropsych">Casa di Cura Neuropsichiatrica</option>
                    </optgroup>
                    <optgroup label="Assistenza Residenziale">
                        <option value="rsa">RSA - Residenza Sanitaria Assistenziale</option>
                    </optgroup>
                </select>
            </div>

            <div id="specificParameters" class="hidden">
                <!-- Parameters will be dynamically inserted here -->
            </div>

            <button class="btn hidden" id="generateBtn" onclick="generateRequirements()">
                Genera Requisiti
            </button>
        </div>

        <div id="requirementsContent" class="hidden">
            <!-- Requirements will be displayed here -->
        </div>
    </div>

    <script>
        // Tabelle ufficiali personale da Allegato 3 DCA 81/2016
        const personnelTables = {
            riabilitazioneIntensiva: {
                medici: {
                    cod_75_28: { modulo_30: 5 },
                    cod_56: { 
                        modulo_30: 4,
                        modulo_60: 7,
                        modulo_90: 9
                    },
                    lungodegenza_60: { 
                        modulo_20: 4,
                        modulo_40: 6,
                        modulo_60: 7
                    }
                },
                infermieri: {
                    cod_75_28: { modulo_30: 18 },
                    cod_56: { 
                        modulo_30: 8,
                        modulo_60: 12,
                        modulo_90: 15
                    },
                    lungodegenza_60: {
                        modulo_20: 8,
                        modulo_40: 12,
                        modulo_60: 15
                    }
                },
                terapisti: {
                    cod_75_28: { modulo_30: 10 },
                    cod_56: { 
                        modulo_30: 8,
                        modulo_60: 12,
                        modulo_90: 15
                    },
                    lungodegenza_60: {
                        modulo_20: 3,
                        modulo_40: 4,
                        modulo_60: 5
                    }
                },
                oss: {
                    riabilitazione: { modulo_30: 6 },
                    lungodegenza: { modulo_30: 4 }
                }
            },
            ospedale: {
                medici: {
                    base_mediche: { modulo_20: 6, modulo_30: 7 },
                    base_chirurgiche: { modulo_20: 7, modulo_30: 9 },
                    media_assistenza: { modulo_20: 7, modulo_30: 9 },
                    elevata_assistenza: { modulo_20: 10 },
                    terapia_intensiva: { modulo_10: 10, modulo_15: 12 },
                    odontoiatria: { modulo_10: 5 },
                    oculistica: { modulo_10: 5 }
                },
                infermieri: {
                    aree_mediche: { modulo_20: 7, modulo_30: 10 },
                    aree_chirurgiche: { modulo_20: 8, modulo_30: 11 },
                    media_assistenza: { modulo_20: 8, modulo_30: 11 },
                    elevata_assistenza: { modulo_20: 10, modulo_30: 15 },
                    terapia_intensiva: { modulo_10: 20, modulo_15: 30 },
                    odontoiatria: { modulo_10: 6 },
                    oculistica: { modulo_10: 6 }
                },
                oss: {
                    base_mediche: { modulo_20: 3, modulo_30: 4 },
                    base_chirurgiche: { modulo_20: 3, modulo_30: 4 },
                    media_assistenza: { modulo_20: 4, modulo_30: 5 },
                    elevata_assistenza: { modulo_20: 6 },
                    terapia_intensiva: { modulo_10: null }, // non specificato
                    odontoiatria: { modulo_10: 3 },
                    oculistica: { modulo_10: 3 }
                }
            }
        };

        // Specialità e livelli di intensità
        const specialtyIntensity = {
            'Cardiologia': { tipo: 'medica', intensita: 'media' },
            'Ematologia': { tipo: 'medica', intensita: 'media', note: 'di elevata ass. se con trapianti' },
            'Malattie endocrine': { tipo: 'medica', intensita: 'base' },
            'Geriatria': { tipo: 'medica', intensita: 'base' },
            'Malattie infettive': { tipo: 'medica', intensita: 'elevata', note: 'senza SID media intensità' },
            'Medicina interna': { tipo: 'medica', intensita: 'base' },
            'Nefrologia': { tipo: 'medica', intensita: 'media', note: 'di elevata ass. se con trapianti' },
            'Neurologia': { tipo: 'medica', intensita: 'media' },
            'Medicina d\'urgenza e Pronto Soccorso': { tipo: 'medica', intensita: 'base' },
            'Dermatologia': { tipo: 'medica', intensita: 'base' },
            'Gastroenterologia': { tipo: 'medica', intensita: 'base', note: 'di media intensità se interventistica' },
            'Oncologia': { tipo: 'medica', intensita: 'media', note: 'di elevata ass. se con trapianti' },
            'Pneumologia': { tipo: 'medica', intensita: 'base' },
            'Radioterapia': { tipo: 'medica', intensita: 'media' },
            'Reumatologia': { tipo: 'medica', intensita: 'base' },
            'Cardiochirurgia': { tipo: 'chirurgica', intensita: 'elevata' },
            'Chirurgia generale': { tipo: 'chirurgica', intensita: 'base', note: 'di elevata ass. se con trapianti' },
            'Chirurgia d\'urgenza': { tipo: 'chirurgica', intensita: 'base' },
            'Chirurgia maxillo facciale': { tipo: 'chirurgica', intensita: 'media' },
            'Chirurgia plastica': { tipo: 'chirurgica', intensita: 'base' },
            'Chirurgia toracica': { tipo: 'chirurgica', intensita: 'media', note: 'elev. ass. se con pneumectomia e resezione pleura o polmone' },
            'Chirurgia vascolare': { tipo: 'chirurgica', intensita: 'media', note: 'elev. ass. se con interventi endocavitari aortici o carotidei' },
            'Neurochirurgia': { tipo: 'chirurgica', intensita: 'elevata' },
            'Oculistica': { tipo: 'chirurgica', intensita: 'base' },
            'Odontoiatria e stomatologia': { tipo: 'chirurgica', intensita: 'base' },
            'Ortopedia e traumatologia': { tipo: 'chirurgica', intensita: 'base' },
            'Otorinolaringoiatria': { tipo: 'chirurgica', intensita: 'base' },
            'Urologia': { tipo: 'chirurgica', intensita: 'base', note: 'di elevata ass. se con trapianti' },
            'Cardiochirurgia Pediatrica': { tipo: 'materno-infantile', intensita: 'elevata' },
            'Chirurgia pediatrica': { tipo: 'materno-infantile', intensita: 'media' },
            'Nido': { tipo: 'materno-infantile', intensita: '-' },
            'Neuropsichiatria infantile': { tipo: 'materno-infantile', intensita: 'media' },
            'Ostetricia e ginecologia': { tipo: 'materno-infantile', intensita: 'base' },
            'Pediatria': { tipo: 'materno-infantile', intensita: 'media' },
            'Neonatologia': { tipo: 'materno-infantile', intensita: 'elevata' },
            'Nefrologia pediatrica': { tipo: 'materno-infantile', intensita: 'elevata' },
            'Urologia pediatrica': { tipo: 'materno-infantile', intensita: 'media' },
            'Cardiologia pediatrica': { tipo: 'materno-infantile', intensita: 'media' },
            'Dermatologia pediatrica': { tipo: 'materno-infantile', intensita: 'base' },
            'Ematologia pediatrica': { tipo: 'materno-infantile', intensita: 'media', note: 'di elevata ass. se con trapianti' },
            'Endocrinologia pediatrica': { tipo: 'materno-infantile', intensita: 'base' },
            'Gastroenterologia pediatrica': { tipo: 'materno-infantile', intensita: 'base' },
            'Neurologia pediatrica': { tipo: 'materno-infantile', intensita: 'media' },
            'Oncologia pediatrica': { tipo: 'materno-infantile', intensita: 'elevata' },
            'Pneumologia pediatrica': { tipo: 'materno-infantile', intensita: 'media' },
            'Reumatologia pediatrica': { tipo: 'materno-infantile', intensita: 'base' },
            'Ortopedia pediatrica': { tipo: 'materno-infantile', intensita: 'media' },
            'Otorinolaringoiatria pediatrica': { tipo: 'materno-infantile', intensita: 'base' },
            'Terapia intensiva': { tipo: 'terapie-intensive', intensita: 'elevata' },
            'Unità coronarica': { tipo: 'terapie-intensive', intensita: 'elevata' },
            'Terapia intensiva neonatale': { tipo: 'terapie-intensive', intensita: 'elevata' },
            'Terapia intensiva pediatrica': { tipo: 'terapie-intensive', intensita: 'elevata' },
            'Psichiatria': { tipo: 'riabilitazione', intensita: 'elevata' },
            'Recupero e riabilitazione funzionale': { tipo: 'riabilitazione', intensita: 'riabilitazione' },
            'Neuroriabilitazione ad alta specialità': { tipo: 'riabilitazione', intensita: 'elevata' },
            'Unità spinale': { tipo: 'riabilitazione', intensita: 'elevata' },
            'Lungodegenza': { tipo: 'lungodegenza', intensita: 'lungodegenza' }
        };

        const requirementsData = {
            hospital: {
                parametri: [
                    { id: 'beds', label: 'Numero posti letto', type: 'number', min: 1, max: 500 },
                    { id: 'specialty', label: 'Specialità principale', type: 'select', options: Object.keys(specialtyIntensity).sort() },
                    { id: 'hasOperatingRooms', label: 'Presenza sale operatorie', type: 'select', options: ['Sì', 'No'] },
                    { id: 'hasEmergency', label: 'Presenza Pronto Soccorso', type: 'select', options: ['Sì', 'No'] },
                    { id: 'hasIntensiveCare', label: 'Presenza Terapia Intensiva', type: 'select', options: ['Sì', 'No'] }
                ],
                strutturali: {
                    generali: [
                        'Separazione percorsi sporco/pulito',
                        'Accessi differenziati (utenti/fornitori/emergenze)',
                        'Percorsi dedicati per barelle',
                        'Impianto di climatizzazione con filtri HEPA per aree critiche',
                        'Gruppo elettrogeno con autonomia minima 48h',
                        'Sistema di chiamata paziente in ogni posto letto',
                        'Rete gas medicali centralizzata'
                    ],
                    reparti_degenza: [
                        'Superficie minima: 9 mq (1 p.l.), 14 mq (2 p.l.), 20 mq (3 p.l.)',
                        'Servizio igienico ogni 4 posti letto max',
                        'Almeno il 10% camere singole con servizio igienico',
                        'Lavabo in ogni camera',
                        'Ossigeno e vuoto per ogni posto letto',
                        'Sistema di chiamata adeguato all\'età dei pazienti'
                    ],
                    blocco_operatorio: [
                        'Pre-sala operatoria (min 20 mq)',
                        'Sala operatoria (min 36 mq)',
                        'Zona risveglio con monitoraggio',
                        'Deposito materiale sterile',
                        'Zona lavaggio chirurghi',
                        'Percorsi differenziati paziente/personale/materiale'
                    ],
                    pronto_soccorso: [
                        'Area triage',
                        'Sale visita differenziate per codici',
                        'Shock room attrezzata',
                        'OBI con posti monitorati',
                        'Area attesa con servizi igienici dedicati'
                    ]
                },
                tecnologici: {
                    diagnostica: [
                        'TAC multislice (min 16 strati)',
                        'RMN (se >120 p.l.)',
                        'Ecografi di reparto',
                        'Radiologia digitale',
                        'Mammografo (se presente breast unit)',
                        'Angiografo (se cardiologia/neurologia interventistica)'
                    ],
                    monitoraggio: [
                        'Centrale monitoraggio per terapia intensiva',
                        'Monitor multiparametrici (1:1 in TI, 1:4 in sub-intensiva)',
                        'Defibrillatori (min 2 per reparto)',
                        'Carrelli emergenza completi'
                    ],
                    sala_operatoria: [
                        'Colonne laparoscopiche',
                        'Elettrobisturi con evacuatore fumi',
                        'Apparecchio anestesia con monitoraggio completo',
                        'Lampade scialitiche LED',
                        'Tavoli operatori polivalenti',
                        'C-arm per radioscopia'
                    ]
                }
            },
            'riab-intensiva-ospedaliera': {
                name: 'Riabilitazione Intensiva Ospedaliera',
                parametri: [
                    { id: 'beds', label: 'Numero posti letto', type: 'number', min: 1, max: 90 },
                    { id: 'code', label: 'Codice riabilitazione', type: 'select', options: [
                        'Codice 56 - Recupero e Riabilitazione Funzionale',
                        'Codice 75 - Neuroriabilitazione/Gravi Cerebrolesioni',
                        'Codice 28 - Unità Spinale Unipolare'
                    ]}
                ],
                strutturali: {
                    degenza: [
                        'Area attrezzata per colloquio ed addestramento familiari',
                        'Bagno assistito: 1 ogni modulo da 30 p.l.',
                        'Spazio/locale per deposito attrezzature (carrozzine, deambulatori, etc.)',
                        'Spazio attrezzato per consumazione pasti, soggiorno e tempo libero',
                        'Dimensioni locali degenza per accesso con barella, carrozzina, deambulatore, sollevatore',
                        'Sistemi di chiamata adatti alle diverse tipologie di disabilità',
                        'Tavoli con altezza per inserimento carrozzina'
                    ],
                    valutazione: [
                        'Area complessiva (ambulatori generali e valutazioni specifiche) non inferiore a 36 mq',
                        'Ambulatori collocati all\'interno della struttura'
                    ],
                    riabilitazione: [
                        'Superficie complessiva minimo 100 mq (90 mq per strutture esistenti)',
                        'Palestra per esercizio terapeutico: minimo 45 mq per 6 pazienti contemporanei',
                        'Incremento 5 mq per ogni paziente in più',
                        'Area attività specifiche di gruppo: minimo 36 mq (strutture esistenti)',
                        'Ambiente/spazio coordinamento terapisti vicino palestra',
                        'Servizi igienici distinti personale/utenti',
                        'Deposito materiale sporco',
                        'Locale/spazio materiale pulito'
                    ]
                },
                tecnologici: {
                    degenza: [
                        'Letti degenza a 3 segmenti regolabili con spondine, trapezi e archetti alzacoperte',
                        'Almeno 20% letti regolabili in altezza',
                        'Sollevatore pazienti elettrico con diverse imbragature',
                        'Sistema pesapersone',
                        'Ausili antidecubito',
                        'Carrozzine di tipologia adeguata alle patologie con accessori'
                    ],
                    valutazione: [
                        'Dispositivi per valutazione degli esiti',
                        'Attrezzature per valutazione e oggettivazione dato informatizzato'
                    ],
                    riabilitazione: [
                        'Lettini per rieducazione motoria ad altezza variabile (100-200x44/85h)',
                        'Letto grandi dimensioni per rieducazione motoria ad altezza variabile (200x200x44/85h)',
                        'Letti di verticalizzazione ad altezza ed inclinazione variabile',
                        'Sollevatore elettrico con imbragatura (fasce, amaca standard e amaca con poggiatesta)',
                        'Standing elettrici per verticalizzazione precoce',
                        'Tapis roulant con supporto peso corporeo',
                        'Cyclette per arti superiori e inferiori',
                        'Parallele regolabili in altezza e larghezza',
                        'Scale e rampa per training deambulazione',
                        'Specchio quadrettato mobile',
                        'Materassini, cunei, rulli per facilitazione posturale',
                        'Carrozzine di varie misure',
                        'Deambulatori, tripodi, bastoni',
                        'Attrezzature per terapia occupazionale',
                        'Elettromedicali per fisioterapia',
                        'Biofeedback',
                        'Apparecchiature FES',
                        'Sistema di valutazione computerizzata'
                    ]
                },
                organizzativi: {
                    generali: [
                        'Presenza medico H24',
                        'Almeno 3 ore giornaliere di terapia specifica per 6 giorni settimanali',
                        'Progetto Riabilitativo Individuale per ogni paziente',
                        'Équipe multidisciplinare con riunioni settimanali',
                        'Scale di valutazione validate',
                        'Procedure per accesso e trasferimento pazienti',
                        'Cartella clinica con documentazione infermieristica integrata',
                        'Addestramento paziente e familiari prima del rientro a domicilio'
                    ]
                }
            },
            dialysis: {
                parametri: [
                    { id: 'stations', label: 'Numero postazioni dialisi', type: 'number', min: 1, max: 50 },
                    { id: 'shifts', label: 'Numero turni giornalieri', type: 'number', min: 1, max: 4 },
                    { id: 'hasNightShift', label: 'Turno notturno', type: 'select', options: ['Sì', 'No'] }
                ],
                strutturali: {
                    sale_dialisi: [
                        'Superficie minima 7 mq per posto rene',
                        'Altezza minima locali 3 metri',
                        'Finestre con schermature regolabili',
                        'Pavimenti e pareti lavabili e disinfettabili',
                        'Impianto di climatizzazione con ricambi 6 vol/h',
                        'Prese elettriche dedicate per ogni postazione'
                    ],
                    aree_supporto: [
                        'Sala attesa con servizi igienici dedicati',
                        'Spogliatoi pazienti con armadietti personali',
                        'Area preparazione pazienti',
                        'Locale per trattamento acque (osmosi inversa)',
                        'Deposito materiale dialisi pulito',
                        'Deposito rifiuti speciali separato',
                        'Locale per emergenze attrezzato'
                    ]
                },
                tecnologici: {
                    apparecchiature: [
                        'Monitor dialisi di ultima generazione',
                        'Sistema centralizzato preparazione concentrati',
                        'Bilance pesa persone di precisione',
                        'Letti/poltrone bilancia integrata',
                        'Monitor multiparametrici',
                        'Defibrillatore',
                        'Ecografo per accessi vascolari'
                    ],
                    trattamento_acque: [
                        'Pre-trattamento con filtri e addolcitori',
                        'Doppia osmosi inversa',
                        'Sistema di disinfezione termica/chimica',
                        'Monitoraggio continuo conducibilità',
                        'Allarmi per superamento parametri',
                        'Analisi microbiologiche mensili'
                    ]
                }
            },
            'riab-estensiva': {
                name: 'Riabilitazione Estensiva Extraospedaliera',
                parametri: [
                    { id: 'pazienti', label: 'Numero pazienti/die', type: 'number', min: 1, max: 100 }
                ],
                strutturali: {
                    requisiti_base: [
                        'Unità funzionale base: 40 pazienti/pro die',
                        'Apertura: 36 ore settimanali minimo',
                        'Locali per attività riabilitative individuali e di gruppo',
                        'Palestra riabilitativa attrezzata',
                        'Servizi igienici per disabili',
                        'Spogliatoi per utenti',
                        'Locale attesa',
                        'Deposito attrezzature e presidi'
                    ],
                    accessibilita: [
                        'Accessibilità totale per persone con disabilità',
                        'Percorsi privi di barriere architettoniche',
                        'Ascensore se su più piani',
                        'Parcheggi riservati disabili',
                        'Segnaletica adeguata'
                    ]
                },
                tecnologici: {
                    attrezzature_base: [
                        'Lettini per terapia',
                        'Parallele per deambulazione',
                        'Specchio quadrettato',
                        'Scale e rampa per training',
                        'Cyclette/tapis roulant',
                        'Attrezzature per terapia occupazionale',
                        'Kit valutazione funzionale'
                    ],
                    attrezzature_specifiche: [
                        'Elettromedicali per fisioterapia',
                        'Ultrasuoni terapeutici',
                        'TENS/elettrostimolatori',
                        'Apparecchiature per biofeedback',
                        'Sistemi di valutazione computerizzata'
                    ]
                },
                organizzativi: {
                    personale_base_40_pazienti: [
                        'Medico specialista Fisiatra: Direttore (0.20 unità)',
                        'Medico specialista Fisiatra: 1 unità',
                        'Terapista della riabilitazione: 6 unità',
                        'Infermiere: 2 unità',
                        'OSS: 2 unità',
                        'Psicologo: 1 unità',
                        'Assistente sociale: 0.33 unità',
                        'Terapista occupazionale: 1 unità',
                        'Logopedista: 2 unità (se necessario)'
                    ],
                    organizzazione: [
                        'Équipe multiprofessionale',
                        'Progetto riabilitativo individuale obbligatorio',
                        'Valutazione multidimensionale',
                        'Riunioni d\'équipe settimanali',
                        'Coordinamento con MMG',
                        'Registro presenze e prestazioni',
                        'Sistema di valutazione outcomes'
                    ]
                }
            },
            laboratory: {
                parametri: [
                    { id: 'dailyTests', label: 'Numero esami/die', type: 'number', min: 50, max: 5000 },
                    { id: 'hasUrgency', label: 'Servizio urgenze', type: 'select', options: ['Sì', 'No'] },
                    { id: 'sectors', label: 'Settori specialistici', type: 'number', min: 1, max: 10 }
                ],
                strutturali: {
                    aree_tecniche: [
                        'Zona accettazione separata',
                        'Sala prelievi (min 12 mq)',
                        'Area processazione campioni',
                        'Laboratori separati per settore',
                        'Locale per strumentazione',
                        'Cella frigorifera per reagenti'
                    ],
                    requisiti_sicurezza: [
                        'Cappe biologiche classe II',
                        'Docce di emergenza',
                        'Lavaocchi',
                        'Armadi sicurezza reagenti',
                        'Sistema aspirazione localizzata',
                        'Uscite di emergenza segnalate'
                    ]
                },
                tecnologici: {
                    strumentazione_base: [
                        'Analizzatori ematologia',
                        'Analizzatori chimica clinica',
                        'Coagulometri',
                        'Analizzatori urine',
                        'Microscopi',
                        'Centrifughe refrigerate'
                    ],
                    strumentazione_specialistica: [
                        'Spettrometri di massa',
                        'Citofluorimetri',
                        'Sequenziatori DNA',
                        'Real-time PCR',
                        'Analizzatori immunometrici',
                        'Gascromatografi'
                    ]
                }
            },
            neuropsych: {
                parametri: [
                    { id: 'beds', label: 'Numero posti letto', type: 'number', min: 10, max: 100 },
                    { id: 'rehabilitationType', label: 'Tipologia riabilitazione', type: 'select', options: ['Rilevanza sociale', 'Terapeutico-riabilitativa'] }
                ],
                strutturali: {
                    generale: [
                        'Minimo 10 posti letto per elevata assistenza',
                        'Minimo 20 posti letto per residenza sanitaria terapeutico-riabilitativa',
                        'Minimo 20 posti letto per residenza socio-sanitaria',
                        'Camere con max 4 posti letto',
                        'Servizi igienici ogni 4 ospiti',
                        'Spazi per attività riabilitative'
                    ],
                    aree_comuni: [
                        'Soggiorno/pranzo dimensionato per tutti gli ospiti',
                        'Locali per attività occupazionali',
                        'Spazi per colloqui riservati',
                        'Area verde attrezzata',
                        'Cucina/tisaneria',
                        'Depositi biancheria pulita/sporca separati'
                    ]
                },
                tecnologici: {
                    dotazioni: [
                        'Sistema di videosorveglianza aree comuni',
                        'Sistema chiamata in ogni camera',
                        'Attrezzature per attività riabilitative',
                        'Arredi anti-infortunistici',
                        'Sistema gestione farmaci controllato',
                        'Mezzi per trasporto pazienti'
                    ]
                },
                organizzativi: {
                    personale_base: [
                        'Direttore Sanitario: 0.10 unità (specialista psichiatria)',
                        'Medico responsabile: 5 unità con funzioni coordinamento',
                        'Infermiere Professionale: 6 unità (assistenza h24)',
                        'OSS: 4 unità (supporto attività)',
                        'Psicologo: 1 unità (attività riabilitative)',
                        'Ausiliario: 2 unità (servizi generali)'
                    ]
                }
            },
            rsa: {
                name: 'RSA - Residenza Sanitaria Assistenziale',
                posti_standard: 60,
                parametri: [
                    { id: 'posti', label: 'Numero posti letto', type: 'number', min: 20, max: 200 }
                ],
                strutturali: {
                    spazi_base: [
                        'Camere singole e doppie con bagno',
                        'Nuclei da max 20 posti letto',
                        'Soggiorno per nucleo (min 1.5 mq/posto)',
                        'Sala pranzo (min 1.2 mq/posto)',
                        'Ambulatorio medico',
                        'Locale medicazioni',
                        'Palestra riabilitazione',
                        'Deposito farmaci con frigo',
                        'Camera ardente',
                        'Cucina e dispensa',
                        'Lavanderia e stireria',
                        'Spogliatoi personale'
                    ],
                    requisiti_camere: [
                        'Max 2 posti letto per camera',
                        'Superficie min 12 mq singola, 18 mq doppia',
                        'Bagno accessibile in camera',
                        'Letti regolabili elettrici',
                        'Campanello chiamata',
                        'Prese ossigeno e vuoto'
                    ]
                },
                organizzativi: {
                    personale: [
                        { figura: 'Direttore sanitario', requisito: 1, tipo: 'medico', note: 'Tempo pieno' },
                        { figura: 'Medico', requisito: '240 min/settimana ogni 10 ospiti', tipo: 'medico', note: 'Presenza programmata' },
                        { figura: 'Coordinatore infermieristico', requisito: 1, tipo: 'infermiere', note: 'Con master coordinamento' },
                        { figura: 'Infermiere', requisito: '1 ogni 12 ospiti h24', tipo: 'infermiere', note: 'Copertura continuativa' },
                        { figura: 'OSS/OTA', requisito: '1 ogni 4 ospiti diurno, 1 ogni 8 notturno', tipo: 'supporto', note: 'Assistenza diretta' },
                        { figura: 'Fisioterapista', requisito: '90 min/settimana ogni 10 ospiti', tipo: 'tecnico', note: 'Riabilitazione' },
                        { figura: 'Animatore/Educatore', requisito: '120 min/settimana ogni 10 ospiti', tipo: 'educativo', note: 'Attività socializzazione' },
                        { figura: 'Assistente sociale', requisito: '36 ore settimanali', tipo: 'sociale', note: 'Supporto sociale' }
                    ],
                    organizzazione: [
                        'PAI per ogni ospite',
                        'UVI per valutazione ingresso',
                        'Équipe multidisciplinare',
                        'Turni copertura H24',
                        'Protocolli assistenziali',
                        'Registro terapie',
                        'Cartella socio-sanitaria integrata'
                    ]
                },
                tecnologici: {
                    attrezzature_assistenza: [
                        'Letti elettrici con sponde',
                        'Materassi antidecubito',
                        'Sollevatori elettrici',
                        'Carrozzine e deambulatori',
                        'Ausili per alimentazione',
                        'Aspiratori',
                        'Ossigeno terapia',
                        'Monitor parametri',
                        'Defibrillatore',
                        'Carrello emergenza completo'
                    ],
                    attrezzature_riabilitazione: [
                        'Standing elettrici',
                        'Cyclette per arti',
                        'Elettrostimolatori',
                        'Attrezzature fisioterapia',
                        'Percorso deambulazione'
                    ],
                    informatica: [
                        'Cartella clinica informatizzata',
                        'Sistema gestione terapie',
                        'Controllo accessi',
                        'Sistema chiamata evoluto',
                        'Telemedicina per consulti'
                    ]
                }
            }
        };

        function updateForm() {
            const structureType = document.getElementById('structureType').value;
            const specificParams = document.getElementById('specificParameters');
            const generateBtn = document.getElementById('generateBtn');
            
            if (!structureType) {
                specificParams.classList.add('hidden');
                generateBtn.classList.add('hidden');
                return;
            }
            
            const params = requirementsData[structureType].parametri;
            let html = '';
            
            params.forEach(param => {
                html += '<div class="form-group">';
                html += `<label for="${param.id}">${param.label}</label>`;
                
                if (param.type === 'number') {
                    html += `<input type="number" id="${param.id}" min="${param.min}" max="${param.max}" required>`;
                } else if (param.type === 'select') {
                    html += `<select id="${param.id}" required>`;
                    html += '<option value="">Seleziona...</option>';
                    param.options.forEach(opt => {
                        html += `<option value="${opt}">${opt}</option>`;
                    });
                    html += '</select>';
                }
                
                html += '</div>';
            });
            
            specificParams.innerHTML = html;
            specificParams.classList.remove('hidden');
            generateBtn.classList.remove('hidden');
        }

        function calculateHospitalPersonnel(beds, specialty, hasOperatingRooms, hasEmergency, hasIntensiveCare) {
            const personnel = {};
            const specInfo = specialtyIntensity[specialty] || { tipo: 'medica', intensita: 'base' };
            const isChirurgica = specInfo.tipo === 'chirurgica' || hasOperatingRooms === 'Sì';
            const intensity = specInfo.intensita;
            
            // Calcola modulo appropriato
            const modulo = beds <= 20 ? 'modulo_20' : 'modulo_30';
            const moduloBeds = beds <= 20 ? 20 : 30;
            const factor = beds / moduloBeds;
            
            // MEDICI
            let mediKey = isChirurgica ? 'base_chirurgiche' : 'base_mediche';
            if (intensity === 'media') mediKey = 'media_assistenza';
            if (intensity === 'elevata') mediKey = 'elevata_assistenza';
            
            if (personnelTables.ospedale.medici[mediKey] && personnelTables.ospedale.medici[mediKey][modulo]) {
                const base = personnelTables.ospedale.medici[mediKey][modulo];
                personnel['Medici di reparto'] = {
                    quantita: Math.ceil(base * factor),
                    note: `${specInfo.tipo === 'chirurgica' ? 'Specialità chirurgica' : 'Specialità medica'} - Intensità ${intensity}`,
                    formula: `${base} medici per modulo ${moduloBeds} p.l.`
                };
            }
            
            // INFERMIERI
            let infKey = isChirurgica ? 'aree_chirurgiche' : 'aree_mediche';
            if (intensity === 'media') infKey = 'media_assistenza';
            if (intensity === 'elevata') infKey = 'elevata_assistenza';
            
            if (personnelTables.ospedale.infermieri[infKey] && personnelTables.ospedale.infermieri[infKey][modulo]) {
                const base = personnelTables.ospedale.infermieri[infKey][modulo];
                personnel['Infermieri di reparto'] = {
                    quantita: Math.ceil(base * factor),
                    note: 'Copertura h24 con minimo 3 per turno',
                    formula: `${base} infermieri per modulo ${moduloBeds} p.l.`
                };
            }
            
            // OSS
            let ossKey = isChirurgica ? 'base_chirurgiche' : 'base_mediche';
            if (intensity === 'media') ossKey = 'media_assistenza';
            if (intensity === 'elevata') ossKey = 'elevata_assistenza';
            
            if (personnelTables.ospedale.oss[ossKey] && personnelTables.ospedale.oss[ossKey][modulo]) {
                const base = personnelTables.ospedale.oss[ossKey][modulo];
                personnel['OSS di reparto'] = {
                    quantita: Math.ceil(base * factor),
                    note: 'Presenza h12',
                    formula: `${base} OSS per modulo ${moduloBeds} p.l.`
                };
            }
            
            // PRONTO SOCCORSO
            if (hasEmergency === 'Sì') {
                personnel['PS - Medici emergenza'] = {
                    quantita: 'Minimo 3 per turno h24',
                    note: 'Specializzazione in medicina d\'emergenza-urgenza'
                };
                personnel['PS - Infermieri'] = {
                    quantita: 'Minimo 4 per turno (3 + 1 triage)',
                    note: 'Formazione triage accreditata, BLS-D, ATLS'
                };
                personnel['PS - OSS'] = {
                    quantita: '2 per turno h12',
                    note: 'Formazione supporto emergenze'
                };
            }
            
            // TERAPIA INTENSIVA
            if (hasIntensiveCare === 'Sì') {
                const tiPosti = Math.max(Math.ceil(beds * 0.05), 4);
                personnel['TI - Medici anestesisti'] = {
                    quantita: Math.ceil(tiPosti / 6),
                    note: `Rapporto 1:6 posti letto - Presenza h24`,
                    formula: `${tiPosti} posti TI previsti`
                };
                personnel['TI - Infermieri'] = {
                    quantita: Math.ceil(tiPosti / 2) + ' per turno',
                    note: 'Rapporto 1:2 posti letto h24'
                };
                personnel['TI - OSS'] = {
                    quantita: Math.ceil(tiPosti / 4) + ' per turno',
                    note: 'Rapporto 1:4 posti letto'
                };
            }
            
            // SALE OPERATORIE
            if (hasOperatingRooms === 'Sì' || isChirurgica) {
                const numSale = Math.max(Math.ceil(beds / 40), 1);
                personnel['Infermieri strumentisti'] = {
                    quantita: numSale * 2,
                    note: `Per ${numSale} sale operatorie`,
                    formula: '2 strumentisti per sala'
                };
                personnel['OSS blocco operatorio'] = {
                    quantita: numSale * 2,
                    note: 'Formazione specifica blocco operatorio'
                };
            }
            
            // ALTRI SERVIZI
            personnel['Coordinatore infermieristico'] = {
                quantita: 1,
                note: 'Master in coordinamento'
            };
            
            return personnel;
        }

        function calculateRiabilitazioneIntensivaPersonnel(beds, code) {
            const personnel = {};
            let codiceRiab = '';
            
            // Estrai il codice dalla selezione
            if (code.includes('56')) codiceRiab = '56';
            else if (code.includes('75')) codiceRiab = '75';
            else if (code.includes('28')) codiceRiab = '28';
            
            // Determina il modulo appropriato basato sui posti letto
            let modulo = '';
            let moduloBeds = 0;
            
            if (beds <= 30) {
                modulo = 'modulo_30';
                moduloBeds = 30;
            } else if (beds <= 60) {
                modulo = 'modulo_60';
                moduloBeds = 60;
            } else {
                modulo = 'modulo_90';
                moduloBeds = 90;
            }
            
            const factor = beds / moduloBeds;
            
            // MEDICI
            if (codiceRiab === '56') {
                if (beds <= 30) {
                    personnel['Medici'] = {
                        quantita: Math.ceil(4 * factor),
                        note: 'Fisiatra coordinatore + specialisti secondo necessità',
                        formula: '4 medici per modulo 30 p.l.'
                    };
                } else if (beds <= 60) {
                    personnel['Medici'] = {
                        quantita: Math.ceil(7 * factor),
                        note: 'Fisiatra coordinatore + specialisti secondo necessità',
                        formula: '7 medici per modulo 60 p.l.'
                    };
                } else {
                    personnel['Medici'] = {
                        quantita: Math.ceil(9 * factor),
                        note: 'Fisiatra coordinatore + specialisti secondo necessità',
                        formula: '9 medici per modulo 90 p.l.'
                    };
                }
            } else { // Codice 75 e 28
                personnel['Medici'] = {
                    quantita: Math.ceil(5 * factor),
                    note: 'Fisiatra coordinatore + neurologo/pneumologo/cardiologo secondo necessità',
                    formula: '5 medici per modulo 30 p.l.'
                };
            }
            
            // INFERMIERI
            if (codiceRiab === '56') {
                if (beds <= 30) {
                    personnel['Infermieri'] = {
                        quantita: Math.ceil(8 * factor),
                        note: 'Con competenze riabilitative - Copertura h24',
                        formula: '8 infermieri per modulo 30 p.l.'
                    };
                } else if (beds <= 60) {
                    personnel['Infermieri'] = {
                        quantita: Math.ceil(12 * factor),
                        note: 'Con competenze riabilitative - Copertura h24',
                        formula: '12 infermieri per modulo 60 p.l.'
                    };
                } else {
                    personnel['Infermieri'] = {
                        quantita: Math.ceil(15 * factor),
                        note: 'Con competenze riabilitative - Copertura h24',
                        formula: '15 infermieri per modulo 90 p.l.'
                    };
                }
            } else { // Codice 75 e 28
                personnel['Infermieri'] = {
                    quantita: Math.ceil(18 * factor),
                    note: 'Con competenze riabilitative specifiche - Copertura h24',
                    formula: '18 infermieri per modulo 30 p.l.'
                };
            }
            
            // TERAPISTI DELLA RIABILITAZIONE
            if (codiceRiab === '56') {
                if (beds <= 30) {
                    personnel['Terapisti della riabilitazione'] = {
                        quantita: Math.ceil(8 * factor),
                        note: 'Fisioterapisti, Terapisti occupazionali, Logopedisti secondo necessità',
                        formula: '8 terapisti per modulo 30 p.l.'
                    };
                } else if (beds <= 60) {
                    personnel['Terapisti della riabilitazione'] = {
                        quantita: Math.ceil(12 * factor),
                        note: 'Fisioterapisti, Terapisti occupazionali, Logopedisti secondo necessità',
                        formula: '12 terapisti per modulo 60 p.l.'
                    };
                } else {
                    personnel['Terapisti della riabilitazione'] = {
                        quantita: Math.ceil(15 * factor),
                        note: 'Fisioterapisti, Terapisti occupazionali, Logopedisti secondo necessità',
                        formula: '15 terapisti per modulo 90 p.l.'
                    };
                }
            } else { // Codice 75 e 28
                personnel['Terapisti della riabilitazione'] = {
                    quantita: Math.ceil(10 * factor),
                    note: 'Team multidisciplinare: Fisioterapisti, T. occupazionali, Logopedisti, Neuropsicologi',
                    formula: '10 terapisti per modulo 30 p.l.'
                };
            }
            
            // OSS
            personnel['OSS'] = {
                quantita: Math.ceil(6 * factor),
                note: 'Supporto attività riabilitative e assistenziali',
                formula: '6 OSS per modulo 30 p.l.'
            };
            
            // ALTRE FIGURE
            personnel['Assistente sociale'] = {
                quantita: 1,
                note: 'Per pianificazione dimissione e supporto sociale'
            };
            
            personnel['Psicologo'] = {
                quantita: codiceRiab === '56' ? 0.5 : 1,
                note: codiceRiab === '56' ? 'Part-time per supporto psicologico' : 'Tempo pieno per supporto e valutazione neuropsicologica'
            };
            
            // Standard assistenziale
            personnel['Standard assistenziale'] = {
                quantita: 'Minimo 3 ore/die di terapia per 6 giorni/settimana',
                note: `Codice ${codiceRiab} - ${code.split(' - ')[1]}`
            };
            
            return personnel;
        }

        function calculateRiabilitazioneEstensivaPersonnel(pazienti) {
            const personnel = {};
            const unitaBase = 40; // pazienti/die
            const factor = pazienti / unitaBase;
            
            personnel['Direttore (Medico Fisiatra)'] = {
                quantita: (0.20 * factor).toFixed(2),
                note: 'Specialista in Medicina Fisica e Riabilitazione',
                formula: '0.20 unità per 40 pazienti/die'
            };
            
            personnel['Medico Fisiatra'] = {
                quantita: Math.ceil(1 * factor),
                note: 'Specialista in Medicina Fisica e Riabilitazione',
                formula: '1 unità per 40 pazienti/die'
            };
            
            personnel['Terapisti della riabilitazione'] = {
                quantita: Math.ceil(6 * factor),
                note: 'Fisioterapisti e/o equipollenti',
                formula: '6 unità per 40 pazienti/die'
            };
            
            personnel['Infermiere'] = {
                quantita: Math.ceil(2 * factor),
                note: 'Per gestione terapie e assistenza',
                formula: '2 unità per 40 pazienti/die'
            };
            
            personnel['OSS'] = {
                quantita: Math.ceil(2 * factor),
                note: 'Supporto attività riabilitative',
                formula: '2 unità per 40 pazienti/die'
            };
            
            personnel['Psicologo'] = {
                quantita: Math.ceil(1 * factor),
                note: 'Supporto psicologico pazienti e familiari',
                formula: '1 unità per 40 pazienti/die'
            };
            
            personnel['Assistente sociale'] = {
                quantita: (0.33 * factor).toFixed(2),
                note: 'Supporto sociale e pianificazione dimissioni',
                formula: '0.33 unità per 40 pazienti/die'
            };
            
            personnel['Terapista occupazionale'] = {
                quantita: Math.ceil(1 * factor),
                note: 'Training autonomia e ADL',
                formula: '1 unità per 40 pazienti/die'
            };
            
            personnel['Logopedista'] = {
                quantita: Math.ceil(2 * factor),
                note: 'Se necessario per la casistica',
                formula: '2 unità per 40 pazienti/die (se necessario)'
            };
            
            return personnel;
        }

        function calculateRSAPersonnel(posti) {
            const personnel = {};
            const data = requirementsData['rsa'];
            const proportionalFactor = posti / data.posti_standard;
            
            data.organizzativi.personale.forEach(req => {
                if (typeof req.requisito === 'string') {
                    if (req.requisito.includes('min/settimana ogni')) {
                        const minutiMatch = req.requisito.match(/(\d+)\s*min.*ogni\s*(\d+)/);
                        if (minutiMatch) {
                            const minutiBase = parseInt(minutiMatch[1]);
                            const ogniOspiti = parseInt(minutiMatch[2]);
                            const gruppi = Math.ceil(posti / ogniOspiti);
                            const minutiTotali = minutiBase * gruppi;
                            const oreTotali = (minutiTotali / 60).toFixed(1);
                            
                            personnel[req.figura] = {
                                quantita: `${oreTotali} ore/settimana`,
                                note: req.note || '',
                                formula: `${minutiTotali} min/sett per ${posti} posti`
                            };
                        }
                    } else if (req.requisito.includes('ore')) {
                        personnel[req.figura] = {
                            quantita: req.requisito,
                            note: req.note || '',
                            formula: 'Requisito fisso'
                        };
                    } else {
                        personnel[req.figura] = {
                            quantita: req.requisito,
                            note: req.note || '',
                            formula: `Calcolare per ${posti} posti`
                        };
                    }
                } else if (typeof req.requisito === 'number') {
                    personnel[req.figura] = {
                        quantita: req.requisito,
                        note: req.note || '',
                        formula: 'Unità fissa'
                    };
                }
            });
            
            return personnel;
        }

        function generateRequirements() {
            const structureType = document.getElementById('structureType').value;
            const data = requirementsData[structureType];
            const params = {};
            
            // Collect parameters
            data.parametri.forEach(param => {
                const value = document.getElementById(param.id).value;
                params[param.id] = value;
            });
            
            // Check if all parameters are filled
            for (let param of data.parametri) {
                if (!params[param.id]) {
                    alert('Compilare tutti i campi richiesti');
                    return;
                }
            }
            
            displayRequirements(structureType, params);
        }

        function displayRequirements(structureType, params) {
            const content = document.getElementById('requirementsContent');
            const data = requirementsData[structureType];
            
            let html = '<div class="requirements-section">';
            html += '<h3>Requisiti per ' + getStructureName(structureType) + '</h3>';
            
            // Alert con parametri inseriti
            html += '<div class="alert-info">';
            html += '<h4>Parametri configurati:</h4>';
            html += '<ul style="list-style: none; padding-left: 0;">';
            for (let key in params) {
                const param = data.parametri.find(p => p.id === key);
                let displayValue = params[key];
                
                // Aggiungi badge intensità per specialità ospedaliere
                if (key === 'specialty' && structureType === 'hospital') {
                    const specInfo = specialtyIntensity[params[key]];
                    if (specInfo) {
                        const intensityClass = specInfo.intensita === 'base' ? 'intensity-base' : 
                                             specInfo.intensita === 'media' ? 'intensity-media' : 'intensity-elevata';
                        displayValue += `<span class="intensity-badge ${intensityClass}">${specInfo.intensita.toUpperCase()}</span>`;
                        
                        if (specInfo.note) {
                            displayValue += `<br><small style="color: #6c757d; margin-left: 12px;">${specInfo.note}</small>`;
                        }
                    }
                }
                
                html += `<li><strong>${param.label}:</strong> ${displayValue}</li>`;
            }
            html += '</ul>';
            html += '</div>';
            
            // Note per riabilitazione intensiva
            if (structureType === 'riab-intensiva-ospedaliera') {
                html += '<div class="note-box">';
                html += '<strong>Nota:</strong> La normativa calabrese mantiene l\'equipollenza tra la figura storica del "terapista della riabilitazione" ';
                html += 'e l\'attuale fisioterapista. Il team riabilitativo può includere fisioterapisti, terapisti occupazionali, logopedisti e neuropsicologi ';
                html += 'secondo le necessità della casistica trattata.';
                html += '</div>';
            }
            
            // Requisiti Strutturali
            html += '<div class="requirement-category">';
            html += '<h4>Requisiti Strutturali</h4>';
            html += '<div class="req-grid">';
            for (let category in data.strutturali) {
                html += '<div class="req-card">';
                html += `<h4>${formatCategoryName(category)}</h4>`;
                html += '<ul>';
                data.strutturali[category].forEach(req => {
                    html += `<li>${req}</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }
            html += '</div>';
            html += '</div>';
            
            // Requisiti Organizzativi - Personale
            html += '<div class="requirement-category">';
            html += '<h4>Requisiti Organizzativi - Dotazione Personale</h4>';
            
            let personnel = {};
            
            if (structureType === 'hospital') {
                personnel = calculateHospitalPersonnel(
                    parseInt(params.beds),
                    params.specialty,
                    params.hasOperatingRooms,
                    params.hasEmergency,
                    params.hasIntensiveCare
                );
            } else if (structureType === 'riab-intensiva-ospedaliera') {
                personnel = calculateRiabilitazioneIntensivaPersonnel(
                    parseInt(params.beds),
                    params.code
                );
            } else if (structureType === 'dialysis') {
                const stations = parseInt(params.stations);
                const turni = parseInt(params.shifts);
                
                personnel['Responsabile U.O. Nefrologia'] = {
                    quantita: 1,
                    note: 'Specialista in nefrologia'
                };
                personnel['Medici nefrologi'] = {
                    quantita: `${turni} + reperibilità`,
                    note: '1 per turno + reperibilità h24'
                };
                personnel['Infermieri'] = {
                    quantita: Math.ceil(stations/4) * turni,
                    note: '1 ogni 4 posti per turno'
                };
                personnel['OSS'] = {
                    quantita: Math.ceil(stations/8),
                    note: '1 ogni 8 posti'
                };
                personnel['Tecnico dialisi'] = {
                    quantita: 1,
                    note: 'Manutenzione apparecchiature'
                };
            } else if (structureType === 'riab-estensiva') {
                personnel = calculateRiabilitazioneEstensivaPersonnel(parseInt(params.pazienti));
            } else if (structureType === 'neuropsych') {
                data.organizzativi.personale_base.forEach(p => {
                    const [figura, dettagli] = p.split(':');
                    personnel[figura] = {
                        quantita: dettagli.match(/(\d+\.?\d*)/)[1],
                        note: dettagli.substring(dettagli.indexOf('(') + 1, dettagli.lastIndexOf(')'))
                    };
                });
            } else if (structureType === 'rsa') {
                personnel = calculateRSAPersonnel(parseInt(params.posti));
            }
            
            html += '<table class="requirement-table">';
            html += '<thead><tr><th>Figura Professionale</th><th>Requisito</th><th>Note</th></tr></thead>';
            html += '<tbody>';
            
            for (let figura in personnel) {
                html += '<tr>';
                html += `<td><strong>${figura}</strong></td>`;
                html += `<td>${personnel[figura].quantita}</td>`;
                html += `<td>${personnel[figura].note}`;
                if (personnel[figura].formula) {
                    html += `<br><small style="color: #6c757d;">${personnel[figura].formula}</small>`;
                }
                html += '</td>';
                html += '</tr>';
            }
            
            html += '</tbody></table>';
            
            // Altri requisiti organizzativi
            if (data.organizzativi) {
                html += '<div class="req-grid" style="margin-top: 20px;">';
                for (let category in data.organizzativi) {
                    if (category !== 'personale' && category !== 'personale_base' && category !== 'personale_base_40_pazienti') {
                        html += '<div class="req-card">';
                        html += `<h4>${formatCategoryName(category)}</h4>`;
                        html += '<ul>';
                        data.organizzativi[category].forEach(req => {
                            html += `<li>${req}</li>`;
                        });
                        html += '</ul>';
                        html += '</div>';
                    }
                }
                html += '</div>';
            }
            
            html += '</div>';
            
            // Requisiti Tecnologici
            html += '<div class="requirement-category">';
            html += '<h4>Requisiti Tecnologici</h4>';
            html += '<div class="req-grid">';
            
            for (let category in data.tecnologici) {
                html += '<div class="req-card">';
                html += `<h4>${formatCategoryName(category)}</h4>`;
                html += '<ul>';
                data.tecnologici[category].forEach(req => {
                    html += `<li>${req}</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }
            html += '</div>';
            html += '</div>';
            
            // Riepilogo
            html += '<div class="summary">';
            html += '<h4>Riepilogo Requisiti</h4>';
            html += '<div class="summary-stats">';
            
            const totalReqs = countRequirements(data);
            html += `<div class="stat-box"><div class="number">${totalReqs.strutturali}</div><div class="label">Requisiti Strutturali</div></div>`;
            html += `<div class="stat-box"><div class="number">${totalReqs.organizzativi}</div><div class="label">Requisiti Organizzativi</div></div>`;
            html += `<div class="stat-box"><div class="number">${totalReqs.tecnologici}</div><div class="label">Requisiti Tecnologici</div></div>`;
            html += `<div class="stat-box"><div class="number">${totalReqs.totale}</div><div class="label">Totale Requisiti</div></div>`;
            
            html += '</div>';
            html += '</div>';
            
            // Processo 4A
            html += `
                <div class="requirement-item" style="margin-top: 25px;">
                    <h4>Processo di Autorizzazione e Accreditamento - Modello 4A</h4>
                    <p>Il modello regionale calabrese prevede un processo graduale:</p>
                    <ol style="margin-left: 20px; margin-top: 10px; font-size: 0.875rem;">
                        <li style="margin-bottom: 8px;"><strong>Autorizzazione alla realizzazione</strong> - Verifica compatibilità con programmazione sanitaria regionale (art. 8-ter D.Lgs. 502/92)</li>
                        <li style="margin-bottom: 8px;"><strong>Autorizzazione all'esercizio</strong> - Verifica requisiti minimi strutturali, tecnologici e organizzativi (DCA 81/2016)</li>
                        <li style="margin-bottom: 8px;"><strong>Accreditamento istituzionale</strong> - Verifica requisiti ulteriori di qualità per l'erogazione di prestazioni per conto del SSR</li>
                        <li style="margin-bottom: 8px;"><strong>Accordi contrattuali</strong> - Definizione volumi e tipologie di prestazioni con remunerazione</li>
                    </ol>
                    <p style="margin-top: 12px; font-size: 0.875rem;"><strong>Nota:</strong> I requisiti indicati sono basati sul DCA 81/2016 e successive modifiche. L'OTA (Organismo Tecnicamente Accreditante) effettua le verifiche in loco entro 90 giorni dalla richiesta.</p>
                </div>
            `;
            
            html += '</div>';
            
            content.innerHTML = html;
            content.classList.remove('hidden');
        }

        function getStructureName(type) {
            const names = {
                hospital: 'Struttura Ospedaliera',
                'riab-intensiva-ospedaliera': 'Riabilitazione Intensiva Ospedaliera',
                dialysis: 'Centro Dialisi',
                rehab: 'Centro di Riabilitazione',
                laboratory: 'Laboratorio Analisi',
                neuropsych: 'Casa di Cura Neuropsichiatrica',
                'riab-estensiva': 'Riabilitazione Estensiva Extraospedaliera',
                'rsa': 'RSA - Residenza Sanitaria Assistenziale'
            };
            return names[type] || requirementsData[type]?.name || type;
        }

        function formatCategoryName(category) {
            return category
                .split('_')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ')
                .replace(/Spazi Base/, 'Spazi Base')
                .replace(/Requisiti Sicurezza/, 'Requisiti di Sicurezza')
                .replace(/Requisiti Base/, 'Requisiti Base')
                .replace(/Requisiti Camere/, 'Requisiti Camere di Degenza')
                .replace(/Attrezzature Base/, 'Attrezzature di Base')
                .replace(/Attrezzature Specifiche/, 'Attrezzature Specifiche')
                .replace(/Attrezzature Valutazione/, 'Attrezzature per Valutazione')
                .replace(/Attrezzature Assistenza/, 'Attrezzature per Assistenza')
                .replace(/Attrezzature Riabilitazione/, 'Attrezzature per Riabilitazione')
                .replace(/Degenza/, 'Area Degenza')
                .replace(/Valutazione/, 'Area Valutazione')
                .replace(/Riabilitazione/, 'Area Riabilitazione')
                .replace(/Accessibilita/, 'Accessibilità')
                .replace(/Personale Base 40 Pazienti/, 'Personale (Base 40 pazienti/die)')
                .replace(/Personale Base/, 'Personale Base')
                .replace(/Generali/, 'Requisiti Generali');
        }

        function countRequirements(data) {
            let counts = {
                strutturali: 0,
                organizzativi: 0,
                tecnologici: 0,
                totale: 0
            };
            
            // Count strutturali
            for (let cat in data.strutturali) {
                if (Array.isArray(data.strutturali[cat])) {
                    counts.strutturali += data.strutturali[cat].length;
                }
            }
            
            // Count organizzativi
            if (data.organizzativi) {
                for (let cat in data.organizzativi) {
                    if (cat === 'personale' || cat === 'personale_base' || cat === 'personale_base_40_pazienti') {
                        counts.organizzativi += Array.isArray(data.organizzativi[cat]) ? 
                            data.organizzativi[cat].length : 
                            Object.keys(data.organizzativi[cat]).length;
                    } else if (Array.isArray(data.organizzativi[cat])) {
                        counts.organizzativi += data.organizzativi[cat].length;
                    }
                }
            } else {
                counts.organizzativi = 15; // stima per strutture ospedaliere
            }
            
            // Count tecnologici
            for (let cat in data.tecnologici) {
                counts.tecnologici += data.tecnologici[cat].length;
            }
            
            counts.totale = counts.strutturali + counts.organizzativi + counts.tecnologici;
            
            return counts;
        }
    </script>
</body>
</html>

<?php include 'components/footer.php'; ?>