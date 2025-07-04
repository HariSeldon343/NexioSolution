<?php
/**
 * Database Template Analysis Script
 * 
 * This script analyzes the database to find template-related tables and data
 * specifically looking for rich content templates with headers, footers, and logos
 */

// Include database configuration
require_once 'backend/config/database.php';

echo "<h1>Database Template Analysis</h1>\n";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
.section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; background-color: #f9f9f9; }
.data-preview { max-width: 500px; max-height: 200px; overflow: auto; background: #fff; padding: 10px; border: 1px solid #ddd; }
.html-content { font-size: 12px; color: #666; }
.important { background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 4px; }
</style>\n";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Step 1: Find all tables with 'template' in the name
    echo "<div class='section'>";
    echo "<h2>1. Tables containing 'template'</h2>";
    $stmt = $conn->prepare("SHOW TABLES LIKE '%template%'");
    $stmt->execute();
    $template_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($template_tables)) {
        echo "<p>No tables found with 'template' in the name.</p>";
    } else {
        echo "<ul>";
        foreach ($template_tables as $table) {
            echo "<li><strong>$table</strong></li>";
        }
        echo "</ul>";
    }
    echo "</div>";
    
    // Step 2: Find all tables with 'moduli' in the name
    echo "<div class='section'>";
    echo "<h2>2. Tables containing 'moduli'</h2>";
    $stmt = $conn->prepare("SHOW TABLES LIKE '%moduli%'");
    $stmt->execute();
    $moduli_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($moduli_tables)) {
        echo "<p>No tables found with 'moduli' in the name.</p>";
    } else {
        echo "<ul>";
        foreach ($moduli_tables as $table) {
            echo "<li><strong>$table</strong></li>";
        }
        echo "</ul>";
    }
    echo "</div>";
    
    // Step 3: Show structure of all template-related tables
    $all_template_tables = array_merge($template_tables, $moduli_tables);
    
    foreach ($all_template_tables as $table) {
        echo "<div class='section'>";
        echo "<h2>3. Structure of table: $table</h2>";
        
        try {
            $stmt = $conn->prepare("DESCRIBE `$table`");
            $stmt->execute();
            $columns = $stmt->fetchAll();
            
            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>{$column['Field']}</td>";
                echo "<td>{$column['Type']}</td>";
                echo "<td>{$column['Null']}</td>";
                echo "<td>{$column['Key']}</td>";
                echo "<td>{$column['Default']}</td>";
                echo "<td>{$column['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error describing table $table: " . $e->getMessage() . "</p>";
        }
        
        echo "</div>";
    }
    
    // Step 4: Check for data in template tables and look for rich content
    foreach ($all_template_tables as $table) {
        echo "<div class='section'>";
        echo "<h2>4. Data analysis for table: $table</h2>";
        
        try {
            // Get row count
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM `$table`");
            $stmt->execute();
            $count = $stmt->fetch()['count'];
            
            echo "<p><strong>Total rows:</strong> $count</p>";
            
            if ($count > 0) {
                // Get sample data
                $stmt = $conn->prepare("SELECT * FROM `$table` LIMIT 5");
                $stmt->execute();
                $sample_data = $stmt->fetchAll();
                
                if (!empty($sample_data)) {
                    echo "<h3>Sample Data (first 5 rows):</h3>";
                    echo "<table>";
                    
                    // Headers
                    echo "<tr>";
                    foreach (array_keys($sample_data[0]) as $header) {
                        echo "<th>$header</th>";
                    }
                    echo "</tr>";
                    
                    // Data rows
                    foreach ($sample_data as $row) {
                        echo "<tr>";
                        foreach ($row as $key => $value) {
                            // Check if this looks like HTML content
                            if (is_string($value) && (strlen($value) > 100 || strpos($value, '<') !== false)) {
                                $preview = htmlspecialchars(substr($value, 0, 100)) . (strlen($value) > 100 ? '...' : '');
                                echo "<td><div class='data-preview html-content'>$preview</div></td>";
                            } else {
                                echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
                            }
                        }
                        echo "</tr>";
                    }
                    echo "</table>";
                    
                    // Look specifically for fields that might contain rich template content
                    echo "<h3>Rich Content Analysis:</h3>";
                    foreach ($sample_data as $index => $row) {
                        echo "<h4>Row " . ($index + 1) . ":</h4>";
                        foreach ($row as $field => $value) {
                            if (is_string($value) && (
                                strpos($value, '<header') !== false ||
                                strpos($value, '<footer') !== false ||
                                strpos($value, 'logo') !== false ||
                                strpos($value, '<img') !== false ||
                                strpos($value, 'company') !== false ||
                                strpos($value, 'intestazione') !== false ||
                                strpos($value, 'piè') !== false ||
                                strlen($value) > 500
                            )) {
                                echo "<p><strong>Field '$field' contains rich content:</strong></p>";
                                echo "<div class='data-preview'>";
                                echo "<pre>" . htmlspecialchars($value) . "</pre>";
                                echo "</div>";
                            }
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error analyzing data in table $table: " . $e->getMessage() . "</p>";
        }
        
        echo "</div>";
    }
    
    // Step 5: Look for other tables that might contain template data
    echo "<div class='section'>";
    echo "<h2>5. Other potential template-related tables</h2>";
    
    $potential_tables = [
        'documenti' => 'Documents table - might have template references',
        'frontespizio' => 'Cover page templates',
        'aziende' => 'Companies - might have logo/header data',
        'classificazioni' => 'Classifications - might have template associations'
    ];
    
    foreach ($potential_tables as $table => $description) {
        try {
            $stmt = $conn->prepare("SHOW TABLES LIKE '$table'");
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                echo "<h3>Table: $table ($description)</h3>";
                
                // Check structure
                $stmt = $conn->prepare("DESCRIBE `$table`");
                $stmt->execute();
                $columns = $stmt->fetchAll();
                
                $interesting_columns = [];
                foreach ($columns as $column) {
                    $field = $column['Field'];
                    if (strpos($field, 'template') !== false ||
                        strpos($field, 'header') !== false ||
                        strpos($field, 'footer') !== false ||
                        strpos($field, 'logo') !== false ||
                        strpos($field, 'html') !== false ||
                        strpos($field, 'contenuto') !== false) {
                        $interesting_columns[] = $field;
                    }
                }
                
                if (!empty($interesting_columns)) {
                    echo "<p><strong>Interesting columns found:</strong> " . implode(', ', $interesting_columns) . "</p>";
                    
                    // Sample data from interesting columns
                    $columns_str = implode(', ', array_map(function($col) { return "`$col`"; }, $interesting_columns));
                    $stmt = $conn->prepare("SELECT id, $columns_str FROM `$table` LIMIT 3");
                    $stmt->execute();
                    $data = $stmt->fetchAll();
                    
                    if (!empty($data)) {
                        echo "<table>";
                        echo "<tr><th>ID</th>";
                        foreach ($interesting_columns as $col) {
                            echo "<th>$col</th>";
                        }
                        echo "</tr>";
                        
                        foreach ($data as $row) {
                            echo "<tr>";
                            echo "<td>{$row['id']}</td>";
                            foreach ($interesting_columns as $col) {
                                $value = $row[$col] ?? '';
                                if (strlen($value) > 50) {
                                    echo "<td><div class='data-preview'>" . htmlspecialchars(substr($value, 0, 50)) . "...</div></td>";
                                } else {
                                    echo "<td>" . htmlspecialchars($value) . "</td>";
                                }
                            }
                            echo "</tr>";
                        }
                        echo "</table>";
                    }
                }
            }
        } catch (Exception $e) {
            echo "<p>Table $table not found or error: " . $e->getMessage() . "</p>";
        }
    }
    echo "</div>";
    
    // Step 6: Search for any table with columns containing 'template', 'header', 'footer', or 'logo'
    echo "<div class='section'>";
    echo "<h2>6. All tables with template-related columns</h2>";
    
    $stmt = $conn->prepare("SHOW TABLES");
    $stmt->execute();
    $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($all_tables as $table) {
        try {
            $stmt = $conn->prepare("DESCRIBE `$table`");
            $stmt->execute();
            $columns = $stmt->fetchAll();
            
            $template_columns = [];
            foreach ($columns as $column) {
                $field = strtolower($column['Field']);
                if (strpos($field, 'template') !== false ||
                    strpos($field, 'header') !== false ||
                    strpos($field, 'footer') !== false ||
                    strpos($field, 'logo') !== false ||
                    strpos($field, 'intestazione') !== false ||
                    strpos($field, 'modulo') !== false) {
                    $template_columns[] = $column['Field'];
                }
            }
            
            if (!empty($template_columns)) {
                echo "<h3>$table</h3>";
                echo "<p>Template-related columns: " . implode(', ', $template_columns) . "</p>";
            }
            
        } catch (Exception $e) {
            // Skip tables we can't access
            continue;
        }
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 20px; border: 1px solid red;'>";
    echo "<h2>Database Connection Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

    // Step 7: Quick summary queries
    echo "<div class='important'>";
    echo "<h2>Quick Summary - Key Findings</h2>";
    
    // Check if moduli_template exists and has data
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM moduli_template");
        $stmt->execute();
        $template_count = $stmt->fetch()['count'];
        echo "<p><strong>Templates found:</strong> $template_count records in moduli_template table</p>";
        
        if ($template_count > 0) {
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN header_content IS NOT NULL AND header_content != '' THEN 1 ELSE 0 END) as with_headers,
                    SUM(CASE WHEN footer_content IS NOT NULL AND footer_content != '' THEN 1 ELSE 0 END) as with_footers,
                    SUM(CASE WHEN logo_header IS NOT NULL THEN 1 ELSE 0 END) as with_header_logos,
                    SUM(CASE WHEN logo_footer IS NOT NULL THEN 1 ELSE 0 END) as with_footer_logos
                FROM moduli_template
            ");
            $stmt->execute();
            $summary = $stmt->fetch();
            
            echo "<ul>";
            echo "<li>Templates with custom headers: {$summary['with_headers']}</li>";
            echo "<li>Templates with custom footers: {$summary['with_footers']}</li>";
            echo "<li>Templates with header logos: {$summary['with_header_logos']}</li>";
            echo "<li>Templates with footer logos: {$summary['with_footer_logos']}</li>";
            echo "</ul>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error checking moduli_template: " . $e->getMessage() . "</p>";
    }
    
    // Check company data
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM aziende WHERE logo IS NOT NULL");
        $stmt->execute();
        $company_logos = $stmt->fetch()['count'];
        echo "<p><strong>Companies with logos:</strong> $company_logos</p>";
    } catch (Exception $e) {
        echo "<p>Aziende table not accessible</p>";
    }
    
    echo "</div>";

echo "<div class='section'>";
echo "<h2>Analysis Complete</h2>";
echo "<p>This analysis searched for:</p>";
echo "<ul>";
echo "<li>Tables with 'template' or 'moduli' in their names</li>";
echo "<li>Table structures and column definitions</li>";
echo "<li>Sample data from template tables</li>";
echo "<li>Rich content analysis (HTML, headers, footers, logos)</li>";
echo "<li>Other tables that might contain template-related data</li>";
echo "<li>All columns across all tables that might relate to templates</li>";
echo "</ul>";
echo "<p><strong>Key Finding:</strong> The rich template data you're looking for is likely in the <strong>moduli_template</strong> table, specifically in the <strong>header_content</strong> and <strong>footer_content</strong> columns.</p>";
echo "</div>";
?>