<?php
/**
 * DomPDF Generator per esportazione documenti
 * Wrapper per la generazione PDF con DomPDF
 */

class DompdfGenerator {
    
    public function generateFromHTML($html, $title = 'Documento') {
        // Se DomPDF è disponibile, lo usa, altrimenti fallback
        if (class_exists('Dompdf\Dompdf')) {
            return $this->generateWithDompdf($html, $title);
        } else {
            return $this->generateWithTCPDF($html, $title);
        }
    }
    
    private function generateWithDompdf($html, $title) {
        $dompdf = new \Dompdf\Dompdf();
        
        // Configurazione
        $dompdf->getOptions()->set('isRemoteEnabled', false);
        $dompdf->getOptions()->set('isHtml5ParserEnabled', true);
        
        // HTML completo con stili
        $fullHtml = $this->buildFullHTML($html, $title);
        
        $dompdf->loadHtml($fullHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf->output();
    }
    
    private function generateWithTCPDF($html, $title) {
        // Fallback semplice se DomPDF non è disponibile
        // Crea un HTML che può essere convertito manualmente
        $fullHtml = $this->buildFullHTML($html, $title);
        
        // Per ora ritorna HTML formattato per stampa
        // In produzione si dovrebbe installare una libreria PDF
        return $fullHtml;
    }
    
    private function buildFullHTML($content, $title) {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        @page {
            size: A4;
            margin: 2.5cm 1.9cm;
        }
        
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #000;
            margin: 0;
            padding: 0;
        }
        
        h1 {
            font-size: 18pt;
            margin: 24pt 0 12pt 0;
            page-break-after: avoid;
            font-weight: bold;
        }
        
        h2 {
            font-size: 16pt;
            margin: 18pt 0 6pt 0;
            page-break-after: avoid;
            font-weight: bold;
        }
        
        h3 {
            font-size: 14pt;
            margin: 12pt 0 6pt 0;
            page-break-after: avoid;
            font-weight: bold;
        }
        
        p {
            margin: 0 0 12pt 0;
            text-align: justify;
        }
        
        ul, ol {
            margin: 12pt 0 12pt 24pt;
        }
        
        li {
            margin: 3pt 0;
        }
        
        table {
            margin: 12pt 0;
            border-collapse: collapse;
            width: 100%;
        }
        
        table td, table th {
            border: 1pt solid #000;
            padding: 6pt;
            vertical-align: top;
        }
        
        table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        strong, b {
            font-weight: bold;
        }
        
        em, i {
            font-style: italic;
        }
        
        u {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    ' . $this->cleanHTMLForPDF($content) . '
</body>
</html>';
    }
    
    private function cleanHTMLForPDF($html) {
        // Pulisce HTML per PDF
        
        // Converte page breaks
        $html = preg_replace('/<div[^>]*class="mce-pagebreak"[^>]*>.*?<\/div>/is', '<div class="page-break"></div>', $html);
        
        // Rimuove attributi style complessi che possono causare problemi
        $html = preg_replace('/style="[^"]*"/i', '', $html);
        
        // Pulisce tag non supportati
        $allowedTags = '<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><table><tr><td><th><div><span>';
        $html = strip_tags($html, $allowedTags);
        
        return $html;
    }
}