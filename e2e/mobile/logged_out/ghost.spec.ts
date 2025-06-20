import { devices, test } from '@playwright/test';

test.use({
    ...devices['iPhone 14'],
});

test('Mobile Ghost Test', async ({ page }) => {
    console.log('Navigating to login page');
    await page.goto('http://musikfestapp.test/login');
    console.log('Filling in login credentials');
    await page.getByRole('textbox', { name: 'Email address' }).click();
    await page.getByRole('textbox', { name: 'Email address' }).fill('superadmin@e2e.test');
    await page.getByRole('textbox', { name: 'Password' }).click();
    await page.getByRole('textbox', { name: 'Password' }).fill('superadminpassword');
    await page.getByRole('checkbox', { name: 'Remember me' }).click();
    console.log('Clicking Log in');
    await page.getByRole('button', { name: 'Log in' }).click();
    console.log('Navigating through admin menu');
    await page.getByText('AdministrationClick to select').click();
    await page.getByRole('button', { name: 'Toggle Sidebar' }).click();
    await page.getByRole('link', { name: 'Users' }).click();
    await page.getByRole('link', { name: 'Create User' }).click();
    await page.getByRole('link', { name: 'Users' }).click();
    await page.getByRole('button', { name: 'Toggle Sidebar' }).click();
    await page.getByRole('link', { name: 'Organizations' }).click();
    await page.getByRole('button', { name: 'View' }).click();
    await page.getByRole('menuitemcheckbox', { name: 'name' }).click();
    await page.getByRole('link', { name: 'Create Organization' }).click();
    await page.getByRole('button', { name: 'Toggle Sidebar' }).click();
    await page.getByRole('button', { name: 'Super Admin' }).click();
    await page.getByRole('menuitem', { name: 'Settings' }).click();
    await page.getByRole('link', { name: 'Password' }).click();
    await page.getByRole('link', { name: 'Appearance' }).click();
    console.log('Switching appearance modes');
    await page.getByRole('button', { name: 'Dark' }).click();
    await page.getByRole('button', { name: 'System' }).click();
    await page.getByRole('link', { name: 'Back to Home' }).click();
    console.log('Logging out');
    await page.getByRole('button', { name: 'Log out' }).click();
});
