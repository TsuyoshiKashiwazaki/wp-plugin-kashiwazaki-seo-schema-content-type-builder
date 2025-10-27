const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  try {
    // WordPressにログイン
    console.log('Logging in to WordPress...');
    await page.goto('http://localhost/wp-login.php');
    await page.fill('#user_login', 'debug');
    await page.fill('#user_pass', 'debug');
    await page.click('#wp-submit');
    await page.waitForLoadState('networkidle');

    // プラグイン設定ページにアクセス
    console.log('\nAccessing plugin settings...');
    await page.goto('http://localhost/wp-admin/admin.php?page=kssctb-settings');
    await page.waitForLoadState('networkidle');

    // WebPageタブをクリック
    await page.click('a[href="#webpage"]');
    await page.waitForTimeout(500);

    // WebPageの設定を確認
    const webpageSettings = await page.evaluate(() => {
      const enabled = document.querySelector('input[name="kssctb_settings[webpage][enabled]"]');
      const archiveTypes = Array.from(document.querySelectorAll('input[name="kssctb_settings[webpage][archive_types][]"]:checked'));

      return {
        enabled: enabled ? enabled.checked : false,
        archiveTypes: archiveTypes.map(cb => cb.value)
      };
    });

    console.log('\n=== WebPage Schema Settings ===');
    console.log('Enabled:', webpageSettings.enabled);
    console.log('Archive Types:', webpageSettings.archiveTypes);
    console.log('Has "home"?:', webpageSettings.archiveTypes.includes('home'));

  } catch (error) {
    console.error('Error:', error);
  } finally {
    await browser.close();
  }
})();
