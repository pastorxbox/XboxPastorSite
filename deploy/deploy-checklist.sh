#!/usr/bin/env bash
# =============================================================================
# Deploy Checklist — Pastor Xbox Site (PHP + MySQL) na Hostinger
# =============================================================================
# Este script NÃO se executa automaticamente. É um guia passo a passo.
# Rode cada bloco manualmente via SSH.
#
# Pré-requisitos:
#   - Acesso SSH à Hostinger
#   - Base MySQL nova criada no hPanel (não usar a do WordPress)
#   - PHP 8.x ativo no hPanel
#   - Ficheiros do projeto já enviados via SFTP/SCP para ~/staging/
# =============================================================================

set -euo pipefail

# ─── VARIÁVEIS (preencher antes de usar) ─────────────────────────────────────
SSH_HOST="????"                         # ex: ssh.pastorxbox.com.br ou IP
SSH_PORT="22"                           # porta SSH (Hostinger usa 65002 por vezes)
SSH_USER="????"                         # ex: u285071835

DB_NAME="????"                          # nome da base MySQL nova
DB_USER="????"                          # user MySQL
DB_PASS="????"                          # password MySQL
DB_HOST="localhost"                     # normalmente localhost na Hostinger

SITE_BASE_URL="https://pastorxbox.com.br"
SITE_API_KEY="????"                     # token Bearer (gerar: openssl rand -hex 32)

WP_DB_NAME="????"                       # nome da base WP (para backup)
WP_DB_USER="????"                       # user WP MySQL
# =============================================================================


echo "=== ETAPA 0: Conectar via SSH ==="
echo "ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"
echo ""

# ─── ETAPA 1: Página de manutenção ──────────────────────────────────────────
echo "=== ETAPA 1: Ativar página de manutenção ==="
cat << 'EOF'
# Copiar a página + avatar para public_html (WP ainda está lá)
cp ~/staging/deploy/maintenance.html ~/public_html/maintenance.html
cp ~/staging/public/assets/img/pastorxbox-avatar.png ~/public_html/assets/img/pastorxbox-avatar.png 2>/dev/null || mkdir -p ~/public_html/assets/img && cp ~/staging/public/assets/img/pastorxbox-avatar.png ~/public_html/assets/img/

# Guardar .htaccess original do WP
cp ~/public_html/.htaccess ~/public_html/.htaccess-wordpress-backup

# Ativar .htaccess de manutenção
cp ~/staging/deploy/.htaccess-maintenance ~/public_html/.htaccess

# Testar: https://pastorxbox.com.br deve mostrar página de manutenção
EOF
echo ""

# ─── ETAPA 2: Backup WordPress ──────────────────────────────────────────────
echo "=== ETAPA 2: Backup WordPress ==="
cat << 'EOF'
# Backup dos ficheiros
cd ~ && tar -czf wp-backup-$(date +%F).tar.gz public_html/

# Dump MySQL do WordPress
mysqldump -u $WP_DB_USER -p $WP_DB_NAME > wp-dump-$(date +%F).sql

# Mover para pasta segura
mkdir -p ~/backups
mv wp-backup-*.tar.gz ~/backups/
mv wp-dump-*.sql ~/backups/

# Verificar
ls -lh ~/backups/
EOF
echo ""

# ─── ETAPA 3: Preparar estrutura ────────────────────────────────────────────
echo "=== ETAPA 3: Limpar public_html e preparar estrutura ==="
cat << 'EOF'
# Guardar uploads do WP temporariamente (imagens importadas reutilizam estes ficheiros)
mv ~/public_html/wp-content/uploads ~/wp-uploads-temp 2>/dev/null || true

