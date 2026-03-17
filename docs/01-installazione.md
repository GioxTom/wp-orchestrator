# WP Orchestrator — Guida Installazione Step by Step

## Prerequisiti sul server

- Ubuntu 22.04 / Debian 12
- ISPConfig 3.3 già installato e operativo
- Apache + Nginx + Varnish già configurati
- PHP 8.3 + PHP-FPM
- MariaDB
- Composer
- Node.js 20+ (per assets Filament)
- Supervisord

---

## Step 1 — Dipendenze PHP necessarie

```bash
apt install -y php8.3-cli php8.3-fpm php8.3-mysql php8.3-xml \
  php8.3-mbstring php8.3-curl php8.3-zip php8.3-bcmath \
  php8.3-intl php8.3-soap php8.3-gd unzip git curl
```

> La estensione `soap` è necessaria per le API ISPConfig.

---

## Step 2 — Utente Linux dedicato per l'orchestrator

```bash
useradd -m -s /bin/bash orchestrator
passwd orchestrator   # scegli una password sicura
usermod -aG www-data orchestrator


#QUesto server per utilizzare wp-cli come un utente ispconf senza digitare password.
echo "orchestrator ALL=(ALL) NOPASSWD: /usr/local/bin/wp" > /etc/sudoers.d/orchestrator-wpcli
echo "orchestrator ALL=(ALL) NOPASSWD: /bin/cp" >> /etc/sudoers.d/orchestrator-wpcli

chmod 440 /etc/sudoers.d/orchestrator-wpcli
```
---

## Step 3 — Composer globale

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
```

---

## Step 4 — Installazione Laravel

```bash
su - orchestrator
cd /home/orchestrator

composer create-project laravel/laravel wp-orchestrator
cd wp-orchestrator
```

---

## Step 5 — Installazione dipendenze PHP

```bash
composer require \
  filament/filament:"^3.0" \
  spatie/laravel-settings \
  illuminate/database \
  phpseclib/phpseclib:"~3.0"
```

> `phpseclib` serve per la fase 2 (connessioni SSH multi-server).

---

## Step 6 — Installazione Filament

```bash
php artisan filament:install --panels
```

Quando richiesto:
- Panel ID: `admin`
- Conferma la creazione del provider

---

## Step 7 — Database dell'orchestrator

Accedi a MariaDB come root:

```bash
mysql -u root -p
```

```sql
CREATE DATABASE wp_orchestrator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'orchestrator'@'localhost' IDENTIFIED BY 'PASSWORD_SICURA';
GRANT ALL PRIVILEGES ON wp_orchestrator.* TO 'orchestrator'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> Sostituisci `PASSWORD_SICURA` con una password robusta.

---

## Step 8 — Configurazione .env

```bash
cp .env.example .env
php artisan key:generate
```

Modifica `.env`:

```ini
APP_NAME="WP Orchestrator"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://orchestrator.tuodominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wp_orchestrator
DB_USERNAME=orchestrator
DB_PASSWORD=PASSWORD_SICURA

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
```

---

## Step 9 — Migrations e Seeders

```bash
php artisan migrate --force
php artisan db:seed --force
```

Il seeder installa i **prompt di sistema** predefiniti (type=system, non modificabili).

---

## Step 10 — Assets Filament

```bash
npm install
npm run build
php artisan filament:assets
```

---

## Step 11 — Permessi cartelle

```bash
chown -R orchestrator:www-data /home/orchestrator/wp-orchestrator
chmod -R 755 /home/orchestrator/wp-orchestrator
chmod -R 775 /home/orchestrator/wp-orchestrator/storage
chmod -R 775 /home/orchestrator/wp-orchestrator/bootstrap/cache
```

---

## Step 12 — Storage per i Blueprint ZIP

```bash
php artisan storage:link
mkdir -p storage/app/blueprints
chmod -R 775 storage/app/blueprints
```

---

## Step 13 — Pool PHP-FPM dedicato per l'orchestrator

Crea il file `/etc/php/8.3/fpm/pool.d/orchestrator.conf`:

```ini
[orchestrator]
user = orchestrator
group = www-data
listen = /run/php/php8.3-fpm-orchestrator.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 5
php_admin_value[disable_functions] = ""
php_admin_value[open_basedir] = /home/orchestrator/wp-orchestrator:/tmp
```

Riavvia PHP-FPM:

```bash
systemctl restart php8.3-fpm
```

---

## Step 14 — Vhost Nginx per l'orchestrator

Crea `/etc/nginx/sites-available/orchestrator.conf`:

```nginx
server {
    listen 8443 ssl;
    server_name orchestrator.tuodominio.com;

    ssl_certificate /etc/letsencrypt/live/orchestrator.tuodominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/orchestrator.tuodominio.com/privkey.pem;

    root /home/orchestrator/wp-orchestrator/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm-orchestrator.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/orchestrator.conf /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

---

## Step 15 — Certificato SSL per l'orchestrator

```bash
certbot certonly --nginx -d orchestrator.tuodominio.com
```

---

## Step 16 — Supervisord per il Queue Worker

Crea `/etc/supervisor/conf.d/wp-orchestrator.conf`:

```ini
[program:wp-orchestrator-worker]
command=php /home/orchestrator/wp-orchestrator/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
directory=/home/orchestrator/wp-orchestrator
autostart=true
autorestart=true
user=orchestrator
numprocs=2
redirect_stderr=true
stdout_logfile=/home/orchestrator/wp-orchestrator/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start wp-orchestrator-worker:*
```

---

## Step 17 — Scheduler Laravel (cron)

```bash
crontab -e -u orchestrator
```

Aggiungi:

```
* * * * * cd /home/orchestrator/wp-orchestrator && php artisan schedule:run >> /dev/null 2>&1
```

---

## Step 18 — Primo accesso

```bash
php artisan make:filament-user
```

Inserisci nome, email e password dell'utente amministratore.

Accedi a: `https://orchestrator.tuodominio.com:8443/admin`

---

## Verifica finale

```bash
# Queue worker attivo
supervisorctl status wp-orchestrator-worker:*

# Scheduler attivo
php artisan schedule:list

# Database connesso
php artisan migrate:status

# Storage accessibile
php artisan storage:link
ls -la public/storage
```

---

## Note di sicurezza

- Il pannello è accessibile **solo su porta 8443** (non esposta su Varnish/Nginx pubblico)
- Configura un firewall per limitare l'accesso alla porta 8443 solo agli IP autorizzati:
  ```bash
  ufw allow from TUO_IP to any port 8443
  ```
- Abilita 2FA dal pannello Filament dopo il primo accesso
- Le credenziali sensibili nel DB sono cifrate con la `APP_KEY` di Laravel
