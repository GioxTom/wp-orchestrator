# WP Orchestrator — Documentazione Tecnica

## Indice

1. [Architettura generale](#architettura)
2. [Stack tecnologico](#stack)
3. [Schema database](#database)
4. [Pipeline di provisioning](#pipeline)
5. [Sistema Blueprint](#blueprint)
6. [Sistema Prompt](#prompt)
7. [Connessioni server](#connessioni)
8. [Filament — Pannello di controllo](#filament)
9. [Job e Code](#job)
10. [Governance e sicurezza](#governance)
11. [Monitoring e audit](#audit)
12. [Scalabilità multi-server](#multiserver)
13. [Integrazione NanaBanana](#nanobanana)
14. [FAQ e troubleshooting](#faq)

---

## 1. Architettura generale {#architettura}

WP Orchestrator è un pannello Laravel + Filament che gestisce il lifecycle completo di siti WordPress su uno o più server, interfacciandosi con:

- **ISPConfig** come layer di provisioning server (vhost, DB, certificati)
- **WP-CLI** come layer applicativo WordPress
- **Varnish + Nginx + Apache** come stack HTTP

```
┌─────────────────────────────────────────────┐
│            WP Orchestrator                  │
│         (Laravel + Filament)                │
│                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  │
│  │ Filament │  │  Horizon │  │Scheduler │  │
│  │  Panel   │  │  Queue   │  │  Cron    │  │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  │
└───────┼─────────────┼─────────────┼─────────┘
        │             │             │
        ▼             ▼             ▼
┌───────────────────────────────────────────┐
│              SERVER (stesso host)         │
│                                           │
│  ISPConfig API ◄──── IspConfigService     │
│  WP-CLI        ◄──── WpCliService         │
│  Varnish       ◄──── VarnishService       │
│  MariaDB       ◄──── (ISPConfig gestisce) │
└───────────────────────────────────────────┘
```

---

## 2. Stack tecnologico {#stack}

| Componente | Tecnologia | Versione |
|---|---|---|
| Framework | Laravel | 11.x |
| Pannello admin | Filament | 3.x |
| Coda job | Laravel Queue (database driver) | — |
| Process manager | Supervisord | — |
| Web server siti | Apache + Nginx + Varnish | — |
| Pannello server | ISPConfig | 3.3 |
| DB orchestrator | MariaDB | 10.6+ |
| PHP | PHP-FPM | 8.3 |
| SSH (fase 2) | phpseclib | 3.x |

---

## 3. Schema database {#database}

### `servers`
Configurazione dei server target. In fase 1 conterrà un solo record (stesso server).

| Campo | Tipo | Note |
|---|---|---|
| connection_type | enum(local,ssh) | `local` usa exec(), `ssh` usa phpseclib |
| ispconfig_password | text (encrypted) | Cifrato con APP_KEY Laravel |

### `ispconfig_clients`
Clienti sincronizzati da ISPConfig tramite API SOAP. Aggiornati ogni ora dal `SyncIspConfigDataJob`.

### `ispconfig_php_versions`
Versioni PHP disponibili sul server, sincronizzate da ISPConfig.

### `blueprints`
Pacchetti preconfigurati: ZIP tema + lista plugin + impostazioni WP + skeleton child theme.

| Campo | Tipo | Note |
|---|---|---|
| zip_path | string | Path relativo in `storage/app/blueprints/` |
| plugin_list | JSON | Array di `{slug, version, activate}` |
| wp_settings | JSON | Opzioni WordPress da impostare |
| child_skeleton | text | Template functions.php con placeholder |

### `prompts`
Prompt per le API AI. I prompt di sistema (`type=system`) sono read-only e non eliminabili.

| Campo | Tipo | Note |
|---|---|---|
| action | string | `logo_generation`, `content_generation`, ecc. |
| type | enum(system,user) | system = read-only |
| content | text | Testo con placeholder `{site_name}`, `{site_description}`, `{locale}` |

### `sites`
Record principale di ogni sito WordPress gestito.

| Campo | Tipo | Note |
|---|---|---|
| db_password | text (encrypted) | Cifrato |
| wp_admin_password | text (encrypted) | Cifrato, one-time |
| status | enum | pending→provisioning→active/error |

### `provisioning_logs`
Ogni step della pipeline scrive un record qui. Visibile nel pannello per debug.

### `site_audits`
Risultati del monitoraggio periodico (ogni 30 min) per ogni sito attivo.

### `settings`
Chiave/valore per configurazioni globali (API keys, ecc.). I valori sensibili vengono cifrati.

---

## 4. Pipeline di provisioning {#pipeline}

La pipeline è una catena di Job Laravel dove ogni job lancia il successivo solo in caso di successo.

```
CreateIspConfigDomainJob
    ↓ (successo)
CreateIspConfigDatabaseJob
    ↓ (successo)
InstallWordPressJob
    ↓ (successo)
ApplyBlueprintJob
    ↓ (successo)
DeployMuPluginJob
    ↓ (successo)
FinalHealthCheckJob
    ↓ (successo)
PurgeVarnishJob
    ↓ (sito → ACTIVE)
    └──► GenerateLogoJob (asincrono, non bloccante)
```

**In caso di errore:** il sito va in stato `error`, il log del job fallito viene salvato con l'output completo, e il provisioning si ferma. Possibile rilanciare dal pannello (feature futura).

### Dettaglio step

**CreateIspConfigDomainJob**
- Chiama `sites_web_domain_add` via API SOAP ISPConfig
- Passa flag `ssl=y` e `ssl_letsencrypt=y` se SSL abilitato
- Attende max 30s che ISPConfig crei il docroot su disco
- Salva `ispconfig_domain_id` e `docroot` sul sito

**CreateIspConfigDatabaseJob**
- Genera `db_name`, `db_user`, `db_password` univoci e sicuri
- Chiama `sites_database_add` via API ISPConfig
- Salva credenziali DB encrypted sul sito

**InstallWordPressJob**
- `wp core download --locale={locale}`
- `wp config create` con le credenziali DB
- `wp core install` con nome sito, email, password admin
- `wp option update blogname / blogdescription`
- Salva `wp_admin_password` encrypted (one-time)

**ApplyBlueprintJob**
- Copia ZIP tema in `/tmp/`, installa con `wp theme install`
- Installa plugin dalla lista con `wp plugin install`
- Genera child theme (style.css + functions.php dallo skeleton)
- Attiva il child theme

**DeployMuPluginJob**
- Genera il contenuto del MU-plugin con l'email canonica del sito
- Copia in `wp-content/mu-plugins/orchestrator-governance.php`

**FinalHealthCheckJob**
- Verifica HTTP :80 e HTTPS :443 rispondono ≥ 200
- Recupera la data di scadenza del certificato SSL
- Salva il primo `SiteAudit`

**PurgeVarnishJob**
- `varnishadm ban 'req.http.host == {domain}'`
- Sito passa a stato `active`
- Dispatch asincrono di `GenerateLogoJob`

---

## 5. Sistema Blueprint {#blueprint}

Un blueprint è un pacchetto riutilizzabile che definisce l'aspetto e la configurazione di un sito WordPress.

### Struttura

```json
{
  "plugin_list": [
    {"slug": "yoast-seo", "version": "latest", "activate": true},
    {"slug": "wp-super-cache", "version": "latest", "activate": true},
    {"slug": "wordfence", "version": "latest", "activate": true}
  ],
  "wp_settings": {
    "permalink_structure": "/%postname%/",
    "timezone": "Europe/Rome",
    "posts_per_page": "10",
    "default_comment_status": "closed"
  }
}
```

### Child theme generato

Il child viene creato automaticamente da `BlueprintService::generateAndActivateChildTheme()`.

Se il blueprint ha uno `child_skeleton`, viene usato come template per `functions.php` con sostituzione dei placeholder:
- `{site_name}` → nome del sito
- `{domain}` → dominio
- `{locale}` → lingua

Se non c'è skeleton, viene usato un `functions.php` minimale che fa solo `wp_enqueue_style` del parent.

**Il child theme NON viene mai sovrascritto** in operazioni successive — protegge le personalizzazioni.

---

## 6. Sistema Prompt {#prompt}

### Logica type system/user

| Tipo | Creato da | Modificabile | Eliminabile | Duplicabile |
|---|---|---|---|---|
| `system` | Seeder PHP | ❌ | ❌ | ✅ |
| `user` | Pannello Filament | ✅ | ✅ | ✅ |

### Aggiungere nuove action

1. Aggiungi la nuova action al seeder `PromptSeeder`
2. Aggiungi l'opzione nella select del `PromptResource`
3. Aggiungi la select nel wizard del sito (se necessario)
4. Crea il Job che usa `$site->resolvedPrompt()` per ottenere il testo

### Placeholder

Il metodo `Site::resolvedPrompt()` sostituisce automaticamente:
- `{site_name}` → `$site->site_name`
- `{site_description}` → `$site->description`
- `{locale}` → `$site->locale`

---

## 7. Connessioni server {#connessioni}

### Fase 1 — LocalConnection

```php
$server->connection(); // → LocalConnection
```

Usa `Symfony\Component\Process` per eseguire comandi locali. Timeout di 300s per WP-CLI.

### Fase 2 — SshConnection

Per aggiungere un server remoto:
1. Cambia `connection_type` a `ssh` nel record Server
2. Imposta `ssh_user` e `ssh_key_path`
3. Il sistema usa automaticamente `phpseclib3` per SSH

I Job non cambiano — usano sempre l'interfaccia `ServerConnection`.

```php
interface ServerConnection {
    public function run(string $command): string;
    public function upload(string $localPath, string $remotePath): void;
    public function test(): bool;
}
```

---

## 8. Filament — Pannello di controllo {#filament}

### Accesso
URL: `https://tuodominio.com:8443/admin`

### Resources

| Resource | Percorso | Funzione |
|---|---|---|
| Sites | /admin/sites | Lista, wizard creazione, azioni runtime |
| Blueprints | /admin/blueprints | Gestione blueprint con upload ZIP |
| Prompts | /admin/prompts | Gestione prompt con duplica |
| Servers | /admin/servers | Config server + test connessione |
| ISPConfig Clients | /admin/isp-config-clients | Clienti sincronizzati (read-only) |
| Settings | /admin/settings | API keys e opzioni globali |

### Azioni runtime sui siti

- **Reset password admin** — genera nuova password, la mostra una sola volta in un modal
- **Purge Varnish** — invalida la cache del dominio
- **Abilita/Disabilita** — abilita o disabilita il vhost via API ISPConfig
- **Log provisioning** — vista step-by-step con output per debug

---

## 9. Job e Code {#job}

### Driver coda: database

Nessun Redis richiesto. La tabella `jobs` in MariaDB gestisce la coda.

```ini
QUEUE_CONNECTION=database
```

### Supervisord

Il worker è gestito da Supervisord con 2 processi paralleli:

```ini
[program:wp-orchestrator-worker]
command=php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
numprocs=2
```

### Scheduler

Il cron Laravel chiama ogni minuto `schedule:run`, che a sua volta:
- Ogni ora: `SyncIspConfigDataJob` per tutti i server attivi
- Ogni 30 minuti: `SiteAuditJob` per tutti i siti attivi

---

## 10. Governance e sicurezza {#governance}

### MU-Plugin

Il file `wp-content/mu-plugins/orchestrator-governance.php` viene deploiato su ogni sito e:
- Blocca la promozione di utenti non autorizzati ad administrator
- Blocca la modifica dell'email dell'admin canonico dal backend WP
- Non può essere disattivato dall'admin WordPress (è un MU-plugin)

### Audit automatico (ogni 30 min)

`SiteAuditJob` verifica per ogni sito:
1. HTTP :80 e HTTPS :443 rispondono 200
2. Esattamente 1 admin con l'email canonica
3. MU-plugin presente nel filesystem

**Remediation automatica:**
- Admin non autorizzati → eliminati
- Admin canonico mancante → ricreato
- MU-plugin mancante → rideploya `DeployMuPluginJob`

### Credenziali cifrate

Tutti i campi sensibili usano il cast `encrypted` di Laravel:
- `servers.ispconfig_password`
- `sites.db_password`
- `sites.wp_admin_password`
- `settings.value` (quando `is_encrypted=true`)

La cifratura usa `APP_KEY`. **Backup della APP_KEY = backup delle credenziali.**

---

## 11. Monitoring e audit {#audit}

### SiteAudit

Ogni run di `SiteAuditJob` crea un record in `site_audits`. Dal pannello è visibile l'ultimo audit per ogni sito nella view di dettaglio.

### Provisioning Logs

Ogni step della pipeline scrive su `provisioning_logs` con:
- Step label leggibile
- Status: pending / running / success / failed
- Output completo del comando
- Timestamp inizio e fine

Visibile dal pannello → Siti → Log.

---

## 12. Scalabilità multi-server {#multiserver}

Per aggiungere un secondo server:

1. Crea un nuovo record in **Servers** con `connection_type=ssh`
2. Imposta `ssh_user` e `ssh_key_path` (chiave privata del worker)
3. Installa WP-CLI sul server remoto
4. Il sistema sincronizzerà automaticamente clienti e versioni PHP

I Job funzionano invariati grazie all'interfaccia `ServerConnection`.

**Prerequisiti sul server remoto:**
- WP-CLI installato e accessibile in PATH
- ISPConfig installato e API abilitata
- Chiave SSH del worker aggiunta a `~/.ssh/authorized_keys`

---

## 13. Integrazione NanaBanana {#nanobanana}

### Configurazione

Dal pannello → Impostazioni → inserisci la API Key NanaBanana.

La chiave viene salvata cifrata in `settings` con `is_encrypted=true`.

### Flusso

1. Al termine del provisioning (sito ACTIVE), viene lanciato `GenerateLogoJob`
2. Il job chiama `NanaBananaService::generateLogo()` con il prompt risolto
3. Il logo viene scaricato e importato nella media library WordPress
4. Viene impostato come `custom_logo` del tema
5. La URL viene salvata in `sites.logo_url`

### Generazione logo manuale

Dalla vista dettaglio del sito sarà possibile rigenerare il logo on-demand (feature da implementare come Action Filament).

### Adattamento API

Il `NanaBananaService` usa una struttura generica. Adatta l'endpoint e il formato della risposta in base alla documentazione ufficiale NanaBanana:

```php
$response = Http::withToken($this->apiKey)
    ->post('https://api.nanobanana.io/v1/generate', [
        'prompt' => $prompt,
        'type'   => 'logo',
        'format' => 'png',
    ]);

return $response->json('url'); // adatta questo al campo reale
```

---

## 14. FAQ e troubleshooting {#faq}

### Il provisioning si ferma su "Creazione vhost ISPConfig"

Verifica:
- URL API ISPConfig corretta (include `/remote/json.php`)
- Credenziali ISPConfig valide
- Estensione PHP `soap` installata: `php -m | grep soap`
- ISPConfig raggiungibile dall'orchestrator

### Il sito non risponde dopo il provisioning

Verifica:
- Varnish in ascolto su :80: `ss -tlnp | grep :80`
- Apache in ascolto su :8080: `ss -tlnp | grep :8080`
- Nginx in ascolto su :443: `ss -tlnp | grep :443`
- Il vhost ISPConfig è stato creato: controlla in ISPConfig panel
- DNS del dominio punta all'IP corretto

### WP-CLI non trovato

```bash
which wp
# se non trovato:
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
mv wp-cli.phar /usr/local/bin/wp
```

### Il queue worker non processa i job

```bash
supervisorctl status wp-orchestrator-worker:*
# se stopped:
supervisorctl start wp-orchestrator-worker:*

# verifica i job in coda:
php artisan queue:monitor
```

### Le credenziali sono illeggibili dopo un cambio APP_KEY

Le credenziali cifrate con la vecchia `APP_KEY` non sono decifrabili con la nuova.
**Non cambiare mai APP_KEY in produzione** senza prima decifrare e ricifrare tutti i dati sensibili.

---

*Documentazione generata per WP Orchestrator v1.0 — Febbraio 2026*
