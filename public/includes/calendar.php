<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Corpo de calendário para HTML: texto com quebras → &lt;br&gt;; HTML já sanitizado na API → intacto.
 */
function calendar_rich_body_html(string $body): string
{
    $body = trim($body);
    if ($body === '') {
        return '';
    }

    if (preg_match('/<(?:p|br|ul|ol|li|strong|em|a|h[1-6]|blockquote)\b/i', $body) === 1) {
        return $body;
    }

    return nl2br(e($body), false);
}

/**
 * @return list<array<string, mixed>>
 */
function calendar_list_upcoming_gamepass(PDO $pdo, ?int $withinDays = null): array
{
    $sql = "SELECT *
         FROM gp_releases
         WHERE status = 'published'
           AND release_at >= CURDATE()";
    $params = [];

    if ($withinDays !== null) {
        $days = max(0, $withinDays);
        $sql .= ' AND release_at < DATE_ADD(CURDATE(), INTERVAL ' . $days . ' DAY)';
    }

    $sql .= ' ORDER BY release_at ASC, sort_order ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return calendar_localize_rows($pdo, 'gamepass', $stmt->fetchAll());
}

/**
 * @return list<array<string, mixed>>
 */
function calendar_list_upcoming_events(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        "SELECT *
         FROM calendar_events
         WHERE status = 'published'
           AND DATE(COALESCE(ends_at, starts_at)) >= CURDATE()
         ORDER BY starts_at ASC, id ASC"
    );
    $stmt->execute();

    return calendar_localize_rows($pdo, 'event', $stmt->fetchAll());
}

/**
 * ends_at efetivo (legado NULL = mesmo dia do início).
 *
 * @param array<string, mixed>|string $rowOrEnds
 */
function calendar_event_end_at(array|string $rowOrEnds, ?string $startsAt = null): string
{
    if (is_array($rowOrEnds)) {
        $ends = trim((string) ($rowOrEnds['ends_at'] ?? ''));
        $starts = trim((string) ($rowOrEnds['starts_at'] ?? ''));
        return $ends !== '' ? $ends : $starts;
    }

    $ends = trim($rowOrEnds);
    if ($ends !== '') {
        return $ends;
    }

    return trim((string) $startsAt);
}

/**
 * Progresso do evento no dia civil de referência.
 *
 * @return array{day: int, total: int}|null
 */
function calendar_event_day_progress(
    string $startsAt,
    ?string $endsAt = null,
    ?DateTimeImmutable $ref = null
): ?array {
    $ref ??= new DateTimeImmutable('today');
    $refDay = $ref->setTime(0, 0, 0);

    $startTs = strtotime($startsAt);
    if ($startTs === false) {
        return null;
    }
    $startDay = new DateTimeImmutable(date('Y-m-d', $startTs));

    $endRaw = calendar_event_end_at($endsAt ?? '', $startsAt);
    $endTs = strtotime($endRaw);
    $endDay = $endTs === false
        ? $startDay
        : new DateTimeImmutable(date('Y-m-d', $endTs));

    if ($endDay < $startDay) {
        $endDay = $startDay;
    }

    if ($refDay < $startDay || $refDay > $endDay) {
        return null;
    }

    $total = ((int) $startDay->diff($endDay)->format('%a')) + 1;
    $day = ((int) $startDay->diff($refDay)->format('%a')) + 1;

    return ['day' => $day, 'total' => max(1, $total)];
}

function calendar_event_day_label(
    string $title,
    int $day,
    int $total,
    bool $isThatDayToday
): string {
    $name = trim($title);
    if ($name === '') {
        return '';
    }

    if ($isThatDayToday && $day === 1) {
        return t('cal.today_title', ['title' => $name]);
    }

    if ($isThatDayToday && $total > 1 && $day === $total) {
        return t('cal.event_ending_today', ['title' => $name]);
    }

    if ($day > 1) {
        return t('cal.event_day_n', ['n' => (string) $day, 'title' => $name]);
    }

    return $name;
}

/**
 * Título HTML com pill em HOJE / Encerrando Hoje.
 */
function calendar_event_day_title_html(
    string $title,
    int $day,
    int $total,
    bool $isThatDayToday
): string {
    $name = trim($title);
    if ($name === '') {
        return '';
    }

    if ($isThatDayToday && $day === 1) {
        return calendar_today_title_html($name);
    }

    if ($isThatDayToday && $total > 1 && $day === $total) {
        return '<span class="cal-today-pill cal-today-title">'
            . e(t('cal.event_ending_today', ['title' => $name]))
            . '</span>';
    }

    return e(calendar_event_day_label($name, $day, $total, $isThatDayToday));
}

function calendar_event_date_range_label(string $startsAt, ?string $endsAt = null): string
{
    $startTs = strtotime($startsAt);
    if ($startTs === false) {
        return '';
    }
    $endRaw = calendar_event_end_at($endsAt ?? '', $startsAt);
    $endTs = strtotime($endRaw);
    if ($endTs === false) {
        $endTs = $startTs;
    }

    $startDay = date('Y-m-d', $startTs);
    $endDay = date('Y-m-d', $endTs);
    if ($startDay === $endDay) {
        return format_post_date($startsAt);
    }

    $fmt = static function (int $ts): string {
        return match (current_lang()) {
            'en' => date('m/d', $ts),
            default => date('d/m', $ts),
        };
    };

    return $fmt($startTs) . ' – ' . $fmt($endTs);
}

/** Título de exibição: no dia do evento usa "Hoje é Dia de …" (carrossel da home). */
function calendar_event_display_title(string $title, string $startsAt): string
{
    $name = trim($title);
    if ($name === '') {
        return '';
    }
    if (calendar_days_until($startsAt) === 0) {
        return t('home.event_title', ['name' => $name]);
    }

    return $name;
}

/**
 * Título com pill verde "HOJE: …" (calendário / listas — não usar no carrossel).
 */
function calendar_today_title_html(string $title): string
{
    $name = trim($title);
    if ($name === '') {
        return '';
    }

    return '<span class="cal-today-pill cal-today-title">'
        . e(t('cal.today_title', ['title' => $name]))
        . '</span>';
}

/**
 * Eventos do dia na home: só no dia de início (carrossel). No dia seguinte somem.
 *
 * @return array{carousel: list<array<string, mixed>>, below: list<array<string, mixed>>}
 */
function calendar_home_events(PDO $pdo): array
{
    $carousel = [];

    try {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM calendar_events
             WHERE status = 'published'
               AND DATE(starts_at) = CURDATE()
             ORDER BY starts_at ASC, id ASC"
        );
        $stmt->execute();
        $carousel = calendar_localize_rows($pdo, 'event', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    } catch (Throwable) {
        // Table may not exist yet.
    }

    return ['carousel' => $carousel, 'below' => []];
}

/**
 * Fatos históricos do dia na home: só no aniversário (mês/dia). No dia seguinte somem.
 *
 * @return list<array<string, mixed>>
 */
function calendar_home_history(PDO $pdo): array
{
    try {
        return calendar_list_history_today($pdo);
    } catch (Throwable) {
        return [];
    }
}

/**
 * Eventos históricos do mês civil de referência (repete todos os anos pelo mês/dia).
 *
 * @return list<array<string, mixed>>
 */
function calendar_list_history_month(PDO $pdo, ?DateTimeImmutable $ref = null): array
{
    $ref ??= new DateTimeImmutable('today');
    $currentMonth = (int) $ref->format('n');
    $year = (int) $ref->format('Y');

    $stmt = $pdo->query(
        "SELECT *
         FROM xbox_history
         WHERE status = 'published'"
    );
    $rows = calendar_localize_rows($pdo, 'history', $stmt->fetchAll());
    $matched = [];

    foreach ($rows as $row) {
        $month = (int) $row['occurred_month'];
        $day = (int) $row['occurred_day'];
        if ($month !== $currentMonth || $day < 1 || $day > 31) {
            continue;
        }

        $candidate = DateTimeImmutable::createFromFormat('!Y-n-j', $year . '-' . $month . '-' . $day);
        if ($candidate === false) {
            continue;
        }

        $row['_anniversary'] = $candidate->format('Y-m-d');
        $row['_years_ago'] = $year - (int) $row['occurred_year'];
        $matched[] = $row;
    }

    usort($matched, static function (array $a, array $b): int {
        return strcmp((string) $a['_anniversary'], (string) $b['_anniversary'])
            ?: ((int) $a['id'] <=> (int) $b['id']);
    });

    return $matched;
}

/**
 * Eventos históricos cuja data (mês/dia) cai hoje — usado no ticker.
 *
 * @return list<array<string, mixed>>
 */
function calendar_list_history_today(PDO $pdo): array
{
    $today = new DateTimeImmutable('today');
    $day = (int) $today->format('j');

    return array_values(array_filter(
        calendar_list_history_month($pdo, $today),
        static fn (array $row): bool => (int) $row['occurred_day'] === $day
    ));
}

/**
 * @return list<array<string, mixed>>
 */
function calendar_list_history_window(PDO $pdo, int $daysAhead = 7): array
{
    unset($daysAhead);

    return calendar_list_history_month($pdo);
}

function calendar_days_until(string $datetime): int
{
    $target = new DateTimeImmutable($datetime);
    $today = new DateTimeImmutable('today');
    $targetDay = $target->setTime(0, 0, 0);
    $diff = (int) $today->diff($targetDay)->format('%r%a');

    return $diff;
}

