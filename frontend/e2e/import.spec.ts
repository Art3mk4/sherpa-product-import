import { expect, test } from '@playwright/test';

const ADMIN = { email: 'admin@example.com', password: 'admin123' };

test.beforeEach(async ({ page }) => {
  // все сценарии идут авторизованными (кроме первого — он про редирект)
  await page.goto('/login');
  await page.getByLabel('Email').fill(ADMIN.email);
  await page.getByLabel('Пароль').fill(ADMIN.password);
  await page.getByRole('button', { name: 'Войти' }).click();
  await page.waitForURL('**/products');
});

test('1. неавторизованный /products редиректит на /login', async ({ page }) => {
  // beforeEach уже вошёл — выходим и проверяем редирект
  await page.getByRole('button', { name: 'Выйти' }).click();
  await page.waitForURL('**/login');

  await page.goto('/products');
  await page.waitForURL('**/login**');
  await expect(page.getByText('Вход')).toBeVisible();
});

test('2. логин → импорт xlsx → прогресс → «Завершён» с 40 товарами', async ({ page }) => {
  await page.goto('/import');

  const fileInput = page.locator('input[type="file"]');
  await fileInput.setInputFiles('e2e/fixtures/import_example.xlsx');
  await expect(page.getByText('import_example.xlsx')).toBeVisible();

  await page.getByRole('button', { name: 'Импортировать' }).click();

  // прогресс: счётчики появляются, статус доходит до «Завершён»
  await expect(page.getByText(/Обработано \d+ из 40/).first()).toBeVisible({ timeout: 30_000 });
  await expect(page.getByText('Завершён')).toBeVisible({ timeout: 60_000 });
  await expect(page.getByText(/успешно 40/)).toBeVisible();
});

test('3. список: поиск «легинсы», фильтр цены, пагинация', async ({ page }) => {
  await page.goto('/products');

  // поиск по названию
  await page.getByLabel('Название').fill('легинсы');
  await page.getByRole('button', { name: 'Найти' }).click();
  await expect(page.locator('tr[mat-row]').first()).toBeVisible();
  const rows = page.locator('tr[mat-row]');
  const count = await rows.count();
  for (let i = 0; i < count; i++) {
    await expect(rows.nth(i)).toContainText(/[легинсы|Легинсы]/);
  }

  // сброс + фильтр цены 1500–2000
  await page.getByRole('button', { name: 'Сбросить' }).click();
  await page.getByLabel('Цена от').fill('1500');
  await page.getByLabel('Цена до').fill('2000');
  await page.getByRole('button', { name: 'Найти' }).click();

  await expect(page.locator('tr[mat-row]').first()).toBeVisible();
  expect(await rows.count()).toBeGreaterThan(0);

  // пагинация: при 40 товарах и странице 20 — вторая страница содержит 21–40
  await page.getByRole('button', { name: 'Сбросить' }).click();
  await expect(page.locator('tr[mat-row]').first()).toBeVisible();
  const paginator = page.locator('mat-paginator');
  await paginator.getByRole('button', { name: /Следующая страница|Next page/ }).click();
  await expect(paginator.locator('.mat-mdc-paginator-range-label')).toContainText('21 –');
});

test('4. карточка товара: поля, атрибуты, блок изображений', async ({ page }) => {
  await page.goto('/products');
  await expect(page.locator('tr[mat-row]').first()).toBeVisible();

  await page.locator('tr[mat-row]').first().click();
  await page.waitForURL(/\/products\/\d+/);

  await expect(page.getByText('Внешний код').or(page.locator('.code'))).toBeVisible();
  await expect(page.getByText('Дополнительные атрибуты')).toBeVisible();
  await expect(page.getByText('Изображения')).toBeVisible();
  // атрибут «Размер»/«Бренд» присутствует у импортированных товаров
  await expect(page.locator('table.attributes').first()).toBeVisible();
});

test('5. загрузка .xlsx-неправильного расширения → ошибка валидации', async ({ page }) => {
  await page.goto('/import');

  await page.locator('input[type="file"]').setInputFiles({
    name: 'import.txt',
    mimeType: 'text/plain',
    buffer: Buffer.from('это не xlsx'),
  });

  await expect(page.getByText('Допустимо только расширение .xlsx')).toBeVisible();
});
