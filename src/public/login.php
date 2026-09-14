<?php

declare(strict_types=1);

// POST /login.php   { "email": "...", "password": "..." }  -> { token, expira, usuario }

require_once __DIR__ . '/../app/helpers.php';

aplicarCors();
requerirMetodo('POST');

$body = cuerpoJson();
$email = trim((string) ($body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');

$errores = [];
if ($email === '') {
    $errores['email'] = 'Requerido.';
}
if ($password === '') {
    $errores['password'] = 'Requerido.';
}
if ($errores !== []) {
    errorResponse(422, 'Datos invalidos.', $errores);
}

$stmt = db()->prepare('SELECT id, nombre, email, password_hash FROM usuarios WHERE email = :email');
$stmt->execute(['email' => $email]);
$usuario = $stmt->fetch();

if ($usuario === false || !password_verify($password, $usuario['password_hash'])) {
    // mismo mensaje para "no existe" y "password mala": no filtra si el
    // email esta registrado.
    errorResponse(401, 'Email o contrasena incorrectos.');
}

$token = bin2hex(random_bytes(32));
$ttlHoras = (int) (getenv('TOKEN_TTL_HORAS') ?: 168); // 7 dias
$expira = (new DateTimeImmutable("+{$ttlHoras} hours"))->format('Y-m-d H:i:s');

$stmt = db()->prepare(
    'INSERT INTO tokens (token, usuario_id, expira_en, created_at) VALUES (:token, :uid, :expira, NOW())'
);
$stmt->execute(['token' => $token, 'uid' => $usuario['id'], 'expira' => $expira]);

unset($usuario['password_hash']);

jsonResponse(['token' => $token, 'expira' => $expira, 'usuario' => $usuario]);
