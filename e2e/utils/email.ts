import { faker } from '@faker-js/faker';
import { Page } from '@playwright/test';

/**
 * Email testing utilities for working with MailCatcher/MailHog
 */

export interface EmailTestConfig {
    mailcatcherUrl?: string;
    waitTime?: number;
    maxRetries?: number;
}

const defaultConfig: Required<EmailTestConfig> = {
    mailcatcherUrl: 'http://localhost:8025',
    waitTime: 2000,
    maxRetries: 3,
};

/**
 * Get the latest email sent to a specific email address
 */
export async function getLatestEmail(page: Page, email: string, config: EmailTestConfig = {}): Promise<string> {
    const finalConfig = { ...defaultConfig, ...config };

    for (let attempt = 1; attempt <= finalConfig.maxRetries; attempt++) {
        try {
            await page.goto(`${finalConfig.mailcatcherUrl}/view/latest.html?query=to:${encodeURIComponent(email)}`);
            await page.waitForTimeout(finalConfig.waitTime);

            const emailContent = await page.textContent('body');

            if (emailContent && emailContent.trim().length > 0) {
                return emailContent;
            }

            if (attempt < finalConfig.maxRetries) {
                console.log(`⏳ Attempt ${attempt} failed, retrying in 1 second...`);
                await page.waitForTimeout(1000);
            }
        } catch (error) {
            if (attempt === finalConfig.maxRetries) {
                throw new Error(`Failed to get email after ${finalConfig.maxRetries} attempts: ${error}`);
            }
            console.log(`⏳ Attempt ${attempt} failed, retrying...`);
            await page.waitForTimeout(1000);
        }
    }

    throw new Error(`No email found for ${email} after ${finalConfig.maxRetries} attempts`);
}

/**
 * Extract verification link from Laravel email content
 */
export function extractVerificationLink(emailContent: string, baseUrl: string = 'http://musikfestapp.test'): string {
    // Try to find full URL first
    const fullUrlMatch = emailContent.match(/http[s]?:\/\/[^\/\s]+\/verify-email\/[^\s"'<>]+/);
    if (fullUrlMatch) {
        return fullUrlMatch[0];
    }

    // Try to find relative path
    const relativeMatch = emailContent.match(/verify-email\/[^\s"'<>]+/);
    if (relativeMatch) {
        return `${baseUrl}/${relativeMatch[0]}`;
    }

    // Try to find just the token parts
    const tokenMatch = emailContent.match(/verify-email\/(\d+)\/([a-f0-9]+)/);
    if (tokenMatch) {
        return `${baseUrl}/verify-email/${tokenMatch[1]}/${tokenMatch[2]}`;
    }

    throw new Error('No verification link found in email content');
}

/**
 * Get email verification link for a specific email address
 */
export async function getEmailVerificationLink(page: Page, email: string, config: EmailTestConfig = {}): Promise<string> {
    const emailContent = await getLatestEmail(page, email, config);
    return extractVerificationLink(emailContent);
}

/**
 * Extract password reset link from Laravel email content
 */
export function extractPasswordResetLink(emailContent: string, baseUrl: string = 'http://musikfestapp.test'): string {
    // Try to find full URL first
    const fullUrlMatch = emailContent.match(/http[s]?:\/\/[^\/\s]+\/reset-password\/[^\s"'<>]+/);
    if (fullUrlMatch) {
        return fullUrlMatch[0];
    }

    // Try to find relative path
    const relativeMatch = emailContent.match(/reset-password\/[^\s"'<>]+/);
    if (relativeMatch) {
        return `${baseUrl}/${relativeMatch[0]}`;
    }

    throw new Error('No password reset link found in email content');
}

/**
 * Get password reset link for a specific email address
 */
export async function getPasswordResetLink(page: Page, email: string, config: EmailTestConfig = {}): Promise<string> {
    const emailContent = await getLatestEmail(page, email, config);
    return extractPasswordResetLink(emailContent);
}

/**
 * Generate a unique test email address
 */
export function generateTestEmail(prefix: string = 'testuser'): string {
    const timestamp = Date.now();
    const hash = faker.string.alphanumeric(8);
    const domain = faker.internet.domainName();
    return `${prefix}-${timestamp}-${hash}@${domain}`;
}
