# Team Vicov

Prima versiune funcțională, 0.1.0: catalog public, administrare protejată, registru de porumbei, fotografii și pedigree manual pe două generații. Interfață în română, adaptată pentru telefon.

## Ce funcționează

- Adăugare și editare porumbei; serie unică, nume, sex, culoare, proprietar, rezultate, note.
- Fișe private implicit; publicare controlată în catalog. Proprietarul și notele interne nu sunt expuse public.
- Fotografii JPG/PNG/WebP de maximum 5 MB, păstrate în baza de date.
- Părinți selectați din registru; verificare sex și prevenirea ciclurilor genealogice. Părinții privați nu apar în catalog.
- Autentificare administrator, sesiuni de 8 ore, limitare încercări, verificarea originii cererilor și protecție împotriva suprascrierii unei fișe editate între timp.
- SQLite persistent, interogări parametrizate, endpoint `/healthz`, comandă de backup.

Nu sunt implementate încă: licitare, conturi de cumpărători, plăți/decontări, comisioane, notificări, import asistat/OCR de pedigree. Pagina de licitații indică explicit că modulul este în pregătire. Nu există date demonstrative sau parole implicite în producție.

## Coolify — prima instalare

1. În aplicație, General → Build strategy: **Dockerfile**. Director `/`, fișier `/Dockerfile`, port expus `3000`. Dockerfile fixează versiunea Node utilizată la testare; nu există dependențe npm de instalat.
2. În **Domains**, folosește adresa temporară existentă, cu prefix **https://**. Domeniul trebuie să rezolve spre VPS, iar porturile 80 și 443 să fie accesibile pentru certificat.
3. În **Environment Variables**, adaugă variabile disponibile la rulare:
   - `APP_ORIGIN=https://ADRESA-TEMPORARA-EXACTA` (fără slash la final)
   - `ADMIN_PASSWORD=` o parolă unică, minimum 16 caractere, aleasă în Coolify. Nu o trimite în conversație și nu o salva în GitHub.
   - `DATA_DIR=/app/data` (implicit în Dockerfile)
4. În **Persistent Storage**, adaugă un volume cu nume `teamvicov-data`, destination `/app/data`. Aplicația rulează ca utilizator `node` (UID 1000). Folosește volume Docker; dacă alegi un bind mount, directorul trebuie să poată fi scris de UID 1000.
5. După configurare, **Deploy**. Accesează pagina publică și `/admin`, apoi autentifică-te cu parola setată.
6. Configurează Healthcheck pe `GET /healthz`, port 3000, dacă activezi verificarea din Coolify.

**Nu publica fără volumul persistent.** Fotografiile și registrul sunt stocate în `/app/data/teamvicov.sqlite`. La schimbarea domeniului actualizează și `APP_ORIGIN`, apoi repornește aplicația. `NODE_ENV=production` este setat în Dockerfile; autentificarea cere HTTPS în producție. Nu folosi mai multe replici cu această bază SQLite locală.

## Dezvoltare

Necesită Node 24.14+ (testat cu 24.19.0). Nu există pachete externe.

```bash
export ADMIN_PASSWORD='o-parola-locala-de-minimum-16-caractere'
export APP_ORIGIN='http://localhost:3000'
npm start
```

`npm test` rulează teste de integrare izolate: acces neautorizat, origini externe, confidențialitate, duplicate, pedigree circular, editări concurente, imagini, logout și persistență după repornire. Datele de test sunt create în directoare temporare și șterse la final.

## Backup și restaurare

În terminalul containerului aplicației:

```bash
npm run backup
```

Creează un snapshot SQLite consistent, inclusiv fotografii, în `/app/data/backups/`. Copiază periodic backupurile în afara VPS-ului; o copie pe același disc nu protejează împotriva pierderii serverului. Backupurile conțin și date private.

Pentru restaurare: oprește aplicația, păstrează o copie a întregului director actual, înlocuiește `teamvicov.sqlite` cu snapshotul ales și îndepărtează fișierele auxiliare vechi `teamvicov.sqlite-wal` și `teamvicov.sqlite-shm` înainte de repornire. Păstrează drepturile de scriere pentru UID 1000. Verifică registrul după pornire. Nu înlocui baza în timp ce aplicația rulează.

## Structură

- `src/server.js` — HTTP, autentificare, API, fotografii.
- `src/db.js` — schema și validarea genealogiei.
- `src/backup.js` — snapshot consistent.
- `public/` — catalog, administrare, interfață responsive.
- `test/` — verificări de integrare.

Documentație tehnică: https://nodejs.org/docs/latest-v24.x/api/sqlite.html și https://coolify.io/docs/applications/builds/dockerfile
