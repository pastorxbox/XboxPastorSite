INSERT INTO posts (id, title, body, status, published_at)
VALUES (
    1,
    'Bem-vindo ao novo Pastor Xbox',
    '<p>Este é o primeiro artigo de teste do site PHP. Na próxima fase, o acervo completo do WordPress será importado com os mesmos IDs em <code>/?p=</code>.</p><p>Fique atento às novidades do mundo Xbox.</p>',
    'published',
    '2026-08-24 12:00:00'
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    body = VALUES(body),
    status = VALUES(status),
    published_at = VALUES(published_at);
