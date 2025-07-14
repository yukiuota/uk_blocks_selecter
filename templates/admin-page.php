<?php
/**
 * 管理画面テンプレート
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = UK_Blocks_Selecter::get_instance();
$post_types = $plugin->get_all_post_types();
$blocks = $plugin->get_blocks_for_admin();

// カテゴリ別にブロックをグループ化
$blocks_by_category = array();
foreach ($blocks as $block) {
    $category = $block['category'];
    // テーマブロックの場合は専用カテゴリにする
    if ($block['is_theme_block']) {
        $category = 'theme-blocks';
    }
    
    if (!isset($blocks_by_category[$category])) {
        $blocks_by_category[$category] = array();
    }
    $blocks_by_category[$category][] = $block;
}

// カテゴリ名を日本語にマッピング
$category_labels = array(
    'common' => '一般',
    'text' => 'テキスト',
    'media' => 'メディア',
    'design' => 'デザイン',
    'widgets' => 'ウィジェット',
    'theme' => 'テーマ',
    'theme-blocks' => 'テーマブロック',
    'embed' => '埋め込み',
    'reusable' => '再利用可能'
);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="uk-blocks-selecter-container">
        <div class="uk-blocks-selecter-sidebar">
            <h2>投稿タイプ</h2>
            <ul class="post-types-list">
                <?php foreach ($post_types as $post_type): ?>
                    <li>
                        <a href="#" class="post-type-item" data-post-type="<?php echo esc_attr($post_type['name']); ?>">
                            <?php echo esc_html($post_type['label']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="uk-blocks-selecter-main">
            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                <?php $plugin->debug_blocks_info(); ?>
            <?php endif; ?>
            
            <div class="blocks-settings-container" style="display: none;">
                <h2>ブロック設定</h2>
                <p class="description">
                    選択した投稿タイプで利用可能にするブロックを選択してください。
                    何も選択しない場合は、すべてのブロックが利用可能になります。
                </p>
                
                <form id="blocks-settings-form">
                    <div class="blocks-selection">
                        <div class="select-all-actions">
                            <button type="button" class="button select-all">すべて選択</button>
                            <button type="button" class="button deselect-all">すべて解除</button>
                        </div>
                        
                        <?php foreach ($blocks_by_category as $category => $category_blocks): ?>
                            <div class="blocks-category">
                                <h3 class="category-title">
                                    <label>
                                        <input type="checkbox" class="category-checkbox" data-category="<?php echo esc_attr($category); ?>">
                                        <?php echo esc_html(isset($category_labels[$category]) ? $category_labels[$category] : $category); ?>
                                    </label>
                                </h3>
                                
                                <div class="blocks-grid">
                                    <?php foreach ($category_blocks as $block): ?>
                                        <label class="block-item <?php echo $block['is_theme_block'] ? 'theme-block' : ''; ?>">
                                            <input type="checkbox" name="blocks[]" value="<?php echo esc_attr($block['name']); ?>" data-category="<?php echo esc_attr($category); ?>">
                                            <div class="block-info">
                                                <span class="block-name"><?php echo esc_html($block['title']); ?></span>
                                                <span class="block-technical-name"><?php echo esc_html($block['name']); ?></span>
                                                <?php if ($block['is_theme_block']): ?>
                                                    <span class="block-badge">テーマ</span>
                                                <?php endif; ?>
                                                <?php if (!empty($block['description'])): ?>
                                                    <span class="block-description"><?php echo esc_html($block['description']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="button button-primary">設定を保存</button>
                        <span class="spinner"></span>
                    </div>
                </form>
            </div>
            
            <div class="welcome-message">
                <h2>ブロック選択設定</h2>
                <p>左側から投稿タイプを選択して、その投稿タイプで利用可能なブロックを設定してください。</p>
            </div>
        </div>
    </div>
    
    <div id="message-container"></div>
</div>
