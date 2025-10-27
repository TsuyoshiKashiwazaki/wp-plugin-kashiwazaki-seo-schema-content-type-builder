<?php
if (!defined('ABSPATH')) {
    exit;
}

class KSSCTB_Schema_Generator {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_schema_types();
    }

    private function load_schema_types() {
        require_once KSSCTB_PLUGIN_DIR . 'includes/schemas/class-schema-article.php';
        require_once KSSCTB_PLUGIN_DIR . 'includes/schemas/class-schema-newsarticle.php';
        require_once KSSCTB_PLUGIN_DIR . 'includes/schemas/class-schema-blogposting.php';
        require_once KSSCTB_PLUGIN_DIR . 'includes/schemas/class-schema-webpage.php';
    }

    public function generate_schema($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        if (!$post_id) {
            return null;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return null;
        }

        $content_type = $this->get_content_type_for_post($post);
        if (!$content_type) {
            return null;
        }

        $schema_class = $this->get_schema_class($content_type);
        if (!$schema_class || !class_exists($schema_class)) {
            return null;
        }

        $schema_instance = new $schema_class();
        return $schema_instance->generate($post);
    }

    private function get_content_type_for_post($post) {
        $settings = KSSCTB_Settings::get_instance()->get_all_settings();
        $post_type = $post->post_type;

        $content_types = array('article', 'newsarticle', 'blogposting', 'webpage');

        foreach ($content_types as $content_type) {
            if (isset($settings[$content_type]['post_types']) &&
                is_array($settings[$content_type]['post_types']) &&
                in_array($post_type, $settings[$content_type]['post_types'])) {
                return $content_type;
            }
        }

        return null;
    }

    private function get_schema_class($content_type) {
        $class_map = array(
            'article' => 'KSSCTB_Schema_Article',
            'newsarticle' => 'KSSCTB_Schema_NewsArticle',
            'blogposting' => 'KSSCTB_Schema_BlogPosting',
            'webpage' => 'KSSCTB_Schema_WebPage'
        );

        return isset($class_map[$content_type]) ? $class_map[$content_type] : null;
    }

    public function get_common_properties($post, $settings) {
        $properties = array(
            '@context' => 'https://schema.org',
            '@type' => '', // This will be set by each schema class
            '@id' => get_permalink($post->ID),
            'url' => get_permalink($post->ID),
            'headline' => mb_substr(wp_strip_all_tags(get_the_title($post->ID)), 0, 110),
            'datePublished' => get_the_date('c', $post->ID),
            'dateModified' => get_the_modified_date('c', $post->ID)
        );

        // mainEntityOfPageは、この記事が主要コンテンツであるWebPageを示します
        // これはGoogle推奨の実装で、ArticleやBlogPostingでも使用されます
        $properties['mainEntityOfPage'] = array(
            '@type' => 'WebPage',
            '@id' => get_permalink($post->ID)
        );

        $author = $this->get_author_data($post, $settings);
        if ($author) {
            $properties['author'] = $author;
        }

        $publisher = $this->get_publisher_data($settings);
        if ($publisher) {
            $properties['publisher'] = $publisher;
        }

        $sponsor = $this->get_sponsor_data($settings);
        if ($sponsor) {
            $properties['sponsor'] = $sponsor;
        }

        $images = $this->get_image_data($post);
        if ($images) {
            $properties['image'] = $images;
        }

        $keywords = get_the_tags($post->ID);
        if ($keywords && !is_wp_error($keywords)) {
            $properties['keywords'] = implode(', ', wp_list_pluck($keywords, 'name'));
        }

        // Description（抜粋または本文の冒頭160文字）
        $description = '';
        if (has_excerpt($post->ID)) {
            $description = wp_strip_all_tags(get_the_excerpt($post->ID));
        } else {
            $content = wp_strip_all_tags($post->post_content);
            $description = mb_substr($content, 0, 160);
        }

        if (!empty($description)) {
            $properties['description'] = $description;
        }

        $properties['inLanguage'] = get_locale();

        return $properties;
    }

    /**
     * 日本語を含むテキストの単語数をカウント
     *
     * @param string $text カウント対象のテキスト
     * @return int 単語数
     */
    public function count_words_multilingual($text) {
        // HTMLタグとエンティティを除去
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 空白文字を正規化
        $text = preg_replace('/\s+/u', ' ', trim($text));

        if (empty($text)) {
            return 0;
        }

        // 日本語（ひらがな、カタカナ、漢字）を検出
        $has_japanese = preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FAF}]/u', $text);

        if ($has_japanese) {
            // 日本語を含む場合の処理
            $word_count = 0;

            // 英数字の単語をカウント
            preg_match_all('/[a-zA-Z0-9]+/u', $text, $matches);
            $word_count += count($matches[0]);

            // 日本語の文字を単語として扱う
            // 句読点、記号、空白を除外
            $japanese_text = preg_replace('/[^\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FAF}\x{3005}]/u', '', $text);

            // 形態素解析の簡易版：連続する漢字、ひらがな、カタカナをそれぞれ1単語として数える
            // より正確にするには MeCab などの形態素解析エンジンが必要
            preg_match_all('/[\x{4E00}-\x{9FAF}\x{3005}]+|[\x{3040}-\x{309F}]+|[\x{30A0}-\x{30FF}]+/u', $text, $japanese_matches);
            $word_count += count($japanese_matches[0]);

            return $word_count;
        } else {
            // 英語のみの場合は標準のstr_word_countを使用
            return str_word_count($text);
        }
    }

    private function get_author_data($post, $settings) {
        $author_id = $post->post_author;
        $author_type = $settings['default_author_type'] ?? 'none';

        // 'none'の場合はAuthorを出力しない
        if ($author_type === 'none' || empty($author_type)) {
            return null;
        }

        $author_data = array('@type' => $author_type);

        switch ($author_type) {
            case 'Person':
                // Person Author - 設定に基づいてユーザーを判別
                if (!empty($settings['author_person_user'])) {
                    // 特定ユーザーが設定されている場合
                    $target_user_id = $settings['author_person_user'];
                } else {
                    // 「投稿者を使用」の場合、実際の投稿者を使用
                    $target_user_id = $author_id;
                }

                $author_name = get_the_author_meta('display_name', $target_user_id);
                if ($author_name) {
                    $author_data['name'] = $author_name;
                } else {
                    return null;
                }

                // URL（常に実際の投稿者のアーカイブURLを使用）
                $author_url = get_author_posts_url($author_id);
                if ($author_url) {
                    $author_data['url'] = $author_url;
                }

                // 画像（対象ユーザーのuser_metaから取得、なければGravatar）
                $custom_image = get_user_meta($target_user_id, 'kssctb_profile_image', true);
                if (!empty($custom_image)) {
                    // ユーザー個別の画像が設定されている場合
                    $author_data['image'] = array(
                        '@type' => 'ImageObject',
                        'url' => $custom_image
                    );
                } else {
                    // 個別画像がない場合はGravatarを取得
                    $author_email = get_the_author_meta('user_email', $target_user_id);
                    if ($author_email) {
                        $gravatar_url = get_avatar_url($author_email, array('size' => 200));
                        if ($gravatar_url) {
                            $author_data['image'] = array(
                                '@type' => 'ImageObject',
                                'url' => $gravatar_url
                            );
                        }
                    }
                }

                // Person拡張フィールドを追加（対象ユーザーのuser_metaから取得）
                $job_title = get_user_meta($target_user_id, 'kssctb_job_title', true);
                if (!empty($job_title)) {
                    $author_data['jobTitle'] = $job_title;
                }

                $works_for = get_user_meta($target_user_id, 'kssctb_works_for', true);
                if (!empty($works_for)) {
                    $author_data['worksFor'] = $works_for;
                }

                $description = get_user_meta($target_user_id, 'kssctb_description', true);
                if (!empty($description)) {
                    $author_data['description'] = $description;
                }

                $address = get_user_meta($target_user_id, 'kssctb_address', true);
                if (!empty($address)) {
                    $author_data['address'] = $address;
                }

                $telephone = get_user_meta($target_user_id, 'kssctb_telephone', true);
                if (!empty($telephone)) {
                    $author_data['telephone'] = $telephone;
                }

                $knows_about = get_user_meta($target_user_id, 'kssctb_knows_about', true);
                if (!empty($knows_about)) {
                    $author_data['knowsAbout'] = $knows_about;
                }

                $alumni_of = get_user_meta($target_user_id, 'kssctb_alumni_of', true);
                if (!empty($alumni_of)) {
                    $author_data['alumniOf'] = $alumni_of;
                }
                break;

            case 'Organization':
                // Organization Author
                if (!empty($settings['author_organization_name'])) {
                    $author_data['name'] = $settings['author_organization_name'];
                } else {
                    return null;
                }

                if (!empty($settings['author_organization_url'])) {
                    $author_data['url'] = $settings['author_organization_url'];
                }

                if (!empty($settings['author_organization_logo'])) {
                    $author_data['logo'] = array(
                        '@type' => 'ImageObject',
                        'url' => $settings['author_organization_logo']
                    );
                    // リッチリザルトテスト対応：imageプロパティも追加
                    $author_data['image'] = array(
                        '@type' => 'ImageObject',
                        'url' => $settings['author_organization_logo']
                    );
                }

                // Organization拡張フィールドを追加
                $address = $this->get_structured_address($settings, 'author_organization_');
                if ($address) {
                    $author_data['address'] = $address;
                }

                if (!empty($settings['author_organization_telephone'])) {
                    $author_data['telephone'] = $settings['author_organization_telephone'];
                }



                if (!empty($settings['author_organization_description'])) {
                    $author_data['description'] = $settings['author_organization_description'];
                }

                if (!empty($settings['author_organization_legal_name'])) {
                    $author_data['legalName'] = $settings['author_organization_legal_name'];
                }

                if (!empty($settings['author_organization_founding_date'])) {
                    $author_data['foundingDate'] = $settings['author_organization_founding_date'];
                }

                if (!empty($settings['author_organization_number_of_employees'])) {
                    $author_data['numberOfEmployees'] = $settings['author_organization_number_of_employees'];
                }

                if (!empty($settings['author_organization_same_as'])) {
                    $author_data['sameAs'] = $settings['author_organization_same_as'];
                }
                break;

            case 'Corporation':
                // Corporation Author
                if (!empty($settings['author_corporation_name'])) {
                    $author_data['name'] = $settings['author_corporation_name'];
                } else {
                    return null;
                }

                if (!empty($settings['author_corporation_url'])) {
                    $author_data['url'] = $settings['author_corporation_url'];
                }

                if (!empty($settings['author_corporation_logo'])) {
                    $author_data['logo'] = array(
                        '@type' => 'ImageObject',
                        'url' => $settings['author_corporation_logo']
                    );
                    // リッチリザルトテスト対応：imageプロパティも追加
                    $author_data['image'] = array(
                        '@type' => 'ImageObject',
                        'url' => $settings['author_corporation_logo']
                    );
                }

                // 新しいフィールドを追加
                $address = $this->get_structured_address($settings, 'author_corporation_');
                if ($address) {
                    $author_data['address'] = $address;
                }

                if (!empty($settings['author_corporation_telephone'])) {
                    $author_data['telephone'] = $settings['author_corporation_telephone'];
                }

                if (!empty($settings['author_corporation_description'])) {
                    $author_data['description'] = $settings['author_corporation_description'];
                }

                // 代表者情報
                if (!empty($settings['author_corporation_representative_name'])) {
                    $representative = array(
                        '@type' => 'Person',
                        'name' => $settings['author_corporation_representative_name']
                    );

                    if (!empty($settings['author_corporation_representative_title'])) {
                        $representative['jobTitle'] = $settings['author_corporation_representative_title'];
                    }

                    $author_data['founder'] = $representative;
                }

                if (!empty($settings['author_corporation_founding_date'])) {
                    $author_data['foundingDate'] = $settings['author_corporation_founding_date'];
                }

                if (!empty($settings['author_corporation_corporate_number'])) {
                    $author_data['taxID'] = $settings['author_corporation_corporate_number'];
                }

                if (!empty($settings['author_corporation_social_media'])) {
                    $author_data['sameAs'] = $settings['author_corporation_social_media'];
                }
                break;

            default:
                return null;
        }

        return $author_data;
    }

    private function get_publisher_data($settings) {
        $publisher_type = $settings['publisher_type'] ?? 'none';

        // 'none'の場合はPublisherを出力しない
        if ($publisher_type === 'none' || empty($publisher_type)) {
            return null;
        }

        $publisher = array('@type' => $publisher_type);

        switch ($publisher_type) {
            case 'Person':
                // Person Publisher - ユーザーIDが指定されている場合
                if (!empty($settings['publisher_person_user'])) {
                    $user_id = $settings['publisher_person_user'];
                    $publisher_name = get_the_author_meta('display_name', $user_id);

                    if ($publisher_name) {
                        $publisher['name'] = $publisher_name;

                        // URL（カスタム設定優先、なければユーザーアーカイブURL）
                        if (!empty($settings['publisher_person_url'])) {
                            $publisher['url'] = $settings['publisher_person_url'];
                        } else {
                            $publisher_url = get_author_posts_url($user_id);
                            if ($publisher_url) {
                                $publisher['url'] = $publisher_url;
                            }
                        }

                        // 画像（カスタム設定優先、なければGravatar）
                        if (!empty($settings['publisher_person_image'])) {
                            $publisher['image'] = array(
                                '@type' => 'ImageObject',
                                'url' => $settings['publisher_person_image']
                            );
                        } else {
                            // Gravatarを取得
                            $user_email = get_the_author_meta('user_email', $user_id);
                            if ($user_email) {
                                $gravatar_url = get_avatar_url($user_email, array('size' => 200));
                                if ($gravatar_url) {
                                    $publisher['image'] = array(
                                        '@type' => 'ImageObject',
                                        'url' => $gravatar_url
                                    );
                                }
                            }
                        }

                        // Person拡張フィールドを追加
                        if (!empty($settings['publisher_person_job_title'])) {
                            $publisher['jobTitle'] = $settings['publisher_person_job_title'];
                        }

                        if (!empty($settings['publisher_person_works_for'])) {
                            $publisher['worksFor'] = $settings['publisher_person_works_for'];
                        }

                        if (!empty($settings['publisher_person_description'])) {
                            $publisher['description'] = $settings['publisher_person_description'];
                        }

                        if (!empty($settings['publisher_person_address'])) {
                            $publisher['address'] = $settings['publisher_person_address'];
                        }

                        if (!empty($settings['publisher_person_telephone'])) {
                            $publisher['telephone'] = $settings['publisher_person_telephone'];
                        }

                        if (!empty($settings['publisher_person_knows_about'])) {
                            $publisher['knowsAbout'] = $settings['publisher_person_knows_about'];
                        }

                        if (!empty($settings['publisher_person_alumni_of'])) {
                            $publisher['alumniOf'] = $settings['publisher_person_alumni_of'];
                        }
                    } else {
                        return null;
                    }
                } else {
                    return null;
                }
                break;

            case 'Organization':
                // Organization Publisher
                if (!empty($settings['publisher_organization_name'])) {
                    $publisher['name'] = $settings['publisher_organization_name'];
                } else {
                    return null;
                }

                if (!empty($settings['publisher_organization_url'])) {
                    $publisher['url'] = $settings['publisher_organization_url'];
                }

                if (!empty($settings['publisher_organization_logo'])) {
                    $publisher['logo'] = array(
                        '@type' => 'ImageObject',
                        'url' => $settings['publisher_organization_logo']
                    );
                    // リッチリザルトテスト対応：imageプロパティも追加
                    $publisher['image'] = array(
                        '@type' => 'ImageObject',
                        'url' => $settings['publisher_organization_logo']
                    );
                }

                // Organization拡張フィールドを追加
                $address = $this->get_structured_address($settings, 'publisher_organization_');
                if ($address) {
                    $publisher['address'] = $address;
                }

                if (!empty($settings['publisher_organization_telephone'])) {
                    $publisher['telephone'] = $settings['publisher_organization_telephone'];
                }



                if (!empty($settings['publisher_organization_description'])) {
                    $publisher['description'] = $settings['publisher_organization_description'];
                }

                if (!empty($settings['publisher_organization_legal_name'])) {
                    $publisher['legalName'] = $settings['publisher_organization_legal_name'];
                }

                if (!empty($settings['publisher_organization_founding_date'])) {
                    $publisher['foundingDate'] = $settings['publisher_organization_founding_date'];
                }

                if (!empty($settings['publisher_organization_number_of_employees'])) {
                    $publisher['numberOfEmployees'] = $settings['publisher_organization_number_of_employees'];
                }

                if (!empty($settings['publisher_organization_same_as'])) {
                    $publisher['sameAs'] = $settings['publisher_organization_same_as'];
                }
                break;

            case 'Corporation':
                // Corporation Publisher
                if (!empty($settings['publisher_corporation_name'])) {
                    $publisher['name'] = $settings['publisher_corporation_name'];
                } else {
                    return null;
                }

                if (!empty($settings['publisher_corporation_url'])) {
                    $publisher['url'] = $settings['publisher_corporation_url'];
                }

                if (!empty($settings['publisher_corporation_logo'])) {
                    $publisher['logo'] = array(
                        '@type' => 'ImageObject',
                        'url' => $settings['publisher_corporation_logo']
                    );
                    // リッチリザルトテスト対応：imageプロパティも追加
                    $publisher['image'] = array(
                        '@type' => 'ImageObject',
                        'url' => $settings['publisher_corporation_logo']
                    );
                }

                // 新しいフィールドを追加
                $address = $this->get_structured_address($settings, 'publisher_corporation_');
                if ($address) {
                    $publisher['address'] = $address;
                }

                if (!empty($settings['publisher_corporation_telephone'])) {
                    $publisher['telephone'] = $settings['publisher_corporation_telephone'];
                }

                if (!empty($settings['publisher_corporation_description'])) {
                    $publisher['description'] = $settings['publisher_corporation_description'];
                }

                // 代表者情報
                if (!empty($settings['publisher_corporation_representative_name'])) {
                    $representative = array(
                        '@type' => 'Person',
                        'name' => $settings['publisher_corporation_representative_name']
                    );

                    if (!empty($settings['publisher_corporation_representative_title'])) {
                        $representative['jobTitle'] = $settings['publisher_corporation_representative_title'];
                    }

                    $publisher['founder'] = $representative;
                }

                if (!empty($settings['publisher_corporation_founding_date'])) {
                    $publisher['foundingDate'] = $settings['publisher_corporation_founding_date'];
                }

                if (!empty($settings['publisher_corporation_corporate_number'])) {
                    $publisher['taxID'] = $settings['publisher_corporation_corporate_number'];
                }

                if (!empty($settings['publisher_corporation_social_media'])) {
                    $publisher['sameAs'] = $settings['publisher_corporation_social_media'];
                }
                break;

            default:
                return null;
        }

        return $publisher;
    }

    private function get_sponsor_data($settings) {
        $sponsor_type = $settings['sponsor_type'] ?? 'none';

        // 'none'の場合はSponsorを出力しない
        if ($sponsor_type === 'none' || empty($sponsor_type)) {
            return null;
        }

        // スポンサー名が設定されていない場合はnullを返す
        $has_sponsor = false;
        switch ($sponsor_type) {
            case 'Person':
                $has_sponsor = !empty($settings['sponsor_person_user']);
                break;
            case 'Organization':
                $has_sponsor = !empty($settings['sponsor_organization_name']);
                break;
            case 'Corporation':
                $has_sponsor = !empty($settings['sponsor_corporation_name']);
                break;
        }

        if (!$has_sponsor) {
            return null;
        }

        $sponsor = array('@type' => $sponsor_type);

        switch ($sponsor_type) {
            case 'Person':
                // Person Sponsor - ユーザーIDが指定されている場合
                if (!empty($settings['sponsor_person_user'])) {
                    $user_id = $settings['sponsor_person_user'];
                    $sponsor_name = get_the_author_meta('display_name', $user_id);

                    if ($sponsor_name) {
                        $sponsor['name'] = $sponsor_name;

                        // URL（カスタム設定優先、なければユーザーアーカイブURL）
                        if (!empty($settings['sponsor_person_url'])) {
                            $sponsor['url'] = $settings['sponsor_person_url'];
                        } else {
                            $sponsor_url = get_author_posts_url($user_id);
                            if ($sponsor_url) {
                                $sponsor['url'] = $sponsor_url;
                            }
                        }

                        // 画像（カスタム設定優先、なければGravatar）
                        if (!empty($settings['sponsor_person_image'])) {
                            $sponsor['image'] = array(
                                '@type' => 'ImageObject',
                                'url' => $settings['sponsor_person_image']
                            );
                        } else {
                            // Gravatarを取得
                            $user_email = get_the_author_meta('user_email', $user_id);
                            if ($user_email) {
                                $gravatar_url = get_avatar_url($user_email, array('size' => 200));
                                if ($gravatar_url) {
                                    $sponsor['image'] = array(
                                        '@type' => 'ImageObject',
                                        'url' => $gravatar_url
                                    );
                                }
                            }
                        }

                        // Person拡張フィールドを追加
                        if (!empty($settings['sponsor_person_job_title'])) {
                            $sponsor['jobTitle'] = $settings['sponsor_person_job_title'];
                        }

                        if (!empty($settings['sponsor_person_works_for'])) {
                            $sponsor['worksFor'] = $settings['sponsor_person_works_for'];
                        }

                        if (!empty($settings['sponsor_person_description'])) {
                            $sponsor['description'] = $settings['sponsor_person_description'];
                        }

                        if (!empty($settings['sponsor_person_address'])) {
                            $sponsor['address'] = $settings['sponsor_person_address'];
                        }

                        if (!empty($settings['sponsor_person_telephone'])) {
                            $sponsor['telephone'] = $settings['sponsor_person_telephone'];
                        }

                        if (!empty($settings['sponsor_person_knows_about'])) {
                            $sponsor['knowsAbout'] = $settings['sponsor_person_knows_about'];
                        }

                        if (!empty($settings['sponsor_person_alumni_of'])) {
                            $sponsor['alumniOf'] = $settings['sponsor_person_alumni_of'];
                        }
                    }
                }
                break;

            case 'Organization':
                // Organization Sponsor
                if (!empty($settings['sponsor_organization_name'])) {
                    $sponsor['name'] = $settings['sponsor_organization_name'];

                    if (!empty($settings['sponsor_organization_url'])) {
                        $sponsor['url'] = $settings['sponsor_organization_url'];
                    }

                    if (!empty($settings['sponsor_organization_logo'])) {
                        $sponsor['logo'] = array(
                            '@type' => 'ImageObject',
                            'url' => $settings['sponsor_organization_logo']
                        );
                        // リッチリザルトテスト対応：imageプロパティも追加
                        $sponsor['image'] = array(
                            '@type' => 'ImageObject',
                            'url' => $settings['sponsor_organization_logo']
                        );
                    }

                    // Organization拡張フィールドを追加
                    $address = $this->get_structured_address($settings, 'sponsor_organization_');
                    if ($address) {
                        $sponsor['address'] = $address;
                    }

                    if (!empty($settings['sponsor_organization_telephone'])) {
                        $sponsor['telephone'] = $settings['sponsor_organization_telephone'];
                    }



                    if (!empty($settings['sponsor_organization_description'])) {
                        $sponsor['description'] = $settings['sponsor_organization_description'];
                    }

                    if (!empty($settings['sponsor_organization_legal_name'])) {
                        $sponsor['legalName'] = $settings['sponsor_organization_legal_name'];
                    }

                    if (!empty($settings['sponsor_organization_founding_date'])) {
                        $sponsor['foundingDate'] = $settings['sponsor_organization_founding_date'];
                    }

                    if (!empty($settings['sponsor_organization_number_of_employees'])) {
                        $sponsor['numberOfEmployees'] = $settings['sponsor_organization_number_of_employees'];
                    }

                    if (!empty($settings['sponsor_organization_same_as'])) {
                        $sponsor['sameAs'] = $settings['sponsor_organization_same_as'];
                    }
                }
                break;

            case 'Corporation':
                // Corporation Sponsor
                if (!empty($settings['sponsor_corporation_name'])) {
                    $sponsor['name'] = $settings['sponsor_corporation_name'];

                    if (!empty($settings['sponsor_corporation_url'])) {
                        $sponsor['url'] = $settings['sponsor_corporation_url'];
                    }

                    if (!empty($settings['sponsor_corporation_logo'])) {
                        $sponsor['logo'] = array(
                            '@type' => 'ImageObject',
                            'url' => $settings['sponsor_corporation_logo']
                        );
                        // リッチリザルトテスト対応：imageプロパティも追加
                        $sponsor['image'] = array(
                            '@type' => 'ImageObject',
                            'url' => $settings['sponsor_corporation_logo']
                        );
                    }

                    // 新しいフィールドを追加
                    $address = $this->get_structured_address($settings, 'sponsor_corporation_');
                    if ($address) {
                        $sponsor['address'] = $address;
                    }

                    if (!empty($settings['sponsor_corporation_telephone'])) {
                        $sponsor['telephone'] = $settings['sponsor_corporation_telephone'];
                    }

                    if (!empty($settings['sponsor_corporation_description'])) {
                        $sponsor['description'] = $settings['sponsor_corporation_description'];
                    }

                    // 代表者情報
                    if (!empty($settings['sponsor_corporation_representative_name'])) {
                        $representative = array(
                            '@type' => 'Person',
                            'name' => $settings['sponsor_corporation_representative_name']
                        );

                        if (!empty($settings['sponsor_corporation_representative_title'])) {
                            $representative['jobTitle'] = $settings['sponsor_corporation_representative_title'];
                        }

                        $sponsor['founder'] = $representative;
                    }

                    if (!empty($settings['sponsor_corporation_founding_date'])) {
                        $sponsor['foundingDate'] = $settings['sponsor_corporation_founding_date'];
                    }

                    if (!empty($settings['sponsor_corporation_corporate_number'])) {
                        $sponsor['taxID'] = $settings['sponsor_corporation_corporate_number'];
                    }

                    if (!empty($settings['sponsor_corporation_social_media'])) {
                        $sponsor['sameAs'] = $settings['sponsor_corporation_social_media'];
                    }
                }
                break;
        }

        return $sponsor;
    }

    private function get_image_data($post) {
        $images = array();

        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $image_src = wp_get_attachment_image_src($thumbnail_id, 'full');
            if ($image_src && isset($image_src[0], $image_src[1], $image_src[2])) {
                // URLをそのまま使用（WordPressが既に適切な形式で返している）
                $image_url = $image_src[0];

                $image = array(
                    '@type' => 'ImageObject',
                    'url' => $image_url,
                    'width' => $image_src[1],
                    'height' => $image_src[2]
                );

                $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                if ($alt_text) {
                    $image['name'] = $alt_text;
                }

                $images[] = $image;
            }
        }

        if (empty($images)) {
            preg_match_all('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $post->post_content, $matches);
            if (!empty($matches[1])) {
                foreach (array_slice($matches[1], 0, 3) as $img_url) {
                    // HTMLエンティティをデコードしてから検証
                    $img_url = html_entity_decode($img_url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (filter_var($img_url, FILTER_VALIDATE_URL)) {
                        $images[] = $img_url;
                    }
                }
            }
        }

        return !empty($images) ? (count($images) === 1 ? $images[0] : $images) : null;
    }

    public function get_breadcrumb_schema() {
        if (!is_singular()) {
            return null;
        }

        $items = array();
        $position = 1;

        $items[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'item' => array(
                '@id' => home_url('/'),
                'name' => get_bloginfo('name')
            )
        );

        $post = get_post();
        if (!$post) {
            return null;
        }

        if ($post->post_parent) {
            $ancestors = array_reverse(get_post_ancestors($post->ID));
            foreach ($ancestors as $ancestor) {
                $items[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'item' => array(
                        '@id' => get_permalink($ancestor),
                        'name' => get_the_title($ancestor)
                    )
                );
            }
        }

        if ($post->post_type === 'post') {
            $categories = get_the_category($post->ID);
            if ($categories && !is_wp_error($categories) && !empty($categories)) {
                $category = $categories[0];
                $items[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'item' => array(
                        '@id' => get_category_link($category->term_id),
                        'name' => $category->name
                    )
                );
            }
        }

        $items[] = array(
            '@type' => 'ListItem',
            'position' => $position,
            'item' => array(
                '@id' => get_permalink($post->ID),
                'name' => get_the_title($post->ID)
            )
        );

        return array(
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items
        );
    }

    public function generate($content_type, $post_id, $settings) {
        $schema_class = $this->get_schema_class($content_type);

        if (!$schema_class || !class_exists($schema_class)) {
            return null;
        }

        $schema_instance = new $schema_class();
        return $schema_instance->generate($post_id, $settings);
    }

    public function generate_archive_schema($archive_type, $settings) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'url' => $this->get_current_url(),
            'name' => $this->get_archive_title($archive_type),
            'isPartOf' => array(
                '@type' => 'WebSite',
                'url' => home_url('/'),
                'name' => get_bloginfo('name')
            )
        );

        // Publisher情報を追加
        $publisher = $this->get_publisher_data($settings);
        if ($publisher) {
            $schema['publisher'] = $publisher;
        }

        // パンくずリストを追加（一般設定から参照）
        $all_settings = KSSCTB_Settings::get_instance()->get_all_settings();
        if (!empty($all_settings['general']['enable_breadcrumb'])) {
            $breadcrumb = $this->get_archive_breadcrumb($archive_type);
            if ($breadcrumb) {
                $schema['breadcrumb'] = $breadcrumb;
            }
        }

        return $schema;
    }

    private function get_current_url() {
        global $wp;
        return home_url(add_query_arg(array(), $wp->request));
    }

    private function get_archive_title($archive_type) {
        if (is_category()) {
            return single_cat_title('', false);
        } elseif (is_tag()) {
            return single_tag_title('', false);
        } elseif (is_author()) {
            return get_the_author();
        } elseif (is_date()) {
            if (is_year()) {
                return get_the_date('Y年');
            } elseif (is_month()) {
                return get_the_date('Y年n月');
            } elseif (is_day()) {
                return get_the_date('Y年n月j日');
            }
        } elseif (is_tax()) {
            return single_term_title('', false);
        } elseif (is_home()) {
            return get_bloginfo('name');
        }

        return get_the_archive_title();
    }



    private function get_archive_breadcrumb($archive_type) {
        $items = array();

        // ホーム
        $items[] = array(
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'ホーム',
            'item' => home_url('/')
        );

        // 現在のアーカイブ
        $items[] = array(
            '@type' => 'ListItem',
            'position' => 2,
            'name' => $this->get_archive_title($archive_type),
            'item' => $this->get_current_url()
        );

        return array(
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items
        );
    }

    /**
     * 構造化住所を生成する
     * @param array $settings 設定値
     * @param string $prefix フィールドプレフィックス（例：'author_organization_'）
     * @return array|string|null 構造化住所、文字列住所、またはnull
     */
    private function get_structured_address($settings, $prefix) {
        $street_address = $settings[$prefix . 'street_address'] ?? '';
        $locality = $settings[$prefix . 'address_locality'] ?? '';
        $region = $settings[$prefix . 'address_region'] ?? '';
        $postal_code = $settings[$prefix . 'postal_code'] ?? '';
        $country = $settings[$prefix . 'address_country'] ?? '';

        // 詳細住所フィールドのいずれかが設定されている場合は構造化住所として出力
        if (!empty($street_address) || !empty($locality) || !empty($region) || !empty($postal_code) || !empty($country)) {
            $address = array('@type' => 'PostalAddress');

            if (!empty($street_address)) {
                $address['streetAddress'] = $street_address;
            }

            if (!empty($locality)) {
                $address['addressLocality'] = $locality;
            }

            if (!empty($region)) {
                $address['addressRegion'] = $region;
            }

            if (!empty($postal_code)) {
                $address['postalCode'] = $postal_code;
            }

            if (!empty($country)) {
                $address['addressCountry'] = $country;
            }

            return $address;
        }

        return null;
    }
}
