// TODO: Remove video and sleeps after video check
import { faker } from '@faker-js/faker';
import { test } from '@playwright/test';

// This test clocks through major app flows as a superadmin user
// TODO: Consider splitting into smaller tests or using utility functions for repeated flows

test('superadmin can click through main app flows (ghost test)', async ({ page }) => {
    // Generate unique test data
    const userName = faker.person.fullName();
    const userEmail = faker.internet.email({ firstName: userName.split(' ')[0], lastName: userName.split(' ')[1] || '' }).toLowerCase();
    const orgName = faker.company.name();
    const orgSlug = faker.helpers.slugify(orgName).toLowerCase();

    // Login
    console.log('Navigating to login page...');
    // Use relative URL for login page
    await page.goto('/login');
    console.log('Filling in superadmin credentials...');
    await page.getByRole('textbox', { name: 'Email address' }).fill('superadmin@e2e.test');
    await page.getByRole('textbox', { name: 'Password' }).fill('superadminpassword');
    await page.getByRole('checkbox', { name: 'Remember me' }).click();
    await page.getByRole('button', { name: 'Log in' }).click();
    console.log('Logged in as superadmin.');

    // Go to Administration
    console.log('Navigating to Administration...');
    await page.getByText('AdministrationClick to select').click();

    // Users section navigation and actions
    console.log('Navigating to Users section...');
    await page.getByRole('link', { name: 'Users' }).click();
    // Sort by Name Asc/Desc (like orgs)
    console.log('Sorting users by Name Asc/Desc...');
    await page.getByRole('button', { name: 'Name' }).click();
    await page.getByRole('menuitem', { name: 'Asc' }).click();
    await page.getByRole('button', { name: 'Name' }).click();
    await page.getByRole('menuitem', { name: 'Desc' }).click();
    // Sort by Email Asc/Desc (like orgs)
    console.log('Sorting users by Email Asc/Desc...');
    await page.getByRole('button', { name: 'Email' }).click();
    await page.getByRole('menuitem', { name: 'Asc' }).getByRole('img').click();
    await page.getByRole('button', { name: 'Email' }).click();
    await page.getByRole('menuitem', { name: 'Desc' }).click();
    // Go to last/first page (like before)
    console.log('Paginating users...');
    await page.getByRole('button', { name: 'Go to last page' }).click();
    await page.getByRole('button', { name: 'Go to first page' }).click();

    // Toggle columns in user view
    for (const col of ['name', 'email', 'email_verified_at']) {
        console.log(`Toggling user column: ${col}`);
        await page.getByRole('button', { name: 'View' }).click();
        await page.getByRole('menuitemcheckbox', { name: col, exact: col === 'email' }).click();
        await page.getByRole('button', { name: 'View' }).click();
        await page.getByRole('menuitemcheckbox', { name: col, exact: col === 'email' }).click();
    }

    // Create a new user
    console.log(`Creating a new user: ${userName} (${userEmail})`);
    await page.getByRole('link', { name: 'Create User' }).click();
    await page.getByRole('textbox', { name: 'Name' }).fill(userName);
    await page.getByRole('textbox', { name: 'Email address' }).fill(userEmail);
    await page.getByRole('button', { name: 'Create User' }).click();
    await page.waitForTimeout(1000); // TODO: Remove sleep after video check
    console.log('User created, searching for user in table...');

    // Paginate through users to find the created user
    let foundUser = false;
    do {
        foundUser = (await page.locator(`tr:has-text(\"${userName}\")`).count()) > 0;
        if (!foundUser) {
            const nextBtn = page.getByRole('button', { name: 'Go to next page' });
            if (await nextBtn.isDisabled()) break;
            await nextBtn.click();
        }
    } while (!foundUser);
    if (foundUser) {
        console.log('Found created user, clicking action button...');
        await page.locator(`tr:has-text(\"${userName}\")`).getByRole('button').nth(3).click();
    } else {
        console.log('Created user not found in user table.');
    }

    // Organizations section navigation and actions
    console.log('Navigating to Organizations section...');
    await page.getByRole('link', { name: 'Organizations' }).click();
    // Sort by Organization Name Asc/Desc
    console.log('Sorting organizations by Name Asc/Desc...');
    await page.getByRole('button', { name: 'Organization Name' }).click();
    await page.getByRole('menuitem', { name: 'Asc' }).click();
    await page.getByRole('button', { name: 'Organization Name' }).click();
    await page.getByRole('menuitem', { name: 'Desc' }).click();
    // Sort by Email Asc/Desc
    console.log('Sorting organizations by Email Asc/Desc...');
    await page.getByRole('button', { name: 'Email' }).click();
    await page.getByRole('menuitem', { name: 'Asc' }).getByRole('img').click();
    await page.getByRole('button', { name: 'Email' }).click();
    await page.getByRole('menuitem', { name: 'Desc' }).click();

    // Toggle columns in organization view
    for (const col of ['name', 'email', 'website']) {
        console.log(`Toggling organization column: ${col}`);
        await page.getByRole('button', { name: 'View' }).click();
        await page.getByRole('menuitemcheckbox', { name: col }).click();
    }

    // Create a new organization
    console.log(`Creating a new organization: ${orgName} (${orgSlug})`);
    await page.getByRole('link', { name: 'Create Organization' }).click();
    await page.getByRole('textbox', { name: 'Name' }).fill(orgName);
    await page.getByRole('textbox', { name: 'Slug' }).fill(orgSlug);
    await page.getByRole('button', { name: 'Create Organization' }).click();
    console.log('Organization created, searching for organization in table...');

    // Paginate through organizations to find the created organization
    let foundOrg = false;
    do {
        foundOrg = (await page.locator(`tr:has-text(\"${orgName}\")`).count()) > 0;
        if (!foundOrg) {
            const nextBtn = page.getByRole('button', { name: 'Go to next page' });
            if (await nextBtn.isDisabled()) break;
            await nextBtn.click();
        }
    } while (!foundOrg);
    if (foundOrg) {
        console.log('Found created organization, clicking action button...');
        await page.locator(`tr:has-text(\"${orgName}\")`).getByRole('button').nth(3).click();
    } else {
        console.log('Created organization not found in organization table.');
    }

    // Organization selection
    console.log('Navigating to Organization Selection...');
    await page.getByRole('link', { name: 'Organization Selection' }).click();
    await page.getByRole('heading', { name: 'Test Org 1' }).click();
    await page.getByRole('link').filter({ hasText: /^$/ }).click();

    // Logout (always use sidebar logout button)
    console.log('Logging out...');
    await page.getByRole('button', { name: 'Log out' }).click();
    console.log('Test complete.');
});
