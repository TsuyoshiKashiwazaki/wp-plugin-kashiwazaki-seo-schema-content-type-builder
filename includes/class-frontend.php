<?php
if (!defined('ABSPATH')) {
    exit;
}

class KSSCTB_Frontend {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_head', array($this, 'output_schema'), 5);
    }
    
    public function output_schema() {
        $schemas = array();
        
        // デバッグログの有効/無効を取得
        $all_settings = KSSCTB_Settings::get_instance()->get_all_settings();
        $enable_debug = isset($all_settings['general']['enable_debug_log']) ? $all_settings['general']['enable_debug_log'] : false;
        
        if (is_singular()) {
            // 個別投稿・固定ページの処理
            // より堅牢な投稿取得方法を使用
            $post = get_queried_object();

            // 投稿オブジェクトとステータスを検証
            if (!$post || !isset($post->post_status) || $post->post_status !== 'publish') {
                return;
            }

            $post_type = get_post_type($post);
            $settings = KSSCTB_Settings::get_instance()->get_all_settings();
            
            // デバッグログ
            if ($enable_debug) {
                error_log('KSSCTB: Processing post type: ' . $post_type);
                error_log('KSSCTB: Settings: ' . print_r($settings, true));
            }
            
            foreach ($settings as $content_type => $type_settings) {
                if (!empty($type_settings['enabled']) && 
                    !empty($type_settings['post_types']) && 
                    in_array($post_type, $type_settings['post_types'])) {
                    
                    if ($enable_debug) {
                        error_log('KSSCTB: Generating schema for content type: ' . $content_type);
                    }
                    
                    $generator = KSSCTB_Schema_Generator::get_instance();
                    $schema = $generator->generate($content_type, $post->ID, $type_settings);
                    
                    if ($schema) {
                        $schemas[] = $schema;
                    }
                }
            }
        } elseif (is_archive() || is_home()) {
            // アーカイブページの処理
            $archive_type = $this->get_current_archive_type();
            
            if ($archive_type) {
                $settings = KSSCTB_Settings::get_instance()->get_all_settings();
                
                // WebPageスキーマをアーカイブページに適用
                if (!empty($settings['webpage']['enabled']) && 
                    !empty($settings['webpage']['archive_types']) && 
                    in_array($archive_type, $settings['webpage']['archive_types'])) {
                    
                    $generator = KSSCTB_Schema_Generator::get_instance();
                    $schema = $generator->generate_archive_schema($archive_type, $settings['webpage']);
                    
                    if ($schema) {
                        $schemas[] = $schema;
                    }
                }
            }
        }
        
        if (!empty($schemas)) {
            $output = array();
            
            if (count($schemas) === 1) {
                $output = $schemas[0];
            } else {
                $output = array(
                    '@context' => 'https://schema.org',
                    '@graph' => $schemas
                );
            }
            
            // 常に見やすい形式で出力（JSON_PRETTY_PRINTを常に有効）
            $json_flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
            
            $json = json_encode($output, $json_flags);
            
            if ($json === false) {
                if ($enable_debug) {
                    error_log('KSSCTB: JSON encoding failed - ' . json_last_error_msg());
                }
                return;
            }
            
            // プラグインの出力であることがわかるコメントタグ付きで見やすく出力
            echo "\n<!-- Kashiwazaki SEO Schema Content Type Builder - Structured Data -->\n";
            echo '<script type="application/ld+json">' . "\n";
            echo $json . "\n";
            echo '</script>' . "\n";
            echo "<!-- /Kashiwazaki SEO Schema Content Type Builder -->\n";
        }
    }
    
    private function get_current_archive_type() {
        if (is_category()) {
            return 'category';
        } elseif (is_tag()) {
            return 'post_tag';
        } elseif (is_author()) {
            return 'author';
        } elseif (is_date()) {
            return 'date';
        } elseif (is_tax()) {
            // カスタムタクソノミー
            $queried_object = get_queried_object();
            if ($queried_object && isset($queried_object->taxonomy)) {
                return $queried_object->taxonomy;
            }
        } elseif (is_post_type_archive()) {
            // カスタム投稿タイプのアーカイブ
            $post_type = get_query_var('post_type');
            if (is_array($post_type)) {
                $post_type = reset($post_type);
            }
            return 'post_type_archive_' . $post_type;
        } elseif (is_home()) {
            return 'home';
        }

        return false;
    }
} 