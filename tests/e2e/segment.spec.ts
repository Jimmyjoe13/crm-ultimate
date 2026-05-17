import { test, expect } from '@playwright/test';
import { login } from './helpers';

test.describe('Segments dynamiques', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('liste des segments visible', async ({ page }) => {
        await page.goto('/segments');
        await expect(page.locator('h1')).toContainText('Segments');
    });

    test('créer un segment et voir ses membres', async ({ page }) => {
        await page.goto('/segments/create');

        const segName = `Segment E2E ${Date.now()}`;
        // Le champ nom utilise x-model="name" (Alpine.js), pas name="name"
        await page.fill('input[x-model="name"]', segName);

        // Le bouton utilise @click="submitForm()" (Alpine.js), pas type="submit"
        await page.locator('button:has-text("Créer le segment"), button:has-text("Enregistrer")').first().click();
        await page.waitForURL(/\/segments\/\d+/, { timeout: 15000 });
        await expect(page.locator('body')).toContainText(segName);
    });
});
