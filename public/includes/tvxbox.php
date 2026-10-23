<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar.php';

/**
 * @return ?string 11-char YouTube video id
 */
function tvxbox_parse_youtube_id(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url) === 1) {
        return $url;
    }

    $patterns = [
        '~(?:youtube\.com/watch\?(?:[^#]*&)?v=|youtube\.com/embed/|youtube\.com/shorts/|youtu\.be/)([A-Za-z0-9_-]{11})~i',
        '~youtube\.com/live/([A-Za-z0-9_-]{11})~i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $m) === 1) {
            return $m[1];
        }
    }

    $path = parse_url($url, PHP_URL_PATH);
    if (is_string($path) && preg_match('~/([A-Za-z0-9_-]{11})/?$~', $path, $m) === 1) {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if (str_contains($host, 'youtu.be') || str_contains($host, 'youtube.com')) {
            return $m[1];
        }
    }

    $query = parse_url($url, PHP_URL_QUERY);
    if (is_string($query) && $query !== '') {
        parse_str($query, $params);
        $v = $params['v'] ?? null;
        if (is_string($v) && preg_match('/^[A-Za-z0-9_-]{11}$/', $v) === 1) {
            return $v;
        }
    }

    return null;
}

/**
 * Format release date for TV comments: DD/Mês, year only if after current year.
 */
function tvxbox_format_release_date(string $releaseAt): string
{
    $ts = strtotime($releaseAt);
    if ($ts === false) {
        return '';
    }

    $day = date('d', $ts);
    $monthNum = (int) date('n', $ts);
    $month = t('cal.month.' . $monthNum);
    $year = (int) date('Y', $ts);
    $currentYear = (int) date('Y');

    if ($year > $currentYear) {
        return "{$day}/{$month}/{$year}";
    }

    return "{$day}/{$month}";
}

/** Data curta para o modal da TV Xbox: "Data de Lançamento: 18/Set/2026". */
function tvxbox_format_release_date_full(string $releaseAt): string
{
    $ts = strtotime($releaseAt);
    if ($ts === false) {
        return '';
    }

    $day = date('d', $ts);
    $month = t('cal.month_short.' . (int) date('n', $ts));
    $year = date('Y', $ts);

    return t('ui.tvxbox_release_date', [
        'date' => "{$day}/{$month}/{$year}",
    ]);
}

function tvxbox_is_release_today(string $releaseAt): bool
{
    $ts = strtotime($releaseAt);
    if ($ts === false) {
        return false;
    }

    return date('Y-m-d', $ts) === date('Y-m-d');
}

function tvxbox_is_release_past(string $releaseAt): bool
{
    $ts = strtotime($releaseAt);
    if ($ts === false) {
        return false;
    }

    return date('Y-m-d', $ts) < date('Y-m-d');
}

function tvxbox_gp_badge_html(int $size = 14): string
{
    return '<span class="site-ticker__gp-badge tvxbox__gp-badge" title="Game Pass">'
        . calendar_lucide_icon('gamepass', $size)
        . '<span class="site-ticker__gp-label">GP</span>'
        . '</span>';
}

/**
 * @param array<string, mixed> $row
 * @return array{tag: string, comment: string}
 */
