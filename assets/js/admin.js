/**
 * 管理画面JavaScript
 */

jQuery(document).ready(function($) {
    let currentPostType = null;
    
    // 投稿タイプクリック時の処理
    $('.post-type-item').on('click', function(e) {
        e.preventDefault();
        
        const postType = $(this).data('post-type');
        currentPostType = postType;
        
        // アクティブ状態の切り替え
        $('.post-type-item').removeClass('active');
        $(this).addClass('active');
        
        // ブロック設定の読み込み
        loadBlockSettings(postType);
        
        // 設定コンテナの表示
        $('.welcome-message').hide();
        $('.blocks-settings-container').show();
    });
    
    // ブロック設定の読み込み
    function loadBlockSettings(postType) {
        const $form = $('#blocks-settings-form');
        const $checkboxes = $form.find('input[name="blocks[]"]');
        
        // 全てのチェックボックスをリセット
        $checkboxes.prop('checked', false);
        updateCategoryCheckboxes();
        
        // 保存された設定を取得
        $.ajax({
            url: ukBlocksSelecter.ajax_url,
            type: 'POST',
            data: {
                action: 'get_block_settings',
                post_type: postType,
                nonce: ukBlocksSelecter.nonce
            },
            success: function(response) {
                if (response.success) {
                    const allowedBlocks = response.data.blocks;
                    
                    // 対応するチェックボックスにチェックを入れる
                    allowedBlocks.forEach(function(blockName) {
                        $checkboxes.filter('[value="' + blockName + '"]').prop('checked', true);
                    });
                    
                    updateCategoryCheckboxes();
                }
            },
            error: function() {
                showMessage('設定の読み込みに失敗しました', 'error');
            }
        });
    }
    
    // フォーム送信時の処理
    $('#blocks-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!currentPostType) {
            showMessage('投稿タイプが選択されていません', 'error');
            return;
        }
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $spinner = $form.find('.spinner');
        
        // UI状態の更新
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        // 選択されたブロックを取得
        const selectedBlocks = [];
        $form.find('input[name="blocks[]"]:checked').each(function() {
            selectedBlocks.push($(this).val());
        });
        
        // 設定を保存
        $.ajax({
            url: ukBlocksSelecter.ajax_url,
            type: 'POST',
            data: {
                action: 'save_block_settings',
                post_type: currentPostType,
                blocks: selectedBlocks,
                nonce: ukBlocksSelecter.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                } else {
                    showMessage('設定の保存に失敗しました', 'error');
                }
            },
            error: function() {
                showMessage('設定の保存に失敗しました', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // すべて選択ボタン
    $('.select-all').on('click', function() {
        $('#blocks-settings-form input[name="blocks[]"]').prop('checked', true);
        updateCategoryCheckboxes();
    });
    
    // すべて解除ボタン
    $('.deselect-all').on('click', function() {
        $('#blocks-settings-form input[name="blocks[]"]').prop('checked', false);
        updateCategoryCheckboxes();
    });
    
    // カテゴリチェックボックスの処理
    $('.category-checkbox').on('change', function() {
        const category = $(this).data('category');
        const isChecked = $(this).is(':checked');
        
        $('input[name="blocks[]"][data-category="' + category + '"]').prop('checked', isChecked);
    });
    
    // 個別ブロックチェックボックスの処理
    $(document).on('change', 'input[name="blocks[]"]', function() {
        updateCategoryCheckboxes();
    });
    
    // カテゴリチェックボックスの状態を更新
    function updateCategoryCheckboxes() {
        $('.category-checkbox').each(function() {
            const category = $(this).data('category');
            const $categoryBlocks = $('input[name="blocks[]"][data-category="' + category + '"]');
            const checkedCount = $categoryBlocks.filter(':checked').length;
            const totalCount = $categoryBlocks.length;
            
            if (checkedCount === 0) {
                $(this).prop('checked', false).prop('indeterminate', false);
            } else if (checkedCount === totalCount) {
                $(this).prop('checked', true).prop('indeterminate', false);
            } else {
                $(this).prop('checked', false).prop('indeterminate', true);
            }
        });
    }
    
    // メッセージ表示
    function showMessage(message, type) {
        const $container = $('#message-container');
        const alertClass = type === 'success' ? 'notice-success' : 
                          type === 'error' ? 'notice-error' : 
                          'notice-warning';
        
        const $notice = $('<div class="notice ' + alertClass + '"><p>' + message + '</p></div>');
        
        $container.empty().append($notice);
        
        // 3秒後に自動で消去
        setTimeout(function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        }, 3000);
    }
});
