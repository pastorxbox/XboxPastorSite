SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO gp_releases (
    id, title, slug, cover_url, release_at, body,
    on_gamepass, on_ultimate, on_premium, on_essential, on_pc,
    platform_console, platform_pc, platform_cloud,
    status, sort_order
) VALUES
(
    1,
    'Avowed',
    'avowed',
    NULL,
    DATE_ADD(CURDATE(), INTERVAL 5 DAY) + INTERVAL 0 HOUR,
    'Chegada ao Game Pass no dia do lançamento.',
    1, 1, 1, 0, 1,
    1, 1, 1,
    'published', 0
),
(
    2,
    'Indiana Jones and the Great Circle',
    'indiana-jones-great-circle',
    NULL,
    DATE_ADD(CURDATE(), INTERVAL 12 DAY) + INTERVAL 0 HOUR,
    'Day one no Game Pass Ultimate e PC.',
    1, 1, 0, 0, 1,
    1, 1, 1,
    'published', 0
),
(
    3,
    'South of Midnight',
    'south-of-midnight',
    NULL,
    DATE_ADD(CURDATE(), INTERVAL 25 DAY) + INTERVAL 0 HOUR,
    'Exclusivo Xbox com presença em console, PC e nuvem.',
    1, 1, 1, 0, 1,
    1, 1, 1,
    'published', 0
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    slug = VALUES(slug),
    release_at = VALUES(release_at),
    body = VALUES(body),
    on_gamepass = VALUES(on_gamepass),
    on_ultimate = VALUES(on_ultimate),
    on_premium = VALUES(on_premium),
    on_essential = VALUES(on_essential),
    on_pc = VALUES(on_pc),
    platform_console = VALUES(platform_console),
    platform_pc = VALUES(platform_pc),
    platform_cloud = VALUES(platform_cloud),
    status = VALUES(status);

INSERT INTO calendar_events (id, title, banner_url, starts_at, body, external_url, status) VALUES
(
    1,
    'Xbox Games Showcase',
    NULL,
    DATE_ADD(CURDATE(), INTERVAL 18 DAY) + INTERVAL 16 HOUR,
    '<p>Apresentação anual de jogos Xbox e Game Pass, com trailers, datas e anúncios ao vivo.</p>',
    'https://www.xbox.com',
    'published'
),
(
    2,
    'Call of Duty Next',
    NULL,
    DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 18 HOUR,
    '<p>Evento digital com novidades da próxima temporada e multiplayer.</p>',
    'https://www.callofduty.com',
    'published'
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    starts_at = VALUES(starts_at),
    body = VALUES(body),
    external_url = VALUES(external_url),
    status = VALUES(status);

INSERT INTO xbox_history (
    id, title, occurred_year, occurred_month, occurred_day, context, body, cover_url, status
) VALUES
(
    1,
    'Halo: Combat Evolved',
    2001,
    11,
    15,
    'no Xbox Clássico',
    'O lançamento que definiu a geração e a franquia Halo.',
    NULL,
    'published'
),
(
    2,
    'Xbox 360',
    2005,
    11,
    22,
    'nos Estados Unidos',
    'Início da era Xbox 360.',
    NULL,
    'published'
),
(
    3,
    'Game Pass',
    2017,
    6,
    1,
    'como serviço de assinatura Xbox',
    'Nascimento do serviço que mudou o modelo de consumo de jogos na plataforma.',
    NULL,
    'published'
),
(
    4,
    'Xbox One',
    2013,
    MONTH(CURDATE()),
    DAY(CURDATE()),
    'nos Estados Unidos',
    'Entrada de teste local: aniversário “hoje” para validar o bloco Histórico.',
    NULL,
    'published'
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    occurred_year = VALUES(occurred_year),
    occurred_month = VALUES(occurred_month),
    occurred_day = VALUES(occurred_day),
    context = VALUES(context),
    body = VALUES(body),
    status = VALUES(status);
