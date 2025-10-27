jQuery(document).ready(function ($) {
    console.log('KSSCTB Admin JS loaded');

    // 初期表示時に最初のタブを表示
    $('.kssctb-tab-panel').hide();
    $('.kssctb-tab-panel:first').show();

    $('.nav-tab').on('click', function (e) {
        e.preventDefault();

        var tabId = $(this).data('tab');

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.kssctb-tab-panel').hide();
        $('#' + tabId).show();

        // タブ切り替え時にもフィールドの表示/非表示を更新
        toggleTypeFields();
    });

    // URL検証関数
    function isValidUrl(string) {
        if (!string) return true; // 空欄は許可
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    // URLフィールドのバリデーション
    $(document).on('blur', 'input[type="text"][name*="_url"], input[type="text"][name*="_logo"], input[type="text"][name*="_image"]', function () {
        var $input = $(this);
        var value = $input.val();

        if (value && !isValidUrl(value)) {
            $input.addClass('error');
            if (!$input.next('.error-message').length) {
                $input.after('<span class="error-message" style="color: red; font-size: 12px;">有効なURLを入力してください</span>');
            }
        } else {
            $input.removeClass('error');
            $input.next('.error-message').remove();
        }
    });

    // Author/Publisher/Sponsor Typeの表示切り替え
    function toggleTypeFields() {
        // すべてのタブパネルに対して処理を実行
        $('.kssctb-tab-panel').each(function () {
            const $panel = $(this);
            const contentType = $panel.attr('id');

            // Author Type
            const authorType = $panel.find(`select[name="kssctb_${contentType}_default_author_type"]`).val();
            $panel.find('.author-fields-person, .author-fields-organization, .author-fields-corporation').hide();
            if (authorType && authorType !== '' && authorType !== 'none') {
                $panel.find(`.author-fields-${authorType.toLowerCase()}`).show();
            }

            // Publisher Type
            const publisherType = $panel.find(`select[name="kssctb_${contentType}_publisher_type"]`).val();
            $panel.find('.publisher-fields-person, .publisher-fields-organization, .publisher-fields-corporation').hide();
            if (publisherType && publisherType !== '' && publisherType !== 'none') {
                $panel.find(`.publisher-fields-${publisherType.toLowerCase()}`).show();
            }

            // Sponsor Type
            const sponsorType = $panel.find(`select[name="kssctb_${contentType}_sponsor_type"]`).val();
            $panel.find('.sponsor-fields-person, .sponsor-fields-organization, .sponsor-fields-corporation').hide();
            if (sponsorType && sponsorType !== '' && sponsorType !== 'none') {
                $panel.find(`.sponsor-fields-${sponsorType.toLowerCase()}`).show();
            }
        });
    }

    // ユーザーデータを取得してフォームに反映
    function loadUserData(userId, contentType) {
        if (!userId || userId === '') {
            // ユーザーが選択されていない場合は空にする
            clearUserDataFields(contentType);
            return;
        }

        console.log('ユーザーデータ取得開始:', userId, contentType);

        var formData = {
            action: 'kssctb_get_user_data',
            user_id: userId,
            nonce: $('#kssctb-settings-form').find('input[name="kssctb_nonce"]').val()
        };

        $.post(ajaxurl, formData, function (response) {
            console.log('ユーザーデータ取得レスポンス:', response);

            if (response.success && response.data.user_data) {
                var userData = response.data.user_data;
                var $panel = $('#' + contentType);

                // 各フィールドに値を設定
                $panel.find(`input[name="kssctb_${contentType}_author_person_image"]`).val(userData.profile_image || '');
                $panel.find(`input[name="kssctb_${contentType}_author_person_job_title"]`).val(userData.job_title || '');
                $panel.find(`input[name="kssctb_${contentType}_author_person_works_for"]`).val(userData.works_for || '');
                $panel.find(`input[name="kssctb_${contentType}_author_person_description"]`).val(userData.description || '');
                $panel.find(`input[name="kssctb_${contentType}_author_person_address"]`).val(userData.address || '');
                $panel.find(`input[name="kssctb_${contentType}_author_person_telephone"]`).val(userData.telephone || '');
                $panel.find(`input[name="kssctb_${contentType}_author_person_knows_about"]`).val(userData.knows_about || '');
                $panel.find(`input[name="kssctb_${contentType}_author_person_alumni_of"]`).val(userData.alumni_of || '');

                console.log('ユーザーデータをフォームに反映しました');
            } else {
                console.error('ユーザーデータ取得エラー:', response);
                clearUserDataFields(contentType);
            }
        }).fail(function () {
            console.error('ユーザーデータ取得通信エラー');
            clearUserDataFields(contentType);
        });
    }

    // ユーザーデータフィールドをクリア
    function clearUserDataFields(contentType) {
        var $panel = $('#' + contentType);

        $panel.find(`input[name="kssctb_${contentType}_author_person_image"]`).val('');
        $panel.find(`input[name="kssctb_${contentType}_author_person_job_title"]`).val('');
        $panel.find(`input[name="kssctb_${contentType}_author_person_works_for"]`).val('');
        $panel.find(`input[name="kssctb_${contentType}_author_person_description"]`).val('');
        $panel.find(`input[name="kssctb_${contentType}_author_person_address"]`).val('');
        $panel.find(`input[name="kssctb_${contentType}_author_person_telephone"]`).val('');
        $panel.find(`input[name="kssctb_${contentType}_author_person_knows_about"]`).val('');
        $panel.find(`input[name="kssctb_${contentType}_author_person_alumni_of"]`).val('');
    }

    // ユーザーデータを保存
    function saveUserData(userId, contentType) {
        if (!userId || userId === '') {
            alert('ユーザーが選択されていません');
            return;
        }

        console.log('ユーザーデータ保存開始:', userId, contentType);

        var $panel = $('#' + contentType);

        var formData = {
            action: 'kssctb_save_user_data',
            user_id: userId,
            job_title: $panel.find(`input[name="kssctb_${contentType}_author_person_job_title"]`).val(),
            works_for: $panel.find(`input[name="kssctb_${contentType}_author_person_works_for"]`).val(),
            description: $panel.find(`input[name="kssctb_${contentType}_author_person_description"]`).val(),
            address: $panel.find(`input[name="kssctb_${contentType}_author_person_address"]`).val(),
            telephone: $panel.find(`input[name="kssctb_${contentType}_author_person_telephone"]`).val(),
            knows_about: $panel.find(`input[name="kssctb_${contentType}_author_person_knows_about"]`).val(),
            alumni_of: $panel.find(`input[name="kssctb_${contentType}_author_person_alumni_of"]`).val(),
            profile_image: $panel.find(`input[name="kssctb_${contentType}_author_person_image"]`).val(),
            profile_url: '', // 現在は使用していない
            nonce: $('#kssctb-settings-form').find('input[name="kssctb_nonce"]').val()
        };

        $.post(ajaxurl, formData, function (response) {
            console.log('ユーザーデータ保存レスポンス:', response);

            if (response.success) {
                alert('ユーザーデータを保存しました');
            } else {
                alert('ユーザーデータの保存に失敗しました: ' + (response.data.message || '不明なエラー'));
            }
        }).fail(function () {
            alert('通信エラーが発生しました');
        });
    }

    // 投稿タイプの重複チェック機能
    function updatePostTypeCheckboxes() {
        // すべてのチェックされている投稿タイプを収集
        var checkedPostTypes = {};

        $('.kssctb-tab-panel').each(function () {
            var $panel = $(this);
            var contentType = $panel.attr('id');

            $panel.find('input[name="kssctb_' + contentType + '_post_types[]"]:checked').each(function () {
                var postType = $(this).val();
                if (!checkedPostTypes[postType]) {
                    checkedPostTypes[postType] = [];
                }
                checkedPostTypes[postType].push(contentType);
            });
        });

        // 各投稿タイプのチェックボックスを更新
        $('.kssctb-tab-panel').each(function () {
            var $panel = $(this);
            var currentContentType = $panel.attr('id');

            $panel.find('input[name="kssctb_' + currentContentType + '_post_types[]"]').each(function () {
                var $checkbox = $(this);
                var postType = $checkbox.val();
                var $label = $checkbox.closest('label');

                // 他のContent Typeで使用されているかチェック
                var usedInOther = checkedPostTypes[postType] &&
                    checkedPostTypes[postType].length > 0 &&
                    checkedPostTypes[postType].indexOf(currentContentType) === -1;

                if (usedInOther) {
                    // 他で使用されている場合は無効化
                    $checkbox.prop('disabled', true);
                    $label.css('opacity', '0.5');

                    // 使用中のContent Typeを表示
                    var usedIn = checkedPostTypes[postType][0];
                    var contentTypeLabel = usedIn.charAt(0).toUpperCase() + usedIn.slice(1);

                    // 既存の説明を削除
                    $label.find('.post-type-used-in').remove();

                    // 新しい説明を追加
                    $label.append('<span class="post-type-used-in" style="color: #d63638; font-size: 12px; margin-left: 5px;">(' + contentTypeLabel + 'で使用中)</span>');
                } else {
                    // 使用されていない場合は有効化
                    $checkbox.prop('disabled', false);
                    $label.css('opacity', '1');
                    $label.find('.post-type-used-in').remove();
                }
            });
        });
    }

    // アーカイブタイプの重複チェック機能
    function updateArchiveTypeCheckboxes() {
        // すべてのチェックされているアーカイブタイプを収集
        var checkedArchiveTypes = {};

        $('.kssctb-tab-panel').each(function () {
            var $panel = $(this);
            var contentType = $panel.attr('id');

            $panel.find('input[name="kssctb_' + contentType + '_archive_types[]"]:checked').each(function () {
                var archiveType = $(this).val();
                if (!checkedArchiveTypes[archiveType]) {
                    checkedArchiveTypes[archiveType] = [];
                }
                checkedArchiveTypes[archiveType].push(contentType);
            });
        });

        // 各アーカイブタイプのチェックボックスを更新
        $('.kssctb-tab-panel').each(function () {
            var $panel = $(this);
            var currentContentType = $panel.attr('id');

            $panel.find('input[name="kssctb_' + currentContentType + '_archive_types[]"]').each(function () {
                var $checkbox = $(this);
                var archiveType = $checkbox.val();
                var $label = $checkbox.closest('label');

                // 他のContent Typeで使用されているかチェック
                var usedInOther = checkedArchiveTypes[archiveType] &&
                    checkedArchiveTypes[archiveType].length > 0 &&
                    checkedArchiveTypes[archiveType].indexOf(currentContentType) === -1;

                if (usedInOther) {
                    // 他で使用されている場合は無効化
                    $checkbox.prop('disabled', true);
                    $label.css('opacity', '0.5');

                    // 使用中のContent Typeを表示
                    var usedIn = checkedArchiveTypes[archiveType][0];
                    var contentTypeLabel = usedIn.charAt(0).toUpperCase() + usedIn.slice(1);

                    // 既存の説明を削除
                    $label.find('.archive-type-used-in').remove();

                    // 新しい説明を追加
                    $label.append('<span class="archive-type-used-in" style="color: #d63638; font-size: 12px; margin-left: 5px;">(' + contentTypeLabel + 'で使用中)</span>');
                } else {
                    // 使用されていない場合は有効化
                    $checkbox.prop('disabled', false);
                    $label.css('opacity', '1');
                    $label.find('.archive-type-used-in').remove();
                }
            });
        });
    }

    // 投稿タイプのチェックボックスが変更されたときに実行
    $(document).on('change', 'input[name*="_post_types[]"]', function () {
        updatePostTypeCheckboxes();
    });

    // アーカイブタイプのチェックボックスが変更されたときに実行
    $(document).on('change', 'input[name*="_archive_types[]"]', function () {
        updateArchiveTypeCheckboxes();
    });

    // 初期表示時とselect変更時に実行
    toggleTypeFields();
    updatePostTypeCheckboxes();
    updateArchiveTypeCheckboxes();

    // 初期表示時にDefault Person Authorの状態もチェック
    $('select[name*="_author_person_user"]').each(function () {
        var $select = $(this);
        var userId = $select.val();
        var nameAttr = $select.attr('name');
        var contentType = nameAttr.replace('kssctb_', '').replace('_author_person_user', '');
        var $panel = $('#' + contentType);

        if (!userId || userId === '') {
            // 「投稿者を使用」が選択されている場合
            console.log('初期化 - 投稿者を使用');

            // 詳細フィールドを非表示（ユーザー選択フィールドは除外）
            $panel.find('.author-fields-person').not('.author-user-selector').hide();

            // 「投稿者を使用」の説明を表示
            $panel.find('.kssctb-use-post-author-info').show();
            $panel.find('.kssctb-specific-user-controls').hide();

            // フィールドをクリア
            clearUserDataFields(contentType);
        } else {
            // 特定ユーザーが選択されている場合
            console.log('初期化 - 特定ユーザー選択:', userId);

            // 詳細フィールドを表示
            $panel.find('.author-fields-person').show();

            // 特定ユーザー用のコントロールを表示
            $panel.find('.kssctb-use-post-author-info').hide();
            $panel.find('.kssctb-specific-user-controls').show();

            // ユーザーデータを読み込み
            loadUserData(userId, contentType);

            // ボタンのdata-user-idを更新
            $panel.find('.kssctb-save-user-data').attr('data-user-id', userId);
        }
    });

    $('select[name*="_default_author_type"], select[name*="_publisher_type"], select[name*="_sponsor_type"]').on('change', function () {
        console.log('Type select changed:', $(this).attr('name'), $(this).val());
        toggleTypeFields();
    });

    // Default Person Authorの変更イベント
    $(document).on('change', 'select[name*="_author_person_user"]', function () {
        var $select = $(this);
        var userId = $select.val();
        var nameAttr = $select.attr('name');
        var contentType = nameAttr.replace('kssctb_', '').replace('_author_person_user', '');

        console.log('Author Person User変更:', userId, contentType);

        var $panel = $('#' + contentType);

        if (!userId || userId === '') {
            // 「投稿者を使用」が選択された場合
            console.log('投稿者を使用が選択されました');

            // 詳細フィールドを非表示（ユーザー選択フィールドは除外）
            $panel.find('.author-fields-person').not('.author-user-selector').hide();

            // 「投稿者を使用」の説明を表示
            $panel.find('.kssctb-use-post-author-info').show();
            $panel.find('.kssctb-specific-user-controls').hide();

            // フィールドをクリア
            clearUserDataFields(contentType);
        } else {
            // 特定ユーザーが選択された場合
            console.log('特定ユーザーが選択されました:', userId);

            // 詳細フィールドを表示
            $panel.find('.author-fields-person').show();

            // 特定ユーザー用のコントロールを表示
            $panel.find('.kssctb-use-post-author-info').hide();
            $panel.find('.kssctb-specific-user-controls').show();

            // ユーザーデータを読み込み
            loadUserData(userId, contentType);

            // ボタンのdata-user-idを更新
            $panel.find('.kssctb-save-user-data').attr('data-user-id', userId);
        }
    });

    // ユーザーデータ保存ボタンのクリックイベント
    $(document).on('click', '.kssctb-save-user-data', function () {
        var $button = $(this);
        var contentType = $button.data('content-type');

        // data-user-id属性から取得、なければセレクトボックスの値から取得
        var userId = $button.attr('data-user-id');
        if (!userId || userId === '') {
            var $userSelect = $('#' + contentType).find('select[name*="_author_person_user"]');
            userId = $userSelect.val();
        }

        if (!userId || userId === '') {
            alert('ユーザーデータの保存対象が見つかりません');
            return;
        }

        var userName = '';
        if ($button.attr('data-user-id')) {
            // 「投稿者を使用」の場合
            userName = '現在のユーザー';
        } else {
            // 特定ユーザーが選択されている場合
            var $userSelect = $('#' + contentType).find('select[name*="_author_person_user"]');
            userName = $userSelect.find('option:selected').text() || '選択されたユーザー';
        }

        if (!confirm(userName + 'の詳細情報を保存しますか？')) {
            return;
        }

        // ボタンを無効化
        var originalText = $button.text();
        $button.prop('disabled', true).text('保存中...');

        // ユーザーデータ保存を実行
        saveUserData(userId, contentType);

        // ボタンを有効化
        setTimeout(function () {
            $button.prop('disabled', false).text(originalText);
        }, 2000);
    });

    // メディアライブラリの統合
    var mediaUploader;

    $(document).on('click', '.kssctb-media-select', function (e) {
        e.preventDefault();

        var $button = $(this);
        var targetField = $button.data('target');
        var $targetInput = $('input[name="' + targetField + '"]');

        // メディアアップローダーが既に開いている場合
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // メディアアップローダーを作成
        mediaUploader = wp.media({
            title: '画像を選択',
            button: {
                text: 'この画像を使用'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        // 画像が選択されたときの処理
        mediaUploader.on('select', function () {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $targetInput.val(attachment.url);
            mediaUploader = null; // リセット
        });

        mediaUploader.open();
    });

    // 一括反映機能
    $(document).on('click', '.kssctb-bulk-copy', function () {
        var $button = $(this);
        var sourceContentType = $button.data('content-type');
        var $sourcePanel = $('#' + sourceContentType);

        console.log('一括反映開始 - ソース:', sourceContentType);

        if (!confirm('このタブのAuthor/Publisher/Sponsor設定を他のすべてのタブに反映しますか？')) {
            return;
        }

        // コピーするフィールドのリスト（拡張されたOrganizationとCorporationフィールドを含む）
        var fieldsToCopy = [
            'default_author_type',
            'author_person_user',
            'author_person_url',
            'author_person_image',
            'author_person_job_title',
            'author_person_works_for',
            'author_person_description',
            'author_person_address',
            'author_person_telephone',
            'author_person_knows_about',
            'author_person_alumni_of',
            'author_organization_name',
            'author_organization_url',
            'author_organization_logo',
            'author_organization_street_address',
            'author_organization_address_locality',
            'author_organization_address_region',
            'author_organization_postal_code',
            'author_organization_address_country',
            'author_organization_telephone',
            'author_organization_description',
            'author_organization_legal_name',
            'author_organization_founding_date',
            'author_organization_number_of_employees',
            'author_organization_same_as',
            'author_corporation_name',
            'author_corporation_url',
            'author_corporation_logo',
            'author_corporation_street_address',
            'author_corporation_address_locality',
            'author_corporation_address_region',
            'author_corporation_postal_code',
            'author_corporation_address_country',
            'author_corporation_telephone',
            'author_corporation_description',
            'author_corporation_representative_name',
            'author_corporation_representative_title',
            'author_corporation_founding_date',
            'author_corporation_corporate_number',
            'author_corporation_social_media',
            'publisher_type',
            'publisher_person_user',
            'publisher_person_url',
            'publisher_person_image',
            'publisher_person_job_title',
            'publisher_person_works_for',
            'publisher_person_description',
            'publisher_person_address',
            'publisher_person_telephone',
            'publisher_person_knows_about',
            'publisher_person_alumni_of',
            'publisher_organization_name',
            'publisher_organization_url',
            'publisher_organization_logo',
            'publisher_organization_street_address',
            'publisher_organization_address_locality',
            'publisher_organization_address_region',
            'publisher_organization_postal_code',
            'publisher_organization_address_country',
            'publisher_organization_telephone',
            'publisher_organization_description',
            'publisher_organization_legal_name',
            'publisher_organization_founding_date',
            'publisher_organization_number_of_employees',
            'publisher_organization_same_as',
            'publisher_corporation_name',
            'publisher_corporation_url',
            'publisher_corporation_logo',
            'publisher_corporation_street_address',
            'publisher_corporation_address_locality',
            'publisher_corporation_address_region',
            'publisher_corporation_postal_code',
            'publisher_corporation_address_country',
            'publisher_corporation_telephone',
            'publisher_corporation_description',
            'publisher_corporation_representative_name',
            'publisher_corporation_representative_title',
            'publisher_corporation_founding_date',
            'publisher_corporation_corporate_number',
            'publisher_corporation_social_media',
            'sponsor_type',
            'sponsor_person_user',
            'sponsor_person_url',
            'sponsor_person_image',
            'sponsor_person_job_title',
            'sponsor_person_works_for',
            'sponsor_person_description',
            'sponsor_person_address',
            'sponsor_person_telephone',
            'sponsor_person_knows_about',
            'sponsor_person_alumni_of',
            'sponsor_organization_name',
            'sponsor_organization_url',
            'sponsor_organization_logo',
            'sponsor_organization_street_address',
            'sponsor_organization_address_locality',
            'sponsor_organization_address_region',
            'sponsor_organization_postal_code',
            'sponsor_organization_address_country',
            'sponsor_organization_telephone',
            'sponsor_organization_description',
            'sponsor_organization_legal_name',
            'sponsor_organization_founding_date',
            'sponsor_organization_number_of_employees',
            'sponsor_organization_same_as',
            'sponsor_corporation_name',
            'sponsor_corporation_url',
            'sponsor_corporation_logo',
            'sponsor_corporation_street_address',
            'sponsor_corporation_address_locality',
            'sponsor_corporation_address_region',
            'sponsor_corporation_postal_code',
            'sponsor_corporation_address_country',
            'sponsor_corporation_telephone',
            'sponsor_corporation_description',
            'sponsor_corporation_representative_name',
            'sponsor_corporation_representative_title',
            'sponsor_corporation_founding_date',
            'sponsor_corporation_corporate_number',
            'sponsor_corporation_social_media'
        ];

        console.log('コピー対象フィールド数:', fieldsToCopy.length);

        var copiedCount = 0;

        // 他のすべてのタブに反映
        $('.kssctb-tab-panel').each(function () {
            var $targetPanel = $(this);
            var targetContentType = $targetPanel.attr('id');

            // 同じタブはスキップ
            if (targetContentType === sourceContentType) {
                return;
            }

            console.log('ターゲットタブ:', targetContentType);

            // 各フィールドの値をコピー
            fieldsToCopy.forEach(function (fieldName) {
                // ソースの値を取得
                var sourceFieldName = 'kssctb_' + sourceContentType + '_' + fieldName;
                var targetFieldName = 'kssctb_' + targetContentType + '_' + fieldName;

                // selectフィールドの場合
                var $sourceSelect = $sourcePanel.find('select[name="' + sourceFieldName + '"]');
                if ($sourceSelect.length) {
                    var value = $sourceSelect.val();
                    var $targetSelect = $targetPanel.find('select[name="' + targetFieldName + '"]');
                    if ($targetSelect.length) {
                        $targetSelect.val(value);
                        console.log('SELECT コピー完了:', fieldName, value);
                        copiedCount++;
                    }
                }

                // textフィールドの場合
                var $sourceInput = $sourcePanel.find('input[name="' + sourceFieldName + '"]');
                if ($sourceInput.length) {
                    var value = $sourceInput.val();
                    var $targetInput = $targetPanel.find('input[name="' + targetFieldName + '"]');
                    if ($targetInput.length) {
                        $targetInput.val(value);
                        console.log('INPUT コピー完了:', fieldName, value);
                        copiedCount++;
                    }
                }
            });
        });

        console.log('コピー完了フィールド数:', copiedCount);

        // フィールドの表示/非表示を更新
        toggleTypeFields();

        // 成功メッセージ
        $button.after('<span class="kssctb-bulk-copy-success" style="color: #46b450; margin-left: 10px;">一括反映しました (' + copiedCount + '件) - 「設定を保存」ボタンを押してデータベースに保存してください</span>');

        // 設定を保存ボタンをハイライト表示
        var $saveButton = $('#kssctb-save-settings');
        $saveButton.addClass('button-primary-highlight');
        $saveButton.css({
            'animation': 'pulse 1s infinite',
            'box-shadow': '0 0 10px #0073aa'
        });

        setTimeout(function () {
            $('.kssctb-bulk-copy-success').fadeOut(function () {
                $(this).remove();
            });

            // ハイライト効果を解除
            $saveButton.removeClass('button-primary-highlight');
            $saveButton.css({
                'animation': '',
                'box-shadow': ''
            });
        }, 5000);
    });

    // 設定保存処理
    $('#kssctb-save-settings').on('click', function () {
        var $button = $(this);
        var $message = $('.kssctb-save-message');

        // バリデーションエラーがあるかチェック
        var hasError = false;
        $('input[type="text"][name*="_url"], input[type="text"][name*="_logo"], input[type="text"][name*="_image"]').each(function () {
            var $input = $(this);
            var value = $input.val();

            if (value && !isValidUrl(value)) {
                $input.addClass('error');
                if (!$input.next('.error-message').length) {
                    $input.after('<span class="error-message" style="color: red; font-size: 12px;">有効なURLを入力してください</span>');
                }
                hasError = true;

                // エラーフィールドのタブに切り替え
                var $panel = $input.closest('.kssctb-tab-panel');
                if ($panel.length && !$panel.is(':visible')) {
                    var tabId = $panel.attr('id');
                    $('.nav-tab[data-tab="' + tabId + '"]').click();
                }
            }
        });

        if (hasError) {
            $message.removeClass('success').addClass('error').text('入力エラーがあります。修正してから保存してください。');
            return;
        }

        // 現在のタブを取得
        var activeTab = $('.nav-tab-active').data('tab');

        // フォームデータを収集
        var formData = {
            action: 'kssctb_save_settings',
            nonce: $('#kssctb-settings-form').find('input[name="kssctb_nonce"]').val()
        };

        // 各タブのデータを収集
        $('.kssctb-tab-panel').each(function () {
            var $panel = $(this);
            var contentType = $panel.attr('id');

            // すべてのフィールドを処理
            $panel.find('input[type="checkbox"], input[type="text"], select').each(function () {
                var $input = $(this);
                var name = $input.attr('name');

                if (!name) return; // name属性がない場合はスキップ

                if ($input.is(':checkbox')) {
                    if (name.endsWith('[]')) {
                        // 複数選択可能なチェックボックス（配列）
                        if ($input.is(':checked')) {
                            if (!formData[name]) {
                                formData[name] = [];
                            }
                            formData[name].push($input.val());
                        }
                    } else {
                        // 単一のチェックボックス - 明示的に0または1を送信
                        formData[name] = $input.is(':checked') ? '1' : '0';
                    }
                } else {
                    // テキストフィールドとselect要素
                    formData[name] = $input.val() || '';
                }
            });
        });

        // 保存中の表示
        $button.prop('disabled', true).text('保存中...');
        $message.removeClass('success error').text('');

        // Ajax送信
        $.post(ajaxurl, formData, function (response) {
            if (response.success) {
                $message.addClass('success').text(response.data.message);

                // 保存後に現在のタブを維持
                setTimeout(function () {
                    if (activeTab) {
                        $('.nav-tab[data-tab="' + activeTab + '"]').click();
                    }
                }, 100);
            } else {
                $message.addClass('error').text(response.data.message || '保存に失敗しました。');
            }
        }).fail(function () {
            $message.addClass('error').text('通信エラーが発生しました。');
        }).always(function () {
            $button.prop('disabled', false).text('設定を保存');

            // メッセージを3秒後に消す
            setTimeout(function () {
                $message.fadeOut(function () {
                    $(this).removeClass('success error').text('').show();
                });
            }, 3000);
        });
    });

    // リセット処理
    $('#kssctb-reset-all').on('click', function () {
        if (!confirm('すべての設定をリセットしてもよろしいですか？この操作は取り消せません。')) {
            return;
        }

        var $button = $(this);
        var $message = $('.kssctb-save-message');

        var formData = {
            action: 'kssctb_reset_settings',
            nonce: $('#kssctb-settings-form').find('input[name="kssctb_nonce"]').val()
        };

        // リセット中の表示
        $button.prop('disabled', true).text('リセット中...');
        $message.removeClass('success error').text('');

        // Ajax送信
        $.post(ajaxurl, formData, function (response) {
            if (response.success) {
                $message.addClass('success').text(response.data.message);

                // ページをリロードして初期状態に戻す
                setTimeout(function () {
                    location.reload();
                }, 1000);
            } else {
                $message.addClass('error').text('リセットに失敗しました。');
            }
        }).fail(function () {
            $message.addClass('error').text('通信エラーが発生しました。');
        }).always(function () {
            $button.prop('disabled', false).text('すべてリセット');
        });
    });
});
