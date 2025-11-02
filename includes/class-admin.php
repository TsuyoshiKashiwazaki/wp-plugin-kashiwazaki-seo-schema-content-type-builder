<?php
if (!defined('ABSPATH')) {
    exit;
}

class KSSCTB_Admin {
    private static $instance = null;
    private static $users_cache = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_kssctb_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_kssctb_reset_settings', array($this, 'ajax_reset_settings'));
        add_action('wp_ajax_kssctb_get_user_data', array($this, 'ajax_get_user_data'));
        add_action('wp_ajax_kssctb_save_user_data', array($this, 'ajax_save_user_data'));
    }

    /**
     * ユーザー一覧を取得（キャッシュ付き）
     *
     * @return array ユーザーの配列
     */
    private function get_users_cached() {
        if (null === self::$users_cache) {
            self::$users_cache = get_users(array('orderby' => 'display_name'));
        }
        return self::$users_cache;
    }

    public function add_admin_menu() {
        add_menu_page(
            'Kashiwazaki SEO Schema Content Type Builder',
            'Kashiwazaki SEO Schema Content Type Builder',
            'manage_options',
            'kssctb-settings',
            array($this, 'render_admin_page'),
            'dashicons-editor-code',
            81
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_kssctb-settings') {
            return;
        }

        // メディアアップローダーのスクリプトをエンキュー
        wp_enqueue_media();

        // 統一されたバージョン管理
        $version = kssctb_get_asset_version();

        wp_enqueue_style('kssctb-admin', KSSCTB_PLUGIN_URL . 'assets/css/admin.css', array(), $version);
        wp_enqueue_script('kssctb-admin', KSSCTB_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), $version, true);
        wp_localize_script('kssctb-admin', 'kssctb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kssctb_settings'),
            'current_user_id' => get_current_user_id()
        ));
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        ?>
        <div class="wrap kssctb-admin-wrap">
            <h1>Kashiwazaki SEO Schema Content Type Builder</h1>

            <form id="kssctb-settings-form">
                <?php wp_nonce_field('kssctb_settings', 'kssctb_nonce'); ?>

                <div class="kssctb-tabs-wrapper">
                    <nav class="nav-tab-wrapper">
                        <a href="#article" class="nav-tab nav-tab-active" data-tab="article">Article</a>
                        <a href="#newsarticle" class="nav-tab" data-tab="newsarticle">NewsArticle</a>
                        <a href="#blogposting" class="nav-tab" data-tab="blogposting">BlogPosting</a>
                        <a href="#webpage" class="nav-tab" data-tab="webpage">WebPage</a>
                        <a href="#general" class="nav-tab" data-tab="general">一般設定</a>
                    </nav>

                    <div class="kssctb-tab-content">
                        <?php
                        $this->render_content_type_tab('article', 'Article');
                        $this->render_content_type_tab('newsarticle', 'NewsArticle');
                        $this->render_content_type_tab('blogposting', 'BlogPosting');
                        $this->render_content_type_tab('webpage', 'WebPage');
                        $this->render_general_tab();
                        ?>
                    </div>
                </div>

                <div class="kssctb-save-wrapper">
                    <button type="button" id="kssctb-save-settings" class="button button-primary">設定を保存</button>
                    <button type="button" id="kssctb-reset-all" class="button button-secondary">すべてリセット</button>
                    <span class="kssctb-save-message"></span>
                </div>
            </form>
        </div>
        <?php
    }

    private function render_general_tab() {
        $all_settings = KSSCTB_Settings::get_instance()->get_all_settings();
        $settings = isset($all_settings['general']) ? $all_settings['general'] : array();
        ?>
        <div id="general" class="kssctb-tab-panel">
            <h2>一般設定</h2>

            <table class="form-table">
                <tr>
                    <th scope="row">デバッグログ</th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="kssctb_general_enable_debug_log"
                                   value="1"
                                   <?php checked($settings['enable_debug_log'] ?? false); ?>>
                            デバッグログを有効にする
                        </label>
                        <p class="description">
                            有効にするとスキーマ生成時の詳細情報がerror.logに記録されます。<br>
                            デバッグ目的以外では無効にしておくことをお勧めします。
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">パンくずリスト</th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="kssctb_general_enable_breadcrumb"
                                   value="1"
                                   <?php checked($settings['enable_breadcrumb'] ?? false); ?>>
                            パンくずリストの構造化データを有効にする
                        </label>
                        <p class="description">
                            すべてのスキーマタイプに共通でパンくずリストの構造化データを追加します。
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function render_content_type_tab($content_type, $label) {
        $all_settings = KSSCTB_Settings::get_instance()->get_all_settings();
        $settings = isset($all_settings[$content_type]) ? $all_settings[$content_type] : array();

        // デバッグ: 設定値を確認
        $enable_debug = isset($all_settings['general']['enable_debug_log']) ? $all_settings['general']['enable_debug_log'] : false;
        if ($enable_debug) {
            echo '<!-- Debug settings for ' . $content_type . ': ' . json_encode($settings) . ' -->';
            echo '<!-- Debug all_settings: ' . json_encode($all_settings) . ' -->';
        }

        // 設定が完全に空の場合、デフォルト値を明示的に設定
        if (empty($settings)) {
            $settings = array(
                'default_author_type' => 'none',
                'publisher_type' => 'none',
                'sponsor_type' => 'none'
            );
        }
        ?>
        <div id="<?php echo $content_type; ?>" class="kssctb-tab-panel">
            <h2><?php echo esc_html($label); ?> Schema設定</h2>

            <table class="form-table">
                <tr>
                    <th scope="row">有効化</th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="kssctb_<?php echo $content_type; ?>_enabled"
                                   value="1"
                                   <?php checked($settings['enabled'] ?? false); ?>>
                            <?php echo esc_html($label); ?> Schemaを有効にする
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">対象の投稿タイプ</th>
                    <td>
                        <?php
                        $post_types = get_post_types(array('public' => true), 'objects');
                        $selected_post_types = $settings['post_types'] ?? array();

                        foreach ($post_types as $post_type) {
                            ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox"
                                       name="kssctb_<?php echo $content_type; ?>_post_types[]"
                                       value="<?php echo esc_attr($post_type->name); ?>"
                                       <?php checked(in_array($post_type->name, $selected_post_types)); ?>>
                                <?php echo esc_html($post_type->label); ?> (<?php echo esc_html($post_type->name); ?>)
                            </label>
                            <?php
                        }
                        ?>
                        <p class="description">このスキーマタイプを適用する投稿タイプを選択してください。</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">対象のアーカイブページ</th>
                    <td>
                        <?php
                        $archive_types = array(
                            'category' => 'カテゴリーページ',
                            'post_tag' => 'タグページ',
                            'author' => '著者アーカイブページ',
                            'date' => '日付アーカイブページ'
                        );

                        // カスタムタクソノミーを追加
                        $taxonomies = get_taxonomies(array('public' => true, '_builtin' => false), 'objects');
                        foreach ($taxonomies as $taxonomy) {
                            $archive_types[$taxonomy->name] = $taxonomy->label . 'アーカイブ';
                        }

                        // カスタム投稿タイプのアーカイブを追加
                        $post_types = get_post_types(array('public' => true, '_builtin' => false, 'has_archive' => true), 'objects');
                        foreach ($post_types as $post_type) {
                            $archive_types['post_type_archive_' . $post_type->name] = $post_type->label . 'アーカイブ';
                        }

                        // ホームページを追加
                        $archive_types['home'] = 'ホームページ（投稿一覧）';

                        // カスタムアーカイブタイプを追加（他のプラグインが拡張可能）
                        $archive_types = apply_filters('kssctb_archive_types', $archive_types);

                        $selected_archives = $settings['archive_types'] ?? array();

                        foreach ($archive_types as $archive_type => $archive_label) {
                            ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox"
                                       name="kssctb_<?php echo $content_type; ?>_archive_types[]"
                                       value="<?php echo esc_attr($archive_type); ?>"
                                       <?php checked(in_array($archive_type, $selected_archives)); ?>>
                                <?php echo esc_html($archive_label); ?>
                            </label>
                            <?php
                        }
                        ?>
                        <p class="description">
                            このスキーマタイプを適用するアーカイブページを選択してください。<br>
                            <?php if ($content_type === 'webpage'): ?>
                            <strong>注意:</strong> アーカイブページでは、個別ページの <code>WebPage</code> ではなく、<code>CollectionPage</code>（WebPageの一種）が出力されます。これは複数の投稿を一覧表示するページに最適なSchema.org準拠のスキーマタイプです。
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>

            <div class="kssctb-aps-section">
                <h3 class="kssctb-aps-title">Author / Publisher / Sponsor Type 設定</h3>
                <table class="form-table kssctb-aps-table">
                    <?php
                    // Type選択フィールドのみを表示
                    $type_fields = array('default_author_type', 'publisher_type', 'sponsor_type');
                    $common_fields = $this->get_common_schema_fields();
                    foreach ($type_fields as $field_key) {
                        if (isset($common_fields[$field_key])) {
                            $field = $common_fields[$field_key];
                            $this->render_field_row($content_type, $field_key, $field, $settings);
                        }
                    }
                    ?>
                </table>

                <div class="kssctb-bulk-copy-wrapper">
                    <button type="button" class="button kssctb-bulk-copy" data-content-type="<?php echo esc_attr($content_type); ?>">
                        <span class="dashicons dashicons-admin-page" style="vertical-align: middle;"></span>
                        Author/Publisher/Sponsor Typeを他のタブに一括反映
                    </button>
                    <p class="description">上記のType設定を他のすべてのタブに反映します。</p>
                </div>
            </div>

            <h3 style="margin-top: 30px;">詳細設定</h3>
            <table class="form-table">
                <?php $this->render_schema_fields($content_type, $settings); ?>
            </table>
        </div>
        <?php
    }

    private function render_schema_fields($content_type, $settings) {
        $specific_fields = $this->get_specific_schema_fields($content_type);

        // Content Type固有のフィールドがある場合
        if (!empty($specific_fields)) {
            ?>
            <tr>
                <th colspan="2" style="padding-top: 20px;">
                    <h3 style="margin: 0; font-size: 14px;"><?php echo ucfirst($content_type); ?> 固有の設定</h3>
                </th>
            </tr>
            <?php
            foreach ($specific_fields as $field_key => $field) {
                $this->render_field_row($content_type, $field_key, $field, $settings);
            }
        }
    }

    private function render_field_row($content_type, $field_key, $field, $settings) {
        $this->render_field_row_internal($content_type, $field_key, $field, $settings, false);
    }

    private function render_detail_field_row($content_type, $field_key, $field, $settings) {
        $this->render_field_row_internal($content_type, $field_key, $field, $settings, true);
    }

    /**
     * フィールド行を描画する共通メソッド
     *
     * @param string $content_type コンテンツタイプ
     * @param string $field_key フィールドキー
     * @param array $field フィールド設定
     * @param array $settings 設定値
     * @param bool $is_detail 詳細フィールドかどうか
     */
    private function render_field_row_internal($content_type, $field_key, $field, $settings, $is_detail = false) {
        $tr_class = $this->get_field_row_class($field_key);
        ?>
        <tr class="<?php echo esc_attr($tr_class); ?>">
            <th scope="row"><?php echo esc_html($field['label']); ?></th>
            <td>
                <?php
                switch ($field['type']) {
                    case 'text':
                        ?>
                        <input type="text"
                               name="kssctb_<?php echo $content_type; ?>_<?php echo $field_key; ?>"
                               value="<?php echo esc_attr($settings[$field_key] ?? $field['default'] ?? ''); ?>"
                               placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"
                               class="regular-text">
                        <?php
                        // 画像URLフィールドにメディア選択ボタンを追加
                        if (strpos($field_key, '_logo') !== false || strpos($field_key, '_image') !== false) {
                            ?>
                            <button type="button" class="button kssctb-media-select"
                                    data-target="kssctb_<?php echo $content_type; ?>_<?php echo $field_key; ?>">
                                メディアを選択
                            </button>
                            <?php
                        }
                        break;

                    case 'select':
                        $current_value = isset($settings[$field_key]) ? $settings[$field_key] : (isset($field['default']) ? $field['default'] : 'none');
                        ?>
                        <select name="kssctb_<?php echo $content_type; ?>_<?php echo $field_key; ?>">
                            <?php
                            foreach ($field['options'] as $value => $label):
                                // 空文字列の場合は'none'として扱う
                                $compare_value = ($current_value === '' || $current_value === null) ? 'none' : $current_value;
                                $is_selected = ($compare_value === $value);
                            ?>
                                <option value="<?php echo esc_attr($value); ?>"
                                        <?php echo $is_selected ? 'selected="selected"' : ''; ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                        break;

                    case 'user_select':
                        $users = $this->get_users_cached();
                        ?>
                        <select name="kssctb_<?php echo $content_type; ?>_<?php echo $field_key; ?>">
                            <option value="">投稿者を使用</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo esc_attr($user->ID); ?>"
                                        <?php selected($settings[$field_key] ?? '', $user->ID); ?>>
                                    <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_login); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php
                        // author_person_userフィールドの場合は説明と機能を追加
                        if ($field_key === 'author_person_user') {
                            ?>
                            <div class="kssctb-author-person-explanation" style="margin-top: 10px;">
                                <div class="kssctb-use-post-author-info" style="display: none; background: #f0f8ff; padding: 10px; border: 1px solid #cce7ff; border-radius: 4px;">
                                    <p style="margin: 0; color: #0073aa;"><strong>「投稿者を使用」選択時の動作:</strong></p>
                                    <p style="margin: 5px 0 0 0;">各記事で、その記事の実際の投稿者を自動判別し、その投稿者に設定されている個別データを自動で使用します。管理画面での追加設定は不要です。</p>
                                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">※投稿者の個別データは、各ユーザーを選択して下記の方法で事前に設定しておく必要があります。</p>
                                </div>

                                <div class="kssctb-specific-user-controls" style="display: none;">
                                    <button type="button" class="button kssctb-save-user-data"
                                            data-content-type="<?php echo esc_attr($content_type); ?>">
                                        <span class="dashicons dashicons-admin-users" style="vertical-align: middle;"></span>
                                        選択ユーザーの詳細情報を保存
                                    </button>
                                    <p class="description" style="margin-top: 5px;">
                                        <strong>使用方法:</strong><br>
                                        1. 上のセレクトボックスで設定したいユーザーを選択<br>
                                        2. 下のフィールドに表示される個別データを編集<br>
                                        3. このボタンでそのユーザーの詳細情報のみ保存<br>
                                        ※通常の「設定を保存」ボタンとは別に、個別ユーザーデータ専用の保存機能です
                                    </p>
                                </div>
                            </div>
                            <?php
                        }
                        ?>
                        <?php
                        break;

                    case 'checkbox':
                        ?>
                        <label>
                            <input type="checkbox"
                                   name="kssctb_<?php echo $content_type; ?>_<?php echo $field_key; ?>"
                                   value="1"
                                   <?php checked($settings[$field_key] ?? false); ?>>
                            <?php echo esc_html($field['description'] ?? ''); ?>
                        </label>
                        <?php
                        break;
                }

                if (!empty($field['help'])) {
                    echo '<p class="description">' . esc_html($field['help']) . '</p>';
                }
                ?>
            </td>
        </tr>
        <?php

        // 詳細フィールドの場合は、子フィールドを表示しない
        if ($is_detail) {
            return;
        }

        // Type選択の直後に詳細フィールドを表示
        $detail_fields_map = array(
            'default_author_type' => array(
                'author_person_user', 'author_person_url', 'author_person_image',
                'author_person_job_title', 'author_person_works_for', 'author_person_description',
                'author_person_address', 'author_person_telephone', 'author_person_knows_about', 'author_person_alumni_of',
                'author_organization_name', 'author_organization_url', 'author_organization_logo',
                'author_organization_street_address', 'author_organization_address_locality',
                'author_organization_address_region', 'author_organization_postal_code',
                'author_organization_address_country', 'author_organization_telephone',
                'author_organization_description', 'author_organization_legal_name', 'author_organization_founding_date',
                'author_organization_number_of_employees', 'author_organization_same_as',
                'author_corporation_name', 'author_corporation_url', 'author_corporation_logo',
                'author_corporation_street_address', 'author_corporation_address_locality',
                'author_corporation_address_region', 'author_corporation_postal_code',
                'author_corporation_address_country', 'author_corporation_telephone', 'author_corporation_description',
                'author_corporation_representative_name', 'author_corporation_representative_title',
                'author_corporation_founding_date', 'author_corporation_corporate_number', 'author_corporation_social_media'
            ),
            'publisher_type' => array(
                'publisher_person_user', 'publisher_person_url', 'publisher_person_image',
                'publisher_person_job_title', 'publisher_person_works_for', 'publisher_person_description',
                'publisher_person_address', 'publisher_person_telephone', 'publisher_person_knows_about', 'publisher_person_alumni_of',
                'publisher_organization_name', 'publisher_organization_url', 'publisher_organization_logo',
                'publisher_organization_street_address', 'publisher_organization_address_locality',
                'publisher_organization_address_region', 'publisher_organization_postal_code',
                'publisher_organization_address_country', 'publisher_organization_telephone',
                'publisher_organization_description', 'publisher_organization_legal_name', 'publisher_organization_founding_date',
                'publisher_organization_number_of_employees', 'publisher_organization_same_as',
                'publisher_corporation_name', 'publisher_corporation_url', 'publisher_corporation_logo',
                'publisher_corporation_street_address', 'publisher_corporation_address_locality',
                'publisher_corporation_address_region', 'publisher_corporation_postal_code',
                'publisher_corporation_address_country', 'publisher_corporation_telephone', 'publisher_corporation_description',
                'publisher_corporation_representative_name', 'publisher_corporation_representative_title',
                'publisher_corporation_founding_date', 'publisher_corporation_corporate_number', 'publisher_corporation_social_media'
            ),
            'sponsor_type' => array(
                'sponsor_person_user', 'sponsor_person_url', 'sponsor_person_image',
                'sponsor_person_job_title', 'sponsor_person_works_for', 'sponsor_person_description',
                'sponsor_person_address', 'sponsor_person_telephone', 'sponsor_person_knows_about', 'sponsor_person_alumni_of',
                'sponsor_organization_name', 'sponsor_organization_url', 'sponsor_organization_logo',
                'sponsor_organization_street_address', 'sponsor_organization_address_locality',
                'sponsor_organization_address_region', 'sponsor_organization_postal_code',
                'sponsor_organization_address_country', 'sponsor_organization_telephone',
                'sponsor_organization_description', 'sponsor_organization_legal_name', 'sponsor_organization_founding_date',
                'sponsor_organization_number_of_employees', 'sponsor_organization_same_as',
                'sponsor_corporation_name', 'sponsor_corporation_url', 'sponsor_corporation_logo',
                'sponsor_corporation_street_address', 'sponsor_corporation_address_locality',
                'sponsor_corporation_address_region', 'sponsor_corporation_postal_code',
                'sponsor_corporation_address_country', 'sponsor_corporation_telephone', 'sponsor_corporation_description',
                'sponsor_corporation_representative_name', 'sponsor_corporation_representative_title',
                'sponsor_corporation_founding_date', 'sponsor_corporation_corporate_number', 'sponsor_corporation_social_media'
            )
        );

        if (isset($detail_fields_map[$field_key])) {
            foreach ($detail_fields_map[$field_key] as $detail_field_key) {
                if (isset($this->get_common_schema_fields()[$detail_field_key])) {
                    $detail_field = $this->get_common_schema_fields()[$detail_field_key];
                    $this->render_detail_field_row($content_type, $detail_field_key, $detail_field, $settings);
                }
            }
        }
    }

    /**
     * フィールド行のCSSクラスを取得
     *
     * @param string $field_key フィールドキー
     * @return string CSSクラス
     */
    private function get_field_row_class($field_key) {
        // author_person_userは専用クラス
        if ($field_key === 'author_person_user') {
            return 'author-user-selector';
        }

        $prefixes = array(
            'author_person_' => 'author-fields-person',
            'author_organization_' => 'author-fields-organization',
            'author_corporation_' => 'author-fields-corporation',
            'publisher_person_' => 'publisher-fields-person',
            'publisher_organization_' => 'publisher-fields-organization',
            'publisher_corporation_' => 'publisher-fields-corporation',
            'sponsor_person_' => 'sponsor-fields-person',
            'sponsor_organization_' => 'sponsor-fields-organization',
            'sponsor_corporation_' => 'sponsor-fields-corporation'
        );

        foreach ($prefixes as $prefix => $class) {
            if (strpos($field_key, $prefix) === 0) {
                return $class;
            }
        }

        return '';
    }

    private function get_common_schema_fields() {
        return array(
            'default_author_type' => array(
                'label' => 'Author Type',
                'type' => 'select',
                'options' => array(
                    'none' => 'none',
                    'Person' => 'Person',
                    'Organization' => 'Organization',
                    'Corporation' => 'Corporation'
                ),
                'default' => 'none'
            ),
            'author_person_user' => array(
                'label' => 'Default Person Author',
                'type' => 'user_select',
                'help' => '「投稿者を使用」: 各記事でその記事の実際の投稿者の個別データを自動使用 / 特定ユーザー選択: 全記事で同じ固定ユーザーのデータを使用'
            ),
            'author_person_url' => array(
                'label' => 'Author Person URL',
                'type' => 'text',
                'placeholder' => 'https://example.com/author/taro',
                'help' => '【無効】この設定は無視され、常に実際の投稿者のアーカイブURLが使用されます'
            ),
            'author_person_image' => array(
                'label' => 'Author Person Image',
                'type' => 'text',
                'placeholder' => 'https://example.com/images/author.jpg',
                'help' => '選択したユーザー専用のプロフィール画像URL（空欄の場合はGravatarを使用）'
            ),
            'author_person_job_title' => array(
                'label' => 'Author Person Job Title',
                'type' => 'text',
                'placeholder' => 'SEOコンサルタント',
                'help' => '選択したユーザー専用の職業・役職'
            ),
            'author_person_works_for' => array(
                'label' => 'Author Person Works For',
                'type' => 'text',
                'placeholder' => 'SEO対策研究室',
                'help' => '選択したユーザー専用の所属組織'
            ),
            'author_person_description' => array(
                'label' => 'Author Person Description',
                'type' => 'text',
                'placeholder' => 'SEO専門のコンサルタント。企業のWeb戦略をサポート。',
                'help' => '選択したユーザー専用の人物説明'
            ),
            'author_person_address' => array(
                'label' => 'Author Person Address',
                'type' => 'text',
                'placeholder' => '東京都渋谷区',
                'help' => '選択したユーザー専用の住所'
            ),
            'author_person_telephone' => array(
                'label' => 'Author Person Telephone',
                'type' => 'text',
                'placeholder' => '03-6276-4579',
                'help' => '選択したユーザー専用の電話番号'
            ),
            'author_person_knows_about' => array(
                'label' => 'Author Person Knows About',
                'type' => 'text',
                'placeholder' => 'SEO, Webマーケティング',
                'help' => '選択したユーザー専用の専門分野'
            ),
            'author_person_alumni_of' => array(
                'label' => 'Author Person Alumni Of',
                'type' => 'text',
                'placeholder' => '早稲田大学',
                'help' => '選択したユーザー専用の出身校'
            ),
            'author_organization_name' => array(
                'label' => 'Organization Author Name',
                'type' => 'text',
                'placeholder' => '〇〇編集部',
                'help' => 'Organization選択時の組織名'
            ),
            'author_organization_url' => array(
                'label' => 'Organization Author URL',
                'type' => 'text',
                'placeholder' => 'https://example.com/editorial',
                'help' => 'Organization選択時の組織URL'
            ),
            'author_organization_logo' => array(
                'label' => 'Organization Author Logo',
                'type' => 'text',
                'placeholder' => 'https://example.com/editorial-logo.png',
                'help' => 'Organization選択時のロゴ画像URL'
            ),

            'author_organization_street_address' => array(
                'label' => 'Organization Author Street Address',
                'type' => 'text',
                'placeholder' => '代々木2丁目26−2 第二桑野ビル5D',
                'help' => 'Organization選択時の番地・建物名'
            ),
            'author_organization_address_locality' => array(
                'label' => 'Organization Author Address Locality',
                'type' => 'text',
                'placeholder' => '渋谷区',
                'help' => 'Organization選択時の市区町村'
            ),
            'author_organization_address_region' => array(
                'label' => 'Organization Author Address Region',
                'type' => 'text',
                'placeholder' => '東京都',
                'help' => 'Organization選択時の都道府県'
            ),
            'author_organization_postal_code' => array(
                'label' => 'Organization Author Postal Code',
                'type' => 'text',
                'placeholder' => '151-0053',
                'help' => 'Organization選択時の郵便番号'
            ),
            'author_organization_address_country' => array(
                'label' => 'Organization Author Address Country',
                'type' => 'text',
                'placeholder' => 'JP',
                'help' => 'Organization選択時の国コード（ISO 3166-1 alpha-2）'
            ),
            'author_organization_street_address' => array(
                'label' => 'Organization Author Street Address',
                'type' => 'text',
                'placeholder' => '代々木2丁目26−2 第二桑野ビル5D',
                'help' => 'Organization選択時の番地・建物名'
            ),
            'author_organization_address_locality' => array(
                'label' => 'Organization Author Address Locality',
                'type' => 'text',
                'placeholder' => '渋谷区',
                'help' => 'Organization選択時の市区町村'
            ),
            'author_organization_address_region' => array(
                'label' => 'Organization Author Address Region',
                'type' => 'text',
                'placeholder' => '東京都',
                'help' => 'Organization選択時の都道府県'
            ),
            'author_organization_postal_code' => array(
                'label' => 'Organization Author Postal Code',
                'type' => 'text',
                'placeholder' => '151-0053',
                'help' => 'Organization選択時の郵便番号'
            ),
            'author_organization_address_country' => array(
                'label' => 'Organization Author Address Country',
                'type' => 'text',
                'placeholder' => 'JP',
                'help' => 'Organization選択時の国コード（ISO 3166-1 alpha-2）'
            ),
            'author_organization_telephone' => array(
                'label' => 'Organization Author Telephone',
                'type' => 'text',
                'placeholder' => '03-1234-5678',
                'help' => 'Organization選択時の電話番号'
            ),

            'author_organization_description' => array(
                'label' => 'Organization Author Description',
                'type' => 'text',
                'placeholder' => '専門的な編集チームです。',
                'help' => 'Organization選択時の組織説明'
            ),
            'author_organization_legal_name' => array(
                'label' => 'Organization Author Legal Name',
                'type' => 'text',
                'placeholder' => '正式組織名',
                'help' => 'Organization選択時の正式名称'
            ),
            'author_organization_founding_date' => array(
                'label' => 'Organization Author Founding Date',
                'type' => 'text',
                'placeholder' => '2020-01-01',
                'help' => 'Organization選択時の設立年月日（YYYY-MM-DD形式）'
            ),
            'author_organization_number_of_employees' => array(
                'label' => 'Organization Author Number of Employees',
                'type' => 'text',
                'placeholder' => '50',
                'help' => 'Organization選択時の従業員数'
            ),
            'author_organization_same_as' => array(
                'label' => 'Organization Author Social Media',
                'type' => 'text',
                'placeholder' => 'https://twitter.com/organization',
                'help' => 'Organization選択時のSNSアカウントURL'
            ),
            'author_corporation_name' => array(
                'label' => 'Corporation Author Name',
                'type' => 'text',
                'placeholder' => '株式会社〇〇',
                'help' => 'Corporation選択時の企業名'
            ),
            'author_corporation_url' => array(
                'label' => 'Corporation Author URL',
                'type' => 'text',
                'placeholder' => 'https://corp.example.com',
                'help' => 'Corporation選択時の企業URL'
            ),
            'author_corporation_logo' => array(
                'label' => 'Corporation Author Logo',
                'type' => 'text',
                'placeholder' => 'https://corp.example.com/logo.png',
                'help' => 'Corporation選択時のロゴ画像URL'
            ),

            'author_corporation_street_address' => array(
                'label' => 'Corporation Author Street Address',
                'type' => 'text',
                'placeholder' => '代々木2丁目26−2 第二桑野ビル5D',
                'help' => 'Corporation選択時の番地・建物名'
            ),
            'author_corporation_address_locality' => array(
                'label' => 'Corporation Author Address Locality',
                'type' => 'text',
                'placeholder' => '渋谷区',
                'help' => 'Corporation選択時の市区町村'
            ),
            'author_corporation_address_region' => array(
                'label' => 'Corporation Author Address Region',
                'type' => 'text',
                'placeholder' => '東京都',
                'help' => 'Corporation選択時の都道府県'
            ),
            'author_corporation_postal_code' => array(
                'label' => 'Corporation Author Postal Code',
                'type' => 'text',
                'placeholder' => '151-0053',
                'help' => 'Corporation選択時の郵便番号'
            ),
            'author_corporation_address_country' => array(
                'label' => 'Corporation Author Address Country',
                'type' => 'text',
                'placeholder' => 'JP',
                'help' => 'Corporation選択時の国コード（ISO 3166-1 alpha-2）'
            ),
            'author_corporation_telephone' => array(
                'label' => 'Corporation Author Telephone',
                'type' => 'text',
                'placeholder' => '03-1234-5678',
                'help' => 'Corporation選択時の企業電話番号'
            ),
            'author_corporation_description' => array(
                'label' => 'Corporation Author Description',
                'type' => 'text',
                'placeholder' => 'Webサービス開発を行う企業です。',
                'help' => 'Corporation選択時の企業説明'
            ),
            'author_corporation_representative_name' => array(
                'label' => 'Corporation Author Representative Name',
                'type' => 'text',
                'placeholder' => '田中 太郎',
                'help' => 'Corporation選択時の代表者名'
            ),
            'author_corporation_representative_title' => array(
                'label' => 'Corporation Author Representative Title',
                'type' => 'text',
                'placeholder' => '代表取締役社長',
                'help' => 'Corporation選択時の代表者役職'
            ),
            'author_corporation_founding_date' => array(
                'label' => 'Corporation Author Founding Date',
                'type' => 'text',
                'placeholder' => '2020-01-01',
                'help' => 'Corporation選択時の設立年月日（YYYY-MM-DD形式）'
            ),
            'author_corporation_corporate_number' => array(
                'label' => 'Corporation Author Corporate Number',
                'type' => 'text',
                'placeholder' => '1234567890123',
                'help' => 'Corporation選択時の法人番号（13桁）'
            ),
            'author_corporation_social_media' => array(
                'label' => 'Corporation Author Social Media',
                'type' => 'text',
                'placeholder' => 'https://twitter.com/company',
                'help' => 'Corporation選択時の公式SNSアカウントURL'
            ),
            'publisher_type' => array(
                'label' => 'Publisher Type',
                'type' => 'select',
                'options' => array(
                    'none' => 'none',
                    'Organization' => 'Organization',
                    'Person' => 'Person',
                    'Corporation' => 'Corporation'
                ),
                'default' => 'none'
            ),
            'publisher_person_user' => array(
                'label' => 'Publisher Person',
                'type' => 'user_select',
                'help' => '発行者となるユーザーを選択'
            ),
            'publisher_person_url' => array(
                'label' => 'Publisher Person URL',
                'type' => 'text',
                'placeholder' => 'https://example.com/about',
                'help' => 'Person選択時のURL（空欄の場合は選択したユーザーのアーカイブURLを使用）'
            ),
            'publisher_person_image' => array(
                'label' => 'Publisher Person Image',
                'type' => 'text',
                'placeholder' => 'https://example.com/images/profile.jpg',
                'help' => 'Person選択時のプロフィール画像URL'
            ),
            'publisher_person_job_title' => array(
                'label' => 'Publisher Person Job Title',
                'type' => 'text',
                'placeholder' => '編集長',
                'help' => 'Person選択時の職業・役職'
            ),
            'publisher_person_works_for' => array(
                'label' => 'Publisher Person Works For',
                'type' => 'text',
                'placeholder' => '〇〇メディア',
                'help' => 'Person選択時の所属組織'
            ),
            'publisher_person_description' => array(
                'label' => 'Publisher Person Description',
                'type' => 'text',
                'placeholder' => 'メディア運営のプロフェッショナル。業界のトレンドを発信。',
                'help' => 'Person選択時の人物説明'
            ),
            'publisher_person_address' => array(
                'label' => 'Publisher Person Address',
                'type' => 'text',
                'placeholder' => '東京都渋谷区',
                'help' => 'Person選択時の住所'
            ),
            'publisher_person_telephone' => array(
                'label' => 'Publisher Person Telephone',
                'type' => 'text',
                'placeholder' => '03-1234-5678',
                'help' => 'Person選択時の電話番号'
            ),
            'publisher_person_knows_about' => array(
                'label' => 'Publisher Person Knows About',
                'type' => 'text',
                'placeholder' => 'メディア運営, コンテンツ制作',
                'help' => 'Person選択時の専門分野'
            ),
            'publisher_person_alumni_of' => array(
                'label' => 'Publisher Person Alumni Of',
                'type' => 'text',
                'placeholder' => '慶應義塾大学',
                'help' => 'Person選択時の出身校'
            ),
            'publisher_organization_name' => array(
                'label' => 'Publisher Organization Name',
                'type' => 'text',
                'placeholder' => '〇〇メディア',
                'help' => 'Organization選択時の組織名'
            ),
            'publisher_organization_url' => array(
                'label' => 'Publisher Organization URL',
                'type' => 'text',
                'placeholder' => 'https://example.com',
                'help' => 'Organization選択時の組織URL'
            ),
            'publisher_organization_logo' => array(
                'label' => 'Publisher Organization Logo',
                'type' => 'text',
                'placeholder' => 'https://example.com/logo.png',
                'help' => 'Organization選択時のロゴ画像URL（推奨: 600x60px以上）'
            ),

            'publisher_organization_street_address' => array(
                'label' => 'Publisher Organization Street Address',
                'type' => 'text',
                'placeholder' => '代々木2丁目26−2 第二桑野ビル5D',
                'help' => 'Organization選択時の番地・建物名'
            ),
            'publisher_organization_address_locality' => array(
                'label' => 'Publisher Organization Address Locality',
                'type' => 'text',
                'placeholder' => '渋谷区',
                'help' => 'Organization選択時の市区町村'
            ),
            'publisher_organization_address_region' => array(
                'label' => 'Publisher Organization Address Region',
                'type' => 'text',
                'placeholder' => '東京都',
                'help' => 'Organization選択時の都道府県'
            ),
            'publisher_organization_postal_code' => array(
                'label' => 'Publisher Organization Postal Code',
                'type' => 'text',
                'placeholder' => '151-0053',
                'help' => 'Organization選択時の郵便番号'
            ),
            'publisher_organization_address_country' => array(
                'label' => 'Publisher Organization Address Country',
                'type' => 'text',
                'placeholder' => 'JP',
                'help' => 'Organization選択時の国コード（ISO 3166-1 alpha-2）'
            ),
            'publisher_organization_telephone' => array(
                'label' => 'Publisher Organization Telephone',
                'type' => 'text',
                'placeholder' => '03-1234-5678',
                'help' => 'Organization選択時の電話番号'
            ),

            'publisher_organization_description' => array(
                'label' => 'Publisher Organization Description',
                'type' => 'text',
                'placeholder' => 'メディア運営組織です。',
                'help' => 'Organization選択時の組織説明'
            ),
            'publisher_organization_legal_name' => array(
                'label' => 'Publisher Organization Legal Name',
                'type' => 'text',
                'placeholder' => '正式組織名',
                'help' => 'Organization選択時の正式名称'
            ),
            'publisher_organization_founding_date' => array(
                'label' => 'Publisher Organization Founding Date',
                'type' => 'text',
                'placeholder' => '2020-01-01',
                'help' => 'Organization選択時の設立年月日（YYYY-MM-DD形式）'
            ),
            'publisher_organization_number_of_employees' => array(
                'label' => 'Publisher Organization Number of Employees',
                'type' => 'text',
                'placeholder' => '100',
                'help' => 'Organization選択時の従業員数'
            ),
            'publisher_organization_same_as' => array(
                'label' => 'Publisher Organization Social Media',
                'type' => 'text',
                'placeholder' => 'https://twitter.com/publisher',
                'help' => 'Organization選択時のSNSアカウントURL'
            ),
            'publisher_corporation_name' => array(
                'label' => 'Publisher Corporation Name',
                'type' => 'text',
                'placeholder' => '株式会社〇〇',
                'help' => 'Corporation選択時の企業名'
            ),
            'publisher_corporation_url' => array(
                'label' => 'Publisher Corporation URL',
                'type' => 'text',
                'placeholder' => 'https://corp.example.com',
                'help' => 'Corporation選択時の企業URL'
            ),
            'publisher_corporation_logo' => array(
                'label' => 'Publisher Corporation Logo',
                'type' => 'text',
                'placeholder' => 'https://corp.example.com/logo.png',
                'help' => 'Corporation選択時のロゴ画像URL'
            ),

            'publisher_corporation_street_address' => array(
                'label' => 'Publisher Corporation Street Address',
                'type' => 'text',
                'placeholder' => '代々木2丁目26−2 第二桑野ビル5D',
                'help' => 'Corporation選択時の番地・建物名'
            ),
            'publisher_corporation_address_locality' => array(
                'label' => 'Publisher Corporation Address Locality',
                'type' => 'text',
                'placeholder' => '渋谷区',
                'help' => 'Corporation選択時の市区町村'
            ),
            'publisher_corporation_address_region' => array(
                'label' => 'Publisher Corporation Address Region',
                'type' => 'text',
                'placeholder' => '東京都',
                'help' => 'Corporation選択時の都道府県'
            ),
            'publisher_corporation_postal_code' => array(
                'label' => 'Publisher Corporation Postal Code',
                'type' => 'text',
                'placeholder' => '151-0053',
                'help' => 'Corporation選択時の郵便番号'
            ),
            'publisher_corporation_address_country' => array(
                'label' => 'Publisher Corporation Address Country',
                'type' => 'text',
                'placeholder' => 'JP',
                'help' => 'Corporation選択時の国コード（ISO 3166-1 alpha-2）'
            ),
            'publisher_corporation_telephone' => array(
                'label' => 'Publisher Corporation Telephone',
                'type' => 'text',
                'placeholder' => '03-1234-5678',
                'help' => 'Corporation選択時の企業電話番号'
            ),
            'publisher_corporation_description' => array(
                'label' => 'Publisher Corporation Description',
                'type' => 'text',
                'placeholder' => 'Webサービス開発を行う企業です。',
                'help' => 'Corporation選択時の企業説明'
            ),
            'publisher_corporation_representative_name' => array(
                'label' => 'Publisher Corporation Representative Name',
                'type' => 'text',
                'placeholder' => '田中 太郎',
                'help' => 'Corporation選択時の代表者名'
            ),
            'publisher_corporation_representative_title' => array(
                'label' => 'Publisher Corporation Representative Title',
                'type' => 'text',
                'placeholder' => '代表取締役社長',
                'help' => 'Corporation選択時の代表者役職'
            ),
            'publisher_corporation_founding_date' => array(
                'label' => 'Publisher Corporation Founding Date',
                'type' => 'text',
                'placeholder' => '2020-01-01',
                'help' => 'Corporation選択時の設立年月日（YYYY-MM-DD形式）'
            ),
            'publisher_corporation_corporate_number' => array(
                'label' => 'Publisher Corporation Corporate Number',
                'type' => 'text',
                'placeholder' => '1234567890123',
                'help' => 'Corporation選択時の法人番号（13桁）'
            ),
            'publisher_corporation_social_media' => array(
                'label' => 'Publisher Corporation Social Media',
                'type' => 'text',
                'placeholder' => 'https://twitter.com/company',
                'help' => 'Corporation選択時の公式SNSアカウントURL'
            ),
            'sponsor_type' => array(
                'label' => 'Sponsor Type',
                'type' => 'select',
                'options' => array(
                    'none' => 'none',
                    'Organization' => 'Organization',
                    'Person' => 'Person',
                    'Corporation' => 'Corporation'
                ),
                'default' => 'none'
            ),
            'sponsor_person_user' => array(
                'label' => 'Sponsor Person',
                'type' => 'user_select',
                'help' => 'スポンサーとなるユーザーを選択'
            ),
            'sponsor_person_url' => array(
                'label' => 'Sponsor Person URL',
                'type' => 'text',
                'placeholder' => 'https://example.com/sponsor',
                'help' => 'Person選択時のURL（空欄の場合は選択したユーザーのアーカイブURLを使用）'
            ),
            'sponsor_person_image' => array(
                'label' => 'Sponsor Person Image',
                'type' => 'text',
                'placeholder' => 'https://example.com/images/sponsor.jpg',
                'help' => 'Person選択時のプロフィール画像URL'
            ),
            'sponsor_person_job_title' => array(
                'label' => 'Sponsor Person Job Title',
                'type' => 'text',
                'placeholder' => '代表取締役',
                'help' => 'Person選択時の職業・役職'
            ),
            'sponsor_person_works_for' => array(
                'label' => 'Sponsor Person Works For',
                'type' => 'text',
                'placeholder' => '〇〇スポンサー',
                'help' => 'Person選択時の所属組織'
            ),
            'sponsor_person_description' => array(
                'label' => 'Sponsor Person Description',
                'type' => 'text',
                'placeholder' => '事業投資のプロフェッショナル。新しいビジネスを支援。',
                'help' => 'Person選択時の人物説明'
            ),
            'sponsor_person_address' => array(
                'label' => 'Sponsor Person Address',
                'type' => 'text',
                'placeholder' => '東京都港区',
                'help' => 'Person選択時の住所'
            ),
            'sponsor_person_telephone' => array(
                'label' => 'Sponsor Person Telephone',
                'type' => 'text',
                'placeholder' => '03-5555-6666',
                'help' => 'Person選択時の電話番号'
            ),
            'sponsor_person_knows_about' => array(
                'label' => 'Sponsor Person Knows About',
                'type' => 'text',
                'placeholder' => '事業投資, ビジネス戦略',
                'help' => 'Person選択時の専門分野'
            ),
            'sponsor_person_alumni_of' => array(
                'label' => 'Sponsor Person Alumni Of',
                'type' => 'text',
                'placeholder' => '東京大学',
                'help' => 'Person選択時の出身校'
            ),
            'sponsor_organization_name' => array(
                'label' => 'Sponsor Organization Name',
                'type' => 'text',
                'placeholder' => '〇〇スポンサー',
                'help' => 'Organization選択時の組織名'
            ),
            'sponsor_organization_url' => array(
                'label' => 'Sponsor Organization URL',
                'type' => 'text',
                'placeholder' => 'https://sponsor.example.com',
                'help' => 'Organization選択時の組織URL'
            ),
            'sponsor_organization_logo' => array(
                'label' => 'Sponsor Organization Logo',
                'type' => 'text',
                'placeholder' => 'https://sponsor.example.com/logo.png',
                'help' => 'Organization選択時のロゴ画像URL'
            ),

            'sponsor_organization_street_address' => array(
                'label' => 'Sponsor Organization Street Address',
                'type' => 'text',
                'placeholder' => '代々木2丁目26−2 第二桑野ビル5D',
                'help' => 'Organization選択時の番地・建物名'
            ),
            'sponsor_organization_address_locality' => array(
                'label' => 'Sponsor Organization Address Locality',
                'type' => 'text',
                'placeholder' => '渋谷区',
                'help' => 'Organization選択時の市区町村'
            ),
            'sponsor_organization_address_region' => array(
                'label' => 'Sponsor Organization Address Region',
                'type' => 'text',
                'placeholder' => '東京都',
                'help' => 'Organization選択時の都道府県'
            ),
            'sponsor_organization_postal_code' => array(
                'label' => 'Sponsor Organization Postal Code',
                'type' => 'text',
                'placeholder' => '151-0053',
                'help' => 'Organization選択時の郵便番号'
            ),
            'sponsor_organization_address_country' => array(
                'label' => 'Sponsor Organization Address Country',
                'type' => 'text',
                'placeholder' => 'JP',
                'help' => 'Organization選択時の国コード（ISO 3166-1 alpha-2）'
            ),
            'sponsor_organization_telephone' => array(
                'label' => 'Sponsor Organization Telephone',
                'type' => 'text',
                'placeholder' => '03-1234-5678',
                'help' => 'Organization選択時の電話番号'
            ),

            'sponsor_organization_description' => array(
                'label' => 'Sponsor Organization Description',
                'type' => 'text',
                'placeholder' => 'スポンサー組織です。',
                'help' => 'Organization選択時の組織説明'
            ),
            'sponsor_organization_legal_name' => array(
                'label' => 'Sponsor Organization Legal Name',
                'type' => 'text',
                'placeholder' => '正式組織名',
                'help' => 'Organization選択時の正式名称'
            ),
            'sponsor_organization_founding_date' => array(
                'label' => 'Sponsor Organization Founding Date',
                'type' => 'text',
                'placeholder' => '2020-01-01',
                'help' => 'Organization選択時の設立年月日（YYYY-MM-DD形式）'
            ),
            'sponsor_organization_number_of_employees' => array(
                'label' => 'Sponsor Organization Number of Employees',
                'type' => 'text',
                'placeholder' => '200',
                'help' => 'Organization選択時の従業員数'
            ),
            'sponsor_organization_same_as' => array(
                'label' => 'Sponsor Organization Social Media',
                'type' => 'text',
                'placeholder' => 'https://twitter.com/sponsor',
                'help' => 'Organization選択時のSNSアカウントURL'
            ),
            'sponsor_corporation_name' => array(
                'label' => 'Sponsor Corporation Name',
                'type' => 'text',
                'placeholder' => '株式会社〇〇スポンサー',
                'help' => 'Corporation選択時の企業名'
            ),
            'sponsor_corporation_url' => array(
                'label' => 'Sponsor Corporation URL',
                'type' => 'text',
                'placeholder' => 'https://sponsor-corp.example.com',
                'help' => 'Corporation選択時の企業URL'
            ),
            'sponsor_corporation_logo' => array(
                'label' => 'Sponsor Corporation Logo',
                'type' => 'text',
                'placeholder' => 'https://sponsor-corp.example.com/logo.png',
                'help' => 'Corporation選択時のロゴ画像URL'
            ),

            'sponsor_corporation_street_address' => array(
                'label' => 'Sponsor Corporation Street Address',
                'type' => 'text',
                'placeholder' => '代々木2丁目26−2 第二桑野ビル5D',
                'help' => 'Corporation選択時の番地・建物名'
            ),
            'sponsor_corporation_address_locality' => array(
                'label' => 'Sponsor Corporation Address Locality',
                'type' => 'text',
                'placeholder' => '渋谷区',
                'help' => 'Corporation選択時の市区町村'
            ),
            'sponsor_corporation_address_region' => array(
                'label' => 'Sponsor Corporation Address Region',
                'type' => 'text',
                'placeholder' => '東京都',
                'help' => 'Corporation選択時の都道府県'
            ),
            'sponsor_corporation_postal_code' => array(
                'label' => 'Sponsor Corporation Postal Code',
                'type' => 'text',
                'placeholder' => '151-0053',
                'help' => 'Corporation選択時の郵便番号'
            ),
            'sponsor_corporation_address_country' => array(
                'label' => 'Sponsor Corporation Address Country',
                'type' => 'text',
                'placeholder' => 'JP',
                'help' => 'Corporation選択時の国コード（ISO 3166-1 alpha-2）'
            ),
            'sponsor_corporation_telephone' => array(
                'label' => 'Sponsor Corporation Telephone',
                'type' => 'text',
                'placeholder' => '03-1234-5678',
                'help' => 'Corporation選択時の企業電話番号'
            ),
            'sponsor_corporation_description' => array(
                'label' => 'Sponsor Corporation Description',
                'type' => 'text',
                'placeholder' => 'Webサービス開発を行う企業です。',
                'help' => 'Corporation選択時の企業説明'
            ),
            'sponsor_corporation_representative_name' => array(
                'label' => 'Sponsor Corporation Representative Name',
                'type' => 'text',
                'placeholder' => '田中 太郎',
                'help' => 'Corporation選択時の代表者名'
            ),
            'sponsor_corporation_representative_title' => array(
                'label' => 'Sponsor Corporation Representative Title',
                'type' => 'text',
                'placeholder' => '代表取締役社長',
                'help' => 'Corporation選択時の代表者役職'
            ),
            'sponsor_corporation_founding_date' => array(
                'label' => 'Sponsor Corporation Founding Date',
                'type' => 'text',
                'placeholder' => '2020-01-01',
                'help' => 'Corporation選択時の設立年月日（YYYY-MM-DD形式）'
            ),
            'sponsor_corporation_corporate_number' => array(
                'label' => 'Sponsor Corporation Corporate Number',
                'type' => 'text',
                'placeholder' => '1234567890123',
                'help' => 'Corporation選択時の法人番号（13桁）'
            ),
            'sponsor_corporation_social_media' => array(
                'label' => 'Sponsor Corporation Social Media',
                'type' => 'text',
                'placeholder' => 'https://twitter.com/company',
                'help' => 'Corporation選択時の公式SNSアカウントURL'
            )
        );
    }

    private function get_specific_schema_fields($content_type) {
        $specific_fields = array(
            'article' => array(
                'article_section' => array(
                    'label' => 'Article Section',
                    'type' => 'text',
                    'placeholder' => 'Technology, Business, Lifestyle',
                    'help' => '記事のセクション（空欄の場合はカテゴリー名を自動使用）'
                )
            ),
            'newsarticle' => array(
                'news_keywords' => array(
                    'label' => 'News Keywords',
                    'type' => 'text',
                    'placeholder' => '新製品, テクノロジー, イノベーション',
                    'help' => 'ニュース記事のキーワード（カンマ区切り）'
                )
            ),
            'blogposting' => array(
                'enable_comments' => array(
                    'label' => 'コメント数',
                    'type' => 'checkbox',
                    'description' => 'コメント数を構造化データに含める'
                )
            ),
            'webpage' => array(
                'enable_speakable' => array(
                    'label' => 'Speakable',
                    'type' => 'checkbox',
                    'description' => '音声アシスタント向けのSpeakableプロパティを有効にする'
                )
            )
        );

        return $specific_fields[$content_type] ?? array();
    }

    private function get_schema_fields($content_type) {
        return array_merge($this->get_common_schema_fields(), $this->get_specific_schema_fields($content_type));
    }

    public function ajax_save_settings() {
        try {
            // 権限チェック
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => '権限がありません'));
                return;
            }

            // nonceチェック
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kssctb_settings')) {
                wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました'));
                return;
            }

            $settings = array();

            // 一般設定を処理
            $settings['general'] = array();
            $settings['general']['enable_debug_log'] = (isset($_POST['kssctb_general_enable_debug_log']) && $_POST['kssctb_general_enable_debug_log'] === '1');
            $settings['general']['enable_breadcrumb'] = (isset($_POST['kssctb_general_enable_breadcrumb']) && $_POST['kssctb_general_enable_breadcrumb'] === '1');

            $content_types = array('article', 'newsarticle', 'blogposting', 'webpage');

            foreach ($content_types as $content_type) {
                $settings[$content_type] = array();

                // 有効化設定を保存（明示的に送信された値をチェック）
                $enabled_key = 'kssctb_' . $content_type . '_enabled';
                $settings[$content_type]['enabled'] = (isset($_POST[$enabled_key]) && $_POST[$enabled_key] === '1');

                // 投稿タイプの配列処理
                if (isset($_POST['kssctb_' . $content_type . '_post_types'])) {
                    $post_types = $_POST['kssctb_' . $content_type . '_post_types'];
                    if (is_array($post_types)) {
                        $settings[$content_type]['post_types'] = array_map('sanitize_text_field', $post_types);
                    }
                } else {
                    $settings[$content_type]['post_types'] = array();
                }

                // アーカイブタイプの配列処理
                if (isset($_POST['kssctb_' . $content_type . '_archive_types'])) {
                    $archive_types = $_POST['kssctb_' . $content_type . '_archive_types'];
                    if (is_array($archive_types)) {
                        $settings[$content_type]['archive_types'] = array_map('sanitize_text_field', $archive_types);
                    }
                } else {
                    $settings[$content_type]['archive_types'] = array();
                }

                // その他のフィールドを処理
                $fields = $this->get_schema_fields($content_type);
                foreach ($fields as $field_key => $field) {
                    $post_key = 'kssctb_' . $content_type . '_' . $field_key;

                    if ($field['type'] === 'checkbox') {
                        // チェックボックスは明示的に送信された値をチェック
                        $settings[$content_type][$field_key] = (isset($_POST[$post_key]) && $_POST[$post_key] === '1');
                    } else {
                        if (isset($_POST[$post_key])) {
                            // URLフィールドの場合は、sanitize_text_fieldではなくesc_url_rawを使用
                            if (strpos($field_key, '_url') !== false ||
                                strpos($field_key, '_logo') !== false ||
                                strpos($field_key, '_image') !== false) {
                                $value = esc_url_raw($_POST[$post_key]);

                                // URL形式の検証
                                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                                    throw new Exception(sprintf('無効なURL形式です: %s', $field['label']));
                                }
                            } else {
                                $value = sanitize_text_field($_POST[$post_key]);
                            }

                            $settings[$content_type][$field_key] = $value;
                        } else {
                            // フィールドが送信されていない場合は空文字列を設定
                            $settings[$content_type][$field_key] = '';
                        }
                    }
                }
            }

            $result = KSSCTB_Settings::get_instance()->update_settings($settings);

            if ($result) {
                wp_send_json_success(array('message' => '設定を保存しました'));
            } else {
                throw new Exception('設定の保存に失敗しました');
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    public function ajax_reset_settings() {
        try {
            // 権限チェック
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => '権限がありません'));
                return;
            }

            // nonceチェック
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kssctb_settings')) {
                wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました'));
                return;
            }

            // すべての設定を削除
            $content_types = array('article', 'newsarticle', 'blogposting', 'webpage');
            foreach ($content_types as $type) {
                delete_option('kssctb_' . $type . '_settings');
            }

            // メインの設定オプションも削除（もし存在すれば）
            delete_option('kssctb_settings');

            // トランジェントやキャッシュも削除
            delete_transient('kssctb_settings_cache');

            wp_send_json_success(array(
                'message' => 'すべての設定をリセットしました。'
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * ユーザー個別データ取得のAJAXハンドラ
     */
    public function ajax_get_user_data() {
        try {
            // 権限チェック
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => '権限がありません'));
                return;
            }

            // nonceチェック
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kssctb_settings')) {
                wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました'));
                return;
            }

            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

            if (!$user_id) {
                wp_send_json_error(array('message' => '無効なユーザーIDです'));
                return;
            }

            $user_data = KSSCTB_Settings::get_instance()->get_user_schema_data($user_id);

            wp_send_json_success(array(
                'user_data' => $user_data,
                'message' => 'ユーザーデータを取得しました'
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * ユーザー個別データ保存のAJAXハンドラ
     */
    public function ajax_save_user_data() {
        try {
            // 権限チェック
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => '権限がありません'));
                return;
            }

            // nonceチェック
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kssctb_settings')) {
                wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました'));
                return;
            }

            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

            if (!$user_id) {
                wp_send_json_error(array('message' => '無効なユーザーIDです'));
                return;
            }

            // ユーザーデータを取得
            $user_data = array(
                'job_title' => sanitize_text_field($_POST['job_title'] ?? ''),
                'works_for' => sanitize_text_field($_POST['works_for'] ?? ''),
                'description' => sanitize_text_field($_POST['description'] ?? ''),
                'address' => sanitize_text_field($_POST['address'] ?? ''),
                'telephone' => sanitize_text_field($_POST['telephone'] ?? ''),
                'knows_about' => sanitize_text_field($_POST['knows_about'] ?? ''),
                'alumni_of' => sanitize_text_field($_POST['alumni_of'] ?? ''),
                'profile_image' => esc_url_raw($_POST['profile_image'] ?? ''),
                'profile_url' => esc_url_raw($_POST['profile_url'] ?? '')
            );

            $result = KSSCTB_Settings::get_instance()->update_user_schema_data($user_id, $user_data);

            if ($result) {
                wp_send_json_success(array('message' => 'ユーザーデータを保存しました'));
            } else {
                wp_send_json_error(array('message' => 'ユーザーデータの保存に失敗しました'));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}
