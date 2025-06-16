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

---

## Pragmatic Frontend Testing Strategy

> **"Test business logic, skip the obvious, trust E2E for the rest."**

### What to Test (High Priority)

**Composables** - These contain the most critical business logic:

- Complex business logic (e.g., permission checking with wildcards like `usePermissions`)
- State management with external integrations (localStorage, APIs)
- Data transformation and processing utilities
- Custom composables with business rules

**Business Logic Components** - Components with computed properties, conditional logic, or data transformation:

- Components with computed properties that transform data
- Components with conditional rendering based on business rules
- Form components with validation or complex state management
- Components that process props before display

**Utility Functions**:

- Pure functions with business logic
- Data transformation utilities
- Helper functions used across multiple components

### What to Test (Medium Priority)

**Display Components with Logic**:

- Components with conditional rendering based on props
- Components that process or format data for display
- Modal and form components with state management
- Components with interactive behavior beyond simple display

**Layout Components**:

- Components with variant props or conditional layouts
- Components that manage application state
- Navigation components with dynamic behavior

### What NOT to Test (Skip Entirely)

**Shadcn/UI Components** - These are mostly styling wrappers:

- All `components/ui/*` components (these are styling wrappers around Radix Vue)
- Simple wrapper components around third-party UI libraries
- Components that only apply CSS classes without logic

**Simple Display Components**:

- Components that only display props without transformation
- Pure presentational components without logic
- Basic slot wrapper components

**Page Components** (Leave to E2E):

- Full page components (test integration via E2E)
- Components heavily dependent on Inertia page props
- Complex multi-component interactions

### Test Coverage Goals

- **Composables**: 100% line coverage (they contain core business logic)
- **Business Logic Components**: 80-90% line coverage
- **Display Components**: 60-70% line coverage (focus on conditional logic)
- **Overall Frontend**: ~70% line coverage (quality over quantity)

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

| Type                     | Test What                         | Priority | Coverage Goal | Folder                       |
| ------------------------ | --------------------------------- | -------- | ------------- | ---------------------------- |
| **Composables**          | Business logic, state management  | High     | 100%          | `tests/Frontend/Composables` |
| **Business Components**  | Computed props, conditional logic | High     | 80-90%        | `tests/Frontend/Components`  |
| **Utils**                | Pure functions, transformations   | High     | 100%          | `tests/Frontend/Utils`       |
| **Display Components**   | Conditional rendering             | Medium   | 60-70%        | `tests/Frontend/Components`  |
| **Shadcn/UI Components** | Skip entirely                     | None     | 0%            | Skip                         |
| **Page Components**      | Leave to E2E                      | Low      | E2E only      | `tests/e2e`                  |

**Testing Philosophy**: Test business logic thoroughly, skip obvious styling components, trust E2E for complex integrations.
| E2E Test | No | No | Full User Journey | `tests/e2e` |

**Testing Philosophy**: Test business logic thoroughly, skip obvious styling components, trust E2E for complex integrations.

> **Key Rule:** Always test business logic. Choose isolation level based on practicality, not ideology. Focus testing effort where bugs would cause the most damage to users and business.
