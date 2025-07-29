## Coding Agent Guidelines

**Project Stack:** Laravel + Inertia.js + Vue 3 + TypeScript + Tailwind CSS
**Domain:** Winterthurer Musikfestwochen management

---

### 1. Structure & Conventions

* **Follow**: Project conventions from `README.md`, `CONVENTIONS.md`, etc.
* **Modular by Domain:**

    * **Backend:**

        * Controllers, Services, Models, Enums: `app/{Layer}/{Module}`
        * Request validation/permissions: one request class per controller method
    * **Frontend:**

        * Pages: `resources/js/pages/{module}`
        * Components: `resources/js/components`, UI: `components/ui` (only use [shadcn-vue](https://www.shadcn-vue.com/)
          or components built from it)
        * Composables: `resources/js/composables` (+ tests)
    * **Routes:** `routes/{module}.php`
    * **DB:** Factories in `database/factories/{Module}`
* **Controllers:** Only handle request/response, no business logic.
* **Service Classes:** All business logic. Use DI with readonly properties, include security and org checks.
* **Naming:** Respect project naming. Branches: kebab-case, with prefixes (`feat/`, `fix/`, etc.).

---

### 2. Frontend (Vue + Inertia)

* **Stack:** Vue 3, Inertia.js, TypeScript, Tailwind CSS
* **UI:** Only use shadcn-vue components (see above).
* **Testing:**

    * Vitest (unit/component)
    * Playwright (E2E)
* **Types:** Use TypeScript everywhere.

---

### 3. Database & Models

* **Timezone:** All datetimes in UTC; frontend handles local display.
* **Organization Context:** Use `getPermissionsOrgId()`; handle `GLOBAL_ORG_ID` for admin.
* **Relationships:** Use eager loading via `load()`/`with()`.

---

### 4. Testing

* **PestPHP:**

    * Structure: `describe/it`
    * Always run with `--parallel`
    * Coverage: 100% line & mutation; require `covers()` annotations.
    * Categories:

        * Unit: `tests/Unit/` (no framework)
        * Integration: `tests/Integration/`
        * Feature: endpoint/component
    * Setup: `RefreshDatabase`, factories, `RolesAndPermissionsSeeder`
    * Inertia: Use `assertInertia()` with `AssertableInertia`

---

### 5. Quality & Tooling

* **Dev Env:** Herd (PHP 8.4, Node 22), Mailpit (`localhost:8025`)
* **IDE:** VSCode + Laravel, Pest, Intelephense, Vue, Tailwind CSS extensions
* **QA:** PHPStan/Larastan, Rector, Pint, Peck;

    * `composer precommit`: full check
    * `composer test`: all tests + coverage
    * `npm run e2e`: E2E tests
    * `composer mailpit`: Mailpit server

---

### 6. Permissions & Security

* Every controller method uses a dedicated request class for permission checks (`can`).
* Service classes: implement org-based security (e.g., `verifyEventBelongsToCurrentOrganization`).
* Use `throw_if()` for concise error handling.
* New permissions: add to `RolesAndPermissionsSeeder`.

---

### 7. Documentation

* Use PHPDoc only for type hinting/clarity, not redundancy.
* Rely on static code analysis.
* Service types: e.g., `Collection<int, Model>`, `array<string, mixed>`

---

### 8. CI/CD

* Extended testing and CI/CD in place.
* Branches: follow prefix and kebab-case conventions.
* Do **not** auto-generate configs/scripts unless explicitly requested.

---

**Note:**

* Only shadcn-vue components (or those built from them) are allowed in the frontend.
* Always run PestPHP tests with `--parallel`.
