<?php

if (!defined('DEV_MULTI_SESSION')) define('DEV_MULTI_SESSION', false);

function is_dev_multi_session_enabled(): bool {
    return (bool)DEV_MULTI_SESSION;
}

function normalize_dev_profile(?string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?? '';
    return substr($value, 0, 20);
}

function get_dev_profile(): string {
    if (!is_dev_multi_session_enabled()) {
        return '';
    }

    if (isset($_POST['dev_profile'])) {
        return normalize_dev_profile($_POST['dev_profile']);
    }

    if (isset($_GET['dev_profile'])) {
        return normalize_dev_profile($_GET['dev_profile']);
    }

    return '';
}

function get_session_cookie_name(?string $profile = null): string {
    $profile = $profile ?? get_dev_profile();
    return $profile === '' ? 'priorities_token' : 'priorities_token_' . $profile;
}

function get_session_token(): ?string {
    $cookie_name = get_session_cookie_name();
    $token = $_COOKIE[$cookie_name] ?? null;
    return $token === '' ? null : $token;
}

function set_session_cookie(string $token): void {
    setcookie(get_session_cookie_name(), $token, [
        'expires'  => time() + 86400 * 7,
        'httponly' => true,
        'samesite' => 'Strict',
        'path'     => '/',
    ]);
}

function build_path(string $path, array $params = []): string {
    $profile = get_dev_profile();
    if ($profile !== '') {
        $params['dev_profile'] = $profile;
    }

    $query = http_build_query(array_filter(
        $params,
        static fn($value) => $value !== null && $value !== ''
    ));

    return $query === '' ? $path : $path . '?' . $query;
}

