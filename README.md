[![Deploy Laravel App](https://github.com/musikfestwochen/musikfestapp/actions/workflows/deploy.yml/badge.svg)](https://github.com/musikfestwochen/musikfestapp/actions/workflows/deploy.yml) [![tests](https://github.com/musikfestwochen/musikfestapp/actions/workflows/tests.yml/badge.svg)](https://github.com/musikfestwochen/musikfestapp/actions/workflows/tests.yml) [![linter](https://github.com/musikfestwochen/musikfestapp/actions/workflows/lint.yml/badge.svg)](https://github.com/musikfestwochen/musikfestapp/actions/workflows/lint.yml)

# Musikfestapp

Laravel application for Winterthurer Musikfestwochen operations.

## Documentation

- Published docs (GitHub Pages): https://musikfestwochen.github.io/musikfestapp/
- Docs source: `docs/`
- Local docs build guide: `docs/README.md`

## Quick Start

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
```

## Quality Checks

- Tests: `composer test`
- Lint: `composer lint`
- Pre-commit suite: `composer precommit`

## Contributing

- Conventions: `CONVENTIONS.md`
- Testing standards: `TESTING_GUIDELINES.md`
- Development setup: `DEVELOPMENT_SETUP.md`
