<?php
/**
 * Plugin Name: MYZ Floating CTA Button
 * Description: スクロールで表示されるフローティングCTAボタン。テキスト・色・リンク先を管理画面から設定可能。
 * Version: 1.9.2
 * Author: MYZ Inbound Inc.
 * Text Domain: myz-floating-cta
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MYZ_FCTA_VERSION', '1.9.2');
define('MYZ_FCTA_PATH', plugin_dir_path(__FILE__));
define('MYZ_FCTA_URL', plugin_dir_url(__FILE__));

// GitHub自動更新クラスの読み込み
require_once MYZ_FCTA_PATH . 'includes/class-updater.php';

class MYZ_Floating_CTA {

    /**
     * フォントマップ
     */
    private $font_map = [
        'system' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', 'Noto Sans JP', sans-serif",
        'gothic' => "'Hiragino Kaku Gothic ProN', 'Noto Sans JP', 'Yu Gothic', 'Meiryo', sans-serif",
        'mincho' => "'Hiragino Mincho ProN', 'Noto Serif JP', 'Yu Mincho', 'MS PMincho', serif",
        'maru'   => "'Hiragino Maru Gothic ProN', 'Kosugi Maru', 'Yu Gothic', sans-serif",
        'mono'   => "'SF Mono', 'Hiragino Kaku Gothic ProN', 'Courier New', monospace",
        'roman'  => "'Times New Roman', 'Times', 'Hiragino Mincho ProN', 'Noto Serif JP', serif",
    ];

    /**
     * サイズマップ
     */
    private $size_map = [
        'small'  => ['fs' => 13, 'pv' => 10, 'ph' => 18],
        'medium' => ['fs' => 15, 'pv' => 14, 'ph' => 24],
        'large'  => ['fs' => 18, 'pv' => 18, 'ph' => 32],
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_footer', [$this, 'render_button']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX: 更新チェック
        add_action('wp_ajax_myz_fcta_check_update', [$this, 'ajax_check_update']);

        // GitHub更新機能の初期化
        new MYZ_FCTA_Updater();
    }

    /**
     * 管理画面メニュー
     */
    public function add_admin_menu() {
        add_options_page(
            'フローティングCTAボタン',
            'フローティングCTA',
            'manage_options',
            'myz-floating-cta',
            [$this, 'settings_page']
        );
    }

    /**
     * 設定の登録
     */
    public function register_settings() {
        register_setting('myz_floating_cta_group', 'myz_fcta_text', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '無料相談',
        ]);
        register_setting('myz_floating_cta_group', 'myz_fcta_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]);
        register_setting('myz_floating_cta_group', 'myz_fcta_bg_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#159BBE',
        ]);
        register_setting('myz_floating_cta_group', 'myz_fcta_text_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#ffffff',
        ]);
        register_setting('myz_floating_cta_group', 'myz_fcta_font_family', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'system',
        ]);
        register_setting('myz_floating_cta_group', 'myz_fcta_font_weight', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '600',
        ]);
        register_setting('myz_floating_cta_group', 'myz_fcta_size', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'medium',
        ]);
        register_setting('myz_floating_cta_group', 'myz_fcta_border_radius', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 50,
        ]);
        register_setting('myz_floating_cta_group', 'myz_fcta_position', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'right',
        ]);
        register_setting('myz_floating_cta_group', 'myz_fcta_scroll_offset', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 200,
        ]);
        register_setting('myz_floating_cta_group', 'myz_fcta_hide_delay', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 3,
        ]);
        register_setting('myz_floating_cta_group', 'myz_fcta_new_tab', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);
    }

    /**
     * AJAX: 更新チェック
     */
    public function ajax_check_update() {
        check_ajax_referer('myz_fcta_update_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません。');
        }
        MYZ_FCTA_Updater::force_check();
        $update_plugins = get_site_transient('update_plugins');
        $plugin_file = 'myz-floating-cta/myz-floating-cta.php';
        if (isset($update_plugins->response[$plugin_file])) {
            $new = $update_plugins->response[$plugin_file];
            wp_send_json_success([
                'has_update'      => true,
                'new_version'     => $new->new_version,
                'current_version' => MYZ_FCTA_VERSION,
                'update_url'      => admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($plugin_file) . '&_wpnonce=' . wp_create_nonce('upgrade-plugin_' . $plugin_file)),
            ]);
        } else {
            wp_send_json_success([
                'has_update'      => false,
                'current_version' => MYZ_FCTA_VERSION,
            ]);
        }
    }

    /**
     * 設定画面の描画
     */
    public function settings_page() {
        $text          = get_option('myz_fcta_text', '無料相談');
        $url           = get_option('myz_fcta_url', '');
        $bg_color      = get_option('myz_fcta_bg_color', '#159BBE');
        $text_color    = get_option('myz_fcta_text_color', '#ffffff');
        $font_family   = get_option('myz_fcta_font_family', 'system');
        $font_weight   = get_option('myz_fcta_font_weight', '600');
        $size          = get_option('myz_fcta_size', 'medium');
        $border_radius = get_option('myz_fcta_border_radius', 50);
        $position      = get_option('myz_fcta_position', 'right');
        $scroll_offset = get_option('myz_fcta_scroll_offset', 200);
        $hide_delay    = get_option('myz_fcta_hide_delay', 3);
        $new_tab       = get_option('myz_fcta_new_tab', false);

        $s = isset($this->size_map[$size]) ? $this->size_map[$size] : $this->size_map['medium'];
        $current_font_css = isset($this->font_map[$font_family]) ? $this->font_map[$font_family] : $this->font_map['system'];
        ?>
        <div class="wrap">
            <h1>フローティングCTAボタン 設定</h1>
            <form method="post" action="options.php">
                <?php settings_fields('myz_floating_cta_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="myz_fcta_text">ボタンテキスト</label></th>
                        <td><input type="text" id="myz_fcta_text" name="myz_fcta_text" value="<?php echo esc_attr($text); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="myz_fcta_url">リンク先URL</label></th>
                        <td><input type="url" id="myz_fcta_url" name="myz_fcta_url" value="<?php echo esc_attr($url); ?>" class="regular-text" placeholder="https://example.com/contact" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="myz_fcta_bg_color">ボタン背景色</label></th>
                        <td><input type="color" id="myz_fcta_bg_color" name="myz_fcta_bg_color" value="<?php echo esc_attr($bg_color); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="myz_fcta_text_color">テキスト色</label></th>
                        <td><input type="color" id="myz_fcta_text_color" name="myz_fcta_text_color" value="<?php echo esc_attr($text_color); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="myz_fcta_font_family">フォント</label></th>
                        <td>
                            <select id="myz_fcta_font_family" name="myz_fcta_font_family">
                                <option value="system" <?php selected($font_family, 'system'); ?>>システム標準</option>
                                <option value="gothic" <?php selected($font_family, 'gothic'); ?>>ゴシック体</option>
                                <option value="mincho" <?php selected($font_family, 'mincho'); ?>>明朝体</option>
                                <option value="maru"   <?php selected($font_family, 'maru'); ?>>丸ゴシック</option>
                                <option value="mono"   <?php selected($font_family, 'mono'); ?>>等幅フォント</option>
                                <option value="roman"  <?php selected($font_family, 'roman'); ?>>Times New Roman</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="myz_fcta_font_weight">フォントの太さ</label></th>
                        <td>
                            <select id="myz_fcta_font_weight" name="myz_fcta_font_weight">
                                <option value="300" <?php selected($font_weight, '300'); ?>>Light（細い）</option>
                                <option value="400" <?php selected($font_weight, '400'); ?>>Regular（標準）</option>
                                <option value="600" <?php selected($font_weight, '600'); ?>>Semi Bold（やや太い）</option>
                                <option value="700" <?php selected($font_weight, '700'); ?>>Bold（太い）</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">ボタンのサイズ</th>
                        <td>
                            <fieldset style="display:flex; gap:16px; flex-wrap:wrap;">
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; padding:8px 12px; border:2px solid <?php echo $size === 'small' ? '#159BBE' : '#ddd'; ?>; border-radius:8px;">
                                    <input type="radio" name="myz_fcta_size" value="small" <?php checked($size, 'small'); ?> /> 小
                                </label>
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; padding:8px 12px; border:2px solid <?php echo $size === 'medium' ? '#159BBE' : '#ddd'; ?>; border-radius:8px;">
                                    <input type="radio" name="myz_fcta_size" value="medium" <?php checked($size, 'medium'); ?> /> 中（デフォルト）
                                </label>
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; padding:8px 12px; border:2px solid <?php echo $size === 'large' ? '#159BBE' : '#ddd'; ?>; border-radius:8px;">
                                    <input type="radio" name="myz_fcta_size" value="large" <?php checked($size, 'large'); ?> /> 大
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="myz_fcta_border_radius">角の丸み (px)</label></th>
                        <td>
                            <input type="range" id="myz_fcta_border_radius" name="myz_fcta_border_radius" value="<?php echo esc_attr($border_radius); ?>" min="0" max="50" step="1" style="vertical-align:middle;" />
                            <span id="myz_fcta_radius_value"><?php echo esc_html($border_radius); ?>px</span>
                            <p class="description">0 = 四角 / 50 = 完全な丸型（デフォルト）</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">表示位置</th>
                        <td>
                            <fieldset style="display:flex; gap:16px; flex-wrap:wrap;">
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; padding:8px 12px; border:2px solid <?php echo $position === 'left' ? '#159BBE' : '#ddd'; ?>; border-radius:8px;">
                                    <input type="radio" name="myz_fcta_position" value="left" <?php checked($position, 'left'); ?> /> 左下
                                </label>
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer; padding:8px 12px; border:2px solid <?php echo $position === 'right' ? '#159BBE' : '#ddd'; ?>; border-radius:8px;">
                                    <input type="radio" name="myz_fcta_position" value="right" <?php checked($position, 'right'); ?> /> 右下（デフォルト）
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="myz_fcta_scroll_offset">表示開始スクロール量 (px)</label></th>
                        <td>
                            <input type="number" id="myz_fcta_scroll_offset" name="myz_fcta_scroll_offset" value="<?php echo esc_attr($scroll_offset); ?>" min="0" step="10" />
                            <p class="description">ページ上部からこのピクセル数スクロールするとボタンが表示されます。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="myz_fcta_hide_delay">スクロール停止後に消えるまでの秒数</label></th>
                        <td>
                            <input type="number" id="myz_fcta_hide_delay" name="myz_fcta_hide_delay" value="<?php echo esc_attr($hide_delay); ?>" min="1" max="30" step="1" style="width:70px;" />
                            <span>秒</span>
                            <p class="description">スクロールを止めてからボタンが消えるまでの待ち時間（デフォルト: 3秒）</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="myz_fcta_new_tab">新しいタブで開く</label></th>
                        <td><input type="checkbox" id="myz_fcta_new_tab" name="myz_fcta_new_tab" value="1" <?php checked($new_tab); ?> /></td>
                    </tr>
                </table>

                <h2>プレビュー</h2>
                <div style="position:relative; height:100px; background:#f0f0f0; border-radius:8px; overflow:hidden;">
                    <a id="myz-fcta-preview" href="#" onclick="return false;" style="
                        position: absolute;
                        bottom: 16px;
                        <?php echo $position === 'left' ? 'left: 16px;' : 'right: 16px;'; ?>
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        background: <?php echo esc_attr($bg_color); ?>;
                        color: <?php echo esc_attr($text_color); ?>;
                        border: none;
                        border-radius: <?php echo esc_attr($border_radius); ?>px;
                        padding: <?php echo $s['pv']; ?>px <?php echo $s['ph']; ?>px;
                        font-size: <?php echo $s['fs']; ?>px;
                        font-weight: <?php echo esc_attr($font_weight); ?>;
                        text-decoration: none;
                        box-shadow: 0 4px 16px rgba(0,0,0,0.2);
                        font-family: <?php echo $current_font_css; ?>;
                    "><?php echo esc_html($text); ?></a>
                </div>

                <script>
                (function(){
                    var textInput   = document.getElementById('myz_fcta_text');
                    var bgInput     = document.getElementById('myz_fcta_bg_color');
                    var txInput     = document.getElementById('myz_fcta_text_color');
                    var ffInput     = document.getElementById('myz_fcta_font_family');
                    var fwInput     = document.getElementById('myz_fcta_font_weight');
                    var sizeInputs  = document.querySelectorAll('input[name="myz_fcta_size"]');
                    var brInput     = document.getElementById('myz_fcta_border_radius');
                    var brValue     = document.getElementById('myz_fcta_radius_value');
                    var posInputs   = document.querySelectorAll('input[name="myz_fcta_position"]');
                    var preview     = document.getElementById('myz-fcta-preview');

                    var fonts = {
                        system: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Hiragino Sans", "Noto Sans JP", sans-serif',
                        gothic: '"Hiragino Kaku Gothic ProN", "Noto Sans JP", "Yu Gothic", "Meiryo", sans-serif',
                        mincho: '"Hiragino Mincho ProN", "Noto Serif JP", "Yu Mincho", "MS PMincho", serif',
                        maru:   '"Hiragino Maru Gothic ProN", "Kosugi Maru", "Yu Gothic", sans-serif',
                        mono:   '"SF Mono", "Hiragino Kaku Gothic ProN", "Courier New", monospace',
                        roman:  '"Times New Roman", "Times", "Hiragino Mincho ProN", "Noto Serif JP", serif'
                    };

                    var sizeMap = {small:{fs:13,pv:10,ph:18}, medium:{fs:15,pv:14,ph:24}, large:{fs:18,pv:18,ph:32}};

                    textInput.addEventListener('input', function(){ preview.textContent = this.value; });
                    bgInput.addEventListener('input', function(){ preview.style.background = this.value; });
                    txInput.addEventListener('input', function(){ preview.style.color = this.value; });

                    ffInput.addEventListener('change', function(){ preview.style.fontFamily = fonts[this.value] || fonts.system; });
                    fwInput.addEventListener('change', function(){ preview.style.fontWeight = this.value; });

                    sizeInputs.forEach(function(radio){
                        radio.addEventListener('change', function(){
                            var s = sizeMap[this.value] || sizeMap.medium;
                            preview.style.fontSize = s.fs + 'px';
                            preview.style.padding = s.pv + 'px ' + s.ph + 'px';
                            sizeInputs.forEach(function(r){ r.parentElement.style.borderColor = r.checked ? '#159BBE' : '#ddd'; });
                        });
                    });

                    brInput.addEventListener('input', function(){ preview.style.borderRadius = this.value + 'px'; brValue.textContent = this.value + 'px'; });

                    posInputs.forEach(function(radio){
                        radio.addEventListener('change', function(){
                            if (this.value === 'left') {
                                preview.style.right = 'auto';
                                preview.style.left = '16px';
                            } else {
                                preview.style.left = 'auto';
                                preview.style.right = '16px';
                            }
                            posInputs.forEach(function(r){ r.parentElement.style.borderColor = r.checked ? '#159BBE' : '#ddd'; });
                        });
                    });
                })();
                </script>

                <h2>プラグイン更新</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">現在のバージョン</th>
                        <td>
                            <p><strong style="font-size:16px;">v<?php echo MYZ_FCTA_VERSION; ?></strong></p>
                            <button type="button" id="myz-fcta-check-update-btn" class="button button-secondary">更新をチェック</button>
                            <span id="myz-fcta-update-status" style="margin-left:12px;"></span>
                            <p class="description" style="margin-top:8px;">リポジトリ: <code>myzinbound/myz-floating-cta</code></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('設定を保存'); ?>
            </form>
        </div>

        <script>
        (function(){
            var btn = document.getElementById('myz-fcta-check-update-btn');
            if (!btn) return;
            btn.addEventListener('click', function(){
                btn.disabled = true;
                var status = document.getElementById('myz-fcta-update-status');
                status.textContent = 'チェック中...';
                status.style.color = '#666';
                var fd = new FormData();
                fd.append('action', 'myz_fcta_check_update');
                fd.append('nonce', '<?php echo wp_create_nonce("myz_fcta_update_nonce"); ?>');
                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {method:'POST', body:fd})
                    .then(function(r){return r.json();})
                    .then(function(data){
                        btn.disabled = false;
                        if (data.success) {
                            if (data.data.has_update) {
                                status.innerHTML = '<span style="color:#d63638;">🔔 新バージョン v' + data.data.new_version + ' が利用可能です！</span> '
                                    + '<a href="' + data.data.update_url + '" class="button button-primary" style="margin-left:8px;">今すぐ更新</a>';
                            } else {
                                status.innerHTML = '<span style="color:#00a32a;">✅ 最新バージョンです（v' + data.data.current_version + '）</span>';
                            }
                        } else {
                            status.innerHTML = '<span style="color:#d63638;">チェックに失敗しました。</span>';
                        }
                    })
                    .catch(function(){
                        btn.disabled = false;
                        status.innerHTML = '<span style="color:#d63638;">通信エラーが発生しました。</span>';
                    });
            });
        })();
        </script>
        <?php
    }

    /**
     * フロントエンドにCSS/JSを読み込み
     */
    public function enqueue_assets() {
        $url = get_option('myz_fcta_url', '');
        if (empty($url)) {
            return;
        }

        wp_enqueue_style(
            'myz-floating-cta',
            MYZ_FCTA_URL . 'assets/css/floating-cta.css',
            [],
            MYZ_FCTA_VERSION
        );

        wp_enqueue_script(
            'myz-floating-cta',
            MYZ_FCTA_URL . 'assets/js/floating-cta.js',
            [],
            MYZ_FCTA_VERSION,
            true
        );

        wp_localize_script('myz-floating-cta', 'myzFctaConfig', [
            'scrollOffset' => (int) get_option('myz_fcta_scroll_offset', 200),
            'hideDelay'    => (int) get_option('myz_fcta_hide_delay', 3) * 1000,
        ]);
    }

    /**
     * ボタンHTMLの出力
     */
    public function render_button() {
        $text          = get_option('myz_fcta_text', '無料相談');
        $url           = get_option('myz_fcta_url', '');
        $bg_color      = get_option('myz_fcta_bg_color', '#159BBE');
        $text_color    = get_option('myz_fcta_text_color', '#ffffff');
        $font_family   = get_option('myz_fcta_font_family', 'system');
        $font_weight   = get_option('myz_fcta_font_weight', '600');
        $size          = get_option('myz_fcta_size', 'medium');
        $border_radius = get_option('myz_fcta_border_radius', 50);
        $position      = get_option('myz_fcta_position', 'right');
        $new_tab       = get_option('myz_fcta_new_tab', false);

        if (empty($url)) {
            return;
        }

        $s = isset($this->size_map[$size]) ? $this->size_map[$size] : $this->size_map['medium'];
        $font_css = isset($this->font_map[$font_family]) ? $this->font_map[$font_family] : $this->font_map['system'];

        $target = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
        $pos_class = ($position === 'left') ? 'myz-fcta-left' : 'myz-fcta-right';

        // スタイルをstyleタグで出力（モバイル対応のメディアクエリを含む）
        echo '<style>';
        printf(
            '#myz-floating-cta{background:%s!important;color:%s!important;font-size:%spx!important;padding:%spx %spx!important;border-radius:%spx!important;font-family:%s!important;font-weight:%s!important;}',
            esc_attr($bg_color),
            esc_attr($text_color),
            esc_attr($s['fs']),
            esc_attr($s['pv']),
            esc_attr($s['ph']),
            esc_attr($border_radius),
            $font_css,
            esc_attr($font_weight)
        );
        echo '</style>';

        printf(
            '<a id="myz-floating-cta" class="%s" href="%s"%s>%s</a>',
            esc_attr($pos_class),
            esc_url($url),
            $target,
            esc_html($text)
        );
    }
}

new MYZ_Floating_CTA();
