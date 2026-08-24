<?php

declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_post_url(array $config, int $id): string
{
    $base = rtrim((string) ($config['site_base_url'] ?? ''), '/');

    return $base . '/?p=' . $id;
}

function site_home_url(array $config, int $page = 1): string
{
    $base = rtrim((string) ($config['site_base_url'] ?? ''), '/');
    if ($page <= 1) {
        return $base . '/';
    }

    return $base . '/?paged=' . $page;
}

function format_post_date(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '';
    }

    return date('d/m/Y H:i', $timestamp);
}

function excerpt_from_html(string $html, int $maxLength = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength - 1)) . '…';
    }

    if (strlen($text) <= $maxLength) {
        return $text;
    }

    return rtrim(substr($text, 0, $maxLength - 1)) . '…';
}
