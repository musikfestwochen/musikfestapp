# Dockerized Dev Runtime for Composer, Pest, and Mutation Tests

Goal: Run all Composer scripts (including `precommit`) and Pest (incl. mutation testing) inside a Linux Docker container from PhpStorm on Windows, mirroring CI.

Detected from this repo:
- PHP: ^8.4 (composer.json), CI uses 8.4 with Xdebug
- Node: 22 (CI)
- Coverage driver: Xdebug (preferred single driver)
- DB: SQLite locally (`database/database.sqlite`)

Paths in container: /var/www/html (POSIX mapping)

## 1) Build the dev container

Prereq: Docker Desktop running.

- Build image:
  docker compose build

- Quick check (container shell):
  docker compose run --rm app php -v
  docker compose run --rm app php --ri xdebug
  docker compose run --rm app php -m | grep -i sqlite

Expectations:
- PHP 8.4.x
- Xdebug section present, mode: coverage,develop
- sqlite3 and pdo_sqlite in module list

## 2) Install dependencies inside container

- Composer (with cache volume):
  docker compose run --rm app composer install --no-interaction

- Node deps (Node 22 is available inside app container):
  docker compose run --rm app npm ci

- Tooling checks:
  docker compose run --rm app composer validate --strict
  docker compose run --rm app vendor/bin/pest --version

## 3) App bootstrap (SQLite)

- Ensure env and DB file:
  copy .env.local .env   # on Windows PowerShell
  New-Item -ItemType File database/database.sqlite -Force | Out-Null

- Laravel init:
  docker compose run --rm app php artisan key:generate
  docker compose run --rm app php artisan migrate
  docker compose run --rm app php artisan about

## 4) PhpStorm configuration (Docker Interpreter)

- Settings ▸ PHP ▸ CLI Interpreter ▸ + ▸ Docker ▸ Docker Compose ▸ Service: app
  - Lifetime: Always start a new container
  - Path mappings:
    - $PROJECT_DIR$ → /var/www/html

- Settings ▸ Tools ▸ Composer
  - Execution: Remote
  - PHP interpreter: the Docker one created above

- Settings ▸ PHP ▸ Test Frameworks
  - Add Pest by Remote Interpreter
  - Interpreter: the Docker one
  - Default configuration file: $PROJECT_DIR$/phpunit.xml (Pest reads it)

Verification from PhpStorm (Run Anything):
- php -v    → shows PHP 8.4 from /usr/local/bin/php inside container
- composer -V → shows Composer 2.x
- vendor/bin/pest --version → shows Pest 3.x

## 5) Run scripts (parity checks)

- Precommit:
  From PhpStorm (Composer tool window) run script: precommit
  or Run Anything: composer precommit

- Mutation tests:
  From PhpStorm Run Anything: ./vendor/bin/pest --mutate --parallel --covered-only --min=100 --ignore-min-score-on-zero-mutations
  (or composer test:mutation)

Ensure: mutation score is non-zero; repeated runs are consistent.

## 6) Optional: Makefile helpers

Provided at scripts/docker/Makefile:
- make -f scripts/docker/Makefile precommit
- make -f scripts/docker/Makefile test
- make -f scripts/docker/Makefile mutate
- make -f scripts/docker/Makefile artisan ARGS="migrate:fresh --seed"

These invoke docker compose run --rm app ... under the hood.

## Notes
- Only Xdebug is enabled as coverage driver.
- SQLite is the default DB. The .env template uses DB_CONNECTION=sqlite. DB file path: database/database.sqlite.
- The PHP container also has Node 22 installed to support npm commands inside Composer scripts.
