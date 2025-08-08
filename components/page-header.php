<?php
/**
 * Component: Page Header Standardizzato Nexio
 * 
 * Utilizzo:
 * include 'components/page-header.php'; 
 * renderPageHeader($title, $subtitle, $icon);
 * 
 * @param string $title - Titolo principale della pagina
 * @param string $subtitle - Sottotitolo/descrizione
 * @param string $icon - Nome icona FontAwesome (senza "fas fa-")
 */

function renderPageHeader($title, $subtitle, $icon) {
    echo '<div class="page-header">';
    echo '<h1><i class="fas fa-' . htmlspecialchars($icon) . '"></i> ' . htmlspecialchars($title) . '</h1>';
    echo '<div class="page-subtitle">' . htmlspecialchars($subtitle) . '</div>';
    echo '</div>';
}
?>

