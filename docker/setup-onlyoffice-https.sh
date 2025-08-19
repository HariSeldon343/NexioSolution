#!/bin/bash

# Setup HTTPS for OnlyOffice Document Server
echo "Configurazione HTTPS per OnlyOffice Document Server..."

# Genera certificati self-signed nel container
echo "Generazione certificati SSL self-signed..."
docker exec nexio-onlyoffice bash -c "
    mkdir -p /var/www/onlyoffice/Data/certs
    cd /var/www/onlyoffice/Data/certs
    
    # Genera chiave privata
    openssl genrsa -out onlyoffice.key 2048
    
    # Genera certificato self-signed
    openssl req -new -x509 -key onlyoffice.key -out onlyoffice.crt -days 365 \
        -subj '/C=IT/ST=Italy/L=Milan/O=Nexio/CN=localhost'
    
    # Imposta permessi
    chmod 400 onlyoffice.key
    chmod 444 onlyoffice.crt
    
    # Copia in posizione corretta
    cp onlyoffice.crt /usr/local/share/ca-certificates/
    update-ca-certificates
    
    echo 'Certificati SSL creati con successo'
"

# Configura nginx per HTTPS nel container
echo "Configurazione Nginx per HTTPS..."
docker exec nexio-onlyoffice bash -c "
    # Backup configurazione originale
    cp /etc/nginx/conf.d/ds.conf /etc/nginx/conf.d/ds.conf.bak
    
    # Aggiungi configurazione HTTPS
    cat > /etc/nginx/conf.d/ds-ssl.conf << 'EOF'
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name localhost;
    
    ssl_certificate /var/www/onlyoffice/Data/certs/onlyoffice.crt;
    ssl_certificate_key /var/www/onlyoffice/Data/certs/onlyoffice.key;
    
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    
    location / {
        proxy_pass http://localhost:80;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }
}
EOF
    
    # Riavvia nginx
    nginx -t && nginx -s reload
    
    echo 'Nginx configurato per HTTPS'
"

echo "Setup completato! HTTPS dovrebbe essere disponibile su https://localhost:8443"
echo "Nota: Il browser mostrerà un avviso di sicurezza per il certificato self-signed"