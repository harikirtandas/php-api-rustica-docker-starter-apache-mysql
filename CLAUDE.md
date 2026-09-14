# php-api-rustica-docker-starter-apache-mysql

- Cuarto de la serie PHP. **El andamiaje Docker sigue igual** (Dockerfile,
  `docker/php/php.ini`, `docker-compose.yml`, `Makefile`, `.gitignore`): no se
  toca. Lo único nuevo en `docker/` es `docker/apache/000-default.conf`
  (DocumentRoot a `public/`, `CGIPassAuth On`, `AllowOverride None` — no hay
  `.htaccess` que necesite overrides).
- **Filosofía**: sin router, sin clases, sin front controller. Cada archivo de
  `src/public/` es un endpoint completo (valida método, autentica, toca la DB,
  responde) y la URL es literalmente el nombre del archivo (`notas.php`) más
  query string para "parámetros de ruta" (`notas_item.php?id=5`). No hay
  `composer.json`: no hace falta autoload si no hay namespaces.
- **`src/app/` vs `src/public/`**: `db.php` y `helpers.php` viven en `app/`, un
  nivel arriba del docroot — a propósito, igual criterio que el resto de la
  serie (nada que no deba exponerse por URL directa vive en `public/`). Cada
  endpoint hace `require_once __DIR__ . '/../app/helpers.php'` como primera
  línea (que a su vez trae `db.php`).
- **`db()` es una función, no una clase**: mismo comportamiento que
  `Database::connection()` de los hermanos con router (singleton por request via
  `static $pdo`, `getenv()` nunca `$_ENV`, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`,
  `EMULATE_PREPARES` en `false`) pero sin namespace porque no hay autoload.
- **El contrato de cada endpoint, en orden**: `aplicarCors()` primero (manda
  headers CORS, corta 204 en `OPTIONS`) → `requerirMetodo(...)` (405 si el verbo
  no matchea) → `usuarioAutenticado()` si es protegido (401 si no hay token
  válido) → lógica propia → `jsonResponse()`/`errorResponse()` para responder
  (ambas hacen `exit` adentro, un endpoint nunca corta a mano).
- **Header `Authorization`**: no hay `.htaccess` (no hace falta reescribir
  nada), así que el fix vive en el vhost (`CGIPassAuth On`) en vez del truco de
  `mod_rewrite` que usan los starters con router. `bearerToken()` en
  `helpers.php` además cae a `apache_request_headers()` como fallback.
- **`notas.php` (colección) vs `notas_item.php` (item)**: separación deliberada
  en dos archivos en vez de switchear por `$_GET['id']` dentro de uno solo — GET
  de colección y GET de item son cosas distintas, y así cada archivo se lee de
  arriba a abajo sin ramas por presencia/ausencia de `id`.
- **`errorResponse()`/`jsonResponse()` devuelven `never`**: hacen `exit` siempre,
  así que un `if` sin `else` en un endpoint (ej. "si es GET, responder y listo")
  no necesita `return` ni `else` — el flujo simplemente no sigue.
- **Vertical slice completo (auth + notas), no hay nada más "descartable" que
  separar**: todo el repo ES el ejemplo. En un proyecto real se copian
  `notas.php`/`notas_item.php` como plantilla de cada recurso nuevo y se
  reemplaza el schema.
- Todo comando (php, lo que sea) corre via `docker compose exec app ...` o
  `make shell`. No hay PHP en el host a propósito.
- Techo declarado a propósito: sin parámetros de ruta reales, sin middleware,
  sin agrupar validaciones. El salto natural cuando esto queda chico es
  `php-api-docker-starter-apache-mysql` (el hermano con router), no agregarle
  capas a este.
