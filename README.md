# Bhelpdesk

Ticketing system (osTicket-style) for **Linux + nginx + PHP 8.2+ + MariaDB**, no framework and no external dependencies. Base language: **English**, every on-screen text is translatable (see [Translations](#translations)).

One application, two areas:

| URL           | Who        | What                                                         |
|---------------|------------|--------------------------------------------------------------|
| `/`           | —          | redirects to `/customer/`                                    |
| `/customer/`  | customers  | open tickets, check status (number + email), reply           |
| `/op/`        | operators  | dashboard, ticket handling, replies, internal notes          |
| `/op/config/` | admins     | configuration: general settings, departments, operators      |

`/op/` is protected on **three levels**: IP filter in the web server, application IP filter (`op_allowed_ips`), username/password login.

## Layout

```
bhelpdesk/
├── public/                 ← web server document root (the only exposed folder)
│   ├── index.php           redirect to /customer/
│   ├── customer/index.php  customers front controller
│   ├── op/index.php        operators front controller
│   ├── .htaccess, op/.htaccess   Apache rules (ignored by nginx)
│   └── assets/style.css
├── lang/                   translations: en.php (base) + one file per language
├── src/                    application code (namespace Bhelpdesk)
│   ├── bootstrap.php, helpers.php
│   ├── Db.php, Router.php, View.php, Session.php, Csrf.php, Lang.php
│   ├── Auth.php            operator login
│   ├── IpGuard.php         IPv4/IPv6 CIDR matching, X-Forwarded-For handling
│   ├── Throttle.php        rate limiting (login, ticket creation, status lookup)
│   ├── Tickets.php         ticket logic
│   ├── Attachments.php     uploads: validation, storage, protected download
│   ├── Captcha.php         self-hosted image captcha (GD)
│   ├── Settings.php        settings editable from /op/config (stored in DB)
│   ├── Departments.php, Operators.php
├── templates/{customer,op}/
├── config/config.example.php
├── sql/schema.sql, sql/migrations/
├── bin/create-operator.php, bin/check-translations.php
├── storage/                uploaded attachments (outside public/, not in git)
└── deploy/                 nginx, IP allow-list snippet, php-fpm pool examples
```

## Installation (Debian/Ubuntu, nginx)

```bash
# 1. packages
apt install nginx mariadb-server php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-gd fonts-dejavu-core git

# 2. user and code
useradd --system --home /var/www/bhelpdesk --shell /usr/sbin/nologin bhelpdesk
git clone https://github.com/MartinMcFlyRN/Bhelpdesk.git /var/www/bhelpdesk
cd /var/www/bhelpdesk

# 3. database
mariadb -e "CREATE DATABASE bhelpdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
            CREATE USER 'bhelpdesk'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';
            GRANT SELECT, INSERT, UPDATE, DELETE ON bhelpdesk.* TO 'bhelpdesk'@'localhost';"
mariadb bhelpdesk < sql/schema.sql

# 4. configuration
cp config/config.example.php config/config.php   # then edit it

# 5. permissions: code owned by root, readable by the bhelpdesk group (FPM pool user)
chown -R root:bhelpdesk /var/www/bhelpdesk
chmod -R u=rwX,g=rX,o= /var/www/bhelpdesk
chmod o+x /var/www/bhelpdesk && chmod -R o+rX /var/www/bhelpdesk/public
chmod 640 config/config.php
# storage/ (attachments) is the only folder PHP writes to
chown -R bhelpdesk:bhelpdesk storage && chmod -R u=rwX,g=rX,o= storage

# 6. php-fpm
mkdir -p /var/lib/php/sessions-bhelpdesk /var/log/php
chown bhelpdesk:bhelpdesk /var/lib/php/sessions-bhelpdesk
cp deploy/php-fpm-bhelpdesk.conf /etc/php/8.3/fpm/pool.d/bhelpdesk.conf
systemctl reload php8.3-fpm

# 7. nginx
cp deploy/bhelpdesk-op-allow.conf /etc/nginx/snippets/   # put your IPs here
cp deploy/nginx-bhelpdesk.conf /etc/nginx/sites-available/bhelpdesk.conf
ln -s ../sites-available/bhelpdesk.conf /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# 8. first administrator
sudo -u bhelpdesk php /var/www/bhelpdesk/bin/create-operator.php --role=admin
```

### Updating

```bash
cd /var/www/bhelpdesk
git pull --ff-only
chown -R root:bhelpdesk . && chmod -R u=rwX,g=rX,o= . && chmod o+x . && chmod -R o+rX public && chmod 640 config/config.php
chown -R bhelpdesk:bhelpdesk storage
systemctl reload php8.3-fpm     # clears opcache
```

Apply any new file in `sql/migrations/` after pulling (they are idempotent):

```bash
mariadb bhelpdesk < sql/migrations/001_customer_language.sql
mariadb bhelpdesk < sql/migrations/002_attachments.sql
mariadb bhelpdesk < sql/migrations/003_settings.sql
```

## Configuration area (/op/config)

Reserved to operators with the **Administrator** role (others get 403; the link is not shown to them). It lives under `/op`, so it is covered by the same IP filters and login.

- **General** — application name, public URL, time zone, default/offered languages, ticket reopening, captcha, attachment limits (with a warning when they exceed the PHP limits of the server), operator session timeout, rate limits. Saved in the `settings` table, they override `config/config.php`. Database, allowed IPs, trusted proxies, cookie and storage settings stay in `config.php` only and are shown read-only.
- **Departments** — create, rename, reorder, activate/deactivate. A department used by tickets cannot be deleted (deactivate it: it disappears from the customer form but stays on existing tickets).
- **Operators** — create, edit name/email/role, reset password, activate/deactivate. Safeguards: you cannot remove your own admin role or disable yourself, and at least one active administrator always remains.

If the `settings` table does not exist yet (migration 003 not applied) the app keeps working with `config.php` values only.

## Apache (testing or hosting without nginx)

Requires `mod_rewrite` and `AllowOverride All` on the project folder. PHP can run through PHP-FPM (`proxy_fcgi`) or mod_php.

- **Document root = `public/`** (recommended): `public/.htaccess` (redirects, front controllers, blocks every other `.php`) and `public/op/.htaccess` (IP filter) are used.
- **Document root = project folder** (e.g. hosting where the root cannot be changed): the root `.htaccess` blocks `src/`, `config/`, `sql/`, `templates/`, `bin/`, `deploy/` and dotfiles, and routes everything else into `public/`.

Allowed IPs for `/op` on Apache: edit the `Require ip` line in `public/op/.htaccess` (single IPs, CIDRs, IPv6, space separated).

For plain-HTTP tests set `'cookie_secure' => false` in `config.php`, otherwise the browser does not send the session cookie back and forms say "Session expired".

## IP filter on /op

- **Web server** (nginx `snippets/bhelpdesk-op-allow.conf`, Apache `public/op/.htaccess`): blocks before PHP runs. This is the main filter.
- **Application** (`op_allowed_ips` in `config.php`): second level, in case the vhost is changed by mistake. Single IPs and IPv4/IPv6 CIDRs. Empty array = disabled.
- **Behind a proxy/CDN**: the web server sees the proxy IP. Use `set_real_ip_from` + `real_ip_header` (nginx realip module) and fill `trusted_proxies` in `config.php`: only then is `X-Forwarded-For` taken into account.

## Translations

Every text shown on screen (pages, validation errors, flash messages, ticket history, CLI) comes from `lang/`:

- `lang/en.php` — **base language** and reference: every key must exist here. Missing keys in other languages fall back to English.
- `lang/it.php` — Italian.
- New language: copy `en.php` to `lang/<code>.php` (`de.php`, `fr.php`, `pt_BR.php`…), translate the values, keep keys and `:placeholders` unchanged. It appears in the language selector automatically.

Check a translation (missing keys, untranslated strings, wrong placeholders):

```bash
php bin/check-translations.php        # all languages
php bin/check-translations.php de     # one language
```

How the language is chosen: selector in the header (`?lang=xx`, stored in a cookie for one year) → browser `Accept-Language` → `default_language` in `config.php`. `enabled_languages` limits which languages are offered.

In code and templates:

```php
__('lookup.not_found')                      // plain text
t('ticket.opened_on', ['date' => $d])       // translated + HTML-escaped (templates)
flash('success', 'ticket.reply_sent')       // flash messages take a key
```

Ticket history events (status/priority/department/assignee changes, reopen) are stored as language-neutral JSON and rendered in the reader's language. The customer's language is saved (`customers.language`) for future email notifications.

Not translated by design: data entered by users, such as department names, ticket subjects and messages.

## Attachments and captcha (new ticket form)

**Attachments**: customers can attach images (JPG, PNG, GIF, WEBP) and PDF files when opening a ticket. Default limits: 5 files, 5 MB each, 20 MB in total (`attachments` in `config.php`).

- The type is detected from the file **content** (`finfo` + `getimagesize` / `%PDF-` header); the extension must match it. SVG, HTML, Office files etc. are rejected.
- Files are saved in `storage/attachments/YYYY/MM/` with a random name and no extension, outside the document root. Only PHP writes there (`bhelpdesk` user).
- Downloads go through PHP with a permission check: customers only see files of their own ticket, operators see everything. Responses carry `nosniff` and a sandbox CSP; images open inline, PDFs are downloaded.
- PHP and web-server limits must be higher than the app limits: see `deploy/php-fpm-bhelpdesk.conf` (`upload_max_filesize`, `post_max_size`, `max_file_uploads`) and `client_max_body_size` in the nginx vhost. On Apache with mod_php set them in `php.ini`. If a request exceeds `post_max_size` the user sees "Upload too large".

**Captcha**: self-hosted image captcha generated with PHP GD, no external service and no data sent to third parties. Single-use, expires after 15 minutes, case-insensitive, look-alike characters (0/O/Q, 1/I/L, 2/Z, 5/S, 8/B, 6/G, U/V) excluded. A "New code" button reloads the image (small JS in `assets/app.js`, the form also works without JS). Disable with `'captcha' => ['enabled' => false]`. For a more readable image install `fonts-dejavu-core` (the pool's `open_basedir` already includes `/usr/share/fonts/`).

## Ticket flow

Statuses: **Open** → (operator reply) **Answered** → (customer reply) **Open** … → **Closed**. There is also **On hold**.

- The customer opens a ticket and gets a 6-digit number plus an access link with a token.
- To see it again: number + email, or the link.
- A customer reply on a closed ticket reopens it (`allow_reopen`).
- Operators also see internal notes and the system history.
- On the first public reply an unassigned ticket is assigned to the replying operator.

## Security in place

CSRF on every POST · separate sessions per area (cookie `BHD_CUST` path `/customer/`, `BHD_OP` path `/op/`), HttpOnly, Secure, SameSite · `session_regenerate_id` on login · operator idle timeout · `password_hash` with automatic rehash · rate limits on login, ticket creation, status lookup · anti-bot honeypot · prepared statements everywhere · output escaping · strict CSP · only `public/` exposed, only the front controllers executable.

## Roadmap

1. Email notifications (new ticket, replies) via SMTP, in the customer's language
2. Attachments on replies (customer and operator)
3. Tickets from email (piping/IMAP)
4. Canned responses, SLA/due dates
5. TOTP 2FA for operators
6. Customer accounts with ticket history
