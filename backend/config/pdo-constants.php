<?php
/**
 * Definisce le costanti PDO se non sono già definite
 * Questo risolve il problema quando PDO non è caricato correttamente
 */

// Se PDO non è caricato, definiamo le costanti manualmente
if (!class_exists('PDO')) {
    // Costanti PDO di base
    define('PDO::ATTR_ERRMODE', 3);
    define('PDO::ERRMODE_EXCEPTION', 2);
    define('PDO::ATTR_DEFAULT_FETCH_MODE', 19);
    define('PDO::FETCH_ASSOC', 2);
    define('PDO::ATTR_EMULATE_PREPARES', 20);
    define('PDO::MYSQL_ATTR_INIT_COMMAND', 1002);
}
?>