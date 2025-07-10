# Guia de Deploy - Sistema de Entrega de Assinaturas de Pães

Este documento fornece instruções detalhadas para deploy do sistema em ambiente de produção.

## 📋 Pré-requisitos de Produção

### Servidor
- **OS**: Ubuntu 20.04+ ou CentOS 8+
- **CPU**: 2+ cores
- **RAM**: 4GB+ (recomendado 8GB)
- **Storage**: 50GB+ SSD
- **Network**: Conexão estável com internet

### Software
- **PHP**: 8.1+ com extensões necessárias
- **Composer**: 2.0+
- **Web Server**: Nginx 1.18+ ou Apache 2.4+
- **Database**: MySQL 8.0+ ou PostgreSQL 13+
- **Redis**: 6.0+ (para cache e filas)
- **Supervisor**: Para gerenciar workers
- **SSL Certificate**: Let's Encrypt ou certificado válido

## 🚀 Instalação do Ambiente

### 1. Atualizar Sistema
```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Instalar PHP e Extensões
```bash
sudo apt install -y php8.1 php8.1-fpm php8.1-cli php8.1-common \
    php8.1-mysql php8.1-zip php8.1-gd php8.1-mbstring \
    php8.1-curl php8.1-xml php8.1-bcmath php8.1-redis \
    php8.1-intl php8.1-soap php8.1-opcache
```

### 3. Instalar Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

### 4. Instalar Nginx
```bash
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

### 5. Instalar MySQL
```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

### 6. Instalar Redis
```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### 7. Instalar Supervisor
```bash
sudo apt install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

## 📁 Deploy da Aplicação

### 1. Criar Usuário para Deploy
```bash
sudo adduser deploy
sudo usermod -aG www-data deploy
sudo su - deploy
```

### 2. Clonar Repositório
```bash
cd /var/www
git clone https://github.com/seu-usuario/bread-subscription-system.git
cd bread-subscription-system
```

### 3. Instalar Dependências
```bash
composer install --no-dev --optimize-autoloader
```

### 4. Configurar Permissões
```bash
sudo chown -R deploy:www-data /var/www/bread-subscription-system
sudo chmod -R 755 /var/www/bread-subscription-system
sudo chmod -R 775 /var/www/bread-subscription-system/storage
sudo chmod -R 775 /var/www/bread-subscription-system/bootstrap/cache
```

### 5. Configurar Ambiente
```bash
cp .env.example .env
php artisan key:generate
```

### 6. Editar Configurações de Produção
```bash
nano .env
```

```env
APP_NAME="Sistema de Assinaturas de Pães"
APP_ENV=production
APP_KEY=base64:generated_key_here
APP_DEBUG=false
APP_URL=https://api.breaddelivery.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bread_subscription
DB_USERNAME=bread_user
DB_PASSWORD=secure_password_here

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=noreply@breaddelivery.com
MAIL_PASSWORD=app_password_here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@breaddelivery.com
MAIL_FROM_NAME="${APP_NAME}"

# Asaas Configuration
ASAAS_API_KEY=your_production_api_key_here
ASAAS_ENVIRONMENT=production
ASAAS_WEBHOOK_SECRET=your_webhook_secret_here
ASAAS_WEBHOOK_URL=https://api.breaddelivery.com/api/webhooks/asaas
```

### 7. Configurar Banco de Dados
```bash
# Criar banco e usuário
sudo mysql -u root -p

CREATE DATABASE bread_subscription CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bread_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON bread_subscription.* TO 'bread_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Executar migrations
php artisan migrate --force
```

### 8. Otimizações de Produção
```bash
# Cache de configuração
php artisan config:cache

# Cache de rotas
php artisan route:cache

# Cache de views
php artisan view:cache

# Otimizar autoloader
composer dump-autoload --optimize