function tvxbox_resolve_display(array $row): array
{
    $releaseId = $row['gp_release_id'] ?? null;
    $releaseAt = $row['release_at'] ?? null;
    if ($releaseId !== null && $releaseId !== '' && is_string($releaseAt) && $releaseAt !== '') {
        $onGp = (int) ($row['on_gamepass'] ?? 0) === 1;
        $tag = $onGp ? t('ui.tvxbox_tag_gamepass') : t('ui.tvxbox_tag_xbox');

        if (tvxbox_is_release_today($releaseAt)) {
            return [
                'tag' => $tag,
                'comment' => $onGp
                    ? t('ui.tvxbox_available_today_gp')
                    : t('ui.tvxbox_available_today_xbox'),
            ];
        }

        // After launch day, keep on TV with "available" copy (not a future launch date).
        if (tvxbox_is_release_past($releaseAt)) {
            return [
                'tag' => $tag,
                'comment' => $onGp
                    ? t('ui.tvxbox_available_gp')
                    : t('ui.tvxbox_available_xbox'),
            ];
        }

        $date = tvxbox_format_release_date($releaseAt);
        if ($onGp) {
            return [
                'tag' => $tag,
                'comment' => $date !== ''
                    ? t('ui.tvxbox_launch_gp', ['date' => $date])
                    : t('ui.tvxbox_launch_gp_nodate'),
            ];
        }

        return [
            'tag' => $tag,
            'comment' => $date !== ''
                ? t('ui.tvxbox_launch_xbox', ['date' => $date])
                : t('ui.tvxbox_launch_xbox_nodate'),
        ];
    }

    return [
        'tag' => trim((string) ($row['tag'] ?? '')),
        'comment' => trim((string) ($row['comment'] ?? '')),
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function tvxbox_format_item(array $row): array
{
    $display = tvxbox_resolve_display($row);
    $expiresAt = isset($row['expires_at']) && $row['expires_at'] !== null && $row['expires_at'] !== ''
        ? (string) $row['expires_at']
        : null;
    if ($expiresAt !== null) {
        $expiresAt = substr($expiresAt, 0, 10);
    }

    return [
        'id' => (int) $row['id'],
        'youtube_url' => (string) $row['youtube_url'],
        'youtube_id' => (string) $row['youtube_id'],
        'tag' => (string) ($row['tag'] ?? ''),
        'comment' => (string) ($row['comment'] ?? ''),
        'display_tag' => $display['tag'],
        'display_comment' => $display['comment'],
        'show_on_tv' => (int) ($row['show_on_tv'] ?? 0),
        'expires_at' => $expiresAt,
        'gp_release_id' => isset($row['gp_release_id']) && $row['gp_release_id'] !== null
            ? (int) $row['gp_release_id']
            : null,
        'gp_release_title' => isset($row['gp_release_title']) && $row['gp_release_title'] !== null
            ? (string) $row['gp_release_title']
            : null,
        'on_gamepass' => isset($row['on_gamepass']) ? (int) $row['on_gamepass'] : null,
        'release_at' => isset($row['release_at']) && $row['release_at'] !== null
            ? (string) $row['release_at']
            : null,
        'status' => (string) ($row['status'] ?? 'published'),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function tvxbox_select_sql(): string
{
    return 'SELECT v.*,
                   r.title AS gp_release_title,
                   r.release_at,
                   r.on_gamepass,
                   r.price_brl,
                   r.body AS gp_body,
                   r.cover_url AS gp_cover_url,
                   r.store_url AS gp_store_url,
                   r.on_ultimate,
                   r.on_premium,
                   r.on_essential,
                   r.on_pc,
                   r.platform_console,
                   r.platform_pc,
                   r.platform_cloud,
                   r.play_anywhere,
                   r.supports_pt_br
            FROM tv_videos v
            LEFT JOIN gp_releases r ON r.id = v.gp_release_id';
}

/**
 * @param array<string, mixed> $row Release row or joined tv/release row.
 * @return array<string, mixed>|null
 */
function tvxbox_build_game_payload(array $row, ?int $releaseId = null): ?array
{
    $id = $releaseId;
    if ($id === null) {
        if (isset($row['gp_release_id']) && $row['gp_release_id'] !== null && $row['gp_release_id'] !== '') {
            $id = (int) $row['gp_release_id'];
        } elseif (isset($row['id']) && isset($row['title']) && !isset($row['youtube_id'])) {
            $id = (int) $row['id'];
        } else {
            $id = 0;
        }
    }
    if ($id <= 0) {
        return null;
    }

    $title = trim((string) ($row['gp_release_title'] ?? $row['title'] ?? ''));
    if ($title === '') {
        return null;
    }

    $platforms = [];
    foreach (calendar_gamepass_platforms($row) as $platform) {
        $platforms[] = [
            'icon' => (string) ($platform['icon'] ?? ''),
            'label' => (string) ($platform['label'] ?? ''),
        ];
    }

    $cover = trim((string) ($row['gp_cover_url'] ?? $row['cover_url'] ?? ''));
    $store = trim((string) ($row['gp_store_url'] ?? $row['store_url'] ?? ''));
    $onGp = (int) ($row['on_gamepass'] ?? 0) === 1;
    $rewards = calendar_rewards_info($row);
    $releaseAt = isset($row['release_at']) && $row['release_at'] !== null && $row['release_at'] !== ''
        ? (string) $row['release_at']
        : null;
    $releaseDate = $releaseAt !== null ? tvxbox_format_release_date_full($releaseAt) : '';

    return [
        'id' => $id,
        'title' => $title,
        'body' => trim((string) ($row['gp_body'] ?? $row['body'] ?? '')),
        'coverUrl' => $cover,
        'storeUrl' => $store,
        'platforms' => $platforms,
        'subscriptions' => calendar_gamepass_subscriptions($row),
        'playAnywhere' => !empty($row['play_anywhere']),
        'supportsPtBr' => !empty($row['supports_pt_br']),
        'onGamePass' => $onGp,
        'releaseAt' => $releaseAt,
        'releaseDate' => $releaseDate,
        'rewards' => $rewards !== null ? [
            'label' => $rewards['label'],
            'tip' => $rewards['tip'],
        ] : null,
    ];
}

/**
 * @return array{lang: string, tag: string, comment: string, status: string, updated_at: string}|null
 */
function tvxbox_translation_get(PDO $pdo, int $videoId, string $lang): ?array
{
    $lang = normalize_site_lang($lang);
    if ($videoId <= 0 || !in_array($lang, ['en', 'es'], true)) {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT lang, tag, comment, status, updated_at
             FROM tv_video_translations
             WHERE video_id = :video_id AND lang = :lang
             LIMIT 1'
        );
        $stmt->execute([
            'video_id' => $videoId,
            'lang' => $lang,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return null;
    }

    return is_array($row) ? $row : null;
}

function tvxbox_translation_upsert(
    PDO $pdo,
    int $videoId,
    string $lang,
    string $tag,
    string $comment,
    string $status = 'published'
): void {
    $lang = normalize_site_lang($lang);
    if ($videoId <= 0 || !in_array($lang, ['en', 'es'], true)) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO tv_video_translations (video_id, lang, tag, comment, status)
         VALUES (:video_id, :lang, :tag, :comment, :status)
         ON DUPLICATE KEY UPDATE
             tag = VALUES(tag),
             comment = VALUES(comment),
             status = VALUES(status)'
    );
    $stmt->execute([
        'video_id' => $videoId,
        'lang' => $lang,
        'tag' => mb_substr(trim($tag), 0, 40),
        'comment' => mb_substr(trim($comment), 0, 255),
        'status' => $status === 'draft' ? 'draft' : 'published',
    ]);
}

/**
 * @param list<int> $ids
 * @return array<int, array<string, array<string, mixed>>>
 */
function tvxbox_translations_map(PDO $pdo, array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    if ($ids === []) {
        return [];
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT video_id, lang, tag, comment, status, updated_at
             FROM tv_video_translations
             WHERE video_id IN ($placeholders)"
        );
        $stmt->execute($ids);
    } catch (Throwable) {
        return [];
    }

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $id = (int) $row['video_id'];
        $lang = (string) $row['lang'];
        $map[$id][$lang] = [
            'lang' => $lang,
            'tag' => (string) ($row['tag'] ?? ''),
            'comment' => (string) ($row['comment'] ?? ''),
            'status' => (string) ($row['status'] ?? 'published'),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return $map;
}

/**
 * @param list<array<string, mixed>> $items
 * @return list<array<string, mixed>>
 */
function tvxbox_attach_translations(PDO $pdo, array $items): array
{
    $ids = [];
    foreach ($items as $item) {
        $ids[] = (int) ($item['id'] ?? 0);
    }
    $map = tvxbox_translations_map($pdo, $ids);
    foreach ($items as &$item) {
        $id = (int) ($item['id'] ?? 0);
        $item['translations'] = [
            'en' => $map[$id]['en'] ?? null,
            'es' => $map[$id]['es'] ?? null,
        ];
    }
    unset($item);

    return $items;
}

/**
 * @return list<array<string, mixed>>
 */
function tvxbox_list_admin(PDO $pdo): array
{
    $stmt = $pdo->query(tvxbox_select_sql() . ' ORDER BY v.updated_at DESC, v.id DESC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = [];
    foreach ($rows as $row) {
        $out[] = tvxbox_format_item($row);
    }

    return tvxbox_attach_translations($pdo, $out);
}

/**
 * @return ?array<string, mixed>
 */
function tvxbox_get(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare(tvxbox_select_sql() . ' WHERE v.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }

    return tvxbox_format_item($row);
}

/**
 * Newer TV videos win over older cadastrado duplicates (same YouTube id).
 * Calendar meta is merged afterward from matching gp_releases trailers.
 */
function tvxbox_player_entry_score(int|string|null $videoId, string $tag, string $comment): int
{
    $score = 0;
    if (is_int($videoId) || (is_string($videoId) && ctype_digit($videoId))) {
        $score += 1_000_000 + (int) $videoId;
    }
    if (trim($tag) !== '') {
        $score += 20;
    }
    if (trim($comment) !== '') {
        $score += 20;
    }

    return $score;
}

/**
 * @param array<string, array{score: int, entry: array<string, mixed>}> $byYoutube
 * @param array<string, mixed> $entry
 */
function tvxbox_player_upsert(array &$byYoutube, string $youtubeId, int $score, array $entry): void
{
    if ($youtubeId === '') {
        return;
    }
    if (!isset($byYoutube[$youtubeId]) || $score > $byYoutube[$youtubeId]['score']) {
        $byYoutube[$youtubeId] = ['score' => $score, 'entry' => $entry];
    }
}

/**
 * Keep the newest trailer per calendar game when multiple YouTube ids map to it.
 *
 * @param list<array<string, mixed>> $entries
 * @return list<array<string, mixed>>
 */
function tvxbox_player_dedupe_by_game(array $entries): array
{
    $bestByGame = [];
    $passthrough = [];
    foreach ($entries as $entry) {
        $game = is_array($entry['game'] ?? null) ? $entry['game'] : null;
        $gameId = is_array($game) ? (int) ($game['id'] ?? 0) : 0;
        if ($gameId <= 0) {
            $passthrough[] = $entry;
            continue;
        }
        $vid = $entry['id'] ?? 0;
        $rank = is_int($vid) || (is_string($vid) && ctype_digit((string) $vid))
            ? (int) $vid
            : 0;
        if (!isset($bestByGame[$gameId]) || $rank > $bestByGame[$gameId]['rank']) {
            $bestByGame[$gameId] = ['rank' => $rank, 'entry' => $entry];
        }
    }
    $out = $passthrough;
    foreach ($bestByGame as $slot) {
        $out[] = $slot['entry'];
    }

    return $out;
}

/**
 * Videos eligible for the public player (resolved display fields).
 *
 * @return list<array<string, mixed>>
 */
function tvxbox_list_for_player(PDO $pdo): array
{
    /** @var array<string, array{score: int, entry: array<string, mixed>}> $byYoutube */
    $byYoutube = [];

    $pushFormatted = static function (array $item, array $row, string $tag, string $comment) use (&$byYoutube): void {
        $onGp = (int) ($item['on_gamepass'] ?? 0) === 1;
        $priceBrl = $row['price_brl'] ?? null;
        $rewards = calendar_rewards_info([
            'on_gamepass' => $onGp ? 1 : 0,
            'price_brl' => $priceBrl,
        ]);
        $gpReleaseId = isset($item['gp_release_id']) && $item['gp_release_id'] !== null
            ? (int) $item['gp_release_id']
            : null;
        $game = $gpReleaseId !== null ? tvxbox_build_game_payload($row, $gpReleaseId) : null;
        $entry = [
            'id' => $item['id'],
            'youtubeId' => $item['youtube_id'],
            'tag' => $tag,
            'comment' => $comment,
            'isToday' => is_string($item['release_at']) && $item['release_at'] !== ''
                && tvxbox_is_release_today($item['release_at']),
            'onGamePass' => $onGp,
            'gpReleaseId' => $gpReleaseId,
            'game' => $game,
            'rewards' => $rewards !== null ? [
                'label' => $rewards['label'],
                'tip' => $rewards['tip'],
            ] : null,
        ];
        tvxbox_player_upsert(
            $byYoutube,
            (string) $item['youtube_id'],
            tvxbox_player_entry_score($item['id'], $tag, $comment),
            $entry
        );
    };

    try {
        $stmt = $pdo->query(
            tvxbox_select_sql() . " WHERE v.status = 'published'
               AND v.show_on_tv = 1
               AND (v.expires_at IS NULL OR v.expires_at >= CURDATE())
               AND (v.gp_release_id IS NULL OR r.show_on_tv = 1)
             ORDER BY v.id DESC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $formatted = [];
        foreach ($rows as $row) {
            $item = tvxbox_format_item($row);
            if ($item['youtube_id'] === '') {
                continue;
            }
            $formatted[] = ['item' => $item, 'row' => $row];
        }
        $lang = function_exists('current_lang') ? current_lang() : 'pt';
        $manualIds = [];
        if ($lang !== 'pt' && $lang !== '') {
            foreach ($formatted as $entry) {
                if (($entry['item']['gp_release_id'] ?? null) === null) {
                    $manualIds[] = (int) $entry['item']['id'];
                }
            }
        }
        $trMap = $manualIds !== [] ? tvxbox_translations_map($pdo, $manualIds) : [];
        foreach ($formatted as $entry) {
            $item = $entry['item'];
            $tag = $item['display_tag'];
            $comment = $item['display_comment'];
            if (($item['gp_release_id'] ?? null) === null && $lang !== 'pt' && $lang !== '') {
                $tr = $trMap[(int) $item['id']][$lang] ?? null;
                if (is_array($tr) && ($tr['status'] ?? '') === 'published') {
                    if (trim((string) ($tr['tag'] ?? '')) !== '') {
                        $tag = (string) $tr['tag'];
                    }
                    if (trim((string) ($tr['comment'] ?? '')) !== '') {
                        $comment = (string) $tr['comment'];
                    }
                }
            }
            $pushFormatted($item, $entry['row'], $tag, $comment);
        }
    } catch (Throwable) {
        try {
            $stmt = $pdo->query(
                tvxbox_select_sql() . " WHERE v.status = 'published' AND v.show_on_tv = 1 ORDER BY v.id DESC"
            );
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $item = tvxbox_format_item($row);
                if ($item['youtube_id'] === '') {
                    continue;
                }
                $pushFormatted($item, $row, $item['display_tag'], $item['display_comment']);
            }
        } catch (Throwable) {
            // Table may not exist yet.
        }
    }

    // Enrich / fill from calendar trailers. Newer TV rows already win on same youtube id;
    // this attaches game meta to orphans whose URL matches a calendar trailer.
    try {
        $stmt = $pdo->query(
            "SELECT id, title, body, cover_url, store_url, trailer_url, release_at, on_gamepass, price_brl,
                    on_ultimate, on_premium, on_essential, on_pc,
                    platform_console, platform_pc, platform_cloud,
                    play_anywhere, supports_pt_br
             FROM gp_releases
             WHERE show_on_tv = 1
               AND status = 'published'
               AND trailer_url IS NOT NULL
               AND trailer_url <> ''
               AND (release_at IS NULL OR DATE(release_at) >= CURDATE())
             ORDER BY id ASC"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $youtubeId = tvxbox_parse_youtube_id((string) ($row['trailer_url'] ?? ''));
            if ($youtubeId === null) {
                continue;
            }
            $releaseAt = (string) ($row['release_at'] ?? '');
            $onGp = (int) ($row['on_gamepass'] ?? 0) === 1;
            $display = tvxbox_resolve_display([
                'gp_release_id' => (int) $row['id'],
                'release_at' => $releaseAt,
                'on_gamepass' => $onGp ? 1 : 0,
                'tag' => '',
                'comment' => '',
            ]);
            $tag = $display['tag'];
            $comment = $display['comment'];
            $rewards = calendar_rewards_info($row);
            $game = tvxbox_build_game_payload($row, (int) $row['id']);

            if (isset($byYoutube[$youtubeId])) {
                $existing = $byYoutube[$youtubeId]['entry'];
                if (trim((string) ($existing['tag'] ?? '')) === '' && $tag !== '') {
                    $existing['tag'] = $tag;
                }
                if (trim((string) ($existing['comment'] ?? '')) === '' && $comment !== '') {
                    $existing['comment'] = $comment;
                }
                if (empty($existing['game']) && $game !== null) {
                    $existing['game'] = $game;
                    $existing['gpReleaseId'] = (int) $row['id'];
                }
                if (empty($existing['rewards']) && $rewards !== null) {
                    $existing['rewards'] = [
                        'label' => $rewards['label'],
                        'tip' => $rewards['tip'],
                    ];
                }
                $existing['onGamePass'] = $onGp;
                $existing['isToday'] = $releaseAt !== '' && tvxbox_is_release_today($releaseAt);
                $byYoutube[$youtubeId]['entry'] = $existing;
                continue;
            }

            $entry = [
                'id' => 'gp-' . (int) $row['id'],
                'youtubeId' => $youtubeId,
                'tag' => $tag,
                'comment' => $comment,
                'isToday' => $releaseAt !== '' && tvxbox_is_release_today($releaseAt),
                'onGamePass' => $onGp,
                'gpReleaseId' => (int) $row['id'],
                'game' => $game,
                'rewards' => $rewards !== null ? [
                    'label' => $rewards['label'],
                    'tip' => $rewards['tip'],
                ] : null,
            ];
            tvxbox_player_upsert(
                $byYoutube,
                $youtubeId,
                tvxbox_player_entry_score(0, $tag, $comment),
                $entry
            );
        }
    } catch (Throwable) {
        // Column may not exist yet on older deploys.
    }

    $out = [];
    foreach ($byYoutube as $slot) {
        $out[] = $slot['entry'];
    }

    return tvxbox_player_dedupe_by_game($out);
}

function tvxbox_create(PDO $pdo, array $data): int
{
    if ($data['gp_release_id'] !== null) {
        tvxbox_clear_release_link($pdo, (int) $data['gp_release_id']);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO tv_videos
            (youtube_url, youtube_id, tag, comment, show_on_tv, expires_at, gp_release_id, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['youtube_url'],
        $data['youtube_id'],
        $data['tag'],
        $data['comment'],
        $data['show_on_tv'],
        $data['expires_at'],
        $data['gp_release_id'],
        $data['status'],
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @param array{
 *   youtube_url: string,
 *   youtube_id: string,
 *   tag: string,
 *   comment: string,
 *   show_on_tv: int,
 *   expires_at: ?string,
 *   gp_release_id: ?int,
 *   status: string
 * } $data
 */
function tvxbox_update(PDO $pdo, int $id, array $data): void
{
    if ($data['gp_release_id'] !== null) {
        tvxbox_clear_release_link($pdo, (int) $data['gp_release_id'], $id);
    }

    $stmt = $pdo->prepare(
        'UPDATE tv_videos
         SET youtube_url = ?, youtube_id = ?, tag = ?, comment = ?,
             show_on_tv = ?, expires_at = ?, gp_release_id = ?, status = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $data['youtube_url'],
        $data['youtube_id'],
        $data['tag'],
        $data['comment'],
        $data['show_on_tv'],
        $data['expires_at'],
        $data['gp_release_id'],
        $data['status'],
        $id,
    ]);
}

function tvxbox_delete(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM tv_videos WHERE id = ?');
    $stmt->execute([$id]);
}

function tvxbox_clear_release_link(PDO $pdo, int $releaseId, ?int $exceptVideoId = null): void
{
    if ($releaseId <= 0) {
        return;
    }
    if ($exceptVideoId !== null && $exceptVideoId > 0) {
        $stmt = $pdo->prepare(
            'UPDATE tv_videos SET gp_release_id = NULL WHERE gp_release_id = ? AND id <> ?'
        );
        $stmt->execute([$releaseId, $exceptVideoId]);
        return;
    }
    $stmt = $pdo->prepare('UPDATE tv_videos SET gp_release_id = NULL WHERE gp_release_id = ?');
    $stmt->execute([$releaseId]);
}

/**
 * Link a catalog video to a game release (or clear). Exclusive 1:1.
 */
function tvxbox_link_to_release(PDO $pdo, int $releaseId, ?int $videoId): void
{
    if ($releaseId <= 0) {
        return;
    }
    tvxbox_clear_release_link($pdo, $releaseId);
    if ($videoId === null || $videoId <= 0) {
        return;
    }
    $stmt = $pdo->prepare('UPDATE tv_videos SET gp_release_id = ? WHERE id = ?');
    $stmt->execute([$releaseId, $videoId]);
}

/**
 * Find video currently linked to a release.
 */
function tvxbox_find_id_by_release(PDO $pdo, int $releaseId): ?int
{
    if ($releaseId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM tv_videos WHERE gp_release_id = ? LIMIT 1');
    $stmt->execute([$releaseId]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
}

/**
 * Sync a game release trailer into the TV Xbox catalog.
 * When $showOnTv is true and a YouTube trailer exists, upsert a linked video with show_on_tv=1.
 * Otherwise turn off TV for the linked video (keep the row).
 */
function tvxbox_sync_release_trailer(PDO $pdo, int $releaseId, ?string $trailerUrl, bool $showOnTv): void
{
    if ($releaseId <= 0) {
        return;
    }

    $existingId = tvxbox_find_id_by_release($pdo, $releaseId);
    $trailerUrl = $trailerUrl !== null ? trim($trailerUrl) : '';
    $youtubeId = $trailerUrl !== '' ? tvxbox_parse_youtube_id($trailerUrl) : null;

    if (!$showOnTv || $youtubeId === null) {
        if ($existingId === null) {
            return;
        }
        $current = tvxbox_get($pdo, $existingId);
        if ($current === null) {
            return;
        }
        tvxbox_update($pdo, $existingId, [
            'youtube_url' => (string) $current['youtube_url'],
            'youtube_id' => (string) $current['youtube_id'],
            'tag' => (string) ($current['tag'] ?? ''),
            'comment' => (string) ($current['comment'] ?? ''),
            'show_on_tv' => 0,
            'expires_at' => isset($current['expires_at']) && is_string($current['expires_at'])
                ? $current['expires_at']
                : null,
            'gp_release_id' => $releaseId,
            'status' => (string) ($current['status'] ?? 'published'),
        ]);
        return;
    }

    // Calendar trailers leave the day after launch (expires_at = launch date).
    $expiresAt = null;
    $release = function_exists('calendar_get_gamepass') ? calendar_get_gamepass($pdo, $releaseId) : null;
    if (is_array($release) && !empty($release['release_at'])) {
        $ts = strtotime((string) $release['release_at']);
        if ($ts !== false) {
            $expiresAt = date('Y-m-d', $ts);
        }
    }

    $data = [
        'youtube_url' => $trailerUrl,
        'youtube_id' => $youtubeId,
        'tag' => '',
        'comment' => '',
        'show_on_tv' => 1,
        'expires_at' => $expiresAt,
        'gp_release_id' => $releaseId,
        'status' => 'published',
    ];

    if ($existingId !== null) {
        $current = tvxbox_get($pdo, $existingId);
        if ($current !== null) {
            $data['tag'] = (string) ($current['tag'] ?? '');
            $data['comment'] = (string) ($current['comment'] ?? '');
            $data['status'] = (string) ($current['status'] ?? 'published');
            // Always refresh calendar-linked expiry to the launch date (off the next day).
            $data['expires_at'] = $expiresAt;
        }
        tvxbox_update($pdo, $existingId, $data);
        return;
    }

    tvxbox_create($pdo, $data);
}

/**
 * @param list<array<string, mixed>> $items
 * @return list<array<string, mixed>>
 */
function tvxbox_enrich_gamepass_items(PDO $pdo, array $items): array
{
    if ($items === []) {
        return $items;
    }

    $map = [];
    try {
        $stmt = $pdo->query(
            'SELECT gp_release_id, show_on_tv FROM tv_videos WHERE gp_release_id IS NOT NULL'
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $rid = (int) ($row['gp_release_id'] ?? 0);
            if ($rid > 0) {
                $map[$rid] = (int) ($row['show_on_tv'] ?? 0) === 1 ? 1 : 0;
            }
        }
    } catch (Throwable) {
        // Table may not exist yet on older deploys.
    }

    foreach ($items as &$item) {
        $id = (int) ($item['id'] ?? 0);
        if (array_key_exists('show_on_tv', $item) && $item['show_on_tv'] !== null && $item['show_on_tv'] !== '') {
            $item['show_on_tv'] = (int) $item['show_on_tv'] === 1 ? 1 : 0;
            continue;
        }
        $item['show_on_tv'] = $map[$id] ?? 0;
    }
    unset($item);

    return $items;
}

/**
 * @param list<array{id: int|string, youtubeId: string, tag: string, comment: string, isToday?: bool, onGamePass?: bool}> $videos
 */
function render_tvxbox_sidebar(array $videos): string
{
    if ($videos === []) {
        return '';
    }

    $first = $videos[0];
    $payload = htmlspecialchars(json_encode($videos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $tag = htmlspecialchars((string) ($first['tag'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $comment = htmlspecialchars((string) ($first['comment'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $isToday = !empty($first['isToday']);
    $onGamePass = !empty($first['onGamePass']);
    $rewards = is_array($first['rewards'] ?? null) ? $first['rewards'] : null;
    $rewardsLabel = htmlspecialchars((string) ($rewards['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $rewardsTip = htmlspecialchars((string) ($rewards['tip'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $showRewards = !$onGamePass && $rewardsLabel !== '';
    $hasGame = is_array($first['game'] ?? null) && (int) (($first['game']['id'] ?? 0)) > 0;
    $title = htmlspecialchars(t('ui.tvxbox'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $nextLabel = htmlspecialchars(t('ui.tvxbox_next'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $detailsLabel = htmlspecialchars(t('ui.tvxbox_details'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $closeLabel = htmlspecialchars(t('ui.close'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $playAnywhereLabel = htmlspecialchars(t('cal.play_anywhere'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $ptBrLabel = htmlspecialchars(t('cal.supports_pt_br'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $platformsLabel = htmlspecialchars(t('cal.platforms'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $storeLabel = htmlspecialchars(t('cal.store'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $rewardsPointsTpl = htmlspecialchars(t('cal.rewards_points'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $commentClasses = 'tvxbox__comment';
    if ($isToday) {
        $commentClasses .= ' tvxbox__comment--today';
    }
    if ($onGamePass) {
        $commentClasses .= ' tvxbox__comment--gp';
    }

    ob_start();
    ?>
                <div class="widget tvxbox" data-tvxbox data-videos="<?= $payload ?>">
                    <div class="widget-head">
                        <span class="bar"></span>
                        <span class="tvxbox__title"><?= calendar_xbox_icon_html(16) ?> <?= $title ?></span>
                        <div class="tvxbox__actions">
                            <button
                                type="button"
                                class="tvxbox__details"
                                data-tvxbox-details
                                title="<?= $detailsLabel ?>"
                                aria-label="<?= $detailsLabel ?>"
                                <?= $hasGame ? '' : ' hidden' ?>
                            >
                                <?= calendar_lucide_icon('maximize', 18) ?>
                            </button>
                            <?php if (count($videos) > 1): ?>
                                <button
                                    type="button"
                                    class="tvxbox__next"
                                    data-tvxbox-next
                                    title="<?= $nextLabel ?>"
                                    aria-label="<?= $nextLabel ?>"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <polygon points="5 4 15 12 5 20 5 4"></polygon>
                                        <line x1="19" x2="19" y1="5" y2="19"></line>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="tvxbox__body">
                        <div class="tvxbox__player">
                            <?php if ($tag !== ''): ?>
                                <span class="tvxbox__tag cat" data-tvxbox-tag><?= $tag ?></span>
                            <?php else: ?>
                                <span class="tvxbox__tag cat is-empty" data-tvxbox-tag hidden></span>
                            <?php endif; ?>
                            <div class="tvxbox__frame">
                                <div class="tvxbox__yt" data-tvxbox-mount></div>
                            </div>
                            <span
                                class="cal-rewards tvxbox__rewards"
                                data-tvxbox-rewards
                                <?= $showRewards ? '' : ' hidden' ?>
                            >
                                <span class="cal-rewards__medal" data-tvxbox-rewards-medal title="<?= $rewardsTip ?>" aria-label="<?= $rewardsTip ?>">🏅</span>
                                <span class="cal-rewards__points" data-tvxbox-rewards-points><?= $rewardsLabel ?></span>
                            </span>
                        </div>
                        <p class="<?= $commentClasses ?>" data-tvxbox-comment<?= $comment === '' ? ' hidden' : '' ?>>
                            <span class="tvxbox__gp-slot" data-tvxbox-gp<?= $onGamePass ? '' : ' hidden' ?>><?= tvxbox_gp_badge_html(14) ?></span>
                            <span class="tvxbox__comment-text" data-tvxbox-comment-text><?= $comment ?></span>
                        </p>
                    </div>
                    <dialog class="tvxbox-modal" data-tvxbox-modal aria-label="<?= $detailsLabel ?>">
                        <div class="tvxbox-modal__panel">
                            <button type="button" class="tvxbox-modal__close" data-tvxbox-modal-close aria-label="<?= $closeLabel ?>">×</button>
                            <div class="tvxbox-modal__grid">
                                <div class="tvxbox-modal__player">
                                    <div class="tvxbox-modal__yt" data-tvxbox-modal-mount></div>
                                </div>
                                <div class="tvxbox-modal__cover-wrap">
                                    <img class="tvxbox-modal__cover" data-tvxbox-modal-cover alt="" hidden>
                                </div>
                                <h3 class="tvxbox-modal__title" data-tvxbox-modal-title></h3>
                                <p class="tvxbox-modal__desc" data-tvxbox-modal-body></p>
                                <aside class="tvxbox-modal__side">
                                    <span class="tvxbox-modal__release" data-tvxbox-modal-release hidden>
                                        <span class="tvxbox-modal__release-gp" data-tvxbox-modal-release-gp hidden><?= tvxbox_gp_badge_html(13) ?></span>
                                        <span data-tvxbox-modal-release-text></span>
                                    </span>
                                    <div class="tvxbox-modal__meta" data-tvxbox-modal-meta></div>
                                    <p class="tvxbox-modal__flags">
                                        <span class="tvxbox-modal__flag" data-tvxbox-modal-pa hidden title="<?= $playAnywhereLabel ?>"><?= $playAnywhereLabel ?></span>
                                        <span class="tvxbox-modal__flag" data-tvxbox-modal-ptbr hidden title="<?= $ptBrLabel ?>">🇧🇷 <?= $ptBrLabel ?></span>
                                    </p>
                                    <div
                                        class="cal-rewards tvxbox-modal__rewards"
                                        data-tvxbox-modal-rewards
                                        data-rewards-points-tpl="<?= $rewardsPointsTpl ?>"
                                        hidden
                                    >
                                        <span class="cal-rewards__medal" data-tvxbox-modal-rewards-medal>🏅</span>
                                        <span class="cal-rewards__points" data-tvxbox-modal-rewards-points></span>
                                    </div>
                                    <a class="tvxbox-modal__store" data-tvxbox-modal-store href="#" target="_blank" rel="noopener noreferrer" hidden><?= calendar_xbox_icon_html(14) ?> <span><?= $storeLabel ?></span></a>
                                    <p class="tvxbox-modal__platforms-label" data-tvxbox-modal-platforms-label hidden><?= $platformsLabel ?></p>
                                    <div class="cal-platforms" data-tvxbox-modal-platforms></div>
                                    <div class="cal-badges" data-tvxbox-modal-subs></div>
                                </aside>
                            </div>
                        </div>
                    </dialog>
                </div>
    <?php
    return (string) ob_get_clean();
}
