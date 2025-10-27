<?php
/**
 * Plugin Name: Kashiwazaki SEO Schema Content Type Builder
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Description: コンテンツタイプ別に構造化データ（JSON-LD）を自動生成するSEOプラグイン。Article、NewsArticle、BlogPosting、WebPageの各スキーマに対応し、パンくずリストも含めた包括的な構造化データを出力します。
 * Version:     1.0.0
 * Author:      柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI:  https://www.tsuyoshikashiwazaki.jp/profile/
 * Text Domain: kashiwazaki-seo-schema-content-type-builder
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KSSCTB_VERSION', '1.0.0');
define('KSSCTB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KSSCTB_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * アセットのバージョンを取得（キャッシュバスティング用）
 * 
 * @return string バージョン文字列
 */
function kssctb_get_asset_version() {
    $version = KSSCTB_VERSION;
    
    // 常にタイムスタンプを追加してキャッシュをクリア
    $version .= '.' . time();
    
    return $version;
}

class KSSCTB_Main {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once KSSCTB_PLUGIN_DIR . 'includes/class-settings.php';
        require_once KSSCTB_PLUGIN_DIR . 'includes/class-admin.php';
        require_once KSSCTB_PLUGIN_DIR . 'includes/class-schema-generator.php';
        require_once KSSCTB_PLUGIN_DIR . 'includes/class-frontend.php';
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'init'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    public function init() {
        KSSCTB_Settings::get_instance();
        
        if (is_admin()) {
            KSSCTB_Admin::get_instance();
        } else {
            KSSCTB_Frontend::get_instance();
        }
    }
    
    public function activate() {
        KSSCTB_Settings::get_instance()->create_default_settings();
    }
    
    public function deactivate() {
    }

    /**
     * プラグイン一覧画面に設定リンクを追加
     *
     * @param array $links 既存のアクションリンク
     * @return array 設定リンクを追加したアクションリンク
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=kssctb-settings">' . __('設定', 'kashiwazaki-seo-schema-content-type-builder') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

KSSCTB_Main::get_instance(); 