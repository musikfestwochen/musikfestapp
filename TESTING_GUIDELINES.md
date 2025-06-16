# Testing Guidelines for Laravel + Vue (Inertia.js) Application

This document outlines the refined testing strategy for our Laravel 12 + Vue 3 (Inertia.js) application using PHP 8.4.
It better distinguishes between unit, integration, and feature tests based on dependency context and emphasizes
pragmatic, context-sensitive test coverage.

## Unified Testing Philosophy

> **"Test what matters, isolate where necessary."**

The goal is to ensure critical business logic and integration paths are thoroughly verified without enforcing overly
rigid classifications. All tests should yield meaningful confidence and actionable failure signals.

---

## Testing Categories (PHP Backend)

### Unit Tests (Isolated, Fast)

Unit tests validate logic **without depending on framework features**, such as the database, filesystem, HTTP stack, or
queues.

**Require Unit Tests:**

- **Models** (`app/Models/*.php`)

    - Test: relationships, scopes, accessors/mutators, attributes
    - Test with in-memory database (treated as unit because Eloquent relationship logic is under test, not DB IO)

- **Form Requests** (`app/Http/Requests/*.php`)

    - Test: validation rules, authorization logic

- **Helpers** (`app/Helpers/*.php`)

    - Pure functions, transform logic

- **Services (Pure)**

    - Business logic that does NOT query models or call framework services
    - Test in `tests/Unit/Services/`

**Unit Test Rules:**

- 100% line and mutation coverage for logic-heavy code
- Mandatory `covers()` annotation
- Avoid mocking the framework; prefer pure input/output

---

### Integration Tests (Class-Level, Framework-Aware)

Integration tests validate the interaction of Laravel services with your own logic, typically on a per-class basis (
e.g., service class with Eloquent usage).

**Require Integration Tests:**

- **Services (Eloquent-Using)**

    - Any service that uses models, DB transactions, or Laravel services like caching
    - Test with real in-memory database or seeded test DB

- **Jobs / Events / Listeners**

    - Dispatching, queuing, effect verification

- **Helpers (Framework-Using)**

    - Helpers that call Laravel APIs (e.g., `Storage::disk()`)

**Integration Test Rules:**

- Test with minimal mocking
- Emulate real environment using factories and Laravel services
- Use `tests/Integration/` directory

---

### Feature Tests (User-Centric, Full Stack)

Feature tests simulate full application behavior, covering HTTP endpoints, routing, middleware, auth flows, and Inertia
pages.

Examples:

- HTTP response assertions
- JSON structure testing
- Auth/login flows
- Inertia component rendering (via assertions)

---

## Frontend Testing Guidelines

Use the same principle of isolation vs. integration:

- **Unit Tests (Vitest)**

    - Props, emitted events, local logic

- **Component Integration Tests**

    - Interactions with other components, stores, or Inertia

- **End-to-End Tests (Playwright)**

    - Full browser-based testing for key flows

---

## Directory Structure

```
tests/
├── Unit/
│   ├── Models/
│   ├── Requests/
│   ├── Services/       # Pure services
│   └── Helpers/        # Pure helpers
├── Integration/
│   ├── Services/       # DB-aware services
│   ├── Jobs/
│   ├── Listeners/
│   └── Helpers/        # Laravel-aware helpers
├── Feature/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Auth/
│   ├── Requests/
│   └── Views/
├── Architecture/
├── frontend/           # Vue + Inertia logic tests
├── e2e/                # Full-stack browser tests
└── TestCase.php
```

---

## Naming and Style Conventions

- **Files:** `*Test.php` or `*.spec.js`
- **PHP Tests:** Use `test()` or `it()` syntax
- **JavaScript Tests:** Use `describe()` and `it()`
- **Use `covers()`** for all unit tests

---

## Precommit Workflow

Run the full suite:

```bash
composer precommit
```

Order:

1. Lint, format, typo check
2. Static analysis (`PHPStan`, level 7)
3. Architecture tests
4. Unit + Integration + Feature + Frontend tests
5. Type coverage
6. Mutation tests (unit only)

---

## Summary

| Type             | Framework-Free | DB-Independent | Target Scope   | Folder              |
| ---------------- | -------------- | -------------- | -------------- | ------------------- |
| Unit Test        | Yes            | Yes            | Logic/Method   | `tests/Unit`        |
| Integration Test | No             | No             | Class/Service  | `tests/Integration` |
| Feature Test     | No             | No             | HTTP/User Flow | `tests/Feature`     |
| E2E Test         | No             | No             | Full-stack     | `tests/e2e`         |

> **Key Rule:** Always test business logic. Choose isolation level based on practicality, not ideology.
