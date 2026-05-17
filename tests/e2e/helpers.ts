import { Page } from '@playwright/test';

export const ADMIN = { email: 'admin@demo.com', password: 'password' };

export async function login(page: Page, user = ADMIN) {
    await page.goto('/login');
    await page.fill('input[name="email"]', user.email);
    await page.fill('input[name="password"]', user.password);
    await page.click('button[type="submit"]');
    // Attend que l'URL sorte de /login (redirige vers / = dashboard)
    await page.waitForURL(url => !url.pathname.includes('/login'));
}
