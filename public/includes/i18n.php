<?php

declare(strict_types=1);

const SITE_LANGS = ['pt', 'en', 'es'];
const SITE_DEFAULT_LANG = 'pt';
const SITE_I18N_SETTING_KEY = 'section_translations';
const SITE_BRAND_SETTING_KEY = 'brand.assets';
const DEFAULT_BRAND_LOGO_PATH = '/assets/img/pastorxbox-avatar.png';

/**
 * URL pública da logo do site (header/footer). Fallback para o avatar estático.
 */
function brand_logo_url(?PDO $pdo): string
{
    if (!$pdo instanceof PDO) {
        return DEFAULT_BRAND_LOGO_PATH;
    }

    $settings = site_settings_get($pdo, SITE_BRAND_SETTING_KEY, []);
    $url = trim((string) ($settings['logo_url'] ?? ''));
    if ($url === '' || !str_starts_with($url, '/')) {
        return DEFAULT_BRAND_LOGO_PATH;
    }

    $version = trim((string) ($settings['logo_version'] ?? ''));
    if ($version === '') {
        return $url;
    }

    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode($version);
}

/**
 * @return array<string, array<string, string>>
 */
function default_section_translations(): array
{
    return [
        'site.home' => ['pt' => 'Início', 'en' => 'Home', 'es' => 'Inicio'],
        'site.news' => ['pt' => 'Notícias', 'en' => 'News', 'es' => 'Noticias'],
        'site.articles' => ['pt' => 'Artigos', 'en' => 'Articles', 'es' => 'Artículos'],
        'site.calendar' => ['pt' => 'Calendário', 'en' => 'Calendar', 'es' => 'Calendario'],
        'brand.tagline' => [
            'pt' => 'Xbox, Games & Propósito',
            'en' => 'Xbox, Games & Purpose',
            'es' => 'Xbox, Games & Propósito',
        ],
        'brand.footer' => [
            'pt' => 'Notícias e análises sobre Xbox',
            'en' => 'Xbox news and analysis',
            'es' => 'Noticias y análisis sobre Xbox',
        ],
        'home.latest' => [
            'pt' => 'Últimas notícias',
            'en' => 'Latest news',
            'es' => 'Últimas noticias',
        ],
        'home.lead' => [
            'pt' => 'Notícias e análises sobre Xbox, Game Pass e o mundo dos games.',
            'en' => 'News and analysis about Xbox, Game Pass, and the world of games.',
            'es' => 'Noticias y análisis sobre Xbox, Game Pass y el mundo de los games.',
        ],
        'home.event_pill' => ['pt' => 'EVENTOS', 'en' => 'EVENTS', 'es' => 'EVENTOS'],
        'home.calendar_pill' => ['pt' => 'CALENDÁRIO', 'en' => 'CALENDAR', 'es' => 'CALENDARIO'],
        'home.today_releases_gp' => [
            'pt' => 'Chegando hoje ao Game Pass',
            'en' => 'Arriving today on Game Pass',
            'es' => 'Llegan hoy a Game Pass',
        ],
        'home.today_releases_xbox' => [
            'pt' => 'Chegando hoje ao Xbox',
            'en' => 'Arriving today on Xbox',
            'es' => 'Llegan hoy a Xbox',
        ],
        'home.history_pill' => [
            'pt' => 'FATO HISTÓRICO',
            'en' => 'HISTORICAL FACT',
            'es' => 'HECHO HISTÓRICO',
        ],
        'home.event_title' => [
            'pt' => 'Hoje é Dia de {name}',
            'en' => "Today is {name} Day",
            'es' => 'Hoy es día de {name}',
        ],
        'home.event_more' => ['pt' => 'mais', 'en' => 'more', 'es' => 'más'],
        'lead.news' => [
            'pt' => 'Cobertura do dia a dia do Xbox, Game Pass e da indústria.',
            'en' => 'Daily coverage of Xbox, Game Pass, and the gaming industry.',
            'es' => 'Cobertura diaria de Xbox, Game Pass y la industria.',
        ],
        'lead.article' => [
            'pt' => 'Análises, opinião e textos mais longos do Pastor Xbox.',
            'en' => 'Analysis, opinion, and longer reads from Pastor Xbox.',
            'es' => 'Análisis, opinión y textos más largos de Pastor Xbox.',
        ],
        'ui.read_time' => [
            'pt' => '{n} min de leitura',
            'en' => '{n} min read',
            'es' => '{n} min de lectura',
        ],
        'ui.empty_section' => [
            'pt' => 'Nenhuma publicação nesta secção ainda. Volte em breve!',
            'en' => 'No posts in this section yet. Check back soon!',
            'es' => 'Aún no hay publicaciones en esta sección. ¡Vuelve pronto!',
        ],
        'ui.recent' => ['pt' => 'Mais recentes', 'en' => 'Latest', 'es' => 'Más recientes'],
        'ui.no_posts' => [
            'pt' => 'Nenhum post ainda.',
            'en' => 'No posts yet.',
            'es' => 'Aún no hay posts.',
        ],
        'ui.hashtags' => ['pt' => 'Hashtags', 'en' => 'Hashtags', 'es' => 'Hashtags'],
        'ui.no_hashtags' => [
            'pt' => 'Ainda sem hashtags.',
            'en' => 'No hashtags yet.',
            'es' => 'Aún sin hashtags.',
        ],
        'ui.on_x' => ['pt' => 'no X', 'en' => 'on X', 'es' => 'en X'],
        'ui.follow_x' => [
            'pt' => 'Acompanhe as últimas atualizações direto do nosso perfil.',
            'en' => 'Follow the latest updates straight from our profile.',
            'es' => 'Sigue las últimas actualizaciones directamente desde nuestro perfil.',
        ],
        'ui.see_on_x' => ['pt' => 'Ver no X', 'en' => 'View on X', 'es' => 'Ver en X'],
        'ui.tvxbox' => ['pt' => 'TV Xbox', 'en' => 'Xbox TV', 'es' => 'TV Xbox'],
        'ui.tvxbox_next' => ['pt' => 'Próximo', 'en' => 'Next', 'es' => 'Siguiente'],
        'ui.tvxbox_details' => [
            'pt' => 'Detalhes do jogo',
            'en' => 'Game details',
            'es' => 'Detalles del juego',
        ],
        'ui.tvxbox_release_date' => [
            'pt' => 'Data de Lançamento: {date}',
            'en' => 'Release date: {date}',
            'es' => 'Fecha de lanzamiento: {date}',
        ],
        'ui.close' => ['pt' => 'Fechar', 'en' => 'Close', 'es' => 'Cerrar'],
        'ui.search' => ['pt' => 'Buscar no site', 'en' => 'Search the site', 'es' => 'Buscar en el sitio'],
        'ui.search_placeholder' => [
            'pt' => 'Buscar no site…',
            'en' => 'Search the site…',
            'es' => 'Buscar en el sitio…',
        ],
        'ui.open_search' => ['pt' => 'Abrir busca', 'en' => 'Open search', 'es' => 'Abrir búsqueda'],
        'ui.copyright' => [
            'pt' => 'Todos os direitos reservados.',
            'en' => 'All rights reserved.',
            'es' => 'Todos los derechos reservados.',
        ],
        'ui.cookies_aria' => ['pt' => 'Aviso de cookies', 'en' => 'Cookie notice', 'es' => 'Aviso de cookies'],
        'ui.cookies_text' => [
            'pt' => 'Usamos cookies para medir audiência{ga} e melhorar a experiência no site. Ao navegar, você concorda com o uso de cookies.',
            'en' => 'We use cookies to measure audience{ga} and improve the site experience. By browsing, you agree to the use of cookies.',
            'es' => 'Usamos cookies para medir audiencia{ga} y mejorar la experiencia del sitio. Al navegar, aceptas el uso de cookies.',
        ],
        'ui.accept' => ['pt' => 'Aceitar', 'en' => 'Accept', 'es' => 'Aceptar'],
        'ui.featured' => [
            'pt' => 'Destaques do Xbox',
            'en' => 'Xbox Highlights',
            'es' => 'Destacados de Xbox',
        ],
        'ui.show_story' => [
            'pt' => 'Mostrar notícia {n} de {total}',
            'en' => 'Show story {n} of {total}',
            'es' => 'Mostrar noticia {n} de {total}',
        ],
        'ui.page' => ['pt' => 'Página {n}', 'en' => 'Page {n}', 'es' => 'Página {n}'],
        'ui.pagination' => ['pt' => 'Paginação', 'en' => 'Pagination', 'es' => 'Paginación'],
        'ui.prev_page' => ['pt' => 'Página anterior', 'en' => 'Previous page', 'es' => 'Página anterior'],
        'ui.next_page' => ['pt' => 'Página seguinte', 'en' => 'Next page', 'es' => 'Página siguiente'],
        'ui.nav_main' => ['pt' => 'Principal', 'en' => 'Main', 'es' => 'Principal'],
        'ui.sidebar' => ['pt' => 'Barra lateral', 'en' => 'Sidebar', 'es' => 'Barra lateral'],
        'ui.back_news' => ['pt' => 'Voltar às notícias', 'en' => 'Back to news', 'es' => 'Volver a las noticias'],
        'ui.back_articles' => ['pt' => 'Voltar aos artigos', 'en' => 'Back to articles', 'es' => 'Volver a los artículos'],
        'ui.not_found' => ['pt' => 'Não encontrado', 'en' => 'Not found', 'es' => 'No encontrado'],
        'ui.og_home' => [
            'pt' => 'Notícias e análises sobre Xbox.',
            'en' => 'Xbox news and analysis.',
            'es' => 'Noticias y análisis sobre Xbox.',
        ],
        'ui.not_found_body' => [
            'pt' => 'O artigo que você procura não existe ou foi removido.',
            'en' => 'The article you are looking for does not exist or was removed.',
            'es' => 'El artículo que buscas no existe o fue eliminado.',
        ],
        'ui.back_home' => [
            'pt' => 'Voltar ao início',
            'en' => 'Back to home',
            'es' => 'Volver al inicio',
        ],
        'ui.search_results' => [
            'pt' => 'Resultados para “{q}”',
            'en' => 'Results for “{q}”',
            'es' => 'Resultados para “{q}”',
        ],
        'ui.search_heading' => ['pt' => 'Busca', 'en' => 'Search', 'es' => 'Búsqueda'],
        'ui.search_lead' => [
            'pt' => 'Notícias, artigos e itens do calendário Xbox.',
            'en' => 'News, articles, and Xbox calendar items.',
            'es' => 'Noticias, artículos e ítems del calendario Xbox.',
        ],
        'ui.no_results' => [
            'pt' => 'Nenhum resultado para “{q}”.',
            'en' => 'No results for “{q}”.',
            'es' => 'Ningún resultado para “{q}”.',
        ],
        'share.label' => [
            'pt' => 'Compartilhar',
            'en' => 'Share',
            'es' => 'Compartir',
        ],
        'share.see_more' => [
            'pt' => 'Veja mais em:',
            'en' => 'See more at:',
            'es' => 'Ver más en:',
        ],
        'share.copied' => [
            'pt' => 'Copiado! Cole no Instagram.',
            'en' => 'Copied! Paste into Instagram.',
            'es' => '¡Copiado! Pégalo en Instagram.',
        ],
        'share.network.x' => ['pt' => 'X', 'en' => 'X', 'es' => 'X'],
        'share.network.instagram' => ['pt' => 'Instagram', 'en' => 'Instagram', 'es' => 'Instagram'],
        'share.network.bluesky' => ['pt' => 'Bluesky', 'en' => 'Bluesky', 'es' => 'Bluesky'],
        'share.network.whatsapp' => ['pt' => 'WhatsApp', 'en' => 'WhatsApp', 'es' => 'WhatsApp'],
        'push.enable' => [
            'pt' => 'Receber alertas',
            'en' => 'Enable alerts',
            'es' => 'Recibir alertas',
        ],
        'push.enabled' => [
            'pt' => 'Alertas ativos',
            'en' => 'Alerts on',
            'es' => 'Alertas activos',
        ],
        'push.denied' => [
            'pt' => 'Notificações bloqueadas no browser.',
            'en' => 'Notifications blocked in the browser.',
            'es' => 'Notificaciones bloqueadas en el navegador.',
        ],
        'push.unsupported' => [
            'pt' => 'Este browser não suporta alertas.',
            'en' => 'This browser does not support alerts.',
            'es' => 'Este navegador no admite alertas.',
        ],
        'push.error' => [
            'pt' => 'Não foi possível ativar os alertas.',
            'en' => 'Could not enable alerts.',
            'es' => 'No se pudieron activar las alertas.',
        ],
        'cal.ticker_aria' => [
            'pt' => 'Calendário Xbox',
            'en' => 'Xbox Calendar',
            'es' => 'Calendario Xbox',
        ],
        'cal.happened' => [
            'pt' => 'Já aconteceu',
            'en' => 'Already happened',
            'es' => 'Ya ocurrió',
        ],
        'cal.today' => ['pt' => 'É hoje', 'en' => "It's today", 'es' => 'Es hoy'],
        'cal.in_1_day' => [
            'pt' => 'Falta 1 dia',
            'en' => '1 day left',
            'es' => 'Falta 1 día',
        ],
        'cal.in_n_days' => [
            'pt' => 'Faltam {n} dias',
            'en' => '{n} days left',
            'es' => 'Faltan {n} días',
        ],
        'cal.ticker_today' => [
            'pt' => 'É hoje {name}',
            'en' => "It's today: {name}",
            'es' => 'Es hoy {name}',
        ],
        'cal.ticker_1_day' => [
            'pt' => 'Falta 1 dia para {name}',
            'en' => '1 day until {name}',
            'es' => 'Falta 1 día para {name}',
        ],
        'cal.ticker_n_days' => [
            'pt' => 'Faltam {n} dias para {name}',
            'en' => '{n} days until {name}',
            'es' => 'Faltan {n} días para {name}',
        ],
        'cal.ticker_available_today_gp' => [
            'pt' => 'Disponível HOJE no Game Pass',
            'en' => 'Available TODAY on Game Pass',
            'es' => 'Disponible HOY en Game Pass',
        ],
        'cal.ticker_available_today_xbox' => [
            'pt' => 'Disponível HOJE no Xbox',
            'en' => 'Available TODAY on Xbox',
            'es' => 'Disponible HOY en Xbox',
        ],
        'ui.tvxbox_tag_gamepass' => ['pt' => 'GAME PASS', 'en' => 'GAME PASS', 'es' => 'GAME PASS'],
        'ui.tvxbox_tag_xbox' => ['pt' => 'NO XBOX', 'en' => 'NO XBOX', 'es' => 'NO XBOX'],
        'ui.tvxbox_launch_gp' => [
            'pt' => 'Lançamento no Game Pass Dia {date}',
            'en' => 'Game Pass launch on {date}',
            'es' => 'Lanzamiento en Game Pass el {date}',
        ],
        'ui.tvxbox_launch_xbox' => [
            'pt' => 'Lançamento no Xbox Dia {date}',
            'en' => 'Xbox launch on {date}',
            'es' => 'Lanzamiento en Xbox el {date}',
        ],
        'ui.tvxbox_launch_gp_nodate' => [
            'pt' => 'Lançamento no Game Pass',
            'en' => 'Game Pass launch',
            'es' => 'Lanzamiento en Game Pass',
        ],
        'ui.tvxbox_launch_xbox_nodate' => [
            'pt' => 'Lançamento no Xbox',
            'en' => 'Xbox launch',
            'es' => 'Lanzamiento en Xbox',
        ],
        'ui.tvxbox_available_today_gp' => [
            'pt' => 'Disponível HOJE no Game Pass',
            'en' => 'Available TODAY on Game Pass',
            'es' => 'Disponible HOY en Game Pass',
        ],
        'ui.tvxbox_available_today_xbox' => [
            'pt' => 'Disponível HOJE no Xbox',
            'en' => 'Available TODAY on Xbox',
            'es' => 'Disponible HOY en Xbox',
        ],
        'ui.tvxbox_available_gp' => [
            'pt' => 'Disponível no Game Pass',
            'en' => 'Available on Game Pass',
            'es' => 'Disponible en Game Pass',
        ],
        'ui.tvxbox_available_xbox' => [
            'pt' => 'Disponível no Xbox',
            'en' => 'Available on Xbox',
            'es' => 'Disponible en Xbox',
        ],
        'cal.history_years' => [
            'pt' => '{title} aconteceu {n} {unit} atrás',
            'en' => '{title} happened {n} {unit} ago',
            'es' => '{title} ocurrió hace {n} {unit}',
        ],
        'cal.year' => ['pt' => 'ano', 'en' => 'year', 'es' => 'año'],
        'cal.years' => ['pt' => 'anos', 'en' => 'years', 'es' => 'años'],
        'cal.events' => ['pt' => 'Eventos', 'en' => 'Events', 'es' => 'Eventos'],
        'cal.history' => [
            'pt' => 'Fatos Históricos',
            'en' => 'Historical Facts',
            'es' => 'Hechos Históricos',
        ],
        'cal.prev' => ['pt' => 'Anterior', 'en' => 'Previous', 'es' => 'Anterior'],
        'cal.next' => ['pt' => 'Seguinte', 'en' => 'Next', 'es' => 'Siguiente'],
        'cal.no_events' => [
            'pt' => 'Nenhum evento futuro.',
            'en' => 'No upcoming events.',
            'es' => 'Ningún evento próximo.',
        ],
        'cal.learn_more' => ['pt' => 'Saber mais', 'en' => 'Learn more', 'es' => 'Saber más'],
        'cal.play_anywhere' => [
            'pt' => 'Xbox Play Anywhere',
            'en' => 'Xbox Play Anywhere',
            'es' => 'Xbox Play Anywhere',
        ],
        'cal.supports_pt_br' => [
            'pt' => 'Suporte a português do Brasil',
            'en' => 'Portuguese (Brazil) support',
            'es' => 'Compatibilidad con portugués de Brasil',
        ],
        'cal.title' => [
            'pt' => 'Calendário Xbox',
            'en' => 'Xbox Calendar',
            'es' => 'Calendario Xbox',
        ],
        'cal.lead' => [
            'pt' => 'Próximos lançamentos Xbox e fatos históricos da plataforma.',
            'en' => 'Upcoming Xbox releases and historical facts of the platform.',
            'es' => 'Próximos lanzamientos Xbox y hechos históricos de la plataforma.',
        ],
        'cal.head_before' => [
            'pt' => 'Próximos Lançamentos Xbox',
            'en' => 'Upcoming Xbox Releases',
            'es' => 'Próximos Lanzamientos Xbox',
        ],
        'cal.head_and' => ['pt' => 'e', 'en' => 'and', 'es' => 'y'],
        'cal.head_history_link' => [
            'pt' => 'Fatos Históricos',
            'en' => 'Historical Facts',
            'es' => 'Hechos Históricos',
        ],
        'cal.head_history_suffix' => [
            'pt' => 'da Plataforma',
            'en' => 'of the Platform',
            'es' => 'de la Plataforma',
        ],
        'cal.jump_nav' => [
            'pt' => 'Secções do calendário',
            'en' => 'Calendar sections',
            'es' => 'Secciones del calendario',
        ],
        'cal.og' => [
            'pt' => 'Lançamentos Xbox e fatos históricos da plataforma.',
            'en' => 'Xbox releases and historical platform facts.',
            'es' => 'Lanzamientos Xbox y hechos históricos de la plataforma.',
        ],
        'cal.releases' => [
            'pt' => 'Lançamentos Xbox',
            'en' => 'Xbox Releases',
            'es' => 'Lanzamientos Xbox',
        ],
        'cal.view_cards' => ['pt' => 'Cards', 'en' => 'Cards', 'es' => 'Cards'],
        'cal.view_list' => ['pt' => 'Lista', 'en' => 'List', 'es' => 'Lista'],
        'cal.view_month' => ['pt' => 'Calendário', 'en' => 'Calendar', 'es' => 'Calendario'],
        'cal.view_aria' => [
            'pt' => 'Modo de visualização',
            'en' => 'View mode',
            'es' => 'Modo de visualización',
        ],
        'cal.month_empty' => [
            'pt' => 'Nenhum item neste mês.',
            'en' => 'No items this month.',
            'es' => 'Ningún ítem en este mes.',
        ],
        'cal.weekday.0' => ['pt' => 'Dom', 'en' => 'Sun', 'es' => 'Dom'],
        'cal.weekday.1' => ['pt' => 'Seg', 'en' => 'Mon', 'es' => 'Lun'],
        'cal.weekday.2' => ['pt' => 'Ter', 'en' => 'Tue', 'es' => 'Mar'],
        'cal.weekday.3' => ['pt' => 'Qua', 'en' => 'Wed', 'es' => 'Mié'],
        'cal.weekday.4' => ['pt' => 'Qui', 'en' => 'Thu', 'es' => 'Jue'],
        'cal.weekday.5' => ['pt' => 'Sex', 'en' => 'Fri', 'es' => 'Vie'],
        'cal.weekday.6' => ['pt' => 'Sáb', 'en' => 'Sat', 'es' => 'Sáb'],
        'cal.type_game' => ['pt' => 'Jogo', 'en' => 'Game', 'es' => 'Juego'],
        'cal.type_event' => ['pt' => 'Evento', 'en' => 'Event', 'es' => 'Evento'],
        'cal.type_history' => ['pt' => 'Fato Histórico', 'en' => 'Historical Fact', 'es' => 'Hecho Histórico'],
        'cal.legend_game' => ['pt' => 'Jogos', 'en' => 'Games', 'es' => 'Juegos'],
        'cal.legend_event' => ['pt' => 'Eventos', 'en' => 'Events', 'es' => 'Eventos'],
        'cal.legend_history' => [
            'pt' => 'Fato Histórico',
            'en' => 'Historical Fact',
            'es' => 'Hecho Histórico',
        ],
        'cal.legend_aria' => [
            'pt' => 'Legenda do calendário',
            'en' => 'Calendar legend',
            'es' => 'Leyenda del calendario',
        ],
        'cal.subscriptions' => [
            'pt' => 'Assinaturas',
            'en' => 'Subscriptions',
            'es' => 'Suscripciones',
        ],
        'cal.devices' => [
            'pt' => 'Dispositivos',
            'en' => 'Devices',
            'es' => 'Dispositivos',
        ],
        'cal.console' => ['pt' => 'Console', 'en' => 'Console', 'es' => 'Consola'],
        'cal.cloud' => ['pt' => 'Nuvem', 'en' => 'Cloud', 'es' => 'Nube'],
        'cal.clear_filters' => [
            'pt' => 'Limpar filtros',
            'en' => 'Clear filters',
            'es' => 'Limpiar filtros',
        ],
        'cal.prev_month' => [
            'pt' => 'Mês anterior',
            'en' => 'Previous month',
            'es' => 'Mes anterior',
        ],
        'cal.next_month' => [
            'pt' => 'Próximo mês',
            'en' => 'Next month',
            'es' => 'Próximo mes',
        ],
        'cal.gp_filter' => [
            'pt' => 'Filtro Game Pass',
            'en' => 'Game Pass filter',
            'es' => 'Filtro Game Pass',
        ],
        'cal.all' => ['pt' => 'Todos', 'en' => 'All', 'es' => 'Todos'],
        'cal.on_xbox' => ['pt' => 'No Xbox', 'en' => 'On Xbox', 'es' => 'En Xbox'],
        'cal.no_releases' => [
            'pt' => 'Nenhum lançamento Xbox agendado.',
            'en' => 'No Xbox releases scheduled.',
            'es' => 'Ningún lanzamiento Xbox programado.',
        ],
        'cal.arrives' => [
            'pt' => '📅 {date}',
            'en' => '📅 {date}',
            'es' => '📅 {date}',
        ],
        'cal.store' => [
            'pt' => 'Loja do Xbox',
            'en' => 'Xbox Store',
            'es' => 'Tienda',
        ],
        'cal.trailer' => [
            'pt' => 'Trailer no YouTube',
            'en' => 'Trailer on YouTube',
            'es' => 'Tráiler en YouTube',
        ],
        'cal.platforms' => [
            'pt' => 'Plataformas',
            'en' => 'Platforms',
            'es' => 'Plataformas',
        ],
        'cal.more' => ['pt' => 'mais…', 'en' => 'more…', 'es' => 'más…'],
        'cal.more_info' => [
            'pt' => 'Mais informações',
            'en' => 'More information',
            'es' => 'Más información',
        ],
        'cal.rewards_tip' => [
            'pt' => 'Pode Ser Adquirido com {n} Pontos de Microsoft Rewards',
            'en' => 'Can be purchased with {n} Microsoft Rewards points',
            'es' => 'Se puede adquirir con {n} puntos de Microsoft Rewards',
        ],
        'cal.rewards_points' => [
            'pt' => '{n} Pontos Rewards',
            'en' => '{n} Rewards points',
            'es' => '{n} puntos Rewards',
        ],
        'cal.no_filter_matches' => [
            'pt' => 'Nenhum jogo neste mês com os filtros selecionados.',
            'en' => 'No games this month with the selected filters.',
            'es' => 'Ningún juego este mes con los filtros seleccionados.',
        ],
        'cal.history_lead' => [
            'pt' => 'Fatos históricos do mês atual.',
            'en' => 'Historical facts from the current month.',
            'es' => 'Hechos históricos del mes actual.',
        ],
        'cal.no_history' => [
            'pt' => 'Nenhum fato histórico Xbox neste mês.',
            'en' => 'No Xbox historical facts this month.',
            'es' => 'Ningún hecho histórico Xbox este mes.',
        ],
        'cal.search_in' => [
            'pt' => 'No calendário',
            'en' => 'On the calendar',
            'es' => 'En el calendario',
        ],
        'cal.search_more' => [
            'pt' => 'Ver calendário completo →',
            'en' => 'View full calendar →',
            'es' => 'Ver calendario completo →',
        ],
        'cal.released_today' => [
            'pt' => 'Lançados Hoje — {date}',
            'en' => 'Released Today — {date}',
            'es' => 'Lanzados Hoy — {date}',
        ],
        'cal.released_today_badge' => [
            'pt' => 'Hoje',
            'en' => 'Today',
            'es' => 'Hoy',
        ],
        'cal.today_title' => [
            'pt' => 'HOJE: {title}',
            'en' => 'TODAY: {title}',
            'es' => 'HOY: {title}',
        ],
        'cal.event_day_n' => [
            'pt' => '{n}º Dia {title}',
            'en' => 'Day {n}: {title}',
            'es' => 'Día {n}: {title}',
        ],
        'cal.event_ending_today' => [
            'pt' => 'Encerrando Hoje {title}',
            'en' => 'Ending Today: {title}',
            'es' => 'Cierra Hoy: {title}',
        ],
        'cal.released_today_count' => [
            'pt' => '{n} lançamento chega hoje',
            'en' => '{n} release arrives today',
            'es' => '{n} lanzamiento llega hoy',
        ],
        'cal.released_today_count_plural' => [
            'pt' => '{n} lançamentos chegam hoje',
            'en' => '{n} releases arrive today',
            'es' => '{n} lanzamientos llegan hoy',
        ],
        'cal.month.1' => ['pt' => 'Janeiro', 'en' => 'January', 'es' => 'Enero'],
        'cal.month.2' => ['pt' => 'Fevereiro', 'en' => 'February', 'es' => 'Febrero'],
        'cal.month.3' => ['pt' => 'Março', 'en' => 'March', 'es' => 'Marzo'],
        'cal.month.4' => ['pt' => 'Abril', 'en' => 'April', 'es' => 'Abril'],
        'cal.month.5' => ['pt' => 'Maio', 'en' => 'May', 'es' => 'Mayo'],
        'cal.month.6' => ['pt' => 'Junho', 'en' => 'June', 'es' => 'Junio'],
        'cal.month.7' => ['pt' => 'Julho', 'en' => 'July', 'es' => 'Julio'],
        'cal.month.8' => ['pt' => 'Agosto', 'en' => 'August', 'es' => 'Agosto'],
        'cal.month.9' => ['pt' => 'Setembro', 'en' => 'September', 'es' => 'Septiembre'],
        'cal.month.10' => ['pt' => 'Outubro', 'en' => 'October', 'es' => 'Octubre'],
        'cal.month.11' => ['pt' => 'Novembro', 'en' => 'November', 'es' => 'Noviembre'],
        'cal.month.12' => ['pt' => 'Dezembro', 'en' => 'December', 'es' => 'Diciembre'],
        'cal.month_short.1' => ['pt' => 'Jan', 'en' => 'Jan', 'es' => 'Ene'],
        'cal.month_short.2' => ['pt' => 'Fev', 'en' => 'Feb', 'es' => 'Feb'],
        'cal.month_short.3' => ['pt' => 'Mar', 'en' => 'Mar', 'es' => 'Mar'],
        'cal.month_short.4' => ['pt' => 'Abr', 'en' => 'Apr', 'es' => 'Abr'],
        'cal.month_short.5' => ['pt' => 'Mai', 'en' => 'May', 'es' => 'May'],
        'cal.month_short.6' => ['pt' => 'Jun', 'en' => 'Jun', 'es' => 'Jun'],
        'cal.month_short.7' => ['pt' => 'Jul', 'en' => 'Jul', 'es' => 'Jul'],
        'cal.month_short.8' => ['pt' => 'Ago', 'en' => 'Aug', 'es' => 'Ago'],
        'cal.month_short.9' => ['pt' => 'Set', 'en' => 'Sep', 'es' => 'Sep'],
        'cal.month_short.10' => ['pt' => 'Out', 'en' => 'Oct', 'es' => 'Oct'],
        'cal.month_short.11' => ['pt' => 'Nov', 'en' => 'Nov', 'es' => 'Nov'],
        'cal.month_short.12' => ['pt' => 'Dez', 'en' => 'Dec', 'es' => 'Dic'],
    ];
}

