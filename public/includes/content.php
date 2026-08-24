<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function render_home_content(array $config, array $posts, int $page, int $totalPages): string
{
    ob_start();
    ?>
<section class="home">
    <header class="home__header">
        <h1>Últimas notícias</h1>
        <p class="home__lead">Notícias e análises sobre Xbox.</p>
    </header>

    <?php if ($posts === []): ?>
        <p class="home__empty">Nenhuma notícia publicada ainda.</p>
    <?php else: ?>
        <ul class="post-list">
            <?php foreach ($posts as $post): ?>
                <?php
                $postId = (int) $post['id'];
                $title = (string) $post['title'];
                $excerpt = excerpt_from_html((string) $post['body']);
                $date = format_post_date(isset($post['published_at']) ? (string) $post['published_at'] : null);
                ?>
                <li class="post-list__item">
                    <article class="post-card">
                        <h2 class="post-card__title">
                            <a href="<?= e(site_post_url($config, $postId)) ?>"><?= e($title) ?></a>
                        </h2>
                        <?php if ($date !== ''): ?>
                            <time class="post-card__date" datetime="<?= e((string) $post['published_at']) ?>"><?= e($date) ?></time>
                        <?php endif; ?>
                        <?php if ($excerpt !== ''): ?>
                            <p class="post-card__excerpt"><?= e($excerpt) ?></p>
                        <?php endif; ?>
                    </article>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Paginação">
            <?php if ($page > 1): ?>
                <a class="pagination__link" href="<?= e(site_home_url($config, $page - 1)) ?>">&larr; Anterior</a>
            <?php else: ?>
                <span class="pagination__link pagination__link--disabled">&larr; Anterior</span>
            <?php endif; ?>

            <span class="pagination__status">Página <?= e((string) $page) ?> de <?= e((string) $totalPages) ?></span>

            <?php if ($page < $totalPages): ?>
                <a class="pagination__link" href="<?= e(site_home_url($config, $page + 1)) ?>">Próxima &rarr;</a>
            <?php else: ?>
                <span class="pagination__link pagination__link--disabled">Próxima &rarr;</span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
    <?php

    return (string) ob_get_clean();
}

function render_article_content(array $post): string
{
    $title = (string) $post['title'];
    $body = (string) $post['body'];
    $date = format_post_date(isset($post['published_at']) ? (string) $post['published_at'] : null);

    ob_start();
    ?>
<article class="article">
    <header class="article__header">
        <h1 class="article__title"><?= e($title) ?></h1>
        <?php if ($date !== ''): ?>
            <time class="article__date" datetime="<?= e((string) $post['published_at']) ?>"><?= e($date) ?></time>
        <?php endif; ?>
    </header>
    <div class="article__body">
        <?= $body ?>
    </div>
</article>
    <?php

    return (string) ob_get_clean();
}

function render_not_found_content(): string
{
    return <<<'HTML'
<section class="error-page">
    <h1>Página não encontrada</h1>
    <p>O artigo que você procura não existe ou foi removido.</p>
</section>
HTML;
}