function calendar_countdown_label(string $datetime): string
{
    $days = calendar_days_until($datetime);
    if ($days < 0) {
        return t('cal.happened');
    }
    if ($days === 0) {
        return t('cal.today');
    }
    if ($days === 1) {
        return t('cal.in_1_day');
    }

    return t('cal.in_n_days', ['n' => (string) $days]);
}

function calendar_event_ticker_phrase(string $startsAt, string $title, ?string $endsAt = null): string
{
    $name = trim($title);
    $progress = calendar_event_day_progress($startsAt, $endsAt);
    if ($progress !== null) {
        return calendar_event_day_label($name, $progress['day'], $progress['total'], true);
    }

    $days = calendar_days_until($startsAt);
    if ($days <= 0) {
        return t('cal.today_title', ['title' => $name]);
    }
    if ($days === 1) {
        return t('cal.ticker_1_day', ['name' => $name]);
    }

    return t('cal.ticker_n_days', ['n' => (string) $days, 'name' => $name]);
}

function calendar_history_headline(array $item): string
{
    $years = (int) ($item['_years_ago'] ?? 0);
    $title = trim((string) ($item['title'] ?? ''));
    $context = trim((string) ($item['context'] ?? ''));
    $yearWord = $years === 1 ? t('cal.year') : t('cal.years');
    $line = t('cal.history_years', [
        'title' => $title,
        'n' => (string) $years,
        'unit' => $yearWord,
    ]);

    if ($context !== '') {
        $lang = current_lang();
        if ($lang === 'en') {
            if (preg_match('/^(in|on|at|from|for|to|the)\b/iu', $context) === 1) {
                $line .= ' ' . $context;
            } else {
                $line .= ' in ' . $context;
            }
        } elseif ($lang === 'es') {
            if (preg_match('/^(en|de|del|al|a|por|para|el|la|los|las)\b/iu', $context) === 1) {
                $line .= ' ' . $context;
            } else {
                $line .= ' en ' . $context;
            }
        } elseif (preg_match('/^(no|na|em|nos|nas|pelo|pela|para)\b/iu', $context) === 1) {
            $line .= ' ' . $context;
        } else {
            $line .= ' no ' . $context;
        }
    }

    return $line;
}

/**
 * Ícone Lucide inline (Game Pass / Eventos / Fatos Históricos / setas).
 *
 * @param 'gamepass'|'event'|'history'|'chevron-left'|'chevron-right'|'view-cards'|'view-list'|'view-month'|'info' $kind
 */
