<?php

declare(strict_types=1);

// GET    /notas_item.php?id=5   (requiere token) -> una nota
// PUT    /notas_item.php?id=5   { "titulo": "...", "cuerpo": "..." }  -> reemplaza
// DELETE /notas_item.php?id=5   -> 204
//
// Sin router: el "parametro de ruta" es literalmente ?id= del query string.

require_once __DIR__ . '/../app/helpers.php';

aplicarCors();
requerirMetodo('GET', 'PUT', 'DELETE');
$usuario = usuarioAutenticado();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    errorResponse(400, 'Falta ?id= o no es un numero valido.');
}

$stmt = db()->prepare(
    'SELECT id, titulo, cuerpo, created_at, updated_at
     FROM notas WHERE id = :id AND usuario_id = :uid'
);
$stmt->execute(['id' => $id, 'uid' => $usuario['id']]);
$nota = $stmt->fetch();

if ($nota === false) {
    errorResponse(404, 'Nota no encontrada.');
}

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    jsonResponse($nota);
}

if ($metodo === 'DELETE') {
    $stmt = db()->prepare('DELETE FROM notas WHERE id = :id AND usuario_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $usuario['id']]);

    jsonResponse(null, 204);
}

// PUT: reemplazo
$body = cuerpoJson();
$titulo = trim((string) ($body['titulo'] ?? ''));
$cuerpo = trim((string) ($body['cuerpo'] ?? ''));

if ($titulo === '' || mb_strlen($titulo) > 120) {
    errorResponse(422, 'Datos invalidos.', ['titulo' => 'Requerido, maximo 120 caracteres.']);
}

$stmt = db()->prepare(
    'UPDATE notas SET titulo = :titulo, cuerpo = :cuerpo, updated_at = NOW()
     WHERE id = :id AND usuario_id = :uid'
);
$stmt->execute(['titulo' => $titulo, 'cuerpo' => $cuerpo, 'id' => $id, 'uid' => $usuario['id']]);

$stmt = db()->prepare('SELECT id, titulo, cuerpo, created_at, updated_at FROM notas WHERE id = :id');
$stmt->execute(['id' => $id]);

jsonResponse($stmt->fetch());
