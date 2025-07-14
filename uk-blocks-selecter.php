<?php
/**
 * Plugin Name: UK Blocks Selecter
 * Description: カスタム投稿タイプごとに利用可能なブロックを設定できるプラグイン
 * Version: 1.0.0
 * Author: Yuki
 * Text Domain: uk-blocks-selecter
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// プラグインの定数を定義
define('UK_BLOCKS_SELECTER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('UK_BLOCKS_SELECTER_PLUGIN_URL', plugin_dir_url(__FILE__));

// メインクラスの読み込み
require_once UK_BLOCKS_SELECTER_PLUGIN_PATH . 'includes/class-uk-blocks-selecter.php';

// プラグインの初期化
function uk_blocks_selecter_init() {
    UK_Blocks_Selecter::get_instance();
}
// テーマのブロックも確実に取得するため、initフックを使用
add_action('init', 'uk_blocks_selecter_init', 20);

// プラグインの有効化時にテーブルを作成
register_activation_hook(__FILE__, array('UK_Blocks_Selecter', 'create_tables'));

// プラグインの無効化時にクリーンアップ
register_deactivation_hook(__FILE__, array('UK_Blocks_Selecter', 'cleanup'));
