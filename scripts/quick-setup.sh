#!/bin/bash
#
# Quick Setup Script per Nexio Sistema Documentale
# Esegue il setup di base in modo rapido e sicuro
#

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funzioni helper
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Header
echo -e "${BLUE}"
echo "================================================"
echo "   NEXIO SISTEMA DOCUMENTALE - QUICK SETUP"
echo "================================================"
echo -e "${NC}"

# Verifica se eseguito come root
if [ "$EUID" -eq 0 ]; then 
   print_error "Non eseguire questo script come root!"
   exit 1
fi

# Verifica PHP
print_info "Verifica ambiente PHP..."
if ! command -v php &> /dev/null; then
    print_error "PHP non trovato! Installa PHP 7.4 o superiore."
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
print_success "PHP $PHP_VERSION trovato"

# Verifica estensioni PHP
print_info "Verifica estensioni PHP richieste..."
REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "json" "mbstring" "gd" "zip")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "^$ext$"; then
        print_success "✓ $ext"
    else
        print_error "✗ $ext mancante"
        MISSING_EXTENSIONS+=($ext)
    fi
done

if [ ${#MISSING_EXTENSIONS[@]} -ne 0 ]; then
    print_error "Estensioni PHP mancanti: ${MISSING_EXTENSIONS[*]}"
    print_info "Installa le estensioni mancanti prima di continuare"
    exit 1
fi

# Verifica MySQL
print_info "Verifica MySQL/MariaDB..."
if ! command -v mysql &> /dev/null; then
    print_error "MySQL non trovato! Installa MySQL 5.7+ o MariaDB 10.3+"
    exit 1
fi

# Crea directory necessarie
print_info "Creazione directory..."
DIRECTORIES=(
    "uploads/documenti"
    "uploads/templates"
    "uploads/loghi"
    "uploads/attachments"
    "documents/onlyoffice"
    "documents/exports"
    "logs"
    "temp"
    "cache"
)

for dir in "${DIRECTORIES[@]}"; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        print_success "Creata: $dir"
    else
        print_info "Esistente: $dir"
    fi
done

# Imposta permessi
print_info "Impostazione permessi..."
chmod -R 755 uploads documents logs temp cache
print_success "Permessi impostati"

# Verifica file di configurazione
print_info "Verifica configurazione..."
if [ ! -f "backend/config/config.php" ]; then
    if [ -f "backend/config/config.example.php" ]; then
        cp backend/config/config.example.php backend/config/config.php
        print_warning "Creato config.php da esempio - MODIFICA LE CREDENZIALI!"
    else
        print_error "File di configurazione mancante!"
        exit 1
    fi
else
    print_success "File di configurazione presente"
fi

# Chiedi conferma per eseguire setup completo
echo ""
print_info "Vuoi eseguire il setup completo del database? (s/n)"
read -r response

if [[ "$response" =~ ^([sS][iI]|[sS])$ ]]; then
    print_info "Esecuzione setup database..."
    php scripts/setup-nexio-documentale.php
else
    print_warning "Setup database saltato. Esegui manualmente quando pronto:"
    print_info "php scripts/setup-nexio-documentale.php"
fi

# Test rapido del sistema
echo ""
print_info "Vuoi eseguire un test rapido del sistema? (s/n)"
read -r response

if [[ "$response" =~ ^([sS][iI]|[sS])$ ]]; then
    print_info "Esecuzione test sistema..."
    php scripts/test-nexio-documentale.php
fi

# Report finale
echo ""
echo -e "${GREEN}"
echo "================================================"
echo "           SETUP COMPLETATO!"
echo "================================================"
echo -e "${NC}"

print_info "Prossimi passi:"
echo "1. Modifica backend/config/config.php con le tue credenziali"
echo "2. Configura il web server (Apache/Nginx)"
echo "3. Imposta SSL per HTTPS"
echo "4. Configura i cron job per email e backup"
echo "5. Accedi con username: admin, password: admin123"
echo ""
print_warning "IMPORTANTE: Cambia la password di default dopo il primo accesso!"

# Crea file di log setup
echo "Setup eseguito il $(date)" >> logs/setup.log

exit 0