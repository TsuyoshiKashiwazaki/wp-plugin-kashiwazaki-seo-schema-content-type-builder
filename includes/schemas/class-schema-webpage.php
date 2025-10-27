<?php
if (!defined('ABSPATH')) {
    exit;
}

class KSSCTB_Schema_WebPage {
    public function generate($post_id, $settings) {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return null;
        }
        
        $generator = KSSCTB_Schema_Generator::get_instance();
        
        $schema = $generator->get_common_properties($post, $settings);
        if (!$schema) {
            return null;
        }
        
        // Schema.orgとGoogleの推奨順序に従ってプロパティを整理
        $ordered_schema = array(
            '@context' => $schema['@context'],
            '@type' => 'WebPage'
        );
        
        // 識別子
        if (isset($schema['@id'])) {
            $ordered_schema['@id'] = $schema['@id'];
        }
        
        // 基本情報
        if (isset($schema['headline'])) {
            $ordered_schema['name'] = $schema['headline'];
        }

        // 説明文
        if (isset($schema['description'])) {
            $ordered_schema['description'] = $schema['description'];
        }

        // 画像
        if (isset($schema['image'])) {
            $ordered_schema['image'] = $schema['image'];
        }
        
        // 日付情報
        if (isset($schema['datePublished'])) {
            $ordered_schema['datePublished'] = $schema['datePublished'];
        }
        
        if (isset($schema['dateModified'])) {
            $ordered_schema['dateModified'] = $schema['dateModified'];
        }
        
        // 著者と発行者
        if (isset($schema['author'])) {
            $ordered_schema['author'] = $schema['author'];
        }
        
        if (isset($schema['publisher'])) {
            $ordered_schema['publisher'] = $schema['publisher'];
        }
        
        // スポンサー
        if (isset($schema['sponsor'])) {
            $ordered_schema['sponsor'] = $schema['sponsor'];
        }
        
        // WebPage固有のプロパティ
        // WebPageタイプではmainEntityOfPageは通常含めない
        // パンくずリスト（一般設定で有効な場合）
        $all_settings = KSSCTB_Settings::get_instance()->get_all_settings();
        if (!empty($all_settings['general']['enable_breadcrumb'])) {
            $breadcrumb = $generator->get_breadcrumb_schema();
            if ($breadcrumb) {
                $ordered_schema['breadcrumb'] = $breadcrumb;
            }
        }
        
        // Speakable（設定で有効な場合）
        if (!empty($settings['enable_speakable'])) {
            $ordered_schema['speakable'] = array(
                '@type' => 'SpeakableSpecification',
                'cssSelector' => array('.entry-content', 'article', 'main')
            );
        }
        
        // メタデータ
        if (isset($schema['url'])) {
            $ordered_schema['url'] = $schema['url'];
        }
        
        if (isset($schema['inLanguage'])) {
            $ordered_schema['inLanguage'] = $schema['inLanguage'];
        }
        
        return $ordered_schema;
    }
} 