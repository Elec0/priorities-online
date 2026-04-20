<?php
declare(strict_types=1);

/** Return the dev profile string (empty string when not in dev mode). */
function get_dev_profile(): string
{
    if (!defined('DEV_MULTI_SESSION') || !DEV_MULTI_SESSION) {
        return '';
    }
    $profile = $_GET['dev_profile'] ?? $_POST['dev_profile'] ?? '';
    return preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $profile);
}

/** Return the cookie name, optionally namespaced by dev profile. */
function get_cookie_name(): string
{
    $profile = get_dev_profile();
    return $profile !== '' ? "priorities_token_{$profile}" : 'priorities_token';
}

/** Read the session token from the cookie. Returns null if absent. */
function get_token(): ?string
{
    $name = get_cookie_name();
    $token = $_COOKIE[$name] ?? null;
    return is_string($token) && strlen($token) === 64 ? $token : null;
}

/** Set the auth cookie on the response. */
function set_token_cookie(string $token): void
{
    $name = get_cookie_name();
    setcookie($name, $token, [
        'expires'  => time() + 7 * 24 * 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => isset($_SERVER['HTTPS']),
    ]);
}
