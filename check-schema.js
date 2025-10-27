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

    // トップページにアクセス
    console.log('\nAccessing top page...');
    await page.goto('http://localhost/');
    await page.waitForLoadState('networkidle');

    // ページのHTML全体を取得
    const html = await page.content();

    // JSON-LD構造化データを抽出
    const schemas = await page.evaluate(() => {
      const scripts = Array.from(document.querySelectorAll('script[type="application/ld+json"]'));
      return scripts.map(script => {
        try {
          return {
            content: script.textContent,
            parsed: JSON.parse(script.textContent)
          };
        } catch (e) {
          return {
            content: script.textContent,
            error: e.message
          };
        }
      });
    });

    console.log('\n=== JSON-LD Structured Data Found ===');
    console.log(`Number of schema blocks: ${schemas.length}`);

    schemas.forEach((schema, index) => {
      console.log(`\n--- Schema Block ${index + 1} ---`);
      if (schema.error) {
        console.log('Parse Error:', schema.error);
        console.log('Raw Content:', schema.content);
      } else {
        console.log('Type:', schema.parsed['@type']);
        if (schema.parsed['@graph']) {
          console.log('Graph with', schema.parsed['@graph'].length, 'items:');
          schema.parsed['@graph'].forEach((item, i) => {
            console.log(`  ${i + 1}. ${item['@type']}`);
          });
        }
        console.log('\nFull JSON:');
        console.log(JSON.stringify(schema.parsed, null, 2));
      }
    });

    // プラグインのコメントタグを検索
    const pluginComment = html.match(/<!-- Kashiwazaki SEO Schema Content Type Builder.*?<!-- \/Kashiwazaki SEO Schema Content Type Builder -->/s);
    if (pluginComment) {
      console.log('\n=== Plugin Output Found ===');
      console.log(pluginComment[0]);
    } else {
      console.log('\n=== No Plugin Output Found ===');
    }

    // ページタイトルとURL
    console.log('\n=== Page Info ===');
    console.log('URL:', page.url());
    console.log('Title:', await page.title());

    // WordPressのホームページ設定を確認
    console.log('\n=== Checking WordPress Settings ===');
    await page.goto('http://localhost/wp-admin/options-reading.php');
    await page.waitForLoadState('networkidle');

    const homepageSettings = await page.evaluate(() => {
      const showOnFront = document.querySelector('input[name="show_on_front"]:checked');
      const pageOnFront = document.querySelector('select[name="page_on_front"]');
      const pageForPosts = document.querySelector('select[name="page_for_posts"]');

      return {
        showOnFront: showOnFront ? showOnFront.value : 'unknown',
        pageOnFront: pageOnFront ? pageOnFront.options[pageOnFront.selectedIndex].text : 'N/A',
        pageForPosts: pageForPosts ? pageForPosts.options[pageForPosts.selectedIndex].text : 'N/A'
      };
    });

    console.log('Homepage displays:', homepageSettings.showOnFront);
    console.log('Homepage:', homepageSettings.pageOnFront);
    console.log('Posts page:', homepageSettings.pageForPosts);

  } catch (error) {
    console.error('Error:', error);
  } finally {
    await browser.close();
  }
})();
