<?php

declare(strict_types=1);

// Funciones sueltas, no un framework: cada endpoint de public/ hace
// require_once __DIR__ . '/../app/helpers.php' y usa lo que necesita. Si esto
// alguna vez se siente insuficiente, es la señal de que conviene pasarse al
// starter con router (php-api-docker-starter-apache-mysql).

require_once __DIR__ . '/db.php';

// Headers CORS en toda respuesta + corte del preflight. Cada endpoint la
// llama como PRIMERA linea, antes de tocar nada mas.
function aplicarCors(): void
{
    $origen = getenv('CORS_ORIGIN') ?: '*';
    header('Access-Control-Allow-Origin: ' . $origen);
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Vary: Origin');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// Responde JSON con el status dado y CORTA la ejecucion (exit). $datos = null
// para un 204 sin cuerpo.
function jsonResponse(mixed $datos, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    if ($datos !== null) {
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    exit;
}

function errorResponse(int $status, string $mensaje, array $errores = []): never
{
    $error = ['status' => $status, 'mensaje' => $mensaje];
    if ($errores !== []) {
        $error['errores'] = $errores;
    }

    jsonResponse(['error' => $error], $status);
}

// Corta con 405 (+ header Allow) si el metodo de la request no es ninguno de
// los permitidos. Se llama justo despues de aplicarCors().
function requerirMetodo(string ...$permitidos): void
{
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!in_array($metodo, $permitidos, true)) {
        header('Allow: ' . implode(', ', $permitidos));
        errorResponse(405, 'Metodo no permitido para este endpoint.');
    }
}

// Body de la request parseado como JSON asociativo. Body vacio -> [].
// JSON invalido o que no es un objeto -> corta con 400.
function cuerpoJson(): array
{
    $crudo = file_get_contents('php://input');

    if ($crudo === false || trim($crudo) === '') {
        return [];
    }

    $data = json_decode($crudo, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        errorResponse(400, 'El body no es JSON valido.');
    }

    return $data;
}

// Token del header Authorization: Bearer xxx, o null si no vino.
function bearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (preg_match('/^Bearer\s+(.+)$/i', trim((string) $header), $m) === 1) {
        return trim($m[1]);
    }

    return null;
}

// Resuelve el Bearer token contra la tabla `tokens` y devuelve el usuario
// dueño, o corta con 401 si falta, no existe o vencio. Llamarla al principio
// de todo endpoint protegido, antes de tocar la base para otra cosa.
function usuarioAutenticado(): array
{
    $token = bearerToken();

    if ($token === null) {
        errorResponse(401, 'Falta el header Authorization: Bearer <token>.');
    }

    $stmt = db()->prepare(
        'SELECT u.id, u.nombre, u.email
         FROM tokens t
         JOIN usuarios u ON u.id = t.usuario_id
         WHERE t.token = :token AND t.expira_en > NOW()'
    );
    $stmt->execute(['token' => $token]);
    $usuario = $stmt->fetch();

    if ($usuario === false) {
        errorResponse(401, 'Token invalido o vencido.');
    }

    return $usuario;
}
