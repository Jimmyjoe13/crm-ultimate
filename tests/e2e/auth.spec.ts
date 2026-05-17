import { test, expect } from '@playwright/test';
import { login, ADMIN } from './helpers';

test.describe('Authentification', () => {
    test('login admin réussit et redirige vers le CRM', async ({ page }) => {
        await login(page);
        await expect(page).not.toHaveURL(/\/login/);
    });

    test('mauvais mot de passe affiche une erreur', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name="email"]', ADMIN.email);
        await page.fill('input[name="password"]', 'mauvais');
        await page.click('button[type="submit"]');
        await page.waitForURL(/\/login/);
        await expect(page.locator('body')).toContainText(/identifiants|invalid|incorrects/i);
    });

    test('logout redirige vers login', async ({ page }) => {
        await login(page);
        // Le logout est un POST — on utilise l'API fetch avec le CSRF cookie
        await page.evaluate(async () => {
            const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
            await fetch('/logout', {
                method: 'POST',
                headers: { 'X-XSRF-TOKEN': xsrf, 'X-Requested-With': 'XMLHttpRequest' },
            });
        });
        await page.goto('/login');
        await expect(page).toHaveURL(/\/login/);
    });
});