/**
 * @return list<string>
 */
function calendar_month_names_list(): array
{
    $names = [];
    for ($i = 1; $i <= 12; ++$i) {
        $names[] = t('cal.month.' . $i);
    }

    return $names;
}

/**
 * @return array<int, string>
 */
function calendar_month_names_map(): array
{
    $map = [];
    for ($i = 1; $i <= 12; ++$i) {
        $map[$i] = t('cal.month.' . $i);
    }

    return $map;
}

function t(string $key, array $replace = []): string
{
    $pdo = $GLOBALS['pdo'] ?? null;
    if ($pdo instanceof PDO) {
        $text = i18n_t($pdo, $key);
    } else {
        $lang = current_lang();
        $map = default_section_translations();
        $text = $map[$key][$lang] ?? ($map[$key][SITE_DEFAULT_LANG] ?? $key);
    }

    foreach ($replace as $token => $value) {
        $text = str_replace('{' . $token . '}', (string) $value, $text);
    }

    return $text;
}

function normalize_site_lang(?string $lang): string
{
    $lang = strtolower(trim((string) $lang));
    if (in_array($lang, SITE_LANGS, true)) {
        return $lang;
    }

    return SITE_DEFAULT_LANG;
}

function current_lang(): string
{
    static $resolved = null;
    if (is_string($resolved)) {
        return $resolved;
    }

    $fromQuery = isset($_GET['lang']) ? normalize_site_lang((string) $_GET['lang']) : null;
    if ($fromQuery !== null && isset($_GET['lang']) && trim((string) $_GET['lang']) !== '') {
        $resolved = $fromQuery;
        if (!headers_sent()) {
            setcookie('px_lang', $resolved, [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        return $resolved;
    }

    $fromCookie = isset($_COOKIE['px_lang']) ? normalize_site_lang((string) $_COOKIE['px_lang']) : SITE_DEFAULT_LANG;
    $resolved = $fromCookie;

    return $resolved;
}

function current_html_lang(): string
{
    return match (current_lang()) {
        'en' => 'en',
        'es' => 'es',
        default => 'pt-BR',
    };
}

/**
 * @return array<string, mixed>
 */
function site_settings_get(PDO $pdo, string $key, array $fallback = []): array
{
    $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :setting_key LIMIT 1');
    $stmt->execute(['setting_key' => $key]);
    $raw = $stmt->fetchColumn();
    if (!is_string($raw) || trim($raw) === '') {
        return $fallback;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $fallback;
}

function site_settings_put(PDO $pdo, string $key, array $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([
        'setting_key' => $key,
        'setting_value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

/**
 * @return array<string, array<string, string>>
 */
function section_translations(PDO $pdo): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $base = default_section_translations();
    $stored = site_settings_get($pdo, SITE_I18N_SETTING_KEY, []);
    foreach ($stored as $key => $langs) {
        if (!is_string($key) || !is_array($langs)) {
            continue;
        }
        foreach ($langs as $lang => $text) {
            $lang = normalize_site_lang((string) $lang);
            if ($lang === SITE_DEFAULT_LANG && !isset($base[$key][$lang])) {
                continue;
            }
            if (is_string($text) && trim($text) !== '') {
                $base[$key][$lang] = trim($text);
            }
        }
    }

    $cache = $base;

    return $cache;
}

function i18n_t(PDO $pdo, string $key, ?string $lang = null): string
{
    $lang = normalize_site_lang($lang ?? current_lang());
    $map = section_translations($pdo);
    $translations = $map[$key] ?? [];

    if (isset($translations[$lang]) && trim((string) $translations[$lang]) !== '') {
        return trim((string) $translations[$lang]);
    }
    if (isset($translations[SITE_DEFAULT_LANG]) && trim((string) $translations[SITE_DEFAULT_LANG]) !== '') {
        return trim((string) $translations[SITE_DEFAULT_LANG]);
    }

    return $key;
}

/**
 * @return list<array{code: string, label: string, flag: string}>
 */
function site_language_options(): array
{
    return [
        ['code' => 'pt', 'label' => 'Português', 'flag' => '🇧🇷'],
        ['code' => 'en', 'label' => 'English', 'flag' => '🇬🇧'],
        ['code' => 'es', 'label' => 'Español', 'flag' => '🇪🇸'],
    ];
}
