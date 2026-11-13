<?php
declare(strict_types=1);

function app_base_url(): string
{
    static $baseUrl = null;

    if ($baseUrl !== null) {
        return $baseUrl;
    }

    if (defined('APP_ROUTE_BASE_URL')) {
        $baseUrl = rtrim((string) APP_ROUTE_BASE_URL, '/');
        return $baseUrl;
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $directory = str_replace('\\', '/', dirname($scriptName));
    $directory = $directory === '/' || $directory === '\\' ? '' : rtrim($directory, '/');

    $baseUrl = $directory;
    return $baseUrl;
}

function app_root_url(): string
{
    static $rootUrl = null;

    if ($rootUrl !== null) {
        return $rootUrl;
    }

    if (defined('APP_ROOT_BASE_URL')) {
        $rootUrl = rtrim((string) APP_ROOT_BASE_URL, '/');
        return $rootUrl;
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $publicPosition = strpos($scriptName, '/public/');
    if ($publicPosition !== false) {
        $rootUrl = rtrim(substr($scriptName, 0, $publicPosition), '/');
        return $rootUrl;
    }

    $directory = str_replace('\\', '/', dirname($scriptName));
    $rootUrl = $directory === '/' || $directory === '\\' ? '' : rtrim($directory, '/');
    return $rootUrl;
}

function app_url(string $path = ''): string
{
    $normalized = ltrim($path, '/');
    $base = app_base_url();

    if ($normalized === '') {
        return $base !== '' ? $base . '/' : '/';
    }

    return ($base !== '' ? $base : '') . '/' . $normalized;
}

function asset_url(string $path): string
{
    $normalized = ltrim($path, '/');
    $base = app_root_url();

    return ($base !== '' ? $base : '') . '/' . $normalized;
}

function endpoint_url(string $path): string
{
    return asset_url($path);
}

function right_to_care_verification_password(): string
{
    $password = getenv('RIGHT_TO_CARE_VERIFY_PASSWORD');

    if (is_string($password) && $password !== '') {
        return $password;
    }

    return 'Demo2026!';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function send_security_headers(bool $isApi = false): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    if ($isApi) {
        return;
    }

    header(
        "Content-Security-Policy: default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
        "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; " .
        "img-src 'self' data: https:; " .
        "frame-src https://www.google.com https://www.google.com.ph; " .
        "connect-src 'self'; " .
        "base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none';"
    );
}

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
