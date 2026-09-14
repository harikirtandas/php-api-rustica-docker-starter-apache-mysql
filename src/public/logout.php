<?php

declare(strict_types=1);

// POST /logout.php   (requiere token)  -> 204, invalida el token actual

require_once __DIR__ . '/../app/helpers.php';

aplicarCors();
requerirMetodo('POST');
usuarioAutenticado(); // corta con 401 si el token ya no sirve

$stmt = db()->prepare('DELETE FROM tokens WHERE token = :token');
$stmt->execute(['token' => bearerToken()]);

jsonResponse(null, 204);
