# Repository Conventions

To ensure a **clear and consistent workflow**, follow these conventions when working on the repository.

## Branch Naming Conventions

**Format:** `<prefix>/<short-description>`
Use **kebab-case** for the short description.

| Prefix       | Purpose                                     | Example                                    |
| ------------ | ------------------------------------------- | ------------------------------------------ |
| `feat`       | New features or enhancements                | `feat/add-user-authentication`             |
| `fix`        | Bug fixes                                   | `fix/vue-modal-close-issue`                |
| `refactor`   | Code restructuring (no behavior change)     | `refactor/extract-inertia-page-components` |
| `hotfix`     | Urgent fixes on production                  | `hotfix/fix-payment-processing-bug`        |
| `test`       | Writing or updating test cases              | `test/add-api-feature-tests`               |
| `chore`      | Maintenance (dependencies, tooling)         | `chore/update-npm-packages`                |
| `docs`       | Documentation updates                       | `docs/update-api-docs`                     |
| `config`     | Configuration changes                       | `config/setup-vite-aliases`                |
| `perf`       | Performance improvements                    | `perf/optimize-inertia-rendering`          |
| `style`      | Formatting or linter fixes                  | `style/fix-eslint-prettier-errors`         |
| `experiment` | Experimental or proof-of-concept work       | `experiment/test-vue3-composition-api`     |
| `release`    | Release preparation                         | `release/v1.0.0`                           |
| `build`      | Build system changes                        | `build/switch-to-vite`                     |
| `ci`         | CI/CD pipeline updates                      | `ci/github-actions-laravel-tests`          |
| `deps`       | Dependency updates (Laravel, Vue, NPM, PHP) | `deps/update-laravel-10`                   |
| `security`   | Security patches (fixing vulnerabilities)   | `security/fix-sql-injection`               |
| `ui`         | UX/UI improvements without adding features  | `ux/improve-dashboard-spacing`             |
| `i18n`       | Internationalization/localization changes   | `i18n/add-french-translations`             |
| `migration`  | Major database schema changes               | `migration/refactor-user-tables`           |
