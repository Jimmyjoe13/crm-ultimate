import { test, expect } from '@playwright/test';
import { login } from './helpers';

test.describe('Deals — flux complet', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('la liste des deals est accessible', async ({ page }) => {
        await page.goto('/deals');
        await expect(page.locator('h1')).toContainText('Deals');
        await expect(page.locator('table.t')).toBeVisible();
    });

    test('créer un deal via le modal depuis un contact', async ({ page }) => {
        await page.goto('/contacts');
        await page.waitForLoadState('networkidle');

        // Cliquer sur le premier contact dans la table
        await page.locator('table.t tbody tr:has(span.av)').first().click();
        await page.waitForURL(/\/contacts\/\d+/, { timeout: 15000 });
        await page.waitForLoadState('networkidle');

        // Attendre que la page et Alpine.js soient prêts
        await page.waitForTimeout(1000);

        // Ouvrir le modal
        await page.click('button:has-text("Créer un deal")');
        await expect(page.locator('h2:has-text("Nouveau deal")')).toBeVisible();

        const dealName = `Deal E2E ${Date.now()}`;
        await page.fill('input[name="name"]', dealName);
        await page.fill('input[name="amount"]', '5000');

        const stageSelect = page.locator('select[name="pipeline_stage_id"]');
        await stageSelect.selectOption({ index: 1 });

        // Soumettre le formulaire
        await page.locator('button[type="submit"]:has-text("Créer le deal")').click();

        // Le contrôleur redirige vers /pipeline
        await page.waitForURL(url => url.pathname === '/pipeline', { timeout: 15000 });
        await expect(page.locator('body')).toContainText(dealName);
    });

    test('ouvrir un deal existant par URL directe', async ({ page }) => {
        await page.goto('/deals');
        await page.waitForLoadState('networkidle');

        // Extraire l'ID du premier deal depuis l'attribut onclick de la première ligne
        const firstRowOnclick = await page.locator('table.t tbody tr').first()
            .getAttribute('onclick');

        // onclick = "window.location='/deals/42'"
        const match = firstRowOnclick?.match(/\/deals\/(\d+)/);
        expect(match).not.toBeNull();

        const dealId = match![1];
        await page.goto(`/deals/${dealId}`);
        await page.waitForLoadState('networkidle');

        await expect(page.locator('body')).toContainText('Marquer');
    });

    test('marquer un deal ouvert comme gagné', async ({ page }) => {
        await page.goto('/deals');
        await page.waitForLoadState('networkidle');

        // Extraire l'ID d'un deal ouvert depuis la première ligne
        const firstRowOnclick = await page.locator('table.t tbody tr').first()
            .getAttribute('onclick');
        const match = firstRowOnclick?.match(/\/deals\/(\d+)/);
        expect(match).not.toBeNull();

        const dealId = match![1];
        await page.goto(`/deals/${dealId}`);
        await page.waitForLoadState('networkidle');

        // Attendre que le drawer soit stable
        const wonBtn = page.locator('button:has-text("Marquer gagné")');
        await wonBtn.waitFor({ state: 'visible', timeout: 10000 });

        if (await wonBtn.isVisible()) {
            await wonBtn.click({ force: true });
            await page.waitForURL(url => url.pathname === '/deals', { timeout: 15000 });
        } else {
            test.skip();
        }
    });
});
