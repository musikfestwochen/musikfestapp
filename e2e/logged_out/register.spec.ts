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

    // Step 7: Find the user dropdown menu button
    const userMenuButton = page.locator('button[data-sidebar="menu-button"]').filter({ hasText: testUser.name });
    await expect(userMenuButton).toBeVisible();
    console.log(`✅ User name "${testUser.name}" is visible in the interface`);

    // Step 8: Click on the user menu button to open the dropdown menu
    await userMenuButton.click();
    console.log('✅ User dropdown menu opened');

    // Step 9: Wait for the dropdown menu to appear and verify "Log Out" button
    const logoutButton = page.getByRole('menuitem', { name: /log out/i });
    await expect(logoutButton).toBeVisible();
    console.log('✅ Log Out button is visible in dropdown menu');

    // Step 10: Click the logout button
    await logoutButton.click();
    console.log('✅ Clicked Log Out button');

    // Step 11: Verify we're redirected to the login page
    await expect(page).toHaveURL(/\/login$/);
    console.log('✅ Successfully logged out and redirected to login page');

    console.log('✅ Full registration flow completed successfully');
});
