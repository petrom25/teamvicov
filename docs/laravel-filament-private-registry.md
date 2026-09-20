# Team Vicov — registru privat Laravel + Filament

Status: decizie de arhitectură aprobată de utilizator la 20 septembrie 2026.
Această ramură conține încă aplicația Node existentă. Laravel nu este instalat.
Nu conecta ramura la producție înainte de instalare, validare și migrare verificată.

## Obiectiv
Administrarea privată a crescătoriei Team Vicov. Publicarea selectivă va fi o etapă separată.
Fără licitații, conturi pentru alți crescători sau decontări în această etapă.

## Bază tehnică
Laravel + Filament Panel Builder, bază relațională PostgreSQL în producție, fișiere pe disc privat persistent.
Alegerea versiunilor exacte și fișierul composer.lock trebuie validate prin Composer și teste, nu presupuse.
Documentație: https://filamentphp.com/docs/5.x/introduction/installation
https://laravel.com/framework/docs/13.x/installation

## Prima versiune utilizabilă
- Un administrator autorizat explicit, autentificare cu limitare de încercări; fără înregistrare publică.
- Registru: serie, țară, an, nume, sex, culoare, origine, categorie, compartiment, note.
- Categoriile și compartimentele editabile în Filament, fără schimbarea codului.
- Evidență distinctă: porumbel propriu / strămoș de referință; strămoșii nu măresc efectivul.
- Situație: în crescătorie, împrumutat, transferat, pierdut, decedat; istoric datat.
- Părinți selectați din registru; adăugare rapidă de strămoși incompleți, fără inventarea seriilor.
- Pedigree navigabil și tipăribil, cu 3–5 generații selectabile. Stocarea nu limitează genealogia la cinci generații.
- Fotografii multiple și documente pedigree private, accesibile numai prin autorizare pe server.
- Setări crescătorie: denumire, logo, date pentru antet pedigree.
- Import WooCommerce cu previzualizare și raport; niciun import/publicare automată la pornire.
- Export de date și backup documentat, cu probă de restaurare.

## Model de date propus
pigeons: identificator intern stabil, serie originală, cheie serie normalizată unică dacă există,
țară, an, nume, sex, culoare, origine, categorie, compartiment, tip evidență, situație,
father_id, mother_id, note private, versiune pentru protecția editărilor concurente.
Părinții sunt chei străine către pigeons. Nu crea coloane separate pentru fiecare bunic.
Pentru strămoși fără serie se folosește ID intern; nu se fabrică un inel.
categories, compartments: nomenclatoare editabile.
pigeon_status_events: situație anterioară/nouă, dată efectivă, motiv, autor.
pigeon_media: porumbel, tip (foto/ochi/pedigree/document), cale privată,
tip MIME verificat, dimensiune, ordine, fotografie principală, proveniență.
loft_settings: identitate și antet tipărire.
import_batches/import_rows: fișier sursă, verificări, corespondență ID WooCommerce -> porumbel și raport.
audit_events: autor, operațiune, obiect, dată; fără parole, tokenuri sau sesiuni.

## Integritate genealogică
- Respinge auto-parentarea, părinții identici și ciclurile.
- Acceptă consangvinizarea validă: același strămoș poate apărea pe ramuri diferite.
- Verifică compatibilitatea sexului; necunoscut este permis.
- Semnalează anii incompatibili; nu inventează date.
- Identificarea duplicatelor păstrează zerourile inițiale și semnificația seriilor străine.
- Frați, semifrați și descendenți se calculează din relațiile parentale.
- Arhivează fișele folosite în genealogie; fără ștergere în cascadă a ascendenței.
- Modificarea relațiilor și istoricul se salvează tranzacțional; test separat pentru editări concurente.

## Confidențialitate
Toate datele și fișierele sunt private. Nu crea legătură publică storage pentru documentele crescătoriei.
Interzice accesul neautorizat inclusiv la fișiere, căutare, export și linkuri de pedigree.
Catalogul ulterior va folosi o proiecție explicită a câmpurilor aprobate; nu va serializa modelul integral.
Un părinte privat nu devine public prin publicarea puiului.
Logo-ul și pedigree-urile nu includ automat note interne.
Publicarea va avea previzualizare și va fi dezactivată în prima versiune.

## Importul furnizat
Fișier complet: wc-product-export-19-9-2026-1789841606428.csv.
Analiză anterioară: 18 înregistrări, 17 cu sex/culoare, 45 referințe la 38 URL-uri de imagini distincte.
Al doilea CSV conține numai aceleași imagini.
Fișierele sursă nu se includ în repo public.
Titlul produsului corespunde seriei în acest eșantion. Revalidează la import.
Păstrează atributele de origine; nu confunda crescătorul de origine cu proprietarul.
Metadatele istorice de licitație nu activează funcții comerciale.
Fotografiile se descarcă numai din destinații publice aprobate, cu limite de timp/mărime,
verificare a redirecționărilor și blocarea accesului la rețele private.
Pedigree-urile fotografiate se clasifică și se verifică; nu presupune părinții din poziția imaginii.
Repetarea importului nu dublează fișele și nu suprascrie editările fără o alegere explicită.

## Etapa următoare
Perechi pe sezon, ponte, eclozări, pui; rezultate structurate.
Acestea nu sunt implementate prin acest document.

## Verificări înainte de VPS
1. Composer install din lockfile, migrări pe bază de test PostgreSQL și compilarea resurselor.
2. Acces privat pentru rute și documente, restricții administrator, test de sesiune/logout.
3. Pedigree: cicluri respinse, strămoși comuni permiși, sex și editări concurente.
4. Import: previzualizare, duplicate, caractere românești, păstrarea seriilor și fișiere inaccesibile.
5. Verificare Filament pe desktop și telefon: creare/editare, filtrare, fișiere, pedigree.
6. Backup și restaurare demonstrate.
7. Aplicație Coolify separată, volum și bază separate, HTTPS și APP_KEY stabil.
8. Migrare explicită din vechea aplicație/CSV; nu înlocui fișierul SQLite cu o bază Laravel.
9. Publicarea numai după verificarea rezultatului; main rămâne aplicația existentă până atunci.

## Blocaj local constatat
PHP și Composer nu sunt disponibile în mediul de lucru la inițiere.
Instalarea prin apt a eșuat din cauza restricțiilor de permisiuni.
Accesul la Packagist a răspuns HTTP 200.
Sunt necesare un runtime PHP compatibil și Composer pentru instalare și testare.