function calendar_lucide_icon(string $kind, int $size = 16): string
{
    $icons = [
        'gamepass' => [
            'label' => 'Game Pass',
            'paths' => '<line x1="6" x2="10" y1="11" y2="11"/><line x1="8" x2="8" y1="9" y2="13"/><line x1="15" x2="15.01" y1="12" y2="12"/><line x1="18" x2="18.01" y1="10" y2="10"/><path d="M17.32 5H6.68a4 4 0 0 0-3.978 3.59c-.006.052-.01.101-.017.152C2.604 9.416 2 14.456 2 16a3 3 0 0 0 3 3c1 0 1.5-.5 2-1l1.414-1.414A2 2 0 0 1 9.828 16h4.344a2 2 0 0 1 1.414.586L17 18c.5.5 1 1 2 1a3 3 0 0 0 3-3c0-1.545-.604-6.584-.685-7.258-.007-.05-.011-.1-.017-.151A4 4 0 0 0 17.32 5z"/>',
        ],
        'event' => [
            'label' => t('cal.events'),
            'paths' => '<path d="M21 7.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3.5"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h5"/><path d="M17.5 17.5 16 16.3V14"/><circle cx="16" cy="16" r="6"/>',
        ],
        'history' => [
            'label' => t('cal.history'),
            'paths' => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
        ],
        'chevron-left' => [
            'label' => t('cal.prev'),
            'paths' => '<path d="m15 18-6-6 6-6"/>',
        ],
        'chevron-right' => [
            'label' => t('cal.next'),
            'paths' => '<path d="m9 18 6-6-6-6"/>',
        ],
        'view-cards' => [
            'label' => t('cal.view_cards'),
            'paths' => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
        ],
        'view-list' => [
            'label' => t('cal.view_list'),
            'paths' => '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>',
        ],
        'view-month' => [
            'label' => t('cal.view_month'),
            'paths' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        ],
        'info' => [
            'label' => t('cal.more_info'),
            'paths' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        ],
        'maximize' => [
            'label' => t('ui.tvxbox_details'),
            'paths' => '<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/>',
        ],
    ];

    if (!isset($icons[$kind])) {
        return '';
    }

    $sizePx = max(12, $size);
    $label = htmlspecialchars($icons[$kind]['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return '<svg class="lucide lucide--' . htmlspecialchars($kind, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" xmlns="http://www.w3.org/2000/svg" width="' . $sizePx . '" height="' . $sizePx
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" '
        . 'data-lucide-label="' . $label . '">' . $icons[$kind]['paths'] . '</svg>';
}

/**
 * Ícones Lucide clicáveis para saltar entre secções do calendário.
 *
 * @param 'gamepass'|'event'|'history'|null $current Secção atual (opcional, só para estilo).
 */
function render_calendar_section_jumps(?string $current = null): string
{
    $items = [
        [
            'id' => 'game-pass',
            'kind' => 'gamepass',
            'label' => t('cal.releases'),
        ],
        [
            'id' => 'eventos',
            'kind' => 'event',
            'label' => t('cal.events'),
        ],
        [
            'id' => 'historico',
            'kind' => 'history',
            'label' => t('cal.head_history_link'),
        ],
    ];

    ob_start();
    ?>
<nav class="calendar-section-jumps" aria-label="<?= e(t('cal.jump_nav')) ?>">
    <?php foreach ($items as $item): ?>
        <?php
        $isCurrent = $current !== null && $current === $item['kind'];
        $class = 'calendar-section-jumps__btn' . ($isCurrent ? ' is-current' : '');
        $label = (string) $item['label'];
        ?>
        <a
            class="<?= e($class) ?>"
            href="#<?= e((string) $item['id']) ?>"
            title="<?= e($label) ?>"
            aria-label="<?= e($label) ?>"
            <?= $isCurrent ? 'aria-current="true"' : '' ?>
        >
            <?= calendar_lucide_icon((string) $item['kind'], 16) ?>
        </a>
    <?php endforeach; ?>
</nav>
    <?php

    return (string) ob_get_clean();
}

/**
 * Menu compacto no topo do calendário com ícones e âncoras.
 */
function render_calendar_jump_nav(): string
{
    ob_start();
    ?>
<div class="section-head calendar-jumpnav-wrap">
    <nav class="calendar-jumpnav" aria-label="<?= e(t('cal.jump_nav')) ?>">
        <a class="calendar-jumpnav__link" href="#game-pass">
            <?= calendar_lucide_icon('gamepass', 15) ?>
            <span><?= e(t('cal.head_before')) ?></span>
        </a>
        <span class="calendar-jumpnav__punct" aria-hidden="true">,</span>
        <a class="calendar-jumpnav__link" href="#eventos">
            <?= calendar_lucide_icon('event', 15) ?>
            <span><?= e(t('cal.events')) ?></span>
        </a>
        <span class="calendar-jumpnav__sep"><?= e(t('cal.head_and')) ?></span>
        <a class="calendar-jumpnav__link" href="#historico">
            <?= calendar_lucide_icon('history', 15) ?>
            <span><?= e(t('cal.head_history_link')) ?></span>
        </a>
        <span class="calendar-jumpnav__suffix"><?= e(t('cal.head_history_suffix')) ?></span>
    </nav>
</div>
    <?php

    return (string) ob_get_clean();
}

/**
 * Itens do ticker da home: lançamentos (30 dias) + eventos futuros + histórico de hoje.
 * Ordenados do mais próximo ao mais distante no tempo.
 *
 * @return list<array{type: 'gamepass'|'event'|'history', text: string, on_gamepass?: bool, is_today?: bool}>
 */
function calendar_ticker_items(PDO $pdo): array
{
    $items = [];
    $now = time();

    foreach (calendar_list_upcoming_gamepass($pdo, 30) as $row) {
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $timestamp = strtotime((string) ($row['release_at'] ?? ''));
        $isToday = $timestamp !== false && date('Y-m-d', $timestamp) === date('Y-m-d');
        $onGamepass = !empty($row['on_gamepass']);
        if ($isToday) {
            $suffix = $onGamepass
                ? t('cal.ticker_available_today_gp')
                : t('cal.ticker_available_today_xbox');
            $text = $title . ' ' . $suffix;
        } else {
            $date = '';
            if ($timestamp !== false) {
                $date = match (current_lang()) {
                    'en' => date('m/d', $timestamp),
                    default => date('d/m', $timestamp),
                };
            }
            $text = $date !== '' ? $title . ' ' . $date : $title;
        }
        $items[] = [
            'type' => 'gamepass',
            'text' => $text,
            'on_gamepass' => $onGamepass,
            'is_today' => $isToday,
            'sort_at' => $timestamp !== false ? $timestamp : PHP_INT_MAX,
        ];
    }

    foreach (calendar_list_upcoming_events($pdo) as $event) {
        $title = trim((string) ($event['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $startsAt = (string) ($event['starts_at'] ?? '');
        $endsAt = calendar_event_end_at($event);
        $timestamp = strtotime($startsAt);
        $progress = calendar_event_day_progress($startsAt, $endsAt);
        $isToday = $progress !== null;
        $items[] = [
            'type' => 'event',
            'text' => calendar_event_ticker_phrase($startsAt, $title, $endsAt),
            'is_today' => $isToday,
            'sort_at' => $timestamp !== false ? $timestamp : PHP_INT_MAX,
        ];
    }

    foreach (calendar_list_history_today($pdo) as $item) {
        $items[] = [
            'type' => 'history',
            'text' => calendar_history_headline($item),
            'is_today' => true,
            'sort_at' => $now,
        ];
    }

    usort(
        $items,
        static function (array $a, array $b): int {
            $byDate = ($a['sort_at'] <=> $b['sort_at']);
            if ($byDate !== 0) {
                return $byDate;
            }

            return strcmp((string) $a['text'], (string) $b['text']);
        }
    );

    foreach ($items as &$item) {
        unset($item['sort_at']);
    }
    unset($item);

    return $items;
}

/**
 * @param list<array{type: 'gamepass'|'event'|'history', text: string, on_gamepass?: bool, is_today?: bool}> $items
 */
function render_calendar_ticker(array $items): string
{
    if ($items === []) {
        return '';
    }

    ob_start();
    ?>
<div class="site-ticker" aria-label="<?= e(t('cal.ticker_aria')) ?>">
    <div class="site-ticker__viewport">
        <div class="site-ticker__track">
            <?php for ($loop = 0; $loop < 2; ++$loop): ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $isGp = (($item['type'] ?? '') === 'gamepass') && !empty($item['on_gamepass']);
                    $isToday = !empty($item['is_today']);
                    $itemClass = 'site-ticker__item site-ticker__item--' . e((string) $item['type']);
                    if ($isGp) {
                        $itemClass .= ' site-ticker__item--gp';
                    }
                    ?>
                    <span class="<?= $itemClass ?>"<?= $loop === 1 ? ' aria-hidden="true"' : '' ?>>
                        <span class="site-ticker__content<?= $isToday ? ' site-ticker__content--today' : '' ?>">
                            <?php if ($isGp): ?>
                                <span class="site-ticker__gp-badge" title="Game Pass">
                                    <?= calendar_lucide_icon((string) $item['type'], 14) ?>
                                    <span class="site-ticker__gp-label">GP</span>
                                </span>
                            <?php else: ?>
                                <?= calendar_lucide_icon((string) $item['type'], 15) ?>
                            <?php endif; ?>
                            <span class="site-ticker__text"><?= e((string) $item['text']) ?></span>
                        </span>
                        <span class="site-ticker__sep" aria-hidden="true">·</span>
                    </span>
                <?php endforeach; ?>
            <?php endfor; ?>
        </div>
    </div>
</div>
    <?php

    return (string) ob_get_clean();
}

/**
 * @return list<string>
 */
function calendar_gamepass_subscriptions(array $row): array
{
    $labels = [];
    if (!empty($row['on_ultimate'])) {
        $labels[] = 'Ultimate';
    }
    if (!empty($row['on_premium'])) {
        $labels[] = 'Premium';
    }
    if (!empty($row['on_essential'])) {
        $labels[] = 'Essential';
    }
    if (!empty($row['on_pc'])) {
        $labels[] = 'PC';
    }

    return $labels;
}

/**
 * Ícone Xbox (sphere) para plataforma Console — branco no tema escuro, verde no claro.
 */
function calendar_xbox_icon_html(int $size = 14): string
{
    $sizePx = max(12, $size);

    return '<span class="icon-xbox" style="--icon-xbox-size:' . $sizePx . 'px" aria-hidden="true"></span>';
}

/**
 * @param array{icon?: string, label?: string} $platform
 */
function calendar_platform_icon_html(array $platform, int $size = 14): string
{
    if (($platform['icon'] ?? '') === 'xbox') {
        return calendar_xbox_icon_html($size);
    }

    return htmlspecialchars((string) ($platform['icon'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @return list<array{icon: string, label: string}>
 */
function calendar_gamepass_platforms(array $row): array
{
    $out = [];
    if (!empty($row['platform_console'])) {
        $out[] = ['icon' => 'xbox', 'label' => t('cal.console')];
    }
    if (!empty($row['platform_pc'])) {
        $out[] = ['icon' => '💻', 'label' => 'PC'];
    }
    if (!empty($row['platform_cloud'])) {
        $out[] = ['icon' => '☁️', 'label' => t('cal.cloud')];
    }

    return $out;
}

/** Microsoft Rewards: preço BRL × 172,1, arredondado para baixo até a dezena. */
const CALENDAR_REWARDS_POINTS_PER_BRL = 172.1;

function calendar_price_brl(array $row): float
{
    $raw = $row['price_brl'] ?? null;
    if ($raw === null || $raw === '') {
        return 0.0;
    }

    return max(0.0, (float) $raw);
}

function calendar_rewards_points(float $priceBrl): int
{
    if ($priceBrl <= 0) {
        return 0;
    }

    return (int) (floor(($priceBrl * CALENDAR_REWARDS_POINTS_PER_BRL) / 10) * 10);
}

function calendar_format_rewards_points(int $points): string
{
    if ($points <= 0) {
        return '';
    }

    return number_format($points, 0, ',', '.');
}

/**
 * Badge Rewards para jogos fora do Game Pass com preço.
 *
 * @return array{points: int, label: string, tip: string}|null
 */
function calendar_rewards_info(array $row): ?array
{
    if (!empty($row['on_gamepass'])) {
        return null;
    }

    $points = calendar_rewards_points(calendar_price_brl($row));
    if ($points <= 0) {
        return null;
    }

    $label = calendar_format_rewards_points($points);

    return [
        'points' => $points,
        'label' => $label,
        'tip' => t('cal.rewards_tip', ['n' => $label]),
    ];
}

function calendar_rewards_badge_html(array $row, string $extraClass = ''): string
{
    $info = calendar_rewards_info($row);
    if ($info === null) {
        return '';
    }

    $class = trim('cal-rewards ' . $extraClass);

    return '<span class="' . e($class) . '">'
        . '<span class="cal-rewards__medal" title="' . e($info['tip']) . '" aria-label="' . e($info['tip']) . '">🏅</span>'
        . '<span class="cal-rewards__points">' . e($info['label']) . '</span>'
        . '</span>';
}

function calendar_get_gamepass(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM gp_releases WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

function calendar_get_event(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM calendar_events WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

function calendar_get_history(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM xbox_history WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

function calendar_normalize_entity_type(string $type): string
{
    $type = strtolower(trim($type));
    if (!in_array($type, ['gamepass', 'event', 'history'], true)) {
        return '';
    }

    return $type;
}

/**
 * @return array{lang: string, title: string, body: string|null, context: string|null, status: string, updated_at: string}|null
 */
function calendar_translation_get(PDO $pdo, string $entityType, int $entityId, string $lang): ?array
{
    $entityType = calendar_normalize_entity_type($entityType);
    $lang = normalize_site_lang($lang);
    if ($entityType === '' || $entityId <= 0 || !in_array($lang, ['en', 'es'], true)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT lang, title, body, context, status, updated_at
         FROM calendar_translations
         WHERE entity_type = :entity_type AND entity_id = :entity_id AND lang = :lang
         LIMIT 1'
    );
    $stmt->execute([
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'lang' => $lang,
    ]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

function calendar_translation_upsert(
    PDO $pdo,
    string $entityType,
    int $entityId,
    string $lang,
    string $title,
    ?string $body,
    ?string $context,
    string $status = 'published'
): void {
    $entityType = calendar_normalize_entity_type($entityType);
    $lang = normalize_site_lang($lang);
    if ($entityType === '' || $entityId <= 0 || !in_array($lang, ['en', 'es'], true)) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO calendar_translations (entity_type, entity_id, lang, title, body, context, status)
         VALUES (:entity_type, :entity_id, :lang, :title, :body, :context, :status)
         ON DUPLICATE KEY UPDATE
             title = VALUES(title),
             body = VALUES(body),
             context = VALUES(context),
             status = VALUES(status)'
    );
    $stmt->execute([
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'lang' => $lang,
        'title' => $title,
        'body' => $body,
        'context' => $context,
        'status' => $status === 'draft' ? 'draft' : 'published',
    ]);
}

/**
 * @param list<int> $ids
 * @return array<int, array<string, array<string, mixed>>>
 */
function calendar_translations_map(PDO $pdo, string $entityType, array $ids): array
{
    $entityType = calendar_normalize_entity_type($entityType);
    $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    if ($entityType === '' || $ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT entity_id, lang, title, body, context, status, updated_at
         FROM calendar_translations
         WHERE entity_type = ? AND entity_id IN ($placeholders)"
    );
    $stmt->execute([$entityType, ...$ids]);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['entity_id'];
        $lang = (string) $row['lang'];
        $map[$id][$lang] = [
            'lang' => $lang,
            'title' => (string) $row['title'],
            'body' => $row['body'] !== null ? (string) $row['body'] : null,
            'context' => $row['context'] !== null ? (string) $row['context'] : null,
            'status' => (string) $row['status'],
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return $map;
}

/**
 * @param list<array<string, mixed>> $items
 * @return list<array<string, mixed>>
 */
function calendar_attach_translations(PDO $pdo, string $entityType, array $items): array
{
    $ids = [];
    foreach ($items as $item) {
        $ids[] = (int) ($item['id'] ?? 0);
    }
    $map = calendar_translations_map($pdo, $entityType, $ids);
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
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function calendar_localize_rows(PDO $pdo, string $entityType, array $rows): array
{
    $lang = function_exists('current_lang') ? current_lang() : 'pt';
    if ($lang === 'pt' || $lang === '' || $rows === []) {
        return $rows;
    }

    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (int) ($row['id'] ?? 0);
    }
    $map = calendar_translations_map($pdo, $entityType, $ids);
    foreach ($rows as &$row) {
        $id = (int) ($row['id'] ?? 0);
        $tr = $map[$id][$lang] ?? null;
        if ($tr === null || ($tr['status'] ?? '') !== 'published') {
            continue;
        }
        if (trim((string) ($tr['title'] ?? '')) !== '') {
            $row['title'] = $tr['title'];
        }
        if (array_key_exists('body', $row) && $tr['body'] !== null && trim((string) $tr['body']) !== '') {
            $row['body'] = $tr['body'];
        }
        if (array_key_exists('context', $row) && $tr['context'] !== null && trim((string) $tr['context']) !== '') {
            $row['context'] = $tr['context'];
        }
    }
    unset($row);

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function calendar_list_gamepass_admin(PDO $pdo): array
{
    $items = $pdo->query(
        'SELECT *
         FROM gp_releases
         ORDER BY release_at DESC, sort_order ASC, id DESC'
    )->fetchAll();

    return calendar_attach_translations($pdo, 'gamepass', $items);
}

/**
 * @return list<array<string, mixed>>
 */
function calendar_list_events_admin(PDO $pdo): array
{
    $items = $pdo->query(
        'SELECT *
         FROM calendar_events
         ORDER BY starts_at DESC, id DESC'
    )->fetchAll();

    return calendar_attach_translations($pdo, 'event', $items);
}

/**
 * @return list<array<string, mixed>>
 */
function calendar_list_history_admin(PDO $pdo): array
{
    $items = $pdo->query(
        'SELECT *
         FROM xbox_history
         ORDER BY occurred_month ASC, occurred_day ASC, occurred_year DESC, id DESC'
    )->fetchAll();

    return calendar_attach_translations($pdo, 'history', $items);
}

function calendar_delete_gamepass(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM gp_releases WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $pdo->prepare('DELETE FROM calendar_translations WHERE entity_type = \'gamepass\' AND entity_id = :id')
        ->execute(['id' => $id]);
}

function calendar_delete_event(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM calendar_events WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $pdo->prepare('DELETE FROM calendar_translations WHERE entity_type = \'event\' AND entity_id = :id')
        ->execute(['id' => $id]);
}

function calendar_delete_history(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM xbox_history WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $pdo->prepare('DELETE FROM calendar_translations WHERE entity_type = \'history\' AND entity_id = :id')
        ->execute(['id' => $id]);
}

function calendar_create_gamepass(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO gp_releases (
            title, slug, cover_url, store_url, price_brl, trailer_url, show_on_tv, release_at, body,
            on_gamepass, on_ultimate, on_premium, on_essential, on_pc,
            platform_console, platform_pc, platform_cloud,
            play_anywhere, supports_pt_br,
            status, sort_order
         ) VALUES (
            :title, :slug, :cover_url, :store_url, :price_brl, :trailer_url, :show_on_tv, :release_at, :body,
            :on_gamepass, :on_ultimate, :on_premium, :on_essential, :on_pc,
            :platform_console, :platform_pc, :platform_cloud,
            :play_anywhere, :supports_pt_br,
            :status, :sort_order
         )'
    );
    $stmt->execute($data);

    return (int) $pdo->lastInsertId();
}

function calendar_update_gamepass(PDO $pdo, int $id, array $data): void
{
    $data['id'] = $id;
    $stmt = $pdo->prepare(
        'UPDATE gp_releases SET
            title = :title,
            slug = :slug,
            cover_url = :cover_url,
            store_url = :store_url,
            price_brl = :price_brl,
            trailer_url = :trailer_url,
            show_on_tv = :show_on_tv,
            release_at = :release_at,
            body = :body,
            on_gamepass = :on_gamepass,
            on_ultimate = :on_ultimate,
            on_premium = :on_premium,
            on_essential = :on_essential,
            on_pc = :on_pc,
            platform_console = :platform_console,
            platform_pc = :platform_pc,
            platform_cloud = :platform_cloud,
            play_anywhere = :play_anywhere,
            supports_pt_br = :supports_pt_br,
            status = :status,
            sort_order = :sort_order
         WHERE id = :id'
    );
    $stmt->execute($data);
}

function calendar_create_event(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO calendar_events (title, banner_url, starts_at, ends_at, body, external_url, status)
         VALUES (:title, :banner_url, :starts_at, :ends_at, :body, :external_url, :status)'
    );
    $stmt->execute($data);

    return (int) $pdo->lastInsertId();
}

function calendar_update_event(PDO $pdo, int $id, array $data): void
{
    $data['id'] = $id;
    $stmt = $pdo->prepare(
        'UPDATE calendar_events SET
            title = :title,
            banner_url = :banner_url,
            starts_at = :starts_at,
            ends_at = :ends_at,
            body = :body,
            external_url = :external_url,
            status = :status
         WHERE id = :id'
    );
    $stmt->execute($data);
}

function calendar_create_history(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO xbox_history (
            title, occurred_year, occurred_month, occurred_day, context, body, cover_url, status
         ) VALUES (
            :title, :occurred_year, :occurred_month, :occurred_day, :context, :body, :cover_url, :status
         )'
    );
    $stmt->execute($data);

    return (int) $pdo->lastInsertId();
}

function calendar_update_history(PDO $pdo, int $id, array $data): void
{
    $data['id'] = $id;
    $stmt = $pdo->prepare(
        'UPDATE xbox_history SET
            title = :title,
            occurred_year = :occurred_year,
            occurred_month = :occurred_month,
            occurred_day = :occurred_day,
            context = :context,
            body = :body,
            cover_url = :cover_url,
            status = :status
         WHERE id = :id'
    );
    $stmt->execute($data);
}

function calendar_card_media_badges(array $row): string
{
    $onGamePass = !empty($row['on_gamepass']);
    $playAnywhere = !empty($row['play_anywhere']);
    $showPtBr = !empty($row['supports_pt_br']) && current_lang() === 'pt';

    if (!$onGamePass && !$playAnywhere && !$showPtBr) {
        return '';
    }

    ob_start();
    if ($onGamePass): ?>
        <span class="cal-card__gp-badge">Game Pass</span>
    <?php endif;
    if ($playAnywhere || $showPtBr): ?>
        <div class="cal-card__corner-badges">
            <?php if ($playAnywhere): ?>
                <img
                    class="cal-card__xpa"
                    src="/assets/img/play-anywhere.png"
                    alt="<?= e(t('cal.play_anywhere')) ?>"
                    title="<?= e(t('cal.play_anywhere')) ?>"
                    width="32"
                    height="32"
                    loading="lazy"
                >
            <?php endif; ?>
            <?php if ($showPtBr): ?>
                <span class="cal-card__ptbr" title="<?= e(t('cal.supports_pt_br')) ?>" aria-label="<?= e(t('cal.supports_pt_br')) ?>">🇧🇷</span>
            <?php endif; ?>
        </div>
    <?php endif;

    return (string) ob_get_clean();
}

function calendar_today_date_label(?DateTimeImmutable $now = null): string
{
    $now ??= new DateTimeImmutable('today');
    $day = $now->format('j');
    $month = t('cal.month.' . (int) $now->format('n'));

    return match (current_lang()) {
        'en' => $month . ' ' . $day,
        default => $day . ' de ' . $month,
    };
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function calendar_releases_for_today(array $rows, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('today');
    $today = $now->format('Y-m-d');
    $out = [];

    foreach ($rows as $row) {
        $releaseAt = (string) ($row['release_at'] ?? '');
        if ($releaseAt === '') {
            continue;
        }
        $ts = strtotime($releaseAt);
        if ($ts === false) {
            continue;
        }
        if (date('Y-m-d', $ts) === $today) {
            $out[] = $row;
        }
    }

    return $out;
}

function render_calendar_game_card(array $row): string
{
    $subs = calendar_gamepass_subscriptions($row);
    $platforms = calendar_gamepass_platforms($row);
    $arriveLabel = release_arrive_label((string) $row['release_at']);
    $title = (string) $row['title'];
    $body = trim((string) ($row['body'] ?? ''));
    $tipId = 'gp-tip-' . (int) $row['id'];
    $onGamePass = !empty($row['on_gamepass']);
    $mediaBadges = calendar_card_media_badges($row);
    $rewardsBadge = calendar_rewards_badge_html($row, 'cal-rewards--card cal-rewards--on-media');
    $rewardsBadgeList = calendar_rewards_badge_html($row, 'cal-rewards--card cal-rewards--on-list');
    $storeUrl = trim((string) ($row['store_url'] ?? ''));
    $trailerUrl = trim((string) ($row['trailer_url'] ?? ''));
    $storeLabel = t('cal.store');
    $trailerLabel = t('cal.trailer');
    $releaseAt = (string) ($row['release_at'] ?? '');
    $isToday = $releaseAt !== '' && calendar_days_until($releaseAt) === 0;
    $monthKey = '';
    if ($releaseAt !== '') {
        $ts = strtotime($releaseAt);
        if ($ts !== false) {
            $monthKey = date('Y-m', $ts);
        }
    }
    $subKeys = [];
    if (!empty($row['on_ultimate'])) {
        $subKeys[] = 'ultimate';
    }
    if (!empty($row['on_premium'])) {
        $subKeys[] = 'premium';
    }
    if (!empty($row['on_essential'])) {
        $subKeys[] = 'essential';
    }
    if (!empty($row['on_pc'])) {
        $subKeys[] = 'pc';
    }
    $platformKeys = [];
    if (!empty($row['platform_console'])) {
        $platformKeys[] = 'console';
    }
    if (!empty($row['platform_pc'])) {
        $platformKeys[] = 'pc';
    }
    if (!empty($row['platform_cloud'])) {
        $platformKeys[] = 'cloud';
    }

    ob_start();
    ?>
                    <article
                        class="cal-card<?= $isToday ? ' cal-card--today' : '' ?>"
                        data-subs="<?= e(implode(' ', $subKeys)) ?>"
                        data-platforms="<?= e(implode(' ', $platformKeys)) ?>"
                        data-month="<?= e($monthKey) ?>"
                        data-gamepass="<?= $onGamePass ? '1' : '0' ?>"
                    >
                        <?php if (!empty($row['cover_url'])): ?>
                            <div class="cal-card__media">
                                <img src="<?= e((string) $row['cover_url']) ?>" alt="" loading="lazy">
                                <?= $mediaBadges ?>
                                <?= $rewardsBadge ?>
                            </div>
                        <?php else: ?>
                            <div class="cal-card__media cal-card__media--ph" aria-hidden="true">
                                🎮
                                <?= $mediaBadges ?>
                                <?= $rewardsBadge ?>
                            </div>
                        <?php endif; ?>
                        <div class="cal-card__body">
                            <h4><?= $isToday ? calendar_today_title_html($title) : e($title) ?></h4>
                            <?php if ($arriveLabel !== '' || $storeUrl !== '' || $trailerUrl !== ''): ?>
                                <div class="cal-card__meta">
                                    <?php if ($arriveLabel !== ''): ?>
                                        <span class="cal-card__arrive"><?= e($arriveLabel) ?></span>
                                    <?php else: ?>
                                        <span class="cal-card__arrive" aria-hidden="true"></span>
                                    <?php endif; ?>
                                    <?php if ($storeUrl !== '' || $trailerUrl !== ''): ?>
                                        <span class="cal-card__links">
                                            <?php if ($storeUrl !== ''): ?>
                                                <a
                                                    class="cal-card__store"
                                                    href="<?= e($storeUrl) ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    title="<?= e($storeLabel) ?>"
                                                    aria-label="<?= e($storeLabel) ?>"
                                                >🛍️ <?= e($storeLabel) ?></a>
                                            <?php endif; ?>
                                            <?php if ($trailerUrl !== ''): ?>
                                                <a
                                                    class="cal-card__trailer"
                                                    href="<?= e($trailerUrl) ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    title="<?= e($trailerLabel) ?>"
                                                    aria-label="<?= e($trailerLabel) ?>"
                                                ><?= youtube_icon_svg(18) ?></a>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($subs !== []): ?>
                                <div class="cal-badges">
                                    <?php foreach ($subs as $sub): ?>
                                        <span class="cal-badge"><?= e($sub) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($platforms !== []): ?>
                                <div class="cal-platforms" aria-label="<?= e(t('cal.platforms')) ?>">
                                    <?php foreach ($platforms as $platform): ?>
                                        <span title="<?= e($platform['label']) ?>"><?= calendar_platform_icon_html($platform) ?> <?= e($platform['label']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="cal-card__blurb" data-cal-more>
                                <button
                                    type="button"
                                    class="cal-card__info"
                                    aria-expanded="false"
                                    aria-controls="<?= e($tipId) ?>"
                                    aria-label="<?= e(t('cal.more_info')) ?>"
                                    title="<?= e(t('cal.more_info')) ?>"
                                ><?= calendar_lucide_icon('info', 18) ?></button>
                <p class="cal-card__excerpt<?= $body === '' ? ' cal-card__excerpt--empty' : '' ?>">
                                    <?php if ($body !== ''): ?><?= e(excerpt_from_html($body, 220)) ?> <?php endif; ?><button
                                        type="button"
                                        class="cal-card__more"
                                        aria-expanded="false"
                                        aria-controls="<?= e($tipId) ?>"
                                    ><?= e(t('cal.more')) ?></button>
                                </p>
                                <div class="cal-card__popout" id="<?= e($tipId) ?>" role="tooltip">
                                    <p class="cal-card__popout-title"><?= $isToday ? calendar_today_title_html($title) : e($title) ?></p>
                                    <?php if ($arriveLabel !== '' || $storeUrl !== '' || $trailerUrl !== ''): ?>
                                        <div class="cal-card__meta">
                                            <?php if ($arriveLabel !== ''): ?>
                                                <span class="cal-card__arrive"><?= e($arriveLabel) ?></span>
                                            <?php else: ?>
                                                <span class="cal-card__arrive" aria-hidden="true"></span>
                                            <?php endif; ?>
                                            <?php if ($storeUrl !== '' || $trailerUrl !== ''): ?>
                                                <span class="cal-card__links">
                                                    <?php if ($storeUrl !== ''): ?>
                                                        <a
                                                            class="cal-card__store"
                                                            href="<?= e($storeUrl) ?>"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            title="<?= e($storeLabel) ?>"
                                                            aria-label="<?= e($storeLabel) ?>"
                                                        >🛍️ <?= e($storeLabel) ?></a>
                                                    <?php endif; ?>
                                                    <?php if ($trailerUrl !== ''): ?>
                                                        <a
                                                            class="cal-card__trailer"
                                                            href="<?= e($trailerUrl) ?>"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            title="<?= e($trailerLabel) ?>"
                                                            aria-label="<?= e($trailerLabel) ?>"
                                                        ><?= youtube_icon_svg(18) ?></a>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($subs !== []): ?>
                                        <div class="cal-badges">
                                            <?php foreach ($subs as $sub): ?>
                                                <span class="cal-badge"><?= e($sub) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($platforms !== []): ?>
                                        <div class="cal-platforms" aria-label="<?= e(t('cal.platforms')) ?>">
                                            <?php foreach ($platforms as $platform): ?>
                                                <span><?= calendar_platform_icon_html($platform) ?> <?= e($platform['label']) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($body !== ''): ?>
                                        <div class="cal-card__popout-body"><?= calendar_rich_body_html($body) ?></div>
                                    <?php endif; ?>
                                    <?= calendar_rewards_badge_html($row, 'cal-rewards--popout') ?>
                                </div>
                            </div>
                        </div>
                        <?= $rewardsBadgeList ?>
                    </article>
    <?php

    return (string) ob_get_clean();
}

function render_calendar_today_group(array $todayReleases, DateTimeImmutable $now): string
{
    if ($todayReleases === []) {
        return '';
    }

    $count = count($todayReleases);
    $dateLabel = calendar_today_date_label($now);
    $title = t('cal.released_today', ['date' => $dateLabel]);
    $countLabel = $count === 1
        ? t('cal.released_today_count', ['n' => (string) $count])
        : t('cal.released_today_count_plural', ['n' => (string) $count]);
    $monthKey = $now->format('Y-m');

    ob_start();
    ?>
                    <section
                        class="cal-today-group"
                        data-today-card="1"
                        data-month="<?= e($monthKey) ?>"
                    >
                        <div class="cal-today-group__head">
                            <span class="cal-today-group__badge cal-today-pill"><?= e(t('cal.released_today_badge')) ?></span>
                            <h4><?= e($title) ?></h4>
                            <p><?= e($countLabel) ?></p>
                        </div>
                        <div class="cal-today-group__grid">
                            <?php foreach ($todayReleases as $row): ?>
                                <?= render_calendar_game_card($row) ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
    <?php

    return (string) ob_get_clean();
}

/**
 * Itens unificados para a vista de calendário (mês).
 *
 * @param list<array<string, mixed>> $gamepass
 * @param list<array<string, mixed>> $events
 * @param list<array<string, mixed>> $history
 * @return list<array<string, mixed>>
 */
function calendar_unified_month_items(array $gamepass, array $events, array $history, DateTimeImmutable $now): array
{
    $today = $now->format('Y-m-d');
    $items = [];

    foreach ($gamepass as $row) {
        $releaseAt = (string) ($row['release_at'] ?? '');
        $ts = $releaseAt !== '' ? strtotime($releaseAt) : false;
        if ($ts === false) {
            continue;
        }
        $date = date('Y-m-d', $ts);
        $store = trim((string) ($row['store_url'] ?? ''));
        $trailer = trim((string) ($row['trailer_url'] ?? ''));
        $platforms = [];
        foreach (calendar_gamepass_platforms($row) as $platform) {
            $platforms[] = [
                'icon' => (string) ($platform['icon'] ?? ''),
                'label' => (string) ($platform['label'] ?? ''),
            ];
        }
        $rewards = calendar_rewards_info($row);
        $items[] = [
            'type' => 'game',
            'date' => $date,
            'when' => release_arrive_label($releaseAt),
            'title' => (string) ($row['title'] ?? ''),
            'href' => $store !== '' ? $store : '#game-pass',
            'thumb' => (string) ($row['cover_url'] ?? ''),
            'body' => excerpt_from_html((string) ($row['body'] ?? ''), 200),
            'store' => $store,
            'trailer' => $trailer,
            'subs' => calendar_gamepass_subscriptions($row),
            'platforms' => $platforms,
            'today' => $date === $today,
            'gp' => !empty($row['on_gamepass']),
            'rewards' => $rewards !== null ? [
                'label' => $rewards['label'],
                'tip' => $rewards['tip'],
            ] : null,
        ];
    }

    foreach ($events as $row) {
        $starts = (string) ($row['starts_at'] ?? '');
        $ts = $starts !== '' ? strtotime($starts) : false;
        if ($ts === false) {
            continue;
        }
        $ends = calendar_event_end_at($row);
        $endTs = strtotime($ends);
        if ($endTs === false) {
            $endTs = $ts;
        }

        $startDay = (new DateTimeImmutable(date('Y-m-d', $ts)));
        $endDay = (new DateTimeImmutable(date('Y-m-d', $endTs)));
        if ($endDay < $startDay) {
            $endDay = $startDay;
        }

        $ext = trim((string) ($row['external_url'] ?? ''));
        $rawTitle = (string) ($row['title'] ?? '');
        $bodyExcerpt = excerpt_from_html((string) ($row['body'] ?? ''), 200);
        $totalDays = ((int) $startDay->diff($endDay)->format('%a')) + 1;

        for ($cursor = $startDay, $dayNum = 1; $cursor <= $endDay; $cursor = $cursor->modify('+1 day'), ++$dayNum) {
            $date = $cursor->format('Y-m-d');
            $isThatDayToday = $date === $today;
            $items[] = [
                'type' => 'event',
                'date' => $date,
                'when' => calendar_event_date_range_label($starts, $ends),
                'title' => calendar_event_day_label($rawTitle, $dayNum, $totalDays, $isThatDayToday),
                'href' => $ext !== '' ? $ext : '#eventos',
                'thumb' => (string) ($row['banner_url'] ?? ''),
                'body' => $bodyExcerpt,
                'store' => '',
                'trailer' => '',
                'subs' => [],
                'platforms' => [],
                'today' => $isThatDayToday,
                'gp' => false,
                'external' => $ext,
            ];
        }
    }

    foreach ($history as $row) {
        $month = (int) ($row['occurred_month'] ?? 0);
        $day = (int) ($row['occurred_day'] ?? 0);
        $year = (int) ($row['occurred_year'] ?? 0);
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            continue;
        }
        $when = $year > 0
            ? sprintf('%02d/%02d/%04d', $day, $month, $year)
            : sprintf('%02d/%02d', $day, $month);
        $items[] = [
            'type' => 'history',
            'month' => $month,
            'day' => $day,
            'when' => $when,
            'title' => calendar_history_headline($row),
            'href' => '#historico',
            'thumb' => (string) ($row['cover_url'] ?? ''),
            'body' => excerpt_from_html((string) ($row['body'] ?? ''), 200),
            'store' => '',
            'trailer' => '',
            'subs' => [],
            'platforms' => [],
            'today' => $month === (int) $now->format('n') && $day === (int) $now->format('j'),
            'gp' => false,
        ];
    }

    return $items;
}

function render_calendar_view_switcher(): string
{
    ob_start();
    ?>
    <div class="cal-view-switch" role="group" aria-label="<?= e(t('cal.view_aria')) ?>" data-cal-view-switch>
        <button type="button" class="cal-view-switch__btn is-active" data-cal-view="cards" aria-pressed="true">
            <?= calendar_lucide_icon('view-cards', 16) ?>
            <span><?= e(t('cal.view_cards')) ?></span>
        </button>
        <button type="button" class="cal-view-switch__btn" data-cal-view="list" aria-pressed="false">
            <?= calendar_lucide_icon('view-list', 16) ?>
            <span><?= e(t('cal.view_list')) ?></span>
        </button>
        <button type="button" class="cal-view-switch__btn cal-view-switch__btn--desktop" data-cal-view="month" aria-pressed="false">
            <?= calendar_lucide_icon('view-month', 16) ?>
            <span><?= e(t('cal.view_month')) ?></span>
        </button>
    </div>
    <?php

    return (string) ob_get_clean();
}

function render_calendar_content(array $config, array $gamepass, array $history, array $events = []): string
{
    $monthNames = calendar_month_names_map();
    $now = new DateTimeImmutable('today');
    $currentMonthKey = $now->format('Y-m');
    $currentMonthLabel = ($monthNames[(int) $now->format('n')] ?? $now->format('F')) . ' - ' . $now->format('Y');
    $monthNamesJson = json_encode(calendar_month_names_list(), JSON_UNESCAPED_UNICODE);
    $weekdays = [];
    for ($i = 0; $i < 7; ++$i) {
        $weekdays[] = t('cal.weekday.' . $i);
    }
    $weekdaysJson = json_encode($weekdays, JSON_UNESCAPED_UNICODE);
    $monthItems = calendar_unified_month_items($gamepass, $events, $history, $now);
    $monthItemsJson = json_encode($monthItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $todayReleases = calendar_releases_for_today($gamepass, $now);
    $todayIds = [];
    foreach ($todayReleases as $row) {
        $todayIds[(int) $row['id']] = true;
    }
    $otherReleases = [];
    foreach ($gamepass as $row) {
        if (!isset($todayIds[(int) $row['id']])) {
            $otherReleases[] = $row;
        }
    }
    $todayGroupHtml = render_calendar_today_group($todayReleases, $now);
    $typeLabels = [
        'game' => t('cal.type_game'),
        'event' => t('cal.type_event'),
        'history' => t('cal.type_history'),
    ];
    $typeLabelsJson = json_encode($typeLabels, JSON_UNESCAPED_UNICODE);
    $uiLabels = [
        'store' => t('cal.store'),
        'trailer' => t('cal.trailer'),
        'learn_more' => t('cal.learn_more'),
        'today_prefix' => t('cal.released_today_badge'),
        'gp' => 'Game Pass',
    ];
    $uiLabelsJson = json_encode($uiLabels, JSON_UNESCAPED_UNICODE);

    ob_start();
    ?>
<section
    class="calendar"
    data-cal-shell
    data-cal-view-mode="cards"
    data-month-names="<?= e((string) $monthNamesJson) ?>"
    data-weekdays="<?= e((string) $weekdaysJson) ?>"
    data-type-labels="<?= e((string) $typeLabelsJson) ?>"
    data-ui-labels="<?= e((string) $uiLabelsJson) ?>"
    data-today="<?= e($now->format('Y-m-d')) ?>"
>
    <div class="calendar-toolbar">
        <?= render_calendar_jump_nav() ?>
        <?= render_calendar_view_switcher() ?>
    </div>

    <div class="cal-cards-panels" data-cal-cards-panels>
    <section id="game-pass" class="calendar-panel" data-gp-panel data-month-names="<?= e((string) $monthNamesJson) ?>">
        <div class="calendar-panel__head">
            <div class="calendar-panel__title-row">
                <h3><?= calendar_lucide_icon('gamepass', 22) ?> <?= e(t('cal.releases')) ?></h3>
                <?= render_calendar_section_jumps('gamepass') ?>
            </div>
            <div class="gp-toolbar">
                <div class="gp-filters" data-gp-filters>
                    <div class="gp-filters__group" role="group" aria-label="<?= e(t('cal.subscriptions')) ?>">
                        <span class="gp-filters__label"><?= e(t('cal.subscriptions')) ?></span>
                        <div class="gp-filters__chips">
                            <button type="button" class="gp-filter" data-filter-type="sub" data-filter-value="ultimate" aria-pressed="false">Ultimate</button>
                            <button type="button" class="gp-filter" data-filter-type="sub" data-filter-value="premium" aria-pressed="false">Premium</button>
                            <button type="button" class="gp-filter" data-filter-type="sub" data-filter-value="essential" aria-pressed="false">Essential</button>
                            <button type="button" class="gp-filter" data-filter-type="sub" data-filter-value="pc" aria-pressed="false">PC</button>
                        </div>
                    </div>
                    <div class="gp-filters__group" role="group" aria-label="<?= e(t('cal.devices')) ?>">
                        <span class="gp-filters__label"><?= e(t('cal.devices')) ?></span>
                        <div class="gp-filters__chips">
                            <button type="button" class="gp-filter" data-filter-type="platform" data-filter-value="console" aria-pressed="false"><?= calendar_xbox_icon_html(14) ?> <?= e(t('cal.console')) ?></button>
                            <button type="button" class="gp-filter" data-filter-type="platform" data-filter-value="pc" aria-pressed="false">💻 PC</button>
                            <button type="button" class="gp-filter" data-filter-type="platform" data-filter-value="cloud" aria-pressed="false">☁️ <?= e(t('cal.cloud')) ?></button>
                        </div>
                    </div>
                    <button type="button" class="gp-filters__clear" data-gp-clear hidden><?= e(t('cal.clear_filters')) ?></button>
                </div>
                <div class="gp-toolbar__aside">
                    <div class="gp-month" data-gp-month data-month="<?= e($currentMonthKey) ?>">
                        <button type="button" class="gp-month__nav" data-gp-month-prev aria-label="<?= e(t('cal.prev_month')) ?>">
                            <?= calendar_lucide_icon('chevron-left', 18) ?>
                        </button>
                        <p class="gp-month__label" data-gp-month-label><?= e($currentMonthLabel) ?></p>
                        <button type="button" class="gp-month__nav" data-gp-month-next aria-label="<?= e(t('cal.next_month')) ?>">
                            <?= calendar_lucide_icon('chevron-right', 18) ?>
                        </button>
                    </div>
                    <div class="gp-service-filters" role="group" aria-label="<?= e(t('cal.gp_filter')) ?>" data-gp-service>
                        <button type="button" class="gp-service-filter is-active" data-service-filter="all" aria-pressed="true"><?= e(t('cal.all')) ?></button>
                        <button type="button" class="gp-service-filter" data-service-filter="yes" aria-pressed="false">Game Pass</button>
                        <button type="button" class="gp-service-filter" data-service-filter="no" aria-pressed="false"><?= e(t('cal.on_xbox')) ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($gamepass === []): ?>
            <div class="empty"><p><?= e(t('cal.no_releases')) ?></p></div>
        <?php else: ?>
            <div class="calendar-grid" data-gp-grid>
                <?= $todayGroupHtml ?>
                <?php foreach ($otherReleases as $row): ?>
                    <?= render_calendar_game_card($row) ?>
                <?php endforeach; ?>
            </div>
            <div class="empty gp-filters__empty" data-gp-empty hidden>
                <p><?= e(t('cal.no_filter_matches')) ?></p>
            </div>
        <?php endif; ?>
    </section>

    <section id="historico" class="calendar-panel">
        <div class="calendar-panel__head">
            <div class="calendar-panel__title-row">
                <h3><?= calendar_lucide_icon('history', 22) ?> <?= e(t('cal.history')) ?></h3>
                <?= render_calendar_section_jumps('history') ?>
            </div>
            <p><?= e(t('cal.history_lead')) ?></p>
        </div>
        <?php if ($history === []): ?>
            <div class="empty"><p><?= e(t('cal.no_history')) ?></p></div>
        <?php else: ?>
            <ul class="history-list">
                <?php foreach ($history as $item): ?>
                    <?php
                    $hDay = (int) ($item['occurred_day'] ?? 0);
                    $hMonth = (int) ($item['occurred_month'] ?? 0);
                    $hYear = (int) ($item['occurred_year'] ?? 0);
                    $hMonthName = $monthNames[$hMonth] ?? sprintf('%02d', $hMonth);
                    $hIso = ($hYear > 0 && $hMonth > 0 && $hDay > 0)
                        ? sprintf('%04d-%02d-%02d', $hYear, $hMonth, $hDay)
                        : '';
                    $isHistoryToday = $hDay === (int) $now->format('j') && $hMonth === (int) $now->format('n');
                    $historyClass = 'history-item' . ($isHistoryToday ? ' history-item--today' : '');
                    $historyBody = trim((string) ($item['body'] ?? ''));
                    $historyPreview = $historyBody !== '' ? excerpt_from_html($historyBody, 180) : '';
                    $historyWhen = $hIso !== ''
                        ? sprintf('%02d/%02d/%04d', $hDay, $hMonth, $hYear)
                        : '';
                    $historyPop = [
                        'type' => 'history',
                        'title' => calendar_history_headline($item),
                        'when' => $historyWhen,
                        'thumb' => (string) ($item['cover_url'] ?? ''),
                        'body' => $historyBody,
                        'today' => $isHistoryToday,
                        'href' => '#historico',
                    ];
                    $historyLen = function_exists('mb_strlen')
                        ? mb_strlen($historyBody)
                        : strlen($historyBody);
                    $historyHasMore = $historyLen > 110;
                    if ($historyHasMore && $historyPreview !== '') {
                        $endsEllipsis = function_exists('str_ends_with')
                            ? str_ends_with($historyPreview, '…')
                            : substr($historyPreview, -3) === '…' || substr($historyPreview, -1) === '…';
                        if ($endsEllipsis) {
                            $historyPreview = function_exists('mb_substr')
                                ? rtrim(mb_substr($historyPreview, 0, -1))
                                : rtrim(substr($historyPreview, 0, -3));
                        }
                    }
                    ?>
                    <li class="<?= e($historyClass) ?>">
                        <div class="history-item__media">
                            <?php if (!empty($item['cover_url'])): ?>
                                <img class="history-item__cover" src="<?= e((string) $item['cover_url']) ?>" alt="" loading="lazy">
                            <?php endif; ?>
                            <?php if ($hDay > 0 && $hMonth > 0 && $hYear > 0): ?>
                                <time class="history-item__date" datetime="<?= e($hIso) ?>">
                                    <span class="history-item__day"><?= e(sprintf('%02d', $hDay)) ?></span>
                                    <span class="history-item__month"><?= e($hMonthName) ?></span>
                                    <span class="history-item__year"><?= e((string) $hYear) ?></span>
                                </time>
                            <?php endif; ?>
                        </div>
                        <div class="history-item__content">
                            <p class="history-item__headline"><?= $isHistoryToday
                                ? calendar_today_title_html(calendar_history_headline($item))
                                : e(calendar_history_headline($item)) ?></p>
                            <?php if ($historyBody !== ''): ?>
                                <div class="history-item__body"><?= calendar_rich_body_html($historyBody) ?></div>
                                <?php if ($historyHasMore): ?>
                                    <p class="history-item__preview">
                                        <button
                                            type="button"
                                            class="history-item__more"
                                            data-history-more
                                            aria-expanded="false"
                                            data-history-item="<?= e(json_encode($historyPop, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS)) ?>"
                                        ><?= e(t('cal.more')) ?></button>
                                        <span class="history-item__preview-text"><?= e($historyPreview) ?></span>
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    </div>

    <section class="calendar-panel cal-month-panel" data-cal-month-panel hidden>
        <div class="calendar-panel__head">
            <div class="calendar-panel__title-row">
                <h3><?= calendar_lucide_icon('view-month', 22) ?> <?= e(t('cal.view_month')) ?></h3>
            </div>
            <div class="cal-month-panel__toolbar">
                <div class="gp-month cal-month-panel__nav" data-cal-month-nav data-month="<?= e($currentMonthKey) ?>">
                    <button type="button" class="gp-month__nav" data-cal-month-prev aria-label="<?= e(t('cal.prev_month')) ?>">
                        <?= calendar_lucide_icon('chevron-left', 18) ?>
                    </button>
                    <p class="gp-month__label" data-cal-month-label><?= e($currentMonthLabel) ?></p>
                    <button type="button" class="gp-month__nav" data-cal-month-next aria-label="<?= e(t('cal.next_month')) ?>">
                        <?= calendar_lucide_icon('chevron-right', 18) ?>
                    </button>
                </div>
                <ul class="cal-month-legend" aria-label="<?= e(t('cal.legend_aria')) ?>">
                    <li class="cal-month-legend__item">
                        <span class="cal-month-legend__swatch cal-month-legend__swatch--game" aria-hidden="true"></span>
                        <span><?= e(t('cal.legend_game')) ?></span>
                    </li>
                    <li class="cal-month-legend__item">
                        <span class="cal-month-legend__swatch cal-month-legend__swatch--event" aria-hidden="true"></span>
                        <span><?= e(t('cal.legend_event')) ?></span>
                    </li>
                    <li class="cal-month-legend__item">
                        <span class="cal-month-legend__swatch cal-month-legend__swatch--history" aria-hidden="true"></span>
                        <span><?= e(t('cal.legend_history')) ?></span>
                    </li>
                </ul>
            </div>
        </div>
        <div class="cal-month-board" data-cal-month-board></div>
        <p class="cal-month-empty" data-cal-month-empty hidden><?= e(t('cal.month_empty')) ?></p>
        <script type="application/json" data-cal-month-items><?= $monthItemsJson !== false ? str_replace('</', '<\/', $monthItemsJson) : '[]' ?></script>
    </section>
    <div class="cal-month-pop" data-cal-month-pop hidden></div>
</section>
    <?php

    return (string) ob_get_clean();
}

/**
 * Widget de eventos para a sidebar do calendário.
 *
 * @param list<array<string, mixed>> $events
 */
function render_calendar_events_sidebar(array $events): string
{
    ob_start();
    ?>
<div class="widget widget--events" id="eventos">
    <div class="widget-head">
        <span class="bar"></span>
        <?= calendar_lucide_icon('event', 18) ?>
        <span class="widget-head__label"><?= e(t('cal.events')) ?></span>
        <?= render_calendar_section_jumps('event') ?>
    </div>
    <div class="widget-body">
        <?php if ($events === []): ?>
            <p style="color:var(--text-mute);font-size:14px"><?= e(t('cal.no_events')) ?></p>
        <?php else: ?>
            <div class="sidebar-events">
                <?php foreach ($events as $event): ?>
                    <?php
                    $starts = (string) $event['starts_at'];
                    $ends = calendar_event_end_at($event);
                    $progress = calendar_event_day_progress($starts, $ends);
                    $isActiveToday = $progress !== null;
                    $countdown = calendar_countdown_label($starts);
                    $when = calendar_event_date_range_label($starts, $ends);
                    $rawTitle = trim((string) ($event['title'] ?? ''));
                    if ($isActiveToday) {
                        $titleHtml = calendar_event_day_title_html(
                            $rawTitle,
                            $progress['day'],
                            $progress['total'],
                            true
                        );
                    } else {
                        $titleHtml = e($rawTitle);
                    }
                    $url = trim((string) ($event['external_url'] ?? ''));
                    $bodyHtml = calendar_rich_body_html((string) ($event['body'] ?? ''));
                    $eventClass = 'sidebar-event' . ($isActiveToday ? ' sidebar-event--today' : '');
                    ?>
                    <article class="<?= e($eventClass) ?>">
                        <?php if (!empty($event['banner_url'])): ?>
                            <div class="sidebar-event__banner">
                                <img src="<?= e((string) $event['banner_url']) ?>" alt="" loading="lazy">
                            </div>
                        <?php endif; ?>
                        <?php if (!$isActiveToday): ?>
                            <div class="sidebar-event__countdown"><?= e($countdown) ?></div>
                        <?php endif; ?>
                        <h4 class="sidebar-event__title"><?= $titleHtml ?></h4>
                        <?php if ($when !== ''): ?>
                            <p class="cal-card__meta"><?= e($when) ?></p>
                        <?php endif; ?>
                        <?php if ($bodyHtml !== ''): ?>
                            <div class="sidebar-event__excerpt"><?= $bodyHtml ?></div>
                        <?php endif; ?>
                        <?php if ($url !== ''): ?>
                            <a class="sidebar-event__link" href="<?= e($url) ?>" rel="noopener noreferrer" target="_blank"><?= e(t('cal.learn_more')) ?></a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
    <?php

    return (string) ob_get_clean();
}

const CALENDAR_RSS_ITEMS_LIMIT = 50;

/**
 * Itens publicados do calendário para o feed RSS (lançamentos, eventos, histórico).
 *
 * @return list<array{title: string, link: string, guid: string, pub_date: string, description: string}>
 */
function calendar_list_for_feed(PDO $pdo, array $config, int $limit = CALENDAR_RSS_ITEMS_LIMIT): array
{
    $limit = max(1, min(100, $limit));
    $siteUrl = rtrim((string) ($config['site_base_url'] ?? ''), '/');
    $calUrl = $siteUrl . '/calendario';
    $items = [];

    $gp = $pdo->query(
        "SELECT id, title, body, store_url, release_at
         FROM gp_releases
         WHERE status = 'published'
         ORDER BY release_at DESC, id DESC
         LIMIT " . (int) $limit
    );
    foreach ($gp !== false ? $gp->fetchAll() : [] as $row) {
        $id = (int) $row['id'];
        $store = trim((string) ($row['store_url'] ?? ''));
        $link = $store !== '' ? $store : $calUrl;
        $body = trim((string) ($row['body'] ?? ''));
        $items[] = [
            'title' => 'Lançamento: ' . (string) $row['title'],
            'link' => $link,
            'guid' => $siteUrl . '/calendario/lancamento/' . $id,
            'pub_date' => (string) ($row['release_at'] ?? ''),
            'description' => $body !== '' ? $body : (string) $row['title'],
            '_sort' => strtotime((string) ($row['release_at'] ?? '')) ?: 0,
        ];
    }

    $ev = $pdo->query(
        "SELECT id, title, body, external_url, starts_at
         FROM calendar_events
         WHERE status = 'published'
         ORDER BY starts_at DESC, id DESC
         LIMIT " . (int) $limit
    );
    foreach ($ev !== false ? $ev->fetchAll() : [] as $row) {
        $id = (int) $row['id'];
        $ext = trim((string) ($row['external_url'] ?? ''));
        $link = $ext !== '' ? $ext : $calUrl;
        $body = trim((string) ($row['body'] ?? ''));
        $items[] = [
            'title' => 'Evento: ' . (string) $row['title'],
            'link' => $link,
            'guid' => $siteUrl . '/calendario/evento/' . $id,
            'pub_date' => (string) ($row['starts_at'] ?? ''),
            'description' => $body !== '' ? $body : (string) $row['title'],
            '_sort' => strtotime((string) ($row['starts_at'] ?? '')) ?: 0,
        ];
    }

    $hi = $pdo->query(
        "SELECT id, title, body, context, occurred_year, occurred_month, occurred_day
         FROM xbox_history
         WHERE status = 'published'
         ORDER BY occurred_year DESC, occurred_month DESC, occurred_day DESC, id DESC
         LIMIT " . (int) $limit
    );
    foreach ($hi !== false ? $hi->fetchAll() : [] as $row) {
        $id = (int) $row['id'];
        $year = (int) ($row['occurred_year'] ?? 0);
        $month = (int) ($row['occurred_month'] ?? 0);
        $day = (int) ($row['occurred_day'] ?? 0);
        $pubDate = ($year > 0 && $month > 0 && $day > 0)
            ? sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day)
            : '';
        $body = trim((string) ($row['body'] ?? ''));
        $context = trim((string) ($row['context'] ?? ''));
        $desc = $body !== '' ? $body : ($context !== '' ? $context : (string) $row['title']);
        $items[] = [
            'title' => 'Histórico: ' . (string) $row['title'],
            'link' => $calUrl,
            'guid' => $siteUrl . '/calendario/historico/' . $id,
            'pub_date' => $pubDate,
            'description' => $desc,
            '_sort' => strtotime($pubDate) ?: 0,
        ];
    }

    usort(
        $items,
        static fn (array $a, array $b): int => ($b['_sort'] <=> $a['_sort']) ?: strcmp($a['guid'], $b['guid'])
    );

    $items = array_slice($items, 0, $limit);
    foreach ($items as &$item) {
        unset($item['_sort']);
    }
    unset($item);

    return $items;
}

/**
 * Busca itens publicados do calendário (Game Pass, eventos, histórico).
 *
 * @return list<array{type: 'gamepass'|'event'|'history', title: string, excerpt: string, when: string, url: string}>
 */
function calendar_search(PDO $pdo, string $q, int $limit = 8): array
{
    $q = trim($q);
    if (mb_strlen($q) < 2) {
        return [];
    }

    $like = search_like_pattern($q);
    $limit = max(1, min(20, $limit));
    $results = [];

    $gp = $pdo->prepare(
        "SELECT title, body, release_at
         FROM gp_releases
         WHERE status = 'published'
           AND (title LIKE :q1 ESCAPE '\\\\' OR IFNULL(body, '') LIKE :q2 ESCAPE '\\\\')
         ORDER BY release_at DESC
         LIMIT :limit"
    );
    $gp->bindValue('q1', $like);
    $gp->bindValue('q2', $like);
    $gp->bindValue('limit', $limit, PDO::PARAM_INT);
    $gp->execute();
    foreach ($gp->fetchAll() as $row) {
        $results[] = [
            'type' => 'gamepass',
            'title' => (string) $row['title'],
            'excerpt' => excerpt_from_html((string) ($row['body'] ?? ''), 110),
            'when' => format_release_date(isset($row['release_at']) ? (string) $row['release_at'] : null),
            'url' => '/calendario',
        ];
    }

    $ev = $pdo->prepare(
        "SELECT title, body, starts_at, external_url
         FROM calendar_events
         WHERE status = 'published'
           AND (title LIKE :q1 ESCAPE '\\\\' OR IFNULL(body, '') LIKE :q2 ESCAPE '\\\\')
         ORDER BY starts_at DESC
         LIMIT :limit"
    );
    $ev->bindValue('q1', $like);
    $ev->bindValue('q2', $like);
    $ev->bindValue('limit', $limit, PDO::PARAM_INT);
    $ev->execute();
    foreach ($ev->fetchAll() as $row) {
        $ext = trim((string) ($row['external_url'] ?? ''));
        $results[] = [
            'type' => 'event',
            'title' => (string) $row['title'],
            'excerpt' => excerpt_from_html((string) ($row['body'] ?? ''), 110),
            'when' => format_post_date(isset($row['starts_at']) ? (string) $row['starts_at'] : null),
            'url' => $ext !== '' ? $ext : '/calendario',
        ];
    }

    $hi = $pdo->prepare(
        "SELECT title, body, context, occurred_year, occurred_month, occurred_day
         FROM xbox_history
         WHERE status = 'published'
           AND (
             title LIKE :q1 ESCAPE '\\\\'
             OR IFNULL(body, '') LIKE :q2 ESCAPE '\\\\'
             OR IFNULL(context, '') LIKE :q3 ESCAPE '\\\\'
           )
         ORDER BY occurred_year DESC, occurred_month DESC, occurred_day DESC
         LIMIT :limit"
    );
    $hi->bindValue('q1', $like);
    $hi->bindValue('q2', $like);
    $hi->bindValue('q3', $like);
    $hi->bindValue('limit', $limit, PDO::PARAM_INT);
    $hi->execute();
    foreach ($hi->fetchAll() as $row) {
        $year = (int) ($row['occurred_year'] ?? 0);
        $month = (int) ($row['occurred_month'] ?? 0);
        $day = (int) ($row['occurred_day'] ?? 0);
        $when = $year > 0 && $month > 0 && $day > 0
            ? sprintf('%02d/%02d/%04d', $day, $month, $year)
            : '';
        $results[] = [
            'type' => 'history',
            'title' => (string) $row['title'],
            'excerpt' => excerpt_from_html((string) ($row['body'] ?? ''), 110),
            'when' => $when,
            'url' => '/calendario',
        ];
    }

    return array_slice($results, 0, $limit);
}
