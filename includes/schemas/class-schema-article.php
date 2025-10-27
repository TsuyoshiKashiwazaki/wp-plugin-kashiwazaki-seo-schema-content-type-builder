<?php
if (!defined('ABSPATH')) {
    exit;
}

class KSSCTB_Schema_Article {
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
            '@type' => 'Article'
        );
        
        // 識別子
        if (isset($schema['@id'])) {
            $ordered_schema['@id'] = $schema['@id'];
        }
        
        // 基本情報
        if (isset($schema['headline'])) {
            $ordered_schema['headline'] = $schema['headline'];
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
        
        // Article固有のプロパティ
        if (!empty($settings['article_section'])) {
            $ordered_schema['articleSection'] = sanitize_text_field($settings['article_section']);
        } else {
            $categories = get_the_category($post->ID);
            if ($categories && !is_wp_error($categories) && !empty($categories)) {
                $sections = array();
                foreach ($categories as $category) {
                    $sections[] = $category->name;
                }
                if (!empty($sections)) {
                    $ordered_schema['articleSection'] = implode(', ', array_slice($sections, 0, 3));
                }
            }
        }
        
        $content = wp_strip_all_tags($post->post_content);
        
        // 本文
        if (strlen($content) > 5000) {
            $ordered_schema['articleBody'] = mb_substr($content, 0, 5000) . '...';
        } else {
            $ordered_schema['articleBody'] = $content;
        }
        
        // 単語数
        $word_count = $generator->count_words_multilingual($content);
        if ($word_count > 0) {
            $ordered_schema['wordCount'] = $word_count;
        }
        
        // キーワード
        if (isset($schema['keywords'])) {
            $ordered_schema['keywords'] = $schema['keywords'];
        }
        
        // パンくずリスト（一般設定で有効な場合）
        $all_settings = KSSCTB_Settings::get_instance()->get_all_settings();
        if (!empty($all_settings['general']['enable_breadcrumb'])) {
            $breadcrumb = $generator->get_breadcrumb_schema();
            if ($breadcrumb) {
                $ordered_schema['breadcrumb'] = $breadcrumb;
            }
        }
        
        // メタデータ
        if (isset($schema['url'])) {
            $ordered_schema['url'] = $schema['url'];
        }
        
        if (isset($schema['mainEntityOfPage'])) {
            $ordered_schema['mainEntityOfPage'] = $schema['mainEntityOfPage'];
        }
        
        if (isset($schema['inLanguage'])) {
            $ordered_schema['inLanguage'] = $schema['inLanguage'];
        }
        
        return $ordered_schema;
    }
} 