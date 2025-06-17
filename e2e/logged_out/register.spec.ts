import { expect, test } from '@playwright/test';
import { fillRegistrationForm, generateTestUser, getEmailVerificationLink, navigateToRegistration, submitRegistrationForm } from '../utils';

test('User can access registration form', async ({ page }) => {
    // 1. Go to homepage, expect redirect to /login
    await page.goto('/');
    await expect(page).toHaveURL(/\/login$/);

    // 2. Click "Sign up", expect redirect to /register
    await page.getByRole('link', { name: /sign up/i }).click();
    await expect(page).toHaveURL(/\/register$/);

    // 3. Check that registration form elements are present
    await expect(page.getByLabel(/name/i)).toBeVisible();
    await expect(page.getByLabel(/email/i)).toBeVisible();
    await expect(page.getByLabel(/^password$/i)).toBeVisible();
    await expect(page.getByLabel(/confirm password/i)).toBeVisible();
    await expect(page.getByRole('button', { name: /create account/i })).toBeVisible();

    console.log('✅ Registration form is accessible and contains all required fields');
});

test('User can fill out registration form', async ({ page }) => {
    const testUser = generateTestUser();

    // Navigate to registration page
    await navigateToRegistration(page);

    // Fill out the form
    await fillRegistrationForm(page, testUser);

    // Check that form was filled correctly
    await expect(page.getByLabel(/name/i)).toHaveValue(testUser.name);
    await expect(page.getByLabel(/email/i)).toHaveValue(testUser.email);

    console.log('✅ Registration form can be filled out correctly');
});

test('User can complete full registration with email verification', async ({ page }) => {
    const testUser = generateTestUser();

    console.log(`🧪 Testing registration for: ${testUser.email}`);

    // Step 1: Navigate to registration page
    await navigateToRegistration(page);

    // Step 2: Fill out and submit the registration form
    await fillRegistrationForm(page, testUser);
    await submitRegistrationForm(page);

    // Step 3: Expect to be redirected to email verification notice page
    await expect(page).toHaveURL(/\/verify-email$/);
    await expect(page.getByText(/verify your email/i)).toBeVisible();

    console.log('✅ Registration successful, redirected to email verification page');

    // Step 4: Get a verification link from email (with built-in retry mechanism)
    let verificationLink: string;
    try {
        verificationLink = await getEmailVerificationLink(page, testUser.email);
        console.log(`📧 Found verification link: ${verificationLink}`);
    } catch (error) {
        console.error('❌ Failed to get verification link:', error);
        throw error;
    }

    // Step 5: Visit the verification link
    await page.goto(verificationLink);

    // Step 6: Verify we're redirected to the admin dashboard
    await expect(page).toHaveURL(/\/admin\/dashboard$/);
    console.log('✅ Email verification successful, redirected to admin dashboard');

    // Step 7: Check if we need to open the sidebar first (on mobile)
    // Look for the sidebar toggle button with more reliable waiting
    const sidebarToggleButton = page.locator('button[data-sidebar="trigger"]');

    try {
        // Wait for the page to stabilize
        await page.waitForLoadState('networkidle', { timeout: 5000 });

        // Check if the mobile sidebar toggle is visible (mobile view)
        const isMobileView = await sidebarToggleButton.isVisible();

        if (isMobileView) {
            console.log('📱 Mobile viewport detected, opening sidebar');

            // Make sure the button is ready for interaction
            await sidebarToggleButton.waitFor({ state: 'visible', timeout: 5000 });

            // Click the toggle button and wait for the sidebar to appear
            await sidebarToggleButton.click();
            console.log('✅ Clicked sidebar toggle button');

            // Wait for the sidebar element to become visible
            const sidebarElement = page.locator('div[data-sidebar="content"]');
            await sidebarElement.waitFor({ state: 'visible', timeout: 5000 });
        } else {
            console.log('🖥️ Desktop viewport detected, sidebar should be visible');
        }
    } catch (error) {
        console.error('⚠️ Issue with sidebar detection/interaction:', error);
        // Continue the test even if there was an issue with the sidebar
    }

    // Step 8: Find the user dropdown menu button with increased timeout and retry
    console.log(`🔍 Looking for user menu button for "${testUser.name}"`);
    const userMenuButton = page.locator('button[data-sidebar="menu-button"]').filter({ hasText: testUser.name });

    try {
        await expect(userMenuButton).toBeVisible({ timeout: 10000 });
        console.log(`✅ User name "${testUser.name}" is visible in the interface`);
    } catch (error) {
        console.error(`⚠️ Could not find user menu button for "${testUser.name}". Taking screenshot for debugging...`);
        await page.screenshot({ path: `test-results/user-menu-failure-${Date.now()}.png`, fullPage: true });
        throw error;
    }

    // Step 9: Click on the user menu button to open the dropdown menu
    await userMenuButton.click();
    console.log('✅ User dropdown menu opened');

    // Step 10: Wait for the dropdown menu to appear and verify "Log Out" button with increased timeout
    const logoutButton = page.getByRole('menuitem', { name: /log out/i });
    await expect(logoutButton).toBeVisible({ timeout: 10000 });
    console.log('✅ Log Out button is visible in dropdown menu');

    // Step 11: Click the logout button
    await logoutButton.click();
    console.log('✅ Clicked Log Out button');

    // Step 12: Verify we're redirected to the login page
    await expect(page).toHaveURL(/\/login$/, { timeout: 10000 });
    console.log('✅ Successfully logged out and redirected to login page');

    console.log('✅ Full registration flow completed successfully');
});
