# Fuel History

A self-hosted fuel-tracking app for a household/small team's cars, built on
Laravel + [Filament](https://filamentphp.com) (admin-panel framework), with
PostgreSQL.

- Admins manage the car list (add/edit/archive — cars are never hard-deleted).
- Any logged-in user (admin or regular) can log fuel fill-ups against any car.
- Per-car fuel history table shows computed comparison columns: price/litre,
  km since the previous fill, and L/100km — both an "accurate" figure from
  full-tank-to-full-tank stretches and a separate trip-computer-based
  estimate for partial fills, visually distinguished from each other.
- Analytics (spend, volume, efficiency) per car and summed across all cars,
  bucketed by month/quarter/year.
- Login requires password + **mandatory TOTP two-factor authentication**,
  enforced from first login via Filament's built-in multi-factor auth
  (works with Bitwarden's built-in authenticator, Google Authenticator, or
  any TOTP app) — includes recovery codes.
- **MCP server** for AI clients (Claude, Cursor, etc.) to add/edit/delete/
  list fuel entries directly — see "AI client access (MCP)" below.

## Local development (DDEV)

Requires [DDEV](https://ddev.com) and Docker.

```bash
ddev start
ddev composer install
cp .env.example .env
ddev artisan key:generate
ddev artisan migrate
```

Optionally bootstrap an admin account on first `db:seed` by setting
`SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` in `.env` first:

```bash
ddev artisan db:seed
```

Or create/promote an admin interactively at any time (requires a real
terminal — piped/non-interactive input isn't supported by Laravel Prompts):

```bash
ddev artisan users:make-admin
```

If the email already exists it offers to promote that user to admin
instead of creating a new one. Both of these bootstrap a **root** admin
(see "User management" below) — use the panel for everyday user creation
once at least one root admin exists.

Then visit the panel (`ddev launch /admin`) and log in — you'll be forced
through TOTP enrollment (scan the QR code with an authenticator app) before
you can do anything else.

Run tests:

```bash
ddev artisan test
```

Fix code style:

```bash
ddev exec vendor/bin/pint
```

## AI client access (MCP)

The app exposes an [MCP](https://modelcontextprotocol.io) server at
`/mcp/fuel-history`, authenticated with a Laravel Sanctum bearer token.

1. In the panel, go to **API Access** (left nav), click **Generate token**,
   and give it a name (e.g. "Claude Desktop"). The plaintext token is shown
   **once**, in a notification that stays open — copy it immediately, it
   cannot be retrieved again (Sanctum only ever stores its hash).
2. Add a remote MCP server to your client's config, pointing at
   `https://<your-domain>/mcp/fuel-history` with header
   `Authorization: Bearer <token>`. Both Claude and Cursor support remote
   MCP servers with a custom header this way; Gemini's support for that is
   less certain as of writing.
3. Tools available: `list-cars`, `list-fuel-entries`, `add-fuel-entry`,
   `edit-fuel-entry`, `delete-fuel-entry`. Editing/deleting is restricted to
   the entry's owner or an admin — the same `FuelEntryPolicy` the Filament
   panel itself uses, not separate logic.

Revoke a token any time from the same **API Access** page.

## User management

Admins can create both regular users and other admins from the **Users**
page in the panel. Every panel-created user is owned by the admin who
created them (`created_by`), which anchors a management hierarchy:

- An admin can edit/delete anyone they created, directly or transitively
  (their whole "subtree") — but never their own creator or anyone above
  that creator in the chain, and never another admin's subtree.
- **Root admins** — accounts with no creator, i.e. bootstrapped via
  `users:make-admin` or `db:seed` — can never be edited or deleted by
  anyone from the panel, including other root admins.
- Root admins can additionally manage **unowned regular users** (existing
  accounts predating this feature, or any regular user with no creator).
  Nested (non-root) admins cannot.
- Deleting a manageable user reparents their children to that user's own
  creator, so removing someone mid-chain never accidentally promotes a
  descendant admin to root. A user with any logged fuel entries can't be
  deleted at all (`fuel_entries.user_id` is `RESTRICT ON DELETE`).

This hierarchy only governs **user** CRUD — every admin, root or nested,
has the same full (non-hierarchical) admin powers over cars and fuel
entries. See `App\Models\User::manages()` and `App\Policies\UserPolicy`
for the exact rules.

Every user (admin or regular) can edit their own name, email, and
password from **Profile** in the topbar user menu — Filament's built-in
profile page, requiring the current password to confirm any change.

## Architecture notes

- **Database:** PostgreSQL only (not SQLite) — the test suite uses an
  in-memory SQLite connection for speed (see `phpunit.xml`), so any
  Postgres-specific SQL (e.g. the `CHECK` constraints in the `fuel_entries`
  migration) is guarded to only run against the `pgsql` driver.
- **Virtual columns / analytics:** `App\Services\FuelStretchService` is a
  pure, DB-free PHP forward pass over a car's chronologically ordered fuel
  entries — the single source of truth for what counts as a closed
  "full tank to full tank" stretch. `App\Services\AnalyticsService` reuses
  it for period bucketing, so the fuel-history table and analytics can
  never disagree with each other. See the extensive unit test suites for
  both (`tests/Unit/FuelStretchServiceTest.php`,
  `tests/Unit/AnalyticsServiceTest.php`) for the exact behavior contract.
- **Authorization:** `CarPolicy` (admin-only mutation), `FuelEntryPolicy`
  (owner-or-admin edit/delete, any authenticated user can create), and
  `UserPolicy` (admin-only view/create, hierarchy-gated edit/delete — see
  "User management" above) — Filament Resources pick these up
  automatically via Laravel's policy auto-discovery.

## Deployment

Runs natively, no Docker required in production — PHP-FPM + a reverse
proxy + PostgreSQL, with no Node/npm build step (Filament ships its own
pre-built assets). See [`deploy/README.md`](deploy/README.md) for the full
setup (config templates for PHP-FPM and Caddy, and the deploy script).
