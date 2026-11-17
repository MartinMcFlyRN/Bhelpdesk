# Bhelpdesk

Ticketing system (osTicket-style) for **Linux + nginx + PHP 8.2+ + MariaDB**, no framework and no external dependencies. Base language: **English**, every on-screen text is translatable (see [Translations](#translations)).

One application, two areas:

| URL           | Who        | What                                                         |
|---------------|------------|--------------------------------------------------------------|
| `/`           | —          | redirects to `/customer/`                                    |
| `/customer/`  | customers  | open tickets, sign in, my tickets, reply; direct ticket links |
| `/op/`        | operators  | dashboard, ticket handling, replies, internal notes          |
| `/op/customers` | operators | customer registry: list, search, create, edit, ticket history |
| `/op/profile` | operators  | own profile: name, email, password                           |
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
│   ├── Html.php            whitelist HTML sanitizer for editor content
│   ├── Media.php           images inserted with the editor (public, random names)
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
apt install nginx mariadb-server php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml fonts-dejavu-core git

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

# 8. first administrator (at the first login it sets up the two-step verification)
sudo -u bhelpdesk php /var/www/bhelpdesk/bin/create-operator.php --role=admin
# the server clock must be right for the 2FA codes
timedatectl set-ntp true

# 9. cron: download the POP3 mailboxes of the email identities every minute
cp /var/www/bhelpdesk/deploy/bhelpdesk.cron /etc/cron.d/bhelpdesk && chmod 644 /etc/cron.d/bhelpdesk
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
mariadb bhelpdesk < sql/migrations/004_phone.sql
mariadb bhelpdesk < sql/migrations/005_customers.sql
mariadb bhelpdesk < sql/migrations/006_ticket_customers.sql
mariadb bhelpdesk < sql/migrations/007_customer_access.sql
mariadb bhelpdesk < sql/migrations/008_canned_signatures.sql
mariadb bhelpdesk < sql/migrations/009_operator_departments.sql
mariadb bhelpdesk < sql/migrations/010_rich_text.sql
mariadb bhelpdesk < sql/migrations/011_merge.sql
mariadb bhelpdesk < sql/migrations/012_mail_identities.sql
mariadb bhelpdesk < sql/migrations/013_mail_messages.sql
mariadb bhelpdesk < sql/migrations/014_operator_alerts.sql
mariadb bhelpdesk < sql/migrations/015_email_templates.sql
mariadb bhelpdesk < sql/migrations/016_mail_source.sql
mariadb bhelpdesk < sql/migrations/017_tasks.sql
mariadb bhelpdesk < sql/migrations/018_task_ticket.sql
mariadb bhelpdesk < sql/migrations/019_invoices.sql
mariadb bhelpdesk < sql/migrations/020_invoice_ticket.sql
mariadb bhelpdesk < sql/migrations/021_quotes.sql
mariadb bhelpdesk < sql/migrations/022_billing_role.sql
mariadb bhelpdesk < sql/migrations/023_operator_2fa.sql
mariadb bhelpdesk < sql/migrations/024_operator_password_reset.sql
mariadb bhelpdesk < sql/migrations/025_email_blacklist.sql
mariadb bhelpdesk < sql/migrations/026_operator_all_departments.sql
mariadb bhelpdesk < sql/migrations/027_oncall.sql
mariadb bhelpdesk < sql/migrations/028_department_slack.sql
```

## Two-step verification (operators)

Mandatory for **every operator** (customers do not use it).

- After username and password the operator enters the 6-digit code of an authenticator app (Google Authenticator, Microsoft Authenticator, Authy, 1Password, Bitwarden: TOTP, RFC 6238, 30 seconds). Until then no page of the operators area is accessible.
- **First login** (and after a reset): the setup page shows a QR code (drawn in the browser with `assets/vendor/qrcode-generator-1.4.4`, MIT; the secret never leaves the server) and the key to type by hand; the operator confirms with a code and gets **10 recovery codes**, shown once (print / save), each usable once instead of the app code.
- **Trusted computer**: after the second factor the computer is remembered for **30 days** (cookie `BHD_OP_DEVICE`, path `/op/`, HttpOnly, only its sha256 in the DB); the checkbox can be unchecked on shared computers. Changing the password forgets the other computers.
- Codes accept ±30 s of clock drift; a code cannot be used twice; wrong codes are rate limited like the login (`throttle.op_2fa`). Secrets are encrypted with `storage/app.key`.
- **Forgot your password?** (operators sign in page, `/op/password`): username or email -> email (template *Password reset* in E-mail -> Template) with a single-use link valid 60 minutes, stored hashed; same answer whether the operator exists or not, one email every 2 minutes per operator, rate limit `throttle.op_password_mail`. The new password does not sign in: the operator signs in with it **and the second factor**; trusted computers are forgotten.
- **Profile**: status, recovery codes left, new recovery codes (with a current code), forget the other / all computers.
- **Configuration -> Operators -> edit**: an administrator can **reset** an operator's two-step verification (lost phone): it is set up again at the next login. From the command line (e.g. the only administrator lost the phone): `sudo -u bhelpdesk php8.3 bin/reset-2fa.php <username>`.

## Operators area

- **Top bar**: Tickets, My tickets, Customers on the left; language switch and the user menu on the right (avatar with initials and name). The menu has *Configuration* (administrators only), *Profile* and *Log out*. It is a native `<details>` element, so it works without JavaScript; with JS it also closes on outside click / Escape.
- **Profile** (`/op/profile`): every operator can change name, email and password. Changing email or password requires the current password; the session id is regenerated after a password change.
- **Customers** (`/op/customers`): every customer who opened a ticket is registered automatically (by email). Operators can search (name, company, email, phone), create customers manually and edit name, company, email, phone, language and internal notes. The customer page shows the ticket history; internal notes also appear in the ticket sidebar. When an existing customer opens a new ticket, the registry data curated by operators is not overwritten (only a missing phone is filled in). The email is the key customers use to check their tickets: changing it changes who can look them up.

## Customer access: accounts and direct links

**Accounts.** Customers see their tickets after signing in at `/customer/login` (email + password). Accounts are created automatically when a customer opens a ticket (or by operators), without a password:

- the first time they try to sign in they get an email with a link to **create the password** (also from *First access? / Forgot your password?*, `/customer/password`);
- password links are single-use, valid 120 minutes, stored hashed; requesting a reset gives the same answer whether the email exists or not;
- *My tickets* (`/customer/tickets`) lists the tickets where the customer is main or additional customer; signed-in customers open new tickets without typing name/email and without captcha;
- a password change signs out the customer's other sessions. Operators see the account status in the customer page and can send the create/reset email.

**Direct links.** When a ticket is opened a unique link `/customer/t/<token>` is generated (shown on the confirmation page; it will be sent by email with the notifications). It opens that ticket without signing in and expires **`customer_link_days` days (default 30, editable in /op/config/general) after the last reply** visible to the customer — every new reply extends it; internal notes do not. After that the page says *Link expired* and invites to sign in. Tokens are stored as SHA-256 hashes only. Removing a customer from a ticket revokes their links. Operators can create a new link for any customer of the ticket (🔗 in the Customers card) to send it by hand. Old `/customer/ticket/N?t=…` links keep working (migration 007 converts them).

**Email.** `Mailer` sends plain-text UTF-8 messages through the SMTP account of an **email identity** (see *E-mail identities* below). `mail.driver` in `config.php`: `'auto'` (default) uses the identity's SMTP and falls back to PHP `mail()` when there is no identity or SMTP server; `'smtp'` refuses to send without SMTP; `'mail'` always uses `mail()`; `'log'` sends nothing and appends messages to `storage/mail.log` (handy for testing).

## E-mail identities (/op/config/email)

The **E-mail** section of the configuration has three pages: **Account** (the identities below), **Template** (texts of the automatic emails) and **Log** (email received and sent).

An identity is an email address with:

- **SMTP** (sending): server, port, encryption (SSL/TLS, STARTTLS or none), username/password, certificate check. Leave the server empty to send with PHP `mail()`. Authentication uses AUTH PLAIN or LOGIN; the client is built in (no external libraries).
- **POP3** (receiving, optional): server, port, encryption, username/password, certificate check, and what to do with messages on the server after download: delete them, **keep the last N days** (older ones are deleted) or never delete them.
- a **department** (required): tickets created from the mail it downloads go to that department.

Each **department** chooses its **sending identity** (*Departments → Sending identity*); when left automatic it uses an identity receiving for that department, else the **identity for system emails**. That one (chosen at the bottom of the E-mail page) sends emails not tied to a department, such as password creation/reset; without it the *Default from* of General settings is used.

*Test SMTP* sends a test message, *Test POP3* signs in and counts the messages without downloading or deleting anything; both show the server dialogue. The password is never shown: the log gives its length and the first 6 hex chars of its SHA1, the same fingerprint Dovecot writes on *Password mismatch* (`printf '%s' 'the-password' | sha1sum` to compare), and warns when it starts or ends with spaces.

Passwords are encrypted (libsodium secretbox) with a key created automatically in **`storage/app.key`** (mode 0600). **Back it up together with the database**: without it saved passwords cannot be decrypted and must be entered again. The forms never show saved passwords: leave the field empty to keep them.

## Tickets from email and notifications

**Download.** `bin/fetch-mail.php` (cron every minute, `deploy/bhelpdesk.cron`, as the PHP-FPM user) downloads the POP3 mailbox of every active identity with POP3 enabled; *Download now* on the identity page does the same. A lock prevents two runs at once; at most 50 new messages per identity per run. Each message is recorded by its POP3 UIDL in `mail_messages`, so it is processed once; **E-mail -> Log** shows the messages received and sent in the last 60 days with their result (filters by account, direction, result, address/subject/ticket number). Older rows are deleted, except those still needed: messages still on the POP3 server (they prevent downloading them again) and messages of existing tickets (their Message-IDs route the replies).

- **New message** → new ticket in the identity's department, customer created from the sender (Reply-To for no-reply senders such as website forms). People in To/Cc become additional customers (setting; not operators, not helpdesk addresses).
- **Reply** → added to the ticket when `In-Reply-To`/`References` point to a message of the ticket, or when the subject contains `[#123456]` **and** the sender is already a customer of that ticket (a guessed number opens a new ticket instead). Senders matched by Message-ID but not on the ticket are added to it. Replies to closed tickets reopen them (or open a new ticket when reopening is disabled). The quoted conversation is removed (our `##- … -##` marker, "On … wrote:" / "Il … ha scritto:", Outlook headers, `>` lines, Gmail/Outlook HTML quotes).
- **Body**: the text part, or the HTML converted to text. **Attachments** pass the same checks as operator uploads (types, signature, size limits); small images embedded in the HTML (signature logos) are skipped; refused files are listed in the ticket history.
- **Ignored**: our own addresses (loop protection), `Auto-Submitted`, `Precedence: bulk/list/junk`, auto-reply headers, mailing lists (`List-Id`), bounces and delivery reports, no-reply senders, duplicates (same Message-ID), more than *N* new tickets per hour from one sender (setting, default 10), messages over 30 MB (`mail.max_message_mb`, left on the server).
- **Retention on the server**: delete after download, keep the last N days (messages older than N days since download are deleted), or never delete. Messages that failed with an error are left on the server.

**Source of the emails.** The original message of every downloaded email is saved compressed in `storage/mail/YYYY/MM/` (random name, `.eml.gz`). In the ticket the *✉ email* badge of a message received by email opens its source (headers and raw message, with *Download .eml*);  In *E-mail -> Log* administrators open the source of every downloaded message, ignored ones included. In the ticket the source is available to everyone who can see the ticket: the operators of its department, the assignee and the administrators. Sources of messages that did not become tickets or replies are deleted after 30 days; the others are deleted with their ticket. Messages downloaded before this feature have no source.

**Blacklist** (*Configuration -> Blacklist*): email addresses or whole domains (one per line; a domain also covers its subdomains). Their messages are downloaded and recorded in the log with the result *blacklist* (and the rule that matched, source available) but nothing else happens: no ticket, no reply, no notification. Each rule counts the messages blocked; in *E-mail -> Log* the 🚫 next to a received message adds its sender to the blacklist. The blacklist is checked before any other rule, on the sender and on the From address.

Test without POP3: `sudo -u bhelpdesk php8.3 bin/fetch-mail.php --file=/path/message.eml --identity=ID` (without a valid identity the command lists the existing ones). Use the same PHP version as PHP-FPM and the `bhelpdesk` user, so stored attachments get the right owner.

**Notifications** (General → Email can turn them off), sent with the department's sending identity in each customer's language, one message per customer with their own direct link:

- confirmation of a new ticket (web form, email, operator without a reply), `Auto-Submitted: auto-replied`;
- operator replies to all customers of the ticket: HTML + text, department signature, attachments up to 10 MB in total (the others are listed), relative image/link URLs made absolute with `base_url`.

Subjects carry `[#number]`; `Message-ID`, `In-Reply-To` and `References` keep the conversation threaded in mail clients and route replies back to the ticket. Send failures are shown to the operator and logged. Messages received by email have an *email* badge in the ticket.

## Email font

**Email font** in /op/config/general (Arial, Verdana, Tahoma, Trebuchet MS,
Segoe UI, Georgia) is used for every email sent: replies with the department
signature, notifications and alerts. Signatures and templates cannot carry
their own fonts or colours (the HTML is sanitized).

## Images in emails

Images uploaded with the editor (the logo of a department signature, images
pasted in a reply) are embedded in the email as inline parts (`cid:`), up to
3 MB per message: they show even when the mail client blocks remote images.
The department signature is added to replies and, with `{signature}`, to the
"ticket opened" email.

## Email templates (/op/config/email/templates)

The texts of all automatic emails are edited by administrators, with the same editor as signatures: ticket opened, operator reply, password creation and reset (customers); new ticket, customer reply, assignment, moved ticket (operator alerts). They are **not** in the translation files: every email is sent in a single version whatever the recipient's language, so the template contains all the languages needed (the defaults have Italian and English). Labels filled in by placeholders (status, priority) use the default language.

- Placeholders such as `{customer_name}`, `{ticket_number}`, `{ticket_link}`, `{message}`, `{signature}`; the list for each email is on its page (click to insert at the cursor). Links become clickable; `{message}`, `{signature}`, `{attachments}` and `{excerpt}` are HTML blocks.
- Keep `[#{ticket_number}]` in the customer subjects and the `##- … -##` line at the top of customer emails: they route and trim the replies by email.
- *Send me a test* sends the saved template with example data to the administrator; *Restore the default* deletes the customisation. Templates never saved use the built-in defaults (`src/EmailTemplates.php`).
- Emails are sent as HTML with a plain text version.

## Email alerts to operators

Each operator chooses in **their profile** which alerts to receive (texts: templates above):

- **new tickets** in their departments (administrators: all departments), also tickets **moved** to a department they could not see before;
- **customer replies** (web or email) on tickets assigned to them, and on unassigned tickets of their departments (when a ticket is assigned only the assignee is alerted);
- a ticket **assigned** to them.

Nobody is alerted for what they did themselves. Alerts contain the ticket data, an excerpt of the customer's message and the link to `/op/ticket/N`, sent with the department's identity (`Auto-Submitted: auto-generated`). They are sent **after the page has been delivered** (`fastcgi_finish_request`), so operators and customers do not wait for the SMTP server. They can be turned off for everyone in *General → Email*. Emails coming from an operator's address are not turned into tickets: operators answer from the ticket page.

## Slack notifications (/op/config/general)

Each department can post to its own Slack channel, in the format of the
osTicket Slack plugin:

- **New ticket** (web form, email or opened by an operator): green bar,
  "New Ticket #number created".
- **Customer reply** (web or email): amber bar, "Ticket #number updated".

Each message has the subject linked to the ticket, "Name (email) in
*Department*" and the text of the message in a code block (first 2500
characters). Texts use the default language of the system.

Setup: in Slack create an app with **Incoming Webhooks**, add a webhook for
the channel and paste its URL in the department row. The URL is stored
encrypted with `storage/app.key`; leave the field empty to keep it. The
**Test** button posts a test message; the last delivery (or its error) is
shown in the table. Messages are posted after the page has been delivered
(PHP curl extension, 10 s timeout); tickets without a department are not
posted. Table `department_slack`, migration `028_department_slack.sql`.

## Tasks

*Task* in the top menu (New task, All tasks, My tasks) manages internal to-dos for the operators.

- A task has a title, an optional description (rich text), the **day it becomes active** (optional time) and is assigned to **everyone**, to a **department** (all its operators) or to **one operator**.
- From that moment it appears in the **box under the menu**, on every page, for its operators (overdue tasks in red), with a counter next to *Task*. Clicking it opens the detail with **Done**: the task leaves the box of everybody and goes to the list as done (who and when). It can be reopened.
- **All tasks** shows every task to every operator, with assignee and state (scheduled, to do, overdue, done); **My tasks** only those assigned to me, to my departments or to everyone.
- The assignees and the administrators mark tasks done; the creator and the administrators edit or delete them.
- **Tasks of a ticket**: the *Tasks* box in the ticket sidebar lists the tasks linked to it and *+ New task* creates one already linked (back to the ticket after saving). The task shows the ticket (detail, list, box under the menu) and the ticket history records when a task is created and done. Merging tickets moves their tasks to the kept ticket; deleting a ticket keeps its tasks without the link.

## On-call rota (/op/oncall)

The **On call** menu item (operators, not the billing role) shows a monthly
calendar, Monday to Sunday, with arrows to move between months.

- Each week has one on-call operator, picked in the first column: it is shown
  on every day of that week.
- A single day can be given to another operator with ✎ on the day; that day
  is highlighted and overrides the week. ✕ (or "Same as the week") removes it.
- Every operator can see the rota; only administrators can change it.
- Next to the title of every operators page a box shows today's on-call
  operator (it links to the calendar).

Tables: `oncall_weeks` (Monday of the week -> operator) and `oncall_days`
(day -> operator), migration `027_oncall.sql`.

## Billing

*Billing* in the top menu (after Customers) has **Invoice** and **Quote**.

- An invoice has an automatic numeric **ID**, a **customer** chosen from the registry (search by name, company or email), a **text**, the **amount** (`120,50`, `1.234,56` and `1,234.56` are all accepted), the **day billing starts** and the **type**: one-off, monthly, every two months, yearly.
- **Invoice issued** switches it from *to issue* to *issued* (who and when are recorded); in the list the state is a button that switches it in one click. **Only operators with the *Billing* role** can change it: the others (administrators included) see the state read-only and the server refuses the change.
- The list has *To issue / Issued / All*, filter by type, search by customer, text or ID, the count and the total amount. For recurring invoices it shows the **next billing date** computed from the start date (end of month handled: 31 January monthly -> 28/29 February); invoices to issue whose date has come are highlighted.
- The customer page lists the customer's invoices with *+ New invoice*.
- **Invoices of a ticket**: the *Invoices* box in the ticket sidebar lists the linked invoices; *+ New invoice* opens the form with the ticket's main customer and the text `Ticket #number - subject`, linked to the ticket (back to the ticket after saving). The invoice shows the ticket in the form and in the list (searchable by ticket number); the ticket history records the new invoice. Merging tickets moves their invoices.
- Switching an invoice between *to issue* and *issued* (list button or checkbox of an existing invoice) asks for confirmation.
- **Quotes** work like invoices (automatic ID, customer from the registry, text, amount, list with search and total, creation from a ticket with the reference, *Quotes* box in the ticket and in the customer page, history event, merge, delete by creator/admins) without issued state, billing date and type.
- Every operator can use Billing; the creator, the administrators and the billing operators delete invoices and quotes.
- **Billing role** (*Configuration -> Operators*, role *Billing*): these operators reach **only** the Billing section (and their profile): the top menu shows only Billing, any other page sends them to the invoices, customers and tickets are shown without links, and they get no tasks or alerts. They are the only ones who mark invoices issued / to issue.

## Ticket list auto refresh

The ticket list has a **Refresh** selector (OFF, 5s, 10s, 30s), remembered in
the browser. The reload waits while the operator is typing in a field, has
tickets selected for a bulk action or has a menu open.

## Bulk actions in the ticket list

Select tickets with the checkboxes (or *select all*): an action bar appears with **Change status**, **Move**, **Merge** and **Delete**, each with a confirmation dialog. Only tickets the operator can see are affected, even with a forged request.

- **Change status** — Open, Answered, On hold or Closed for all selected tickets; each change is logged in the ticket history.
- **Move** — to any department (not only the operator's own). The change is logged in each ticket's history. If an agent moves a ticket out of their departments it leaves their list.
- **Merge** (2+ tickets) — choose the ticket to keep (the oldest is preselected). Messages, attachments, direct links and all customers (main and additional) of the other tickets move into it, in chronological order, so every customer sees the whole conversation; the history records which tickets were merged. The other tickets are closed, disappear from lists and keep only a redirect: their old numbers and links lead to the kept ticket (operators and customers).
- **Delete** — permanent, after ticking *Yes, delete them permanently*: messages, attachment files on disk, links and tickets merged into them are removed.

- **Assign**: assigns the selected tickets to an operator (or removes the
  assignment); the operator gets the usual "assigned to you" email. Tickets of
  departments the operator does not work on are skipped and listed.
- Assign, change status, move and merge have an optional **internal note**,
  added to every ticket after the action (merge: to the ticket that remains).
  Delete has none: deleted tickets are erased with their notes.

## New ticket opened by an operator

*New ticket* in the ticket list (or in a customer page, pre-filled) opens `/op/tickets/new`, for requests received by phone, email or in person:

1. **Customer**: type an email or part of a name/company to pick an existing customer, or enter email + name for a new one. **People in copy**: any number of additional customers (existing or new), added as additional customers of the ticket.
2. **Customer request** (required): subject and text, saved as the customer's first message (plain text), with optional files received from the customer.
3. **First reply** (optional): written with the editor, with attachments and the status after sending.

Department (only the operator's own departments; administrators see all), priority and "assign to me" are in the side panel. The history records that the ticket was opened by the operator. After creation the operator gets the customer's direct link to send by hand (until email notifications exist).

## Rich text editor and operator attachments

**Editor.** Operator replies, internal notes, department signatures and canned responses use a WYSIWYG editor ([Quill 2](https://quilljs.com), BSD-3, vendored in `public/assets/vendor/quill-2.0.3/` — no CDN, compatible with the CSP). Formatting: headings, bold/italic/underline/strike, lists and indentation, alignment, quotes, code blocks, links and **images**. Without JavaScript a plain textarea is shown and the text is stored as plain text.

- **Sanitized on the server** (`src/Html.php`, whitelist): only formatting tags survive; scripts, styles, event handlers, iframes/objects/SVG, `javascript:`/`data:` URLs and external images are removed. Applied when saving and again when displaying.
- **Images** (toolbar button, drag & drop, paste) are uploaded to `/op/media` (JPG/PNG/GIF/WEBP, max 5 MB, checked with `getimagesize`) and stored in `storage/media/` with a random 32-hex name. They are served publicly from `/customer/media/<name>` (needed by customers and, later, by email clients) with `nosniff` and a sandbox CSP.
- Customer messages stay plain text. Old signatures/canned responses are converted to HTML by migration 010.
- Canned responses are inserted into the editor at the cursor; signatures can contain a logo and are previewed under the reply form.

**Operator attachments** (replies and internal notes): JPG, PNG, GIF, WEBP, PDF, DOC, DOCX, XLS, XLSX, ZIP, TAR, GZ/TGZ (tar.gz), BZ2 (tar.bz2). Each file must have an allowed extension, a matching detected type (`finfo`) and the right signature (OLE, ZIP, ustar, gzip, bzip2, `%PDF-`, image check): a renamed PHP file or executable is rejected. Defaults: 10 files, 20 MB each, 50 MB per reply — editable in *General → Attachments*. Non-image files are always downloaded (never displayed inline). Attachments of internal notes are never available to customers. If a reply is rejected (e.g. wrong file type) the text written in the editor is kept.

> Larger uploads need higher PHP / web-server limits: `upload_max_filesize = 25M`, `post_max_size = 64M` in the FPM pool and `client_max_body_size 64m` in nginx (see `deploy/`).

## Departments and ticket visibility

- Visibility depends on the departments of the operator, not on the role: in */op/config/operators* each **administrator or agent** gets either **All departments** (also those created later) or a list of departments. They see the tickets of those departments plus any ticket assigned to them personally. Administrators limited to some departments still manage the whole configuration. (Migration 026 gives *All departments* to the existing administrators.)
- The rule is enforced on the server everywhere: dashboard list and counters, department filter, opening a ticket by URL (other tickets answer *not found*), replies and changes, attachment downloads, ticket history and counts in the customer pages.
- A ticket can only be assigned to operators who see its department (the *Assigned to* menu lists only them). An agent who moves a ticket to a department they do not handle is sent back to the list.
- Tickets without a department are visible only to operators with *All departments*.
- Upgrading: migration 009 gives existing agents all current departments, so nobody loses tickets; then narrow them down from the operator page.

## Customers on a ticket

Each ticket has a **main customer** and any number of **additional customers** (like CC / collaborators). From the *Customers* card in the ticket sidebar operators can:

- **Add a customer**: type an email or part of a name/company to get suggestions from the registry; if the email is new, also enter a name and the customer is created.
- **Remove** an additional customer (they lose access immediately, even with an open session).
- **Change the main customer**, optionally keeping the previous one as additional customer. The ticket access link (`?t=` token) is regenerated, because the old one was given to the previous main customer.

Every customer on the ticket sees it after signing in with **their own** account (or through a direct link created for them) and can reply; each message records which customer wrote it. Customers see the names (not the emails) of the other customers on the ticket. All changes are logged in the ticket history, and the customer page lists tickets where the customer is main or additional.

## Configuration area (/op/config)

Reserved to operators with the **Administrator** role (others get 403; the link is not shown to them). It lives under `/op`, so it is covered by the same IP filters and login.

- **General** — application name, public URL, time zone, default from address/name for emails, default/offered languages, ticket reopening, hide operator names from customers (they see "Staff Operator"), captcha, attachment limits (with a warning when they exceed the PHP limits of the server), operator session timeout, rate limits. Saved in the `settings` table, they override `config/config.php`. Database, allowed IPs, trusted proxies, cookie and storage settings stay in `config.php` only and are shown read-only.
- **General -> Footer** — rich text shown at the bottom of every page (company name, VAT number, contacts, links; `{year}` and `{app_name}` placeholders), separately enabled for the customer area and the operators area. Empty = no footer (the customer area shows the application name).
- **Departments** — create, rename, reorder, activate/deactivate, **email signature** (added at the end of reply emails for tickets of that department; operators see a preview under the reply form). A department used by tickets cannot be deleted (deactivate it: it disappears from the customer form but stays on existing tickets).
- **E-mail** — email identities with SMTP/POP3 accounts, the department receiving their mail and the identity for system emails (see *E-mail identities*).
- **Canned responses** — ready-made texts, for all departments or one; operators insert them from the *Insert canned response* menu of the reply form (at the cursor position). Deactivated responses are hidden.
- **Operators** — assign one or more **departments** to each agent (required for agents), create, edit name/email/role, reset password, activate/deactivate. Safeguards: you cannot remove your own admin role or disable yourself, and at least one active administrator always remains.

Canned responses and signatures support placeholders replaced with the ticket and operator data: `{customer_name}`, `{customer_email}`, `{ticket_number}`, `{ticket_subject}`, `{department_name}`, `{operator_name}`, `{operator_email}`, `{app_name}`.

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

- The customer opens a ticket and gets a 6-digit number plus a direct link.
- Optional phone number on the form (shown to operators with a `tel:` link, searchable from the dashboard).
- To see it again: sign in, or use the direct link (valid until `customer_link_days` after the last reply). In the conversation support replies are highlighted and aligned left, the customer's messages on the right.
- A customer reply on a closed ticket reopens it (`allow_reopen`).
- Operators also see internal notes and the system history.
- On the first public reply an unassigned ticket is assigned to the replying operator.

## Security in place

CSRF on every POST · separate sessions per area (cookie `BHD_CUST` path `/customer/`, `BHD_OP` path `/op/`), HttpOnly, Secure, SameSite · `session_regenerate_id` on login · operator idle timeout · `password_hash` with automatic rehash · rate limits on login, ticket creation, status lookup · anti-bot honeypot · prepared statements everywhere · output escaping · strict CSP · only `public/` exposed, only the front controllers executable.

## Roadmap

1. Attachments on customer replies from the web
2. IMAP (folders, IDLE) besides POP3
3. SLA/due dates
5. TOTP 2FA for operators
6. Customer accounts with ticket history
