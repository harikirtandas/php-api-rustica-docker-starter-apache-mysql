<?php

declare(strict_types=1);

// GET  /notas.php   (requiere token)  -> lista las notas del usuario
// POST /notas.php   { "titulo": "...", "cuerpo": "..." }  (requiere token) -> 201 + Location

require_once __DIR__ . '/../app/helpers.php';

aplicarCors();
requerirMetodo('GET', 'POST');
$usuario = usuarioAutenticado();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = db()->prepare(
        'SELECT id, titulo, cuerpo, created_at, updated_at
         FROM notas WHERE usuario_id = :uid ORDER BY id DESC'
    );
    $stmt->execute(['uid' => $usuario['id']]);

    jsonResponse(['datos' => $stmt->fetchAll()]);
}

// POST: alta
$body = cuerpoJson();
$titulo = trim((string) ($body['titulo'] ?? ''));
$cuerpo = trim((string) ($body['cuerpo'] ?? ''));

if ($titulo === '' || mb_strlen($titulo) > 120) {
    errorResponse(422, 'Datos invalidos.', ['titulo' => 'Requerido, maximo 120 caracteres.']);
}

$stmt = db()->prepare(
    'INSERT INTO notas (usuario_id, titulo, cuerpo, created_at, updated_at)
     VALUES (:uid, :titulo, :cuerpo, NOW(), NOW())'
);
$stmt->execute(['uid' => $usuario['id'], 'titulo' => $titulo, 'cuerpo' => $cuerpo]);
$id = (int) db()->lastInsertId();

$stmt = db()->prepare('SELECT id, titulo, cuerpo, created_at, updated_at FROM notas WHERE id = :id');
$stmt->execute(['id' => $id]);

header('Location: /notas_item.php?id=' . $id);
jsonResponse($stmt->fetch(), 201);
