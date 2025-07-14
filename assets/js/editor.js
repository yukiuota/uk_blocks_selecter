/**
 * ブロックエディタ用JavaScript
 */

// DOMが読み込まれたときの処理
wp.domReady(function() {
    // 現在の投稿タイプを取得
    const postType = ukBlocksSelecterEditor.postType;
    const allowedBlocks = ukBlocksSelecterEditor.allowedBlocks;
    
    // 許可されたブロックが設定されている場合のみ制限を適用
    if (allowedBlocks && allowedBlocks.length > 0) {
        console.log('UK Blocks Selecter: ブロック制限を適用 (投稿タイプ: ' + postType + ')');
        console.log('許可されたブロック:', allowedBlocks);
        
        // より安全な方法でブロックを制限
        applyBlockRestrictions(allowedBlocks);
    }
    
    // デバッグ機能の追加
    if (ukBlocksSelecterEditor.debug) {
        console.log('UK Blocks Selecter Debug Info:', {
            postType: ukBlocksSelecterEditor.postType,
            allowedBlocks: ukBlocksSelecterEditor.allowedBlocks,
            totalAllowedBlocks: ukBlocksSelecterEditor.allowedBlocks ? ukBlocksSelecterEditor.allowedBlocks.length : 0
        });
        
        // 利用可能なWordPress APIの確認
        console.log('Available WordPress APIs:', {
            'wp.data': typeof wp.data !== 'undefined',
            'wp.blocks': typeof wp.blocks !== 'undefined',
            'wp.hooks': typeof wp.hooks !== 'undefined',
            'wp.data.select': wp.data && typeof wp.data.select !== 'undefined',
            'wp.data.dispatch': wp.data && typeof wp.data.dispatch !== 'undefined'
        });
    }
});

/**
 * ブロック制限を適用する関数
 */
function applyBlockRestrictions(allowedBlocks) {
    // 1. 最も安全な方法：エディタ設定での制限
    if (wp.data && wp.data.dispatch && wp.data.select) {
        try {
            const { dispatch, select } = wp.data;
            
            // エディタストアが利用可能か確認
            if (select('core/editor') && dispatch('core/editor')) {
                const currentSettings = select('core/editor').getEditorSettings();
                
                // 既存の設定を保持しつつ、許可されたブロックのみを設定
                const newSettings = {
                    ...currentSettings,
                    allowedBlockTypes: allowedBlocks
                };
                
                dispatch('core/editor').updateEditorSettings(newSettings);
                console.log('UK Blocks Selecter: エディタ設定でブロック制限を適用しました');
                return true;
            }
        } catch (error) {
            console.warn('UK Blocks Selecter: エディタ設定での制限に失敗:', error);
        }
    }
    
    // 2. フォールバック：ブロックインサーターのフィルタリング
    if (wp.hooks && wp.hooks.addFilter) {
        try {
            wp.hooks.addFilter(
                'blocks.registerBlockType',
                'uk-blocks-selecter/filter-blocks',
                function(settings, name) {
                    if (allowedBlocks.indexOf(name) === -1) {
                        return {
                            ...settings,
                            supports: {
                                ...settings.supports,
                                inserter: false
                            }
                        };
                    }
                    return settings;
                }
            );
            console.log('UK Blocks Selecter: フックでブロック制限を適用しました');
            return true;
        } catch (error) {
            console.warn('UK Blocks Selecter: フックでの制限に失敗:', error);
        }
    }
    
    // 3. 最後の手段：ブロックの非表示化（CSSベース）
    hideUnallowedBlocks(allowedBlocks);
    
    return false;
}

/**
 * 許可されていないブロックを非表示にする（CSSベース）
 */
function hideUnallowedBlocks(allowedBlocks) {
    // 動的にスタイルを追加
    const styleId = 'uk-blocks-selecter-restrictions';
    let style = document.getElementById(styleId);
    
    if (!style) {
        style = document.createElement('style');
        style.id = styleId;
        document.head.appendChild(style);
    }
    
    // 全てのブロックを取得
    const allBlocks = wp.blocks.getBlockTypes();
    const hiddenBlocks = allBlocks
        .filter(block => allowedBlocks.indexOf(block.name) === -1)
        .map(block => block.name);
    
    // 非表示にするCSSを生成
    const hideRules = hiddenBlocks.map(blockName => {
        const cssSelector = blockName.replace(/[^a-zA-Z0-9-]/g, '\\$&');
        return `.block-editor-inserter__menu [data-type="${cssSelector}"] { display: none !important; }`;
    }).join('\n');
    
    style.textContent = hideRules;
    
    console.log('UK Blocks Selecter: CSSでブロック制限を適用しました');
}

// 追加の安全対策：エラーハンドリング
window.addEventListener('error', function(event) {
    if (event.error && event.error.message && 
        event.error.message.includes('Maximum call stack size exceeded') &&
        event.error.stack && event.error.stack.includes('blocks.min.js')) {
        
        console.warn('UK Blocks Selecter: ブロック制限でエラーが発生しました。フォールバック処理を実行します。');
        
        // エラーが発生した場合は制限を無効化
        if (wp.data && wp.data.dispatch && wp.data.dispatch('core/editor')) {
            const { dispatch } = wp.data;
            const currentSettings = wp.data.select('core/editor').getEditorSettings();
            
            const newSettings = {
                ...currentSettings,
                allowedBlockTypes: true // 全てのブロックを許可
            };
            
            dispatch('core/editor').updateEditorSettings(newSettings);
        }
    }
});
