<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function requireLogin(): void
{
    if (empty($_SESSION['idUser'])) {
        flash('Debes iniciar sesión para acceder a esta página.');
        header('Location: login.php');
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (($_SESSION['rol'] ?? null) !== 'admin') {
        flash('No tienes permiso para acceder a esa página.');
        header('Location: index.php');
        exit;
    }
}

function csrfToken(string $scope): string
{
    $key = 'csrf_' . $scope;
    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$key];
}

function csrfValid(string $scope, mixed $token): bool
{
    return is_string($token) && hash_equals(csrfToken($scope), $token);
}

function validDate(string $value, bool $allowToday = true): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $value) {
        return false;
    }
    return $allowToday ? $date >= new DateTimeImmutable('today') : $date <= new DateTimeImmutable('today');
}

function validatePersonalData(array $data): array
{
    $errors = [];
    foreach (['nombre', 'apellidos', 'email', 'telefono', 'fecha_nacimiento', 'direccion', 'sexo'] as $field) {
        if (trim((string) ($data[$field] ?? '')) === '') $errors[] = 'Todos los datos personales son obligatorios.';
    }
    if (($data['email'] ?? '') !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no es válido.';
    if (($data['telefono'] ?? '') !== '' && !preg_match('/^[0-9+ ()-]{7,20}$/', $data['telefono'])) $errors[] = 'El teléfono no tiene un formato válido.';
    if (($data['sexo'] ?? '') !== '' && !in_array($data['sexo'], ['femenino', 'masculino', 'otro'], true)) $errors[] = 'El sexo indicado no es válido.';
    $birth = (string) ($data['fecha_nacimiento'] ?? '');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birth);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if ($birth !== '' && (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count'])) || $date->format('Y-m-d') !== $birth || $date > new DateTimeImmutable('today'))) $errors[] = 'La fecha de nacimiento no es válida o es futura.';
    return array_unique($errors);
}
