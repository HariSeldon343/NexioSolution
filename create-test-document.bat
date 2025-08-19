@echo off
echo ========================================
echo   OnlyOffice Test Document Creator
echo ========================================
echo.

REM Verifica se la directory esiste
if not exist "C:\xampp\htdocs\piattaforma-collaborativa\documents\onlyoffice" (
    echo Creando directory documents\onlyoffice...
    mkdir "C:\xampp\htdocs\piattaforma-collaborativa\documents\onlyoffice"
)

REM Naviga alla directory
cd /d "C:\xampp\htdocs\piattaforma-collaborativa"

REM Crea un documento DOCX di test valido usando PHP
echo Creando documento DOCX di test valido...
C:\xampp\php\php.exe -r "<?php $zip = new ZipArchive(); $filename = 'documents/onlyoffice/test_' . time() . '.docx'; if ($zip->open($filename, ZipArchive::CREATE) === TRUE) { $zip->addFromString('[Content_Types].xml', '<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Types xmlns=\"http://schemas.openxmlformats.org/package/2006/content-types\"><Default Extension=\"rels\" ContentType=\"application/vnd.openxmlformats-package.relationships+xml\"/><Default Extension=\"xml\" ContentType=\"application/xml\"/><Override PartName=\"/word/document.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml\"/></Types>'); $zip->addFromString('_rels/.rels', '<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\"><Relationship Id=\"rId1\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument\" Target=\"word/document.xml\"/></Relationships>'); $zip->addFromString('word/document.xml', '<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><w:document xmlns:w=\"http://schemas.openxmlformats.org/wordprocessingml/2006/main\"><w:body><w:p><w:r><w:t>Test Document for OnlyOffice</w:t></w:r></w:p></w:body></w:document>'); $zip->addFromString('word/_rels/document.xml.rels', '<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\"></Relationships>'); $zip->close(); echo 'Documento creato: ' . $filename; } else { echo 'Errore nella creazione del documento'; } ?>"

echo.
echo Verificando i file esistenti...
dir /b "C:\xampp\htdocs\piattaforma-collaborativa\documents\onlyoffice\*.docx"

echo.
echo ========================================
echo Test Accesso al Documento
echo ========================================
echo.
echo Prova questi URL nel browser:
echo.
echo 1. http://localhost/piattaforma-collaborativa/documents/onlyoffice/new.docx
echo 2. http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/new.docx
echo.
echo Se usi Docker, verifica con:
echo docker exec -it onlyoffice-document-server curl -I http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/new.docx
echo.
pause