# Cache de eventos
php artisan event:cache
```

## 🌐 Configuração do Nginx

### 1. Criar Configuração do Site
```bash
sudo nano /etc/nginx/sites-available/bread-subscription
```

```nginx
server {
    listen 80;
    server_name api.breaddelivery.com;
    root /var/www/bread-subscription-system/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Rate limiting
    location /api/auth/ {
        limit_req zone=auth burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

### 2. Ativar Site
```bash
sudo ln -s /etc/nginx/sites-available/bread-subscription /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 3. Configurar SSL com Let's Encrypt
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d api.breaddelivery.com
```

## 🔧 Configuração do PHP-FPM

### 1. Otimizar PHP-FPM
```bash
sudo nano /etc/php/8.1/fpm/pool.d/www.conf
```

```ini
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.1-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.process_idle_timeout = 10s
pm.max_requests = 500
```

### 2. Configurar PHP para Produção
```bash
sudo nano /etc/php/8.1/fpm/php.ini
```

```ini
memory_limit = 256M
max_execution_time = 60
max_input_vars = 3000
upload_max_filesize = 10M
post_max_size = 10M
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
```

```bash
sudo systemctl restart php8.1-fpm
```

## 🔄 Configuração de Filas com Supervisor

### 1. Criar Configuração do Worker
```bash
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/bread-subscription-system/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/bread-subscription-system/storage/logs/worker.log
stopwaitsecs=3600
```

### 2. Ativar Worker
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

## 📊 Configuração de Logs

### 1. Configurar Rotação de Logs
```bash
sudo nano /etc/logrotate.d/laravel
```

```
/var/www/bread-subscription-system/storage/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 deploy www-data
    postrotate
        sudo supervisorctl restart laravel-worker:*
    endscript
}
```

### 2. Configurar Logs do Nginx
```bash
sudo nano /etc/logrotate.d/nginx
```

## 🔒 Configurações de Segurança

### 1. Configurar Firewall
```bash
sudo ufw enable
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw allow 3306  # MySQL (apenas se necessário)
```

### 2. Configurar Rate Limiting no Nginx
```bash
sudo nano /etc/nginx/nginx.conf
```

```nginx
http {
    # Rate limiting zones
    limit_req_zone $binary_remote_addr zone=auth:10m rate=5r/m;
    limit_req_zone $binary_remote_addr zone=api:10m rate=60r/m;
    
    # Existing configuration...
}
```

### 3. Configurar Headers de Segurança
```nginx
# Adicionar ao bloco server
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self'" always;
```

## 📈 Monitoramento

### 1. Configurar Monitoramento de Saúde
```bash
# Criar script de health check
nano /var/www/bread-subscription-system/health-check.sh
```

```bash
#!/bin/bash
curl -f http://localhost/api/health || exit 1
```

```bash
chmod +x /var/www/bread-subscription-system/health-check.sh
```

### 2. Configurar Cron Jobs
```bash
crontab -e
```

```cron
# Health check a cada 5 minutos
*/5 * * * * /var/www/bread-subscription-system/health-check.sh

# Limpeza de logs antigos
0 2 * * * find /var/www/bread-subscription-system/storage/logs -name "*.log" -mtime +30 -delete

# Backup do banco de dados
0 3 * * * mysqldump -u bread_user -p'password' bread_subscription > /backups/db_$(date +\%Y\%m\%d).sql

# Geração automática de entregas
0 1 * * * cd /var/www/bread-subscription-system && php artisan queue:work --once --queue=deliveries
```

## 🔄 Deploy Automatizado

### 1. Script de Deploy
```bash
nano /var/www/deploy.sh
```

```bash
#!/bin/bash

set -e

echo "🚀 Iniciando deploy..."

# Navegar para diretório
cd /var/www/bread-subscription-system

# Fazer backup do banco
echo "📦 Fazendo backup do banco..."
mysqldump -u bread_user -p'password' bread_subscription > /backups/pre_deploy_$(date +%Y%m%d_%H%M%S).sql

# Ativar modo de manutenção
echo "🔧 Ativando modo de manutenção..."
php artisan down

# Atualizar código
echo "📥 Atualizando código..."
git pull origin main

# Instalar dependências
echo "📦 Instalando dependências..."
composer install --no-dev --optimize-autoloader

# Executar migrations
echo "🗄️ Executando migrations..."
php artisan migrate --force

# Limpar caches
echo "🧹 Limpando caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Recriar caches
echo "⚡ Recriando caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Reiniciar workers
echo "🔄 Reiniciando workers..."
sudo supervisorctl restart laravel-worker:*

# Desativar modo de manutenção
echo "✅ Desativando modo de manutenção..."
php artisan up

echo "🎉 Deploy concluído com sucesso!"
```

```bash
chmod +x /var/www/deploy.sh
```

## 🔍 Troubleshooting

### Problemas Comuns

#### 1. Erro 500 - Internal Server Error
```bash
# Verificar logs
tail -f /var/www/bread-subscription-system/storage/logs/laravel.log
tail -f /var/log/nginx/error.log

# Verificar permissões
sudo chown -R deploy:www-data /var/www/bread-subscription-system
sudo chmod -R 775 storage bootstrap/cache
```

#### 2. Workers não processam filas
```bash
# Verificar status do supervisor
sudo supervisorctl status

# Reiniciar workers
sudo supervisorctl restart laravel-worker:*

# Verificar logs dos workers
tail -f /var/www/bread-subscription-system/storage/logs/worker.log
```

#### 3. Problemas de conexão com banco
```bash
# Testar conexão
php artisan tinker
>>> DB::connection()->getPdo();

# Verificar configurações
php artisan config:show database
```

#### 4. SSL/HTTPS não funciona
```bash
# Verificar certificado
sudo certbot certificates

# Renovar certificado
sudo certbot renew --dry-run

# Verificar configuração do Nginx
sudo nginx -t
```

### Comandos Úteis

```bash
# Verificar status dos serviços
sudo systemctl status nginx php8.1-fpm mysql redis-server supervisor

# Monitorar logs em tempo real
tail -f /var/www/bread-subscription-system/storage/logs/laravel.log

# Verificar uso de recursos
htop
df -h
free -h

# Testar conectividade da API
curl -I https://api.breaddelivery.com/api/health

# Verificar filas
php artisan queue:monitor

# Limpar filas com falha
php artisan queue:flush
```

## 📋 Checklist de Deploy

### Pré-Deploy
- [ ] Backup do banco de dados
- [ ] Verificar se todas as dependências estão atualizadas
- [ ] Testar em ambiente de staging
- [ ] Verificar configurações de produção

### Deploy
- [ ] Ativar modo de manutenção
- [ ] Atualizar código
- [ ] Instalar dependências
- [ ] Executar migrations
- [ ] Limpar e recriar caches
- [ ] Reiniciar workers
- [ ] Desativar modo de manutenção

### Pós-Deploy
- [ ] Verificar health check
- [ ] Testar endpoints principais
- [ ] Verificar logs de erro
- [ ] Monitorar performance
- [ ] Verificar processamento de filas

## 🆘 Suporte

Para problemas de deploy:
- **Email**: devops@breaddelivery.com
- **Slack**: #deploy-support
- **Documentação**: [docs.breaddelivery.com/deploy](https://docs.breaddelivery.com/deploy)

