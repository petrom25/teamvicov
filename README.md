# Team Vicov — registrul privat

Laravel 13 + Filament 5, PHP 8.4. Ramură de dezvoltare `laravel-filament`.
Aplicația de pe `main` este independentă. Această ramură înlocuiește aplicația Node cu Laravel numai într-o instalare nouă.

## Funcționalități implementate

- Autentificare administrativă fără înscriere publică; comandă interactivă pentru primul administrator.
- Registru: serie păstrată ca text, nume, sex, an, țară, culoare, origine, categorie, compartiment, note și rezultate.
- Porumbei proprii / strămoși de referință, arhivare și istoric al stării.
- Părinți legați între fișe; validare pentru cicluri, sex incompatibil și serii duplicate.
- Pedigree 3–5 generații, tipărire și salvare PDF din browser. Arborele din baza de date nu este limitat la cinci generații.
- Fotografii și PDF-uri pe stocare privată; descărcare numai după autentificare.
- Categorii, compartimente, numele crescătoriei, logo și antet configurabile din panou.
- Import WooCommerce în română: previzualizare, serii/sex/culoare/descrieri, omitere duplicate, tranzacție. Nu importă automat imaginile externe sau părinții și nu publică fișele.
- Export CSV al registrului; fotografiile și backupul complet sunt separate de acest export.

Toate datele sunt private. Catalogul public, perechile/cuiburile și rezultatele structurate sunt etape ulterioare. Meniul public și paginile de prezentare nu există încă.

## Local

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan teamvicov:admin
php artisan serve
```

Pentru SQLite, creează `database/database.sqlite` înainte de migrare dacă instalarea Composer nu l-a creat.
Deschide `/admin`. Filament își publică propriile resurse; această versiune nu necesită un build npm.

```sh
php artisan test
```

## Coolify — aplicație nouă de test

1. Creează o resursă PostgreSQL separată, de exemplu `teamvicov-registry-test`. Pornește-o și notează hostname-ul intern, baza, utilizatorul și parola din Coolify. Fără port public pentru baza de date.
2. Creează o aplicație **Git Repository (with GitHub App)** din `petrom25/teamvicov`, ramura **laravel-filament**.
3. Build Pack **Dockerfile**, Base Directory `/`, Dockerfile Location `/Dockerfile`, port intern **80**.
4. Alege un domeniu **HTTPS** separat pentru testare. Configurează variabilele de mai jos **Runtime**, nu Buildtime.
5. Persistent Storage: volum nou `teamvicov-registry-storage`, Destination Path **`/var/www/html/storage`**. Nu reutiliza volumul aplicației Node.
6. Deploy. Entrypoint-ul execută migrările și pornește Apache. Healthcheck: `/up`, port 80.
7. În Terminal-ul **aplicației Laravel**, rulează `php artisan teamvicov:admin`. Completează numele, emailul și parola ascunsă în terminal. Nu există parolă implicită.
8. Deschide `https://DOMENIUL-DE-TEST/admin`. Testează o fișă, fotografia și pedigree-ul, apoi un redeploy pentru verificarea persistenței.

Variabile Runtime (înlocuiește valorile marcate; nu le salva în Git):

```dotenv
APP_NAME="Team Vicov"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:CHEIE_GENERATA_LOCAL
APP_URL=https://DOMENIUL-DE-TEST
APP_LOCALE=ro
APP_FALLBACK_LOCALE=en
DB_CONNECTION=pgsql
DB_HOST=HOSTNAME_INTERN_DIN_COOLIFY
DB_PORT=5432
DB_DATABASE=BAZA_DIN_COOLIFY
DB_USERNAME=UTILIZATORUL_DIN_COOLIFY
DB_PASSWORD=PAROLA_DIN_COOLIFY
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
LOG_CHANNEL=stderr
LOG_LEVEL=warning
```

Generează APP_KEY în terminalul VPS, fără a o trimite în conversație:

```sh
printf 'base64:'
openssl rand -base64 32
```

Copiază rezultatul pe o singură linie în APP_KEY și păstrează cheia la toate redeploy-urile. `ADMIN_PASSWORD` și `APP_ORIGIN` din aplicația Node nu sunt folosite aici.
Serverul web este destinat rețelei interne Coolify, în spatele proxy-ului HTTPS; nu publica direct portul containerului pe internet.

Sursa pașilor Dockerfile: [documentația Coolify](https://coolify.io/docs/applications/builds/dockerfile).

## Backup și restaurare

Exportul CSV este pentru portabilitate, **nu** este un backup complet și nu se reimportă prin importatorul WooCommerce. ID-urile părinților sunt păstrate în export.

Backupul complet include:
- dump PostgreSQL (cu utilizatori, relații și istoric);
- întregul volum `teamvicov-registry-storage`, inclusiv `app/private`;
- APP_KEY și celelalte variabile Runtime, păstrate separat în seiful de parole;
- commitul aplicației care a produs backupul.

Înainte de date reale, configurează backupuri PostgreSQL programate în Coolify către stocare externă și backup separat al volumului. Pentru o copie consistentă, oprește scrierile (`php artisan down`), realizează dumpul bazei și copia volumului, apoi `php artisan up`. Nu lăsa aplicația în mentenanță dacă un backup eșuează.

Restaurare: folosește o bază și un volum noi într-o aplicație izolată; restaurează dumpul și fișierele, setează cheia originală, pornește aceeași versiune, apoi verifică autentificarea, numărul de porumbei, legăturile de rudenie și fotografiile. Treci la o versiune nouă numai după verificarea restaurării. Backupurile automate și restaurarea nu sunt configurate de simpla instalare a codului.

## Limitele verificării locale

Testele automatizate folosesc SQLite. Configurația Docker/PostgreSQL trebuie verificată în noua instalare Coolify; mediul de dezvoltare nu are Docker sau PostgreSQL disponibil. Blocarea globală a scrierilor genealogice folosește `SELECT FOR UPDATE` pe PostgreSQL. Nu există încă o probă automată cu două procese PostgreSQL concurente.
