# WP Orchestrator

Pannello di controllo **Laravel + Filament** per la gestione automatizzata di reti di siti WordPress, con integrazione ISPConfig, WP-CLI e NanaBanana.

---

## Funzionalità

- 🚀 **Provisioning automatico** — crea un sito WordPress completo in 7 step automatici
- 📦 **Sistema Blueprint** — pacchetti riutilizzabili (tema + plugin + child theme + impostazioni)
- 🤖 **Generazione logo AI** — integrazione NanaBanana con sistema prompt flessibile
- 🔒 **Governance WordPress** — MU-plugin che garantisce admin canonico e blocca modifiche non autorizzate
- 📊 **Monitoring** — audit periodico di uptime, SSL, governance per ogni sito
- 🌍 **Multi-lingua** — selezione locale WordPress per ogni sito
- 🔧 **Azioni runtime** — reset password admin, purge Varnish, enable/disable sito
- 📡 **Multi-server ready** — architettura predisposta per gestire server remoti via SSH

---

## Stack

| Layer | Tecnologia |
|---|---|
| Framework | Laravel 11 + Filament 3 |
| Code (job) | Database driver + Supervisord |
| Web server siti | Apache :8080 + Nginx :443 + Varnish :80 |
| Pannello server | ISPConfig 3.3 |
| Provisioning WP | WP-CLI |
| Database | MariaDB |
| PHP | 8.3 |

---

## Installazione rapida

Vedi `docs/01-installazione.md` per la guida completa step-by-step.

```bash
# 1. Crea l'utente orchestrator
useradd -m -s /bin/bash orchestrator

# 2. Installa Laravel
composer create-project laravel/laravel wp-orchestrator
cd wp-orchestrator

# 3. Installa le dipendenze
composer require filament/filament:"^3.0" phpseclib/phpseclib:"~3.0"

# 4. Installa Filament
php artisan filament:install --panels

# 5. Configura .env (vedi .env.example)
cp .env.example .env && php artisan key:generate

# 6. Migra il database e i seeder
php artisan migrate --force && php artisan db:seed --force

# 7. Crea il primo utente admin
php artisan make:filament-user

# 8. Configura Supervisord (vedi deploy/supervisord.conf)
# 9. Configura Nginx (vedi deploy/nginx-orchestrator.conf)
# 10. Configura PHP-FPM (vedi deploy/php-fpm-orchestrator.conf)
```

---

## Struttura progetto

```
wp-orchestrator/
├── app/
│   ├── Contracts/          # ServerConnection interface
│   ├── Exceptions/         # ServerCommandException
│   ├── Console/            # Scheduler (Kernel.php)
│   ├── Filament/
│   │   ├── Pages/          # SettingsPage
│   │   └── Resources/      # Server, Blueprint, Prompt, Site, IspConfigClient
│   ├── Jobs/
│   │   ├── Audit/          # SiteAuditJob
│   │   ├── Provisioning/   # Pipeline 7 step + GenerateLogoJob
│   │   └── Sync/           # SyncIspConfigDataJob
│   ├── Models/             # 8 modelli Eloquent
│   └── Services/
│       ├── Connections/    # LocalConnection, SshConnection
│       ├── BlueprintService.php
│       ├── IspConfigService.php
│       ├── NanaBananaService.php
│       ├── VarnishService.php
│       └── WpCliService.php
├── database/
│   ├── migrations/         # 8 migration in ordine
│   └── seeders/            # PromptSeeder (prompt di sistema)
├── deploy/                 # Config Nginx, PHP-FPM, Supervisord
├── docs/
│   ├── 01-installazione.md
│   └── 02-documentazione-tecnica.md
└── resources/views/filament/  # Blade views per Filament
```

---

## Pipeline provisioning

```
CreateIspConfigDomainJob   → vhost + SSL/LE via ISPConfig
      ↓
CreateIspConfigDatabaseJob → DB + utente MariaDB
      ↓
InstallWordPressJob         → core WP + config + install + locale
      ↓
ApplyBlueprintJob           → tema parent + plugin + child theme
      ↓
DeployMuPluginJob           → MU-plugin governance admin
      ↓
FinalHealthCheckJob         → verifica HTTP/HTTPS
      ↓
PurgeVarnishJob             → ban cache → sito ACTIVE
      ↓ (asincrono)
GenerateLogoJob             → NanaBanana API → logo su WP
```

---

## Documentazione

- **Guida installazione**: `docs/01-installazione.md`
- **Documentazione tecnica**: `docs/02-documentazione-tecnica.md`

---

## Requisiti

- Ubuntu 22.04 / Debian 12
- PHP 8.3 con estensioni: `cli`, `fpm`, `mysql`, `xml`, `mbstring`, `curl`, `zip`, `bcmath`, `intl`, **`soap`**
- ISPConfig 3.3 installato e operativo
- Apache + Nginx + Varnish configurati
- MariaDB 10.6+
- WP-CLI installato (`/usr/local/bin/wp`)
- Supervisord
- Composer 2.x
- Node.js 20+ (per compilare gli asset Filament)
