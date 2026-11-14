# Bhelpdesk

Sistema di ticketing (stile osTicket) su stack **Linux + nginx + PHP 8.2+ + MariaDB**, senza framework né dipendenze esterne.

Un'unica applicazione, due aree:

| URL           | Chi            | Cosa                                                       |
|---------------|----------------|------------------------------------------------------------|
| `/`           | —              | redirect a `/customer/`                                    |
| `/customer/`  | clienti        | apertura ticket, verifica stato (numero + email), risposte |
| `/op/`        | operatori      | dashboard, gestione, risposte, note interne, operatori     |

`/op/` è protetta su **tre livelli**: filtro IP in nginx, filtro IP applicativo (`op_allowed_ips`), login con username/password.

## Struttura

```
bhelpdesk/
├── public/                 ← document root di nginx (unica cartella esposta)
│   ├── index.php           redirect a /customer/
│   ├── customer/index.php  front controller area clienti
│   ├── op/index.php        front controller area operatori
│   └── assets/style.css
├── src/                    codice applicativo (namespace Bhelpdesk)
│   ├── bootstrap.php, helpers.php
│   ├── Db.php, Router.php, View.php, Session.php, Csrf.php
│   ├── Auth.php            login operatori
│   ├── IpGuard.php         match IP/CIDR v4+v6, gestione X-Forwarded-For
│   ├── Throttle.php        rate limit (login, apertura ticket, verifica stato)
│   └── Tickets.php         logica ticket
├── templates/{customer,op}/
├── config/config.example.php
├── sql/schema.sql
├── bin/create-operator.php
└── deploy/                 esempi nginx, snippet IP, pool php-fpm
```

## Installazione (Debian/Ubuntu)

```bash
# 1. pacchetti
apt install nginx mariadb-server php8.3-fpm php8.3-mysql php8.3-mbstring

# 2. utente e codice
useradd --system --home /var/www/bhelpdesk --shell /usr/sbin/nologin bhelpdesk
cp -r bhelpdesk /var/www/bhelpdesk
chown -R root:bhelpdesk /var/www/bhelpdesk && chmod -R o-rwx /var/www/bhelpdesk
chmod -R g-w /var/www/bhelpdesk && chmod 640 /var/www/bhelpdesk/config/config.php
# nginx deve leggere solo public/
chmod o+x /var/www/bhelpdesk && chmod -R o+rX /var/www/bhelpdesk/public

# 3. database
mariadb -e "CREATE DATABASE bhelpdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
            CREATE USER 'bhelpdesk'@'localhost' IDENTIFIED BY 'PASSWORD_FORTE';
            GRANT SELECT, INSERT, UPDATE, DELETE ON bhelpdesk.* TO 'bhelpdesk'@'localhost';"
mariadb bhelpdesk < /var/www/bhelpdesk/sql/schema.sql

# 4. configurazione
cp config/config.example.php config/config.php   # e modificalo

# 5. php-fpm
mkdir -p /var/lib/php/sessions-bhelpdesk /var/log/php
chown bhelpdesk:bhelpdesk /var/lib/php/sessions-bhelpdesk
cp deploy/php-fpm-bhelpdesk.conf /etc/php/8.3/fpm/pool.d/bhelpdesk.conf
systemctl reload php8.3-fpm

# 6. nginx
cp deploy/bhelpdesk-op-allow.conf /etc/nginx/snippets/   # metti i tuoi IP
cp deploy/nginx-bhelpdesk.conf /etc/nginx/sites-available/bhelpdesk.conf
ln -s ../sites-available/bhelpdesk.conf /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# 7. primo amministratore
sudo -u bhelpdesk php /var/www/bhelpdesk/bin/create-operator.php --role=admin
```

## Apache (per test o hosting senza nginx)

Servono `mod_rewrite` e `AllowOverride All` sulla cartella del progetto. PHP può girare con PHP-FPM (`proxy_fcgi`) o con mod_php.

- **Document root = `public/`** (consigliato): vengono usati `public/.htaccess` (redirect, front controller, blocco degli altri `.php`) e `public/op/.htaccess` (filtro IP).
- **Document root = cartella del progetto** (es. hosting dove la root non si può cambiare): il `.htaccess` nella root blocca `src/`, `config/`, `sql/`, `templates/`, `bin/`, `deploy/` e i dotfile, e manda tutto il resto dentro `public/`.

IP ammessi a `/op` su Apache: modifica la riga `Require ip` in `public/op/.htaccess` (IP singoli, CIDR, IPv6, separati da spazio).

Per un test in HTTP semplice imposta `'cookie_secure' => false` in `config.php`, altrimenti il browser non rimanda il cookie di sessione e i form danno "Sessione scaduta".

## Filtro IP su /op

- **nginx** (`snippets/bhelpdesk-op-allow.conf`): blocca prima ancora che PHP venga eseguito. È il filtro principale.
- **applicativo** (`op_allowed_ips` in `config.php`): secondo livello, utile se un domani il vhost viene modificato per errore. Supporta IP singoli e CIDR IPv4/IPv6. Array vuoto = disattivato.
- **dietro proxy/CDN**: se davanti c'è un reverse proxy o una CDN, nginx vede l'IP del proxy. In quel caso usa `set_real_ip_from` + `real_ip_header` (modulo realip) in nginx e valorizza `trusted_proxies` in `config.php`: solo allora `X-Forwarded-For` viene considerato.

## Flusso ticket

Stati: **Aperto** → (risposta operatore) **Risposto** → (risposta cliente) **Aperto** … → **Chiuso**. Esiste anche **In sospeso**.

- Il cliente apre il ticket e riceve numero a 6 cifre + link con token di accesso.
- Per rivederlo usa numero + email oppure il link.
- Una risposta del cliente su ticket chiuso lo riapre (`allow_reopen`).
- Gli operatori vedono anche le note interne e il log di sistema (cambi di stato, priorità, reparto, assegnazione).
- Alla prima risposta pubblica, un ticket non assegnato viene assegnato all'operatore che risponde.

## Sicurezza già presente

CSRF su tutti i POST · sessioni separate per area (cookie `BHD_CUST` path `/customer/`, `BHD_OP` path `/op/`), HttpOnly, Secure, SameSite · `session_regenerate_id` al login · scadenza per inattività operatori · password con `password_hash` (rehash automatico) · rate limit su login, apertura ticket, verifica stato · honeypot anti-bot · prepared statement ovunque · escaping output · CSP restrittiva · solo `public/` esposta, eseguibili solo i due `index.php`.

## Prossimi passi suggeriti

1. Notifiche email (nuovo ticket, risposta) via SMTP
2. Allegati (storage fuori da `public/`, download tramite PHP con controllo permessi)
3. Apertura ticket da email (piping/IMAP)
4. Gestione reparti da interfaccia, risposte predefinite, SLA/scadenze
5. 2FA TOTP per gli operatori
6. Account cliente con storico ticket
