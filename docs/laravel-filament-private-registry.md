# Domeniul primei versiuni

Registru privat pentru crescătoria Team Vicov. Laravel și Filament sunt baza; logica genealogică este specifică aplicației.

Starea funcționalităților implementate și instalarea sunt documentate în [README](../README.md).

## Modelul datelor

- `pigeons`: o fișă pentru fiecare porumbel sau strămoș de referință, cu tată și mamă opționali.
- `categories`, `compartments`: nomenclatoare editabile.
- `status_changes`: istoric cu utilizator și moment.
- `loft_settings`: nume, logo privat și antet pedigree.
- `users`: numai conturile cu `is_admin` pot accesa panoul.
- `registry_locks`: rând folosit pentru serializarea modificărilor genealogice pe PostgreSQL.

Fotografiile/PDF-urile sunt în volumul privat, iar căile sunt în baza de date. Nu rula `storage:link` pentru acest proiect.
Arhivarea păstrează fișa în arbore; ștergerea fișelor de porumbei este blocată.

## Pașii următori

1. Verificarea noii instalări Coolify, a HTTPS, persistenței și restaurării unui backup.
2. Importul celor 18 fișe WooCommerce după verificarea previzualizării; încărcarea fotografiilor și completarea părinților.
3. Ajustarea fișei și a formatului pedigree după documentele reale ale crescătoriei.
4. Perechi pe sezon, cuiburi, ouă, eclozare și pui, dacă sunt necesare.
5. Catalog public separat, numai cu porumbei selectați și câmpuri explicit permise pentru publicare.

Nicio licitație, înscriere publică sau administrare de crescătorii terțe în această etapă.
