# Dezvoltare Team Vicov

## 0.1 — registru și prima pornire
Implementat: catalog, administrare, fotografii, pedigree manual, stocare persistentă și backup.
De verificat pe VPS: construire Docker, montare volum, certificat HTTPS, autentificare și persistență după redeploy.

## Etape următoare
1. Licitații administrate exclusiv de Petru: loturi, perioade, preț de pornire și praguri configurabile pentru pașii de licitare.
2. Conturi de cumpărători și licitare atomică, cu istoric; validări la închiderea licitației și teste de concurență.
3. Încasări și decontări către proprietari: comision procentual, taxe fixe pe porumbel/licitație și reguli pentru nevânduți.
4. Import asistat din imagini de pedigree: selectarea casetelor, extragere, revizuire și salvare explicită.

De stabilit înainte de modulul de licitare: regula exactă la pragul de 1000 lei, prelungirea la oferte în ultimele minute, validarea conturilor, termenul de plată și metoda de încasare.

Păstrează dezvoltarea modulară și datele existente. Nu adăuga date demonstrative în producție. Nu implementa fluxuri financiare înainte de clarificarea regulilor.
