---
name: dependency-upgrade
description: Upgrade dependencies with a risk-based workflow. batch minor updates and handle major updates with migration guides, incremental checks, and fast failure detection.
---

# Dependency Upgrade

## When to Use This Skill

Use when upgrading Composer or npm dependencies, especially when updates include both minor and major version bumps.

## Core Strategy

- Classify by risk first: patch/minor in batches, major in controlled steps.
- Keep lockstep ecosystems together (for example `vite` + `@vitejs/plugin-vue` + `laravel-vite-plugin`; `vitest` + related plugins).
- Start with the smallest high-signal checks and only expand after they pass.

## Minor and Patch Updates

- Upgrade low-risk dependencies in one batch.
- Run one focused verification pass per batch:
  - Frontend-heavy batch: `npm run build` and `npx vitest run --reporter=dot --silent=passed-only`
  - PHP-heavy batch: `composer test:sca:ai` and targeted `php artisan test --compact ...`
- If the batch passes, move on. Do not re-validate each package individually.

## Major Updates

For each major dependency (or tightly-coupled major group):

1. Find and read official migration docs and release notes first.
2. Extract required breaking-change actions before changing code.
3. Upgrade in small steps (version bump, required config/code migration, quick checks).
4. Continue only when the current step is green.

## Fast Failure Detection

- After each major step, run fast high-signal checks first:
  - Install/resolve (`npm install` / scoped `composer update`)
  - Static/type checks relevant to the changed stack
  - Build (`npm run build`) for frontend toolchain changes
  - Focused tests for touched areas
- Stop on first failure, fix root cause, and rerun the smallest failing check.
- Delay full-suite runs until step-level checks are stable.

## Validation Order

1. Dependency install/resolve
2. Static/lint/type checks
3. Build
4. Targeted tests
5. Full project gate (`composer precommit:ai`) near the end

## Tracking

- Track upgrades as checklists grouped by risk batch.
- Mark items complete only after that batch passes verification.
- Keep one source of truth; remove redundant planning sections.

## Practical Rules

- Do not mix unrelated high-risk majors into one change.
- Prefer one commit per coherent upgrade batch.
- Keep migration-driven code/config changes in the same batch as the dependency bump.
- If no migration guide exists, use changelogs and shrink step size.
