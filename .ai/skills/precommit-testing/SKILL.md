---
name: precommit-testing
description: Run and interpret the AI-first precommit quality gate for this project, including when to run each sub-command and when to avoid rerunning the full suite.
---

# Precommit Testing

## When to use this skill
Use this skill when validating code quality before commit, especially when deciding between targeted checks and the full project gate.

## Goal
This project has a comprehensive precommit pipeline that validates PHP, frontend, test quality, mutation score, and e2e behavior.

Because it is intentionally broad and slow, do not run the full suite after every small edit. Run the narrowest useful command during development, then run `composer precommit:ai` once near the end.

## Full suites

- `composer precommit:ai`:
  - Canonical full gate for this project.
  - Includes Rector, typo checks, linting, static analysis, coverage, type coverage, mutation testing, frontend tests, and e2e tests.
  - Uses compact or quiet flags and writes bulky coverage text output to file where appropriate.
  - Default full run for agent-driven workflows.

## Individual commands and what they validate

- `composer rector` (or `composer rector:ai` if added later)
  - Performs automated PHP refactors and framework upgrades based on configured Rector rules.
  - Use after changing backend PHP structure or patterns.

- `composer typos` (or `composer typos:ai` if added later)
  - Runs misspelling checks via Peck.
  - Use after editing identifiers, strings, comments, or docs-like text in code.

- `composer lint:ai`
  - Runs Pint, ESLint, and Prettier with reduced-noise output.
  - Ensures consistent formatting and frontend lint correctness.

- `composer test:sca:ai`
  - Runs PHPStan static analysis.
  - Catches typing and API misuse issues quickly without executing runtime tests.

- `composer test:coverage:ai`
  - Runs Pest coverage with minimum threshold enforcement.
  - Validates test suite execution and line coverage quality targets.

- `composer test:types:ai`
  - Runs Pest type coverage checks.
  - Enforces typed code health (parameter and return type discipline).

- `composer test:mutation:ai`
  - Runs mutation testing with minimum score enforcement.
  - Verifies test effectiveness, not just test pass status.

- `composer test:e2e:ai`
  - Builds frontend, runs Playwright e2e suite, resets DB state.
  - Validates browser-level behavior and integration flows.

## Recommended execution strategy (fast feedback first)

1. During active coding, run only checks relevant to your change area (for example `composer test:sca:ai`, `npx vitest run --reporter=dot --silent=passed-only`, or `composer lint:ai`).
2. After feature completion, run `composer precommit:ai` once to validate the entire gate with compact output.
3. If `precommit:ai` fails, fix and rerun only the failed section until green.
4. Avoid rerunning long steps (`test:mutation:ai`, `test:e2e:ai`) unless related code changed.

## Practical guidance for agents

- Prefer `:ai` variants by default for routine autonomous validation.
- Avoid repeatedly rerunning long steps (`test:mutation`, `test:e2e`) unless touched code justifies it.
- If a broad run fails early in one section, avoid rerunning the whole suite immediately; rerun the failing command first.
- Treat `precommit:ai` as a release-quality gate, not an edit-loop command.

If a human specifically needs verbose output, the `:ai` suffix can be omitted.
