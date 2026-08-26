# Native deployment (no Docker)

This app has no Dockerfile or docker-compose setup, and doesn't need one:
it's plain PHP-FPM + a reverse proxy + PostgreSQL, and (per this cleanup)
has no Node/npm build step either -- Filament ships its own pre-built
frontend assets. That makes it a good fit for a Debian LXC running native
systemd services rather than a VM running Docker.

This directory has the app-specific config templates and the deploy
script. Host provisioning (installing PHP-FPM/Postgres/Caddy packages,
creating the app's system user, firewall rules) is out of scope here --
that's infrastructure-as-code territory, not something this app's repo
should own.

## What the host needs

- **PHP 8.3+** with extensions: `ctype`, `filter`, `hash`, `mbstring`,
  `openssl`, `pdo_pgsql`, `session`, `tokenizer`. (Check
  `composer.json`/`composer.lock` if this list drifts.)
- **PostgreSQL** (any recent version) with a database and role for this
  app -- matches `DB_CONNECTION=pgsql` in `.env.example`. Peer/local auth
  on the same host is fine; the app doesn't need Postgres reachable from
  outside the box.
- **A reverse proxy in front of PHP-FPM.** `Caddyfile.example` assumes
  Caddy (automatic HTTPS, minimal config), but any `php_fastcgi`-capable
  proxy works the same way.
- **Composer** (to run `deploy.sh`). Node/npm are deliberately **not**
  required anywhere in this deploy.

## First deploy

1. Create a dedicated system user for the app (e.g. `fuel-history`) and
   check out this repo somewhere under its home, e.g. `/opt/fuel-history`.
2. `cp .env.example .env`, then fill in real values: `APP_ENV=production`,
   `APP_DEBUG=false`, `APP_URL` (matching the domain in your Caddy
   config), and the real `DB_*` credentials. Leave `SEED_ADMIN_EMAIL`/
   `SEED_ADMIN_PASSWORD` blank -- `php artisan users:make-admin` (see the
   main README) is the supported way to create the first admin, since it
   needs a real TTY and won't silently create a half-configured account.
3. `php artisan key:generate` (writes `APP_KEY` into `.env`).
4. Install `php-fpm-pool.conf.example` as a pool config for the app's PHP-
   FPM install, and `Caddyfile.example` as a Caddy site -- adjust the
   paths/domain in both first, and make sure the socket path in each
   matches the other.
5. Run `deploy/deploy.sh` from the app root. It installs Composer
   dependencies (production-only, optimized autoloader), runs migrations,
   and warms Laravel's + Filament's caches.
6. Restart/reload PHP-FPM so it picks up the new pool config, and reload
   Caddy so it picks up the new site block.
7. Visit the app and run `php artisan users:make-admin` to create the
   first admin account.

## Subsequent deploys

Pull the new code into place (however the host does that -- `git pull`,
an Ansible `deploy_git_app`-style task, etc.), then re-run
`deploy/deploy.sh` and restart PHP-FPM. Nothing else in this directory
needs to change unless the domain or paths change.

## Scheduled tasks

`routes/console.php` defines daily database backup jobs (`backup:run`,
`backup:clean`, `backup:monitor` -- see `config/backup.php`) via Laravel's
scheduler. The scheduler itself does nothing unless something invokes
`php artisan schedule:run` every minute -- that trigger has to be set up
separately on the host, since Laravel doesn't run it on its own.

Matching this app's systemd-native deploy (no cron), install
`fuel-history-schedule.service.example` and
`fuel-history-schedule.timer.example` as systemd units:

1. Adjust the paths and user in both files to match how the app was
   actually provisioned (same as `php-fpm-pool.conf.example`).
2. Copy both into `/etc/systemd/system/` (drop the `.example` suffix).
3. `systemctl daemon-reload`
4. `systemctl enable --now fuel-history-schedule.timer`
5. Verify with `systemctl list-timers | grep fuel-history` and, after the
   next minute tick, `journalctl -u fuel-history-schedule.service`.

The backup destination is Cloudflare R2 via the `s3` disk; make sure
`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, and
`AWS_ENDPOINT` are set to the R2 bucket's credentials in the server's
`.env` (see `.env.example`).

## What's deliberately not here

- **A queue worker service.** `.env.example` sets
  `QUEUE_CONNECTION=database` (Laravel's own scaffold default). The
  Filament "Create Backup" button (see `admin/backups`) does dispatch a
  `ShouldQueue` job, but the panel plugin is configured to run it on the
  `sync` connection (`AdminPanelProvider::panel()`), so it executes
  inline during the request instead of needing a worker. If a real
  queued job gets added later, add a `queue:work` systemd unit then.
- **Anything host-specific** (exact paths, domain, system usernames,
  PHP-FPM socket location, firewall rules). Those belong in whatever
  infrastructure-as-code project actually provisions the host -- this
  directory only carries the app's half of the contract.
