<?php
/**
 * メインクラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class UK_Blocks_Selecter {
    
    private static $instance = null;
    
    /**
     * シングルトンインスタンスを取得
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * フックの初期化
     */
    private function init_hooks() {
        // 管理画面メニューの追加
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // 管理画面スタイルとスクリプトの読み込み
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX ハンドラーの追加
        add_action('wp_ajax_save_block_settings', array($this, 'save_block_settings'));
        add_action('wp_ajax_get_block_settings', array($this, 'get_block_settings'));
        
        // ブロックエディタでのブロック制限
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        
        // サーバーサイドでのブロック制限（より確実）
        add_filter('allowed_block_types_all', array($this, 'filter_allowed_block_types'), 10, 2);
        
        // プラグイン読み込み時の処理
        add_action('init', array($this, 'init'));
    }
    
    /**
     * 初期化処理
     */
    public function init() {
        // 言語ファイルの読み込み
        load_plugin_textdomain('uk-blocks-selecter', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * 管理画面メニューの追加
     */
    public function add_admin_menu() {
        add_options_page(
            __('ブロック選択設定', 'uk-blocks-selecter'),
            __('ブロック選択設定', 'uk-blocks-selecter'),
            'manage_options',
            'uk-blocks-selecter',
            array($this, 'admin_page')
        );
    }
    
    /**
     * 管理画面スクリプトの読み込み
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_uk-blocks-selecter') {
            return;
        }
        
        wp_enqueue_script(
            'uk-blocks-selecter-admin',
            UK_BLOCKS_SELECTER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_enqueue_style(
            'uk-blocks-selecter-admin',
            UK_BLOCKS_SELECTER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            '1.0.0'
        );
        
        // AJAX用のnonce
        wp_localize_script(
            'uk-blocks-selecter-admin',
            'ukBlocksSelecter',
            array(
                'nonce' => wp_create_nonce('uk_blocks_selecter_nonce'),
                'ajax_url' => admin_url('admin-ajax.php')
            )
        );
    }
    
    /**
     * ブロックエディタでのスクリプト読み込み
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'uk-blocks-selecter-editor',
            UK_BLOCKS_SELECTER_PLUGIN_URL . 'assets/js/editor.js',
            array('wp-blocks', 'wp-dom-ready', 'wp-edit-post', 'wp-data', 'wp-hooks'),
            '1.0.1', // バージョンを更新
            true
        );
        
        // 現在の投稿タイプを取得
        $post_type = get_post_type();
        if (!$post_type) {
            global $typenow;
            $post_type = $typenow;
        }
        
        // 投稿タイプが取得できない場合の処理
        if (!$post_type && isset($_GET['post_type'])) {
            $post_type = sanitize_text_field($_GET['post_type']);
        }
        
        // 許可されたブロックを取得
        $allowed_blocks = $this->get_allowed_blocks_for_post_type($post_type);
        
        wp_localize_script(
            'uk-blocks-selecter-editor',
            'ukBlocksSelecterEditor',
            array(
                'postType' => $post_type,
                'allowedBlocks' => $allowed_blocks,
                'debug' => defined('WP_DEBUG') && WP_DEBUG
            )
        );
    }
    
    /**
     * 管理画面ページの表示
     */
    public function admin_page() {
        include UK_BLOCKS_SELECTER_PLUGIN_PATH . 'templates/admin-page.php';
    }
    
    /**
     * 全てのカスタム投稿タイプを取得
     */
    public function get_all_post_types() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $custom_post_types = array();
        
        foreach ($post_types as $post_type) {
            if ($post_type->name !== 'attachment') {
                $custom_post_types[] = array(
                    'name' => $post_type->name,
                    'label' => $post_type->label
                );
            }
        }
        
        return $custom_post_types;
    }
    
    /**
     * 全てのブロックタイプを取得
     */
    public function get_all_blocks() {
        // ブロックレジストリからブロックを取得
        $registry = WP_Block_Type_Registry::get_instance();
        $registered_blocks = $registry->get_all_registered();
        
        $blocks = array();
        foreach ($registered_blocks as $block_name => $block_type) {
            // ブロックのタイトルを取得（多言語対応）
            $title = $block_name;
            if (isset($block_type->title)) {
                $title = $block_type->title;
            } elseif (isset($block_type->attributes['title']['default'])) {
                $title = $block_type->attributes['title']['default'];
            }
            
            // カテゴリを取得
            $category = isset($block_type->category) ? $block_type->category : 'common';
            
            // テーマブロックかどうかを判定（複数のパターンをチェック）
            $is_theme_block = false;
            $theme_name = get_template();
            
            // 一般的なテーマブロックのパターンをチェック
            $theme_patterns = array(
                $theme_name . '/',          // theme-name/block-name
                $theme_name . '-',          // theme-name-block-name
                'theme/',                   // theme/block-name
                'custom/',                  // custom/block-name
                'acf/',                     // Advanced Custom Fields
            );
            
            foreach ($theme_patterns as $pattern) {
                if (strpos($block_name, $pattern) === 0) {
                    $is_theme_block = true;
                    break;
                }
            }
            
            // register_block_type で登録されたブロックの場合、
            // ファイルパスでテーマブロックかどうかを判定
            if (!$is_theme_block && isset($block_type->block_type_supports)) {
                $reflection = new ReflectionClass($block_type);
                if ($reflection->hasProperty('file')) {
                    $file_prop = $reflection->getProperty('file');
                    $file_prop->setAccessible(true);
                    $file_path = $file_prop->getValue($block_type);
                    
                    if ($file_path && strpos($file_path, get_template_directory()) === 0) {
                        $is_theme_block = true;
                    }
                }
            }
            
            $blocks[] = array(
                'name' => $block_name,
                'title' => $title,
                'category' => $category,
                'is_theme_block' => $is_theme_block,
                'description' => isset($block_type->description) ? $block_type->description : ''
            );
        }
        
        // カテゴリ別にソート（テーマブロックは最後に）
        usort($blocks, function($a, $b) {
            // まずテーマブロックかどうかで分類
            if ($a['is_theme_block'] !== $b['is_theme_block']) {
                return $a['is_theme_block'] ? 1 : -1;
            }
            
            // 同じタイプ内ではカテゴリでソート
            if ($a['category'] !== $b['category']) {
                return strcmp($a['category'], $b['category']);
            }
            
            // 同じカテゴリ内ではタイトルでソート
            return strcmp($a['title'], $b['title']);
        });
        
        return $blocks;
    }
    
    /**
     * 投稿タイプの許可されたブロックを取得
     */
    public function get_allowed_blocks_for_post_type($post_type) {
        $settings = get_option('uk_blocks_selecter_settings', array());
        
        if (isset($settings[$post_type])) {
            return $settings[$post_type];
        }
        
        // デフォルトは全てのブロックを許可
        return array();
    }
    
    /**
     * ブロック設定の保存
     */
    public function save_block_settings() {
        // nonceの確認
        if (!wp_verify_nonce($_POST['nonce'], 'uk_blocks_selecter_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $post_type = sanitize_text_field($_POST['post_type']);
        $blocks = isset($_POST['blocks']) ? array_map('sanitize_text_field', $_POST['blocks']) : array();
        
        $settings = get_option('uk_blocks_selecter_settings', array());
        $settings[$post_type] = $blocks;
        
        update_option('uk_blocks_selecter_settings', $settings);
        
        wp_send_json_success(array('message' => '設定が保存されました'));
    }
    
    /**
     * ブロック設定の取得
     */
    public function get_block_settings() {
        // nonceの確認
        if (!wp_verify_nonce($_POST['nonce'], 'uk_blocks_selecter_nonce')) {
            wp_die('Security check failed');
        }
        
        $post_type = sanitize_text_field($_POST['post_type']);
        $allowed_blocks = $this->get_allowed_blocks_for_post_type($post_type);
        
        wp_send_json_success(array('blocks' => $allowed_blocks));
    }
    
    /**
     * プラグイン有効化時のテーブル作成
     */
    public static function create_tables() {
        // 必要に応じてデータベーステーブルを作成
        // 現在は wp_options を使用しているので特に必要なし
    }
    
    /**
     * プラグイン無効化時のクリーンアップ
     */
    public static function cleanup() {
        // 必要に応じてクリーンアップ処理
        // delete_option('uk_blocks_selecter_settings');
    }
    
    /**
     * デバッグ用：ブロック取得状況を確認
     */
    public function debug_blocks_info() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $blocks = $this->get_all_blocks();
        $theme_blocks = array_filter($blocks, function($block) {
            return $block['is_theme_block'];
        });
        
        echo '<div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">';
        echo '<h3>ブロック取得デバッグ情報</h3>';
        echo '<p><strong>総ブロック数:</strong> ' . count($blocks) . '</p>';
        echo '<p><strong>テーマブロック数:</strong> ' . count($theme_blocks) . '</p>';
        echo '<p><strong>現在のテーマ:</strong> ' . get_template() . '</p>';
        
        if (!empty($theme_blocks)) {
            echo '<h4>テーマブロック一覧:</h4>';
            echo '<ul>';
            foreach ($theme_blocks as $block) {
                echo '<li>' . esc_html($block['name']) . ' (' . esc_html($block['title']) . ')</li>';
            }
            echo '</ul>';
        } else {
            echo '<p style="color: #d63638;">テーマブロックが見つかりません。</p>';
            echo '<p>考えられる原因：</p>';
            echo '<ul>';
            echo '<li>テーマがまだブロックを登録していない</li>';
            echo '<li>ブロックの登録タイミングが遅い</li>';
            echo '<li>テーマブロックの命名規則が異なる</li>';
            echo '</ul>';
        }
        
        echo '</div>';
    }
    
    /**
     * 管理画面でのブロック取得（より確実な方法）
     */
    public function get_blocks_for_admin() {
        // まず通常の方法で取得
        $blocks = $this->get_all_blocks();
        
        // wp_blocksテーブルからも取得を試みる（WordPress 5.5以降）
        global $wpdb;
        $table_name = $wpdb->prefix . 'posts';
        
        // 既存の投稿からブロックを検索
        $query = "SELECT DISTINCT post_content FROM {$table_name} 
                  WHERE post_type IN ('post', 'page') 
                  AND post_status = 'publish' 
                  AND post_content LIKE '%wp:%%'
                  LIMIT 100";
        
        $results = $wpdb->get_results($query);
        $found_blocks = array();
        
        foreach ($results as $result) {
            // ブロックコメントから使用されているブロックを抽出
            preg_match_all('/<!-- wp:([^\s]+)/', $result->post_content, $matches);
            if (!empty($matches[1])) {
                $found_blocks = array_merge($found_blocks, $matches[1]);
            }
        }
        
        $found_blocks = array_unique($found_blocks);
        
        // 登録されていないが使用されているブロックを追加
        foreach ($found_blocks as $block_name) {
            $exists = false;
            foreach ($blocks as $block) {
                if ($block['name'] === $block_name) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $blocks[] = array(
                    'name' => $block_name,
                    'title' => $block_name,
                    'category' => 'unknown',
                    'is_theme_block' => strpos($block_name, get_template()) === 0,
                    'description' => '使用されているが登録されていないブロック'
                );
            }
        }
        
        return $blocks;
    }
    
    /**
     * サーバーサイドでブロックタイプを制限
     */
    public function filter_allowed_block_types($allowed_block_types, $block_editor_context) {
        // 投稿タイプを取得
        $post_type = null;
        
        if (isset($block_editor_context->post)) {
            $post_type = $block_editor_context->post->post_type;
        } elseif (isset($block_editor_context->name)) {
            $post_type = $block_editor_context->name;
        }
        
        if (!$post_type) {
            return $allowed_block_types;
        }
        
        // 設定されたブロックを取得
        $allowed_blocks = $this->get_allowed_blocks_for_post_type($post_type);
        
        // 設定がない場合は制限なし
        if (empty($allowed_blocks)) {
            return $allowed_block_types;
        }
        
        // 設定されたブロックのみを許可
        return $allowed_blocks;
    }
}
