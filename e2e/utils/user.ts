import { faker } from '@faker-js/faker';
import { Page, expect } from '@playwright/test';

export interface TestUser {
    name: string;
    email: string;
    password: string;
}

/**
 * Generate a test user with unique email
 */
export function generateTestUser(namePrefix?: string): TestUser {
    const name = namePrefix || faker.person.fullName();
    const timestamp = Date.now();

    // Create slug from name: "John Doe" -> "john-doe"
    const slug = name
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, '') // Remove special characters
        .replace(/\s+/g, '-') // Replace spaces with hyphens
        .replace(/-+/g, '-') // Replace multiple hyphens with single
        .replace(/^-|-$/g, ''); // Remove leading/trailing hyphens

    return {
        name,
        email: `${slug}-${timestamp}@testdomain.example`,
        password: faker.internet.password({ length: 12, memorable: false, pattern: /[A-Za-z0-9!@#$%^&*]/ }),
    };
}

/**
 * Verify that user is logged in
 * This checks for common indicators that a user is authenticated
 */
export async function verifyUserIsLoggedIn(page: Page, user?: TestUser): Promise<void> {
    // The user should not be on auth-related pages
    await expect(page).not.toHaveURL(/\/verify-email$/);
    await expect(page).not.toHaveURL(/\/login$/);

    // Wait for the page to fully load (important for Inertia.js apps)
    await page.waitForLoadState('networkidle');

    // Look for immediate logged-in indicators (that don't require clicking)
    const immediateIndicators = [
        // Look for dashboard content
        page.getByText(/dashboard/i),
        page.getByRole('heading', { name: /dashboard/i }),

        // Look for navigation elements
        page.getByText('Organization Selection'),

        // Look for sidebar elements
        page.locator('[class*="sidebar"]'),

        // Look for the user info in sidebar (which should be visible before clicking)
        page.locator('[data-radix-dropdown-menu-trigger]'),
    ];

    // If we have user info, also check for user name or email in the sidebar
    if (user) {
        immediateIndicators.push(page.getByText(user.name), page.getByText(user.email));
    }

    // Try to find at least one immediate indicator
    let foundIndicator = false;
    for (const indicator of immediateIndicators) {
        try {
            await expect(indicator).toBeVisible({ timeout: 10000 });
            foundIndicator = true;
            break;
        } catch {
            // Continue to next indicator
        }
    }

    // If no immediate indicators found, try to interact with user menu to reveal logout
    if (!foundIndicator) {
        try {
            // Look for user menu trigger (user name/avatar in sidebar)
            const userMenuTrigger = page.locator('[data-radix-dropdown-menu-trigger]').first();
            await userMenuTrigger.click();

            // Now look for logout in the opened dropdown
            await expect(page.getByText('Log out')).toBeVisible({ timeout: 5000 });
            foundIndicator = true;

            // Close the dropdown by clicking outside or pressing escape
            await page.keyboard.press('Escape');
        } catch {
            // Couldn't find or interact with user menu
        }
    }

    if (!foundIndicator) {
        // As a fallback, check if we're on a dashboard URL
        const currentUrl = page.url();
        if (currentUrl.includes('/dashboard') || currentUrl.includes('/admin')) {
            console.log('⚠️ No specific logged-in indicators found, but URL suggests user is logged in');
            return;
        }

        throw new Error('No logged-in indicators found. User may not be properly authenticated.');
    }
}

/**
 * Fill out and submit the login form
 */
export async function loginUser(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email/i).fill(email);
    await page.getByLabel(/password/i).fill(password);
    await page.getByRole('button', { name: /log in|sign in/i }).click();
}

/**
 * Logout the current user
 */
export async function logoutUser(page: Page): Promise<void> {
    try {
        // First, try to find and click the user menu trigger to open the dropdown
        const userMenuTrigger = page.locator('[data-radix-dropdown-menu-trigger]').first();
        await userMenuTrigger.click({ timeout: 5000 });

        // Wait for the dropdown to appear and click logout
        await page.getByText('Log out').click({ timeout: 5000 });
    } catch {
        // Fallback: try to find logout button/link directly
        const logoutSelectors = [
            page.getByRole('button', { name: /logout/i }),
            page.getByRole('link', { name: /logout/i }),
            page.getByText(/logout/i),
        ];

        for (const selector of logoutSelectors) {
            try {
                await selector.click({ timeout: 3000 });
                break;
            } catch {
                // Try next selector
            }
        }
    }

    // Verify we're logged out
    await expect(page).toHaveURL(/\/login$|\/$/);
}