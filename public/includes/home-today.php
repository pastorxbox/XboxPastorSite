<?php

declare(strict_types=1);

/**
 * Slide "Chegando Hoje" do carrossel da Home: arte gerada pelo Xbox Manager com os
 * lançamentos do dia, gravada em site_settings e mostrada só na data indicada.
 */
const HOME_TODAY_SLIDE_KEY = 'home.today_slide';

/** "Hoje" em Brasília: o PHP da Hostinger não fixa fuso e o calendário é brasileiro. */
function home_today_date(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
}

/**
 * @return array{date: string, image_url: string, target: string, titles: list<string>, signature: string, updated_at: string}|null
 */
function home_today_slide_get(PDO $pdo): ?array
{
    try {
        $raw = site_settings_get($pdo, HOME_TODAY_SLIDE_KEY, []);
    } catch (Throwable) {
        return null;
    }
    $date = trim((string) ($raw['date'] ?? ''));
    $image = trim((string) ($raw['image_url'] ?? ''));
    if ($date === '' || $image === '') {
        return null;
    }
    $titles = [];
    foreach ((array) ($raw['titles'] ?? []) as $title) {
        $title = trim((string) $title);
        if ($title !== '') {
            $titles[] = $title;
        }
    }

    return [
        'date' => $date,
        'image_url' => $image,
        'target' => ($raw['target'] ?? '') === 'gamepass' ? 'gamepass' : 'xbox',
        'titles' => $titles,
        'signature' => (string) ($raw['signature'] ?? ''),
        'updated_at' => (string) ($raw['updated_at'] ?? ''),
    ];
}

/** Slide válido apenas no próprio dia (Brasília). */
function home_today_slide_for_today(PDO $pdo): ?array
{
    $slide = home_today_slide_get($pdo);
    if ($slide === null || $slide['date'] !== home_today_date()) {
        return null;
    }

    return $slide;
}
