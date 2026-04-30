import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import type { TestInfo } from '@playwright/test';

async function ensureSidebarLinkVisible(page: Page, testInfo: TestInfo, linkName: string): Promise<void> {
    const sidebarToggle = page.getByRole('button', { name: 'Toggle Sidebar' });
    const targetLink = page.getByRole('link', { name: linkName });

    await expect(sidebarToggle).toBeVisible();

    for (let attempt = 1; attempt <= 3; attempt++) {
        if (await targetLink.isVisible()) {
            return;
        }

        const sidebarLinks = await page
            .getByRole('navigation')
            .getByRole('link')
            .allTextContents()
            .catch(() => []);

        console.log(`Sidebar debug (${linkName}) attempt ${attempt}: url=${page.url()} links=[${sidebarLinks.join(', ')}]`);
        await sidebarToggle.click();
        await page.waitForTimeout(300);

        if (await targetLink.isVisible()) {
            return;
        }

        await testInfo.attach(`sidebar-${linkName.toLowerCase()}-attempt-${attempt}`, {
            body: await page.screenshot({ fullPage: true }),
            contentType: 'image/png',
        });
    }

    await expect(targetLink).toBeVisible();
}

async function ensureSidebarButtonVisible(page: Page, testInfo: TestInfo, buttonName: string): Promise<void> {
    const sidebarToggle = page.getByRole('button', { name: 'Toggle Sidebar' });
    const targetButton = page.getByRole('button', { name: buttonName });

    await expect(sidebarToggle).toBeVisible();

    for (let attempt = 1; attempt <= 3; attempt++) {
        if (await targetButton.isVisible()) {
            return;
        }

        console.log(`Sidebar debug button (${buttonName}) attempt ${attempt}: url=${page.url()}`);
        await sidebarToggle.click();
        await page.waitForTimeout(300);

        if (await targetButton.isVisible()) {
            return;
        }

        await testInfo.attach(`sidebar-button-${buttonName.toLowerCase().replace(/\s+/g, '-')}-attempt-${attempt}`, {
            body: await page.screenshot({ fullPage: true }),
            contentType: 'image/png',
        });
    }

    await expect(targetButton).toBeVisible();
}

test('Mobile Ghost Test', async ({ page }, testInfo) => {
    // TODO: Refactor all e2e tests to use explicit waits for reliability
    console.log('Navigating to login page');
    await page.goto('/login');
    console.log('Filling in login credentials');
    await expect(page.getByRole('textbox', { name: 'Email address' })).toBeVisible();
    await page.getByRole('textbox', { name: 'Email address' }).click();
    await page.getByRole('textbox', { name: 'Email address' }).fill('superadmin@e2e.test');
    await expect(page.getByRole('textbox', { name: 'Password' })).toBeVisible();
    await page.getByRole('textbox', { name: 'Password' }).click();
    await page.getByRole('textbox', { name: 'Password' }).fill('superadminpassword');
    await expect(page.getByRole('checkbox', { name: 'Remember me' })).toBeVisible();
    await page.getByRole('checkbox', { name: 'Remember me' }).click();
    console.log('Clicking Log in');
    await expect(page.getByRole('button', { name: 'Log in' })).toBeVisible();
    await page.getByRole('button', { name: 'Log in' }).click();
    console.log('Navigating through admin menu');
    await expect(page.getByText('AdministrationClick to select')).toBeVisible();
    await page.getByText('AdministrationClick to select').click();
    await ensureSidebarLinkVisible(page, testInfo, 'Users');
    await page.getByRole('link', { name: 'Users' }).click();
    await expect(page.getByRole('link', { name: 'Create User' })).toBeVisible();
    await page.getByRole('link', { name: 'Create User' }).click();
    await expect(page.getByRole('link', { name: 'Users' })).toBeVisible();
    await page.getByRole('link', { name: 'Users' }).click();
    await ensureSidebarLinkVisible(page, testInfo, 'Organizations');
    await page.getByRole('link', { name: 'Organizations' }).click();
    await expect(page.getByRole('button', { name: 'View' })).toBeVisible();
    await page.getByRole('button', { name: 'View' }).click();
    await expect(page.getByRole('menuitemcheckbox', { name: 'name' })).toBeVisible();
    await page.getByRole('menuitemcheckbox', { name: 'name' }).click();
    await expect(page.getByRole('link', { name: 'Create Organization' })).toBeVisible();
    await page.getByRole('link', { name: 'Create Organization' }).click();
    await ensureSidebarButtonVisible(page, testInfo, 'Super Admin');
    await expect(page.getByRole('button', { name: 'Super Admin' })).toBeVisible();
    await page.getByRole('button', { name: 'Super Admin' }).click();
    await expect(page.getByRole('menuitem', { name: 'Settings' })).toBeVisible();
    await page.getByRole('menuitem', { name: 'Settings' }).click();
    await expect(page.getByRole('link', { name: 'Password' })).toBeVisible();
    await page.getByRole('link', { name: 'Password' }).click();
    await expect(page.getByRole('link', { name: 'Appearance' })).toBeVisible();
    await page.getByRole('link', { name: 'Appearance' }).click();
    console.log('Switching appearance modes');
    await expect(page.getByRole('button', { name: 'Dark' })).toBeVisible();
    await page.getByRole('button', { name: 'Dark' }).click();
    await expect(page.getByRole('button', { name: 'System' })).toBeVisible();
    await page.getByRole('button', { name: 'System' }).click();
    await expect(page.getByRole('link', { name: 'Back to Home' })).toBeVisible();
    await page.getByRole('link', { name: 'Back to Home' }).click();
    console.log('Logging out');
    await expect(page.getByRole('button', { name: 'Log out' })).toBeVisible();
    await page.getByRole('button', { name: 'Log out' }).click();
});
