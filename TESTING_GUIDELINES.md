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

## Frontend Testing Guidelines (Vue 3 + Inertia.js)

Apply the same principle of isolation vs. integration to frontend testing:

### Unit Testing Vue Components

Use **Vitest** with Vue Test Utils for component testing:

- **Component Logic Tests**

    - Props validation and reactivity
    - Computed properties and watchers
    - Event emissions and handling
    - Conditional rendering logic
    - Method behavior with various inputs

- **Isolation Requirements**
    - Mock Inertia.js dependencies (`router`, `page`, `form` helpers)
    - Mock external API calls and services
    - Test component behavior independent of parent/child components

**Unit Test Rules for Vue:**

- Focus on component's internal logic, not DOM interactions
- Use shallow mounting when testing component logic in isolation
- Mock all external dependencies (Inertia, stores, composables)

### Component Integration Tests

Test components that interact with framework features and other components:

- **Inertia Feature Integration**

    - Components using `usePage()`, `useForm()`, or `router` methods
    - Form submission flows with Inertia forms
    - Page component props and shared data handling
    - Navigation and link behavior

- **Multi-Component Interactions**
    - Parent-child component communication
    - Event bubbling and prop drilling
    - Slot content and dynamic component rendering

**Integration Test Rules for Vue:**

- Test with minimal mocking of Vue/Inertia features
- Use full mounting when testing component interactions
- Verify actual DOM output and user interactions

### End-to-End Tests (Playwright)

Full browser-based testing for critical user journeys:

- Complete authentication flows
- Multi-step form processes
- Navigation between pages
- Real user interactions (clicks, typing, file uploads)

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
├── frontend/           # Frontend test utilities and setup
├── e2e/                # Full-stack browser tests
└── TestCase.php

resources/js/
├── Components/
│   └── __tests__/      # Component unit tests
├── Pages/
│   └── __tests__/      # Page component tests
├── Composables/
│   └── __tests__/      # Vue composables tests
└── Utils/
    └── __tests__/      # Utility function tests
```

---

## Naming and Style Conventions

### PHP Tests

- **Files:** `*Test.php`
- **Methods:** Use `test()` or `it()` syntax (PestPHP)
- **Use `covers()`** for all unit tests

### Frontend Tests

- **Files:** `*.test.js` or `*.spec.js`
- **Structure:** Use `describe()` and `it()` blocks
- **Component Tests:** Match component file names (e.g., `UserCard.vue` → `UserCard.test.js`)
- **Page Tests:** Match page file names (e.g., `Dashboard.vue` → `Dashboard.test.js`)

### Test Naming Patterns

- **PHP:** `it('should do something when condition')`
- **JavaScript:** `it('should do something when condition')`
- **Descriptive test names** that explain behavior, not implementation

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

### Backend (PHP) Testing

| Type             | Framework-Free | DB-Independent | Target Scope   | Folder              |
| ---------------- | -------------- | -------------- | -------------- | ------------------- |
| Unit Test        | Yes            | Yes            | Logic/Method   | `tests/Unit`        |
| Integration Test | No             | No             | Class/Service  | `tests/Integration` |
| Feature Test     | No             | No             | HTTP/User Flow | `tests/Feature`     |

### Frontend (Vue + Inertia) Testing

| Type                  | Inertia-Free | Component-Isolated | Target Scope        | Folder                     |
| --------------------- | ------------ | ------------------ | ------------------- | -------------------------- |
| Vue Unit Test         | Yes          | Yes                | Component Logic     | `resources/js/*/__tests__` |
| Component Integration | No           | No                 | Component + Inertia | `resources/js/*/__tests__` |
| E2E Test              | No           | No                 | Full User Journey   | `tests/e2e`                |

> **Key Rule:** Always test business logic. Choose isolation level based on practicality, not ideology.
