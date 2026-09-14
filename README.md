# php-api-rustica-docker-starter-apache-mysql

Plantilla de GitHub para una **API rústica en PHP** (sin router, sin MVC, sin
front controller), dockerizada con **Apache + mod_php + MySQL 8**, en cualquier
máquina, con un solo comando.

Es el hermano más chico de la serie: donde
[`php-api-docker-starter-apache-mysql`](../php-api-docker-starter-apache-mysql)
resuelve una API con router propio y clases, este resuelve lo mismo con **PHP
plano de la vieja escuela**: cada archivo dentro de `src/public/` es un
endpoint, la URL apunta directo al archivo, y no hay una sola clase.

## Requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (o Docker Engine + Compose plugin) corriendo.
- [GitHub CLI](https://cli.github.com/) (`gh`) para crear proyectos nuevos desde la terminal. Alternativa: botón **"Use this template"** en GitHub.

## Crear un proyecto nuevo desde este template

```bash
gh repo create mi-api --template harikirtandas/php-api-rustica-docker-starter-apache-mysql --private --clone
cd mi-api
make install
```

Al terminar: API en **http://localhost:8080**, Adminer en **http://localhost:8081**.
No hay `composer.json` — `make install` solo levanta los contenedores, no hay
nada que instalar.

## Arquitectura

Un solo contenedor de aplicación (`php:8.4-apache`, mod_php) más MySQL y Adminer
— igual que el resto de la serie:

| Servicio | Imagen | Rol |
|---|---|---|
| `app` | build propio, `php:8.4-apache` | Apache y PHP en el mismo proceso. Publica `APP_PORT` (default 8080). |
| `mysql` | `mysql:8` | Base de datos, volumen persistente + healthcheck. |
| `adminer` | `adminer` | Cliente web de MySQL, publica `ADMINER_PORT` (default 8081). |

`./src` se monta como bind mount en `app`. `src/public` es el docroot (único
directorio que Apache expone por URL); `src/app` queda un nivel arriba, fuera
de alcance por URL directa.

## Estructura

```
src/
├── app/
│   ├── db.php          # function db(): PDO — conexion, un require away
│   └── helpers.php     # jsonResponse(), cuerpoJson(), requerirMetodo(),
│                        # aplicarCors(), usuarioAutenticado(), bearerToken()
└── public/
    ├── login.php        # POST   /login.php
    ├── logout.php        # POST   /logout.php           (requiere token)
    ├── notas.php          # GET, POST  /notas.php         (requiere token)
    └── notas_item.php      # GET, PUT, DELETE  /notas_item.php?id=5  (requiere token)

docker/mysql/init/01-schema.sql   # tablas: usuarios, tokens, notas
```

Usuario demo: **`demo@demo.test` / `secret`**.

## Probar con curl

```bash
TOKEN=$(curl -s -X POST localhost:8080/login.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"demo@demo.test","password":"secret"}' \
  | php -r 'echo json_decode(file_get_contents("php://stdin"))->token;')

curl -s localhost:8080/notas.php -H "Authorization: Bearer $TOKEN"

curl -s -i -X POST localhost:8080/notas.php \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"titulo":"Hola","cuerpo":"desde curl"}'

curl -s "localhost:8080/notas_item.php?id=1" -H "Authorization: Bearer $TOKEN"

curl -s -X PUT "localhost:8080/notas_item.php?id=1" \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"titulo":"Nuevo titulo","cuerpo":"editado"}'

curl -s -i -X DELETE "localhost:8080/notas_item.php?id=1" -H "Authorization: Bearer $TOKEN"
```

## Cómo agregar un endpoint nuevo

Copiá `notas.php` o `notas_item.php`, cambiale el nombre y la tabla/columnas.
No hay que tocar ningún archivo central: cada endpoint vive y muere solo. Lo
único compartido es `app/helpers.php` — si un endpoint nuevo necesita algo que
no está ahí, se agrega ahí (una función más, no una clase nueva).

## Los "cuatro helpers" de `app/helpers.php`

- `aplicarCors()` — primera línea de **todo** endpoint. Manda los headers CORS
  y corta con 204 si el método es `OPTIONS` (preflight del navegador).
- `requerirMetodo(...$permitidos)` — segunda línea. Corta con 405 + header
  `Allow` si el verbo de la request no es ninguno de los permitidos.
- `cuerpoJson()` — parsea el body como JSON; corta con 400 si no es JSON
  válido.
- `usuarioAutenticado()` — resuelve el Bearer token contra la tabla `tokens` y
  devuelve el usuario dueño, o corta con 401.

`errorResponse($status, $mensaje, $errores = [])` y `jsonResponse($datos, $status)`
son los dos puntos de salida: todo `error*`/`json*` responde y hace `exit`
adentro — un endpoint nunca tiene que acordarse de cortar la ejecución a mano.

## Autenticación

Token opaco (64 hex, `random_bytes`) en la tabla `tokens`, con vencimiento
(`TOKEN_TTL_HORAS`, default 7 días). `login.php` lo emite; el resto de los
endpoints protegidos llaman `usuarioAutenticado()` como primera operación
después de `aplicarCors()`/`requerirMetodo()`.

> El header `Authorization` lo suele descartar Apache antes de que PHP lo vea.
> Este starter no tiene `.htaccess` (no hace falta reescribir URLs), así que la
> reinyección se resuelve en el vhost con `CGIPassAuth On`
> (`docker/apache/000-default.conf`) más un fallback a `apache_request_headers()`
> en `bearerToken()`.

## CORS

`aplicarCors()` manda los headers en toda respuesta y corta el preflight
`OPTIONS` con 204. Origen desde `CORS_ORIGIN` (default `*`).

## Configuración (`.env` en la raíz, no en `src/`)

```bash
APP_PORT=8080
ADMINER_PORT=8081
DB_DATABASE=app
DB_USERNAME=app
DB_PASSWORD=secret
CORS_ORIGIN=*
TOKEN_TTL_HORAS=168
```

`db()` en `src/app/db.php` lee `DB_*` con `getenv()` (nunca `$_ENV`) dentro del
contenedor — docker-compose las inyecta como `environment:`.

## Comandos (Makefile)

Igual que el resto de la serie — `make install`, `up`, `down`, `restart`,
`shell`, `db-shell`, `logs`, `db-import FILE=...`, `fresh`. El target
`composer` sigue existiendo por si el proyecto crece y en algún momento hace
falta una librería real (en ese punto, probablemente convenga migrar al
hermano con router).

## Agregar tablas sin perder datos

`docker/mysql/init/*.sql` solo corre en el primer arranque del volumen
`mysql-data`. Para sumar una tabla a un proyecto con datos ya cargados:

1. Guardar el archivo numerado: `docker/mysql/init/02-nombre.sql`.
2. `make db-import FILE=docker/mysql/init/02-nombre.sql`

## Qué NO tiene esto (a propósito)

- Sin `index.php` central ni tabla de rutas: la URL es literalmente el nombre
  del archivo.
- Sin clases `Controller`/`Router`/`Request`. Cada endpoint valida el método,
  autentica y responde con las mismas 4-5 funciones sueltas.
- Sin `.htaccess` con reescritura de URLs.
- `helpers.php` no es un framework: son funciones para no repetir el mismo
  bloque de código en cada archivo. Si en algún momento sentís que necesitás
  más — parámetros de ruta, middleware, agrupar rutas — es la señal de pasarte
  a [`php-api-docker-starter-apache-mysql`](../php-api-docker-starter-apache-mysql).

## Arrancar un proyecto real

1. `gh repo create mi-api --template harikirtandas/php-api-rustica-docker-starter-apache-mysql --private --clone && cd mi-api`
2. Reemplazar `docker/mysql/init/01-schema.sql` por el schema real.
3. Copiar `notas.php`/`notas_item.php` como base de cada recurso nuevo; borrar
   los que no se usen.
4. Conservar `app/db.php` y `app/helpers.php` tal cual (o sumarles funciones).
5. `make install`.
