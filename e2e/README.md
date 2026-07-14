# Musikfestapp E2E Tests

This directory contains end-to-end (E2E) tests for the Musikfestapp application using Playwright.

## 🚀 Running Tests

```bash
# Run all tests on all configured browsers and devices
npm run e2e

# Run with UI mode (for debugging)
npm run e2e:ui

# Run with visible browsers
npm run e2e:headed

# Run tests on specific browsers or devices
npx playwright test --project=chromium
npx playwright test --project=firefox
npx playwright test --project=webkit
npx playwright test --project=mobile-chrome
npx playwright test --project=mobile-safari
npx playwright test --project=tablet

# Run a specific test file on a specific device
```

## 📝 Best Practices

1. **Test Organization:**

    - Put tests for logged-out users in `logged_out/` directory
    - Put tests for logged-in users in `logged_in/` directory (create if needed)
    - Name test files with `.spec.ts` extension

2. **Test Implementation:**

    - Use role-based selectors (`getByRole`, `getByLabel`) for resilience
    - Create utility functions for repetitive tasks
    - Use meaningful test descriptions
    - Add error handling for critical operations

3. **Data Management:**
    - Leverage utility functions for creating unique test users
    - Clean up test data if it might affect other tests
    - Use `global-setup.ts` for shared setup operations

## 🔧 Common Utilities

The `utils/` directory contains helper functions for common E2E testing tasks including:

- **User Management:** Functions for creating, authenticating, and verifying users
- **Email Testing:** Functions for retrieving and validating email-related links and content
- **Data Helpers:** Functions for generating and managing test data

Refer to the implementation files in the `utils/` directory for the most up-to-date available utilities.

## 🔍 Debugging

1. Use `npm run e2e:ui` to access the Playwright UI mode
2. Add `await page.pause()` in your tests for interactive debugging
3. Use `console.log()` statements for debugging values
4. Check screenshots and videos in the `test-results/` directory after failures

## 📚 Resources

- [Playwright Documentation](https://playwright.dev/docs/intro)
- [E2E Testing Guide](../E2E_TESTING.md)
- [Musikfestapp Testing Guidelines](../TESTING_GUIDELINES.md)