# Limpar public_html
rm -rf ~/public_html/*
rm -rf ~/public_html/.[!.]* 2>/dev/null || true

# Criar pastas fora da web
mkdir -p ~/pastorxbox-config
mkdir -p ~/backups
EOF
echo ""

# ─── ETAPA 4: Deploy dos ficheiros ──────────────────────────────────────────
echo "=== ETAPA 4: Deploy dos ficheiros ==="
cat << 'EOF'
# Copiar public/ → public_html/
cp -r ~/staging/public/* ~/public_html/
cp ~/staging/public/.htaccess ~/public_html/.htaccess

# Copiar config fora da web
cp ~/staging/config/config.php ~/pastorxbox-config/config.php

# Copiar vendor (pré-gerado)
cp -r ~/staging/vendor ~/vendor

# Copiar tools (fora da web, para import)
cp -r ~/staging/tools ~/tools
cp -r ~/staging/sql ~/sql

# Restaurar uploads do WP (imagens importadas)
cp -r ~/wp-uploads-temp/* ~/public_html/uploads/ 2>/dev/null || true
rm -rf ~/wp-uploads-temp

# Verificar permissões
find ~/public_html -type d -exec chmod 755 {} \;
find ~/public_html -type f -exec chmod 644 {} \;
chmod -R 755 ~/public_html/uploads
EOF
echo ""

# ─── ETAPA 5: Configurar .env ───────────────────────────────────────────────
echo "=== ETAPA 5: Criar .env de produção ==="
cat << EOF
cat > ~/pastorxbox-config/.env << 'ENVEOF'
SITE_BASE_URL=${SITE_BASE_URL}
SITE_API_KEY=${SITE_API_KEY}
GA_MEASUREMENT_ID=
X_SCREEN_NAME=PastorXbox
WP_REST_BASE_URL=https://pastorxbox.com.br/wp-json/wp/v2

DB_HOST=${DB_HOST}
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASS}
ENVEOF
EOF
echo ""

# ─── ETAPA 6: Criar tabelas MySQL ───────────────────────────────────────────
echo "=== ETAPA 6: Criar tabelas ==="
cat << 'EOF'
# Rodar os SQLs em ordem
mysql -u $DB_USER -p $DB_NAME < ~/sql/001_schema.sql
mysql -u $DB_USER -p $DB_NAME < ~/sql/003_taxonomy.sql
mysql -u $DB_USER -p $DB_NAME < ~/sql/004_calendar.sql
mysql -u $DB_USER -p $DB_NAME < ~/sql/006_ads.sql

# Seed (opcional — calendário)
mysql -u $DB_USER -p $DB_NAME < ~/sql/005_calendar_seed.sql
EOF
echo ""

# ─── ETAPA 7: Importar posts do WordPress ───────────────────────────────────
echo "=== ETAPA 7: Importar posts do WordPress ==="
cat << 'EOF'
# Opção A: rodar o import diretamente no servidor
cd ~/public_html && php ~/tools/import-wordpress.php

# Opção B: se já tiver o dump SQL gerado localmente
# mysql -u $DB_USER -p $DB_NAME < ~/staging/dump-posts.sql
EOF
echo ""

# ─── ETAPA 8: Ativar site novo ──────────────────────────────────────────────
echo "=== ETAPA 8: Remover manutenção e ativar site ==="
cat << 'EOF'
# O .htaccess definitivo já foi copiado na etapa 4
# Remover página de manutenção
rm -f ~/public_html/maintenance.html

# Testar
curl -sS https://pastorxbox.com.br/health
# Deve retornar: {"ok":true}

# Testar um post importado
curl -sS -o /dev/null -w "%{http_code}" https://pastorxbox.com.br/?p=1194
# Deve retornar: 200

# Testar RSS
curl -sS -o /dev/null -w "%{http_code}" https://pastorxbox.com.br/rss.xml
# Deve retornar: 200

# Testar API sem token (deve dar 401)
curl -sS -o /dev/null -w "%{http_code}" https://pastorxbox.com.br/api/posts
# Deve retornar: 401
EOF
echo ""

# ─── ETAPA 9: Configurar Xbox Manager ───────────────────────────────────────
echo "=== ETAPA 9: Atualizar Xbox Manager ==="
cat << EOF
# No .env do Xbox Manager (VM Oracle), atualizar:
# SITE_BASE_URL=${SITE_BASE_URL}
# SITE_API_KEY=${SITE_API_KEY}
# SITE_API_PATH=/api
#
# Ou configurar via Configurações → Website no painel do Manager.
#
# Testar: publicar um artigo pelo botão "Site" no Manager.
EOF
echo ""

# ─── ROLLBACK (se precisar) ─────────────────────────────────────────────────
echo "=== ROLLBACK (emergência) ==="
cat << 'EOF'
# Restaurar WordPress
cd ~ && rm -rf public_html && tar -xzf backups/wp-backup-*.tar.gz
# Restaurar base WP
mysql -u $WP_DB_USER -p $WP_DB_NAME < ~/backups/wp-dump-*.sql
EOF

echo ""
echo "Deploy completo! Verificar tudo antes de remover o botão WordPress no Manager."
