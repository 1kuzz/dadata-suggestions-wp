<?php
/**
 * Plugin Name: DaData Suggestions
 * Description: Подключает подсказки DaData.ru (адрес, ФИО, организация, email) к полям форм на сайте. Есть быстрый пресет для WooCommerce и проверка ключа.
 * Version: 1.1.0
 * License: MIT
 */

defined('ABSPATH') || exit;

define('DADATA_SUGG_OPTION', 'dadata_suggestions_options');
define('DADATA_SUGG_LIB_VERSION', '16.10.3');

function dadata_suggestions_defaults() {
    return array(
        'enabled'                 => '1',
        'api_key'                 => '',
        'address_selector'        => '',
        'address_postcode_selector' => '',
        'address_city_selector'   => '',
        'address_region_selector' => '',
        'fio_selector'            => '',
        'party_selector'          => '',
        'email_selector'          => '',
        'bank_selector'           => '',
    );
}

function dadata_suggestions_get_options() {
    return wp_parse_args(get_option(DADATA_SUGG_OPTION, array()), dadata_suggestions_defaults());
}

// --- Settings page ---

add_action('admin_menu', function () {
    add_options_page('DaData Suggestions', 'DaData Suggestions', 'manage_options', 'dadata-suggestions', 'dadata_suggestions_settings_page');
});

add_action('admin_init', function () {
    register_setting('dadata_suggestions', DADATA_SUGG_OPTION, 'dadata_suggestions_sanitize');
});

function dadata_suggestions_sanitize($input) {
    $out = array();
    foreach (dadata_suggestions_defaults() as $key => $default) {
        if ($key === 'enabled') {
            $out[$key] = empty($input[$key]) ? '0' : '1';
            continue;
        }
        $out[$key] = isset($input[$key]) ? sanitize_text_field($input[$key]) : $default;
    }
    return $out;
}

// AJAX: проверка ключа прямо из админки, без сохранения настроек
add_action('wp_ajax_dadata_suggestions_test_key', function () {
    check_ajax_referer('dadata_suggestions_test');
    if (!current_user_can('manage_options')) wp_send_json_error('нет прав');

    $key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    if (!$key) wp_send_json_error('ключ пустой');

    $resp = wp_remote_post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address', array(
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Token ' . $key,
        ),
        'body'    => wp_json_encode(array('query' => 'москва')),
        'timeout' => 10,
    ));

    if (is_wp_error($resp)) {
        wp_send_json_error($resp->get_error_message());
    }
    $code = wp_remote_retrieve_response_code($resp);
    if ($code === 200) {
        wp_send_json_success();
    }
    wp_send_json_error('HTTP ' . $code . ' — проверьте ключ на dadata.ru/profile');
});

function dadata_suggestions_settings_page() {
    if (!current_user_can('manage_options')) return;
    $o = dadata_suggestions_get_options();
    $test_nonce = wp_create_nonce('dadata_suggestions_test');
    ?>
    <div class="wrap">
        <h1>DaData Suggestions</h1>
        <p>Свой API-ключ (Token) — в личном кабинете на <a href="https://dadata.ru/profile/#info" target="_blank">dadata.ru</a>.
           В поле селектора можно указать несколько через запятую: <code>#billing_address_1, #shipping_address_1</code>. Пустой селектор — тип подсказки не подключается.</p>
        <form method="post" action="options.php">
            <?php settings_fields('dadata_suggestions'); ?>
            <table class="form-table">
                <tr>
                    <th>Плагин включён</th>
                    <td><label><input type="checkbox" name="<?php echo DADATA_SUGG_OPTION; ?>[enabled]" value="1" <?php checked($o['enabled'], '1'); ?>> подключать скрипты на сайте</label></td>
                </tr>
                <tr>
                    <th><label for="api_key">API-ключ (Token)</label></th>
                    <td>
                        <input type="text" class="regular-text" id="api_key" name="<?php echo DADATA_SUGG_OPTION; ?>[api_key]" value="<?php echo esc_attr($o['api_key']); ?>">
                        <button type="button" class="button" id="dadata-test-key">Проверить ключ</button>
                        <span id="dadata-test-result"></span>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><hr></th>
                </tr>
                <tr>
                    <th><label for="address_selector">Адрес — селектор(ы)</label></th>
                    <td><input type="text" class="regular-text" id="address_selector" name="<?php echo DADATA_SUGG_OPTION; ?>[address_selector]" value="<?php echo esc_attr($o['address_selector']); ?>" placeholder="#billing_address_1"></td>
                </tr>
                <tr>
                    <th><label for="address_postcode_selector">— автозаполнить индекс в</label></th>
                    <td><input type="text" class="regular-text" id="address_postcode_selector" name="<?php echo DADATA_SUGG_OPTION; ?>[address_postcode_selector]" value="<?php echo esc_attr($o['address_postcode_selector']); ?>" placeholder="#billing_postcode"></td>
                </tr>
                <tr>
                    <th><label for="address_city_selector">— автозаполнить город в</label></th>
                    <td><input type="text" class="regular-text" id="address_city_selector" name="<?php echo DADATA_SUGG_OPTION; ?>[address_city_selector]" value="<?php echo esc_attr($o['address_city_selector']); ?>" placeholder="#billing_city"></td>
                </tr>
                <tr>
                    <th><label for="address_region_selector">— автозаполнить регион в</label></th>
                    <td><input type="text" class="regular-text" id="address_region_selector" name="<?php echo DADATA_SUGG_OPTION; ?>[address_region_selector]" value="<?php echo esc_attr($o['address_region_selector']); ?>" placeholder="#billing_state"></td>
                </tr>
                <tr>
                    <th colspan="2">
                        <button type="button" class="button" id="dadata-woo-preset">Заполнить как для WooCommerce checkout</button>
                    </th>
                </tr>
                <tr>
                    <th colspan="2"><hr></th>
                </tr>
                <tr>
                    <th><label for="fio_selector">ФИО — селектор(ы)</label></th>
                    <td><input type="text" class="regular-text" id="fio_selector" name="<?php echo DADATA_SUGG_OPTION; ?>[fio_selector]" value="<?php echo esc_attr($o['fio_selector']); ?>" placeholder="#your-name"></td>
                </tr>
                <tr>
                    <th><label for="party_selector">Организация / ИНН — селектор(ы)</label></th>
                    <td><input type="text" class="regular-text" id="party_selector" name="<?php echo DADATA_SUGG_OPTION; ?>[party_selector]" value="<?php echo esc_attr($o['party_selector']); ?>" placeholder="#company"></td>
                </tr>
                <tr>
                    <th><label for="email_selector">Email — селектор(ы)</label></th>
                    <td><input type="text" class="regular-text" id="email_selector" name="<?php echo DADATA_SUGG_OPTION; ?>[email_selector]" value="<?php echo esc_attr($o['email_selector']); ?>" placeholder="#your-email"></td>
                </tr>
                <tr>
                    <th><label for="bank_selector">Банк (БИК) — селектор(ы)</label></th>
                    <td><input type="text" class="regular-text" id="bank_selector" name="<?php echo DADATA_SUGG_OPTION; ?>[bank_selector]" value="<?php echo esc_attr($o['bank_selector']); ?>" placeholder="#bank-bic"></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <script>
    (function(){
        document.getElementById('dadata-test-key').addEventListener('click', function(){
            var btn = this, result = document.getElementById('dadata-test-result');
            var key = document.getElementById('api_key').value;
            result.textContent = 'проверка...';
            btn.disabled = true;
            var body = new URLSearchParams();
            body.set('action', 'dadata_suggestions_test_key');
            body.set('_ajax_nonce', '<?php echo esc_js($test_nonce); ?>');
            body.set('api_key', key);
            fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    result.textContent = data.success ? '✓ ключ рабочий' : '✗ ' + (data.data || 'ошибка');
                    result.style.color = data.success ? 'green' : 'red';
                })
                .catch(function(){ result.textContent = '✗ ошибка запроса'; result.style.color = 'red'; })
                .finally(function(){ btn.disabled = false; });
        });
        document.getElementById('dadata-woo-preset').addEventListener('click', function(){
            document.getElementById('address_selector').value = '#billing_address_1, #shipping_address_1';
            document.getElementById('address_postcode_selector').value = '#billing_postcode';
            document.getElementById('address_city_selector').value = '#billing_city';
            document.getElementById('address_region_selector').value = '#billing_state';
            document.getElementById('email_selector').value = '#billing_email';
        });
    })();
    </script>
    <?php
}

// --- Frontend enqueue ---

add_action('wp_enqueue_scripts', function () {
    $o = dadata_suggestions_get_options();
    if ($o['enabled'] !== '1' || empty($o['api_key'])) return;

    $has_selector = $o['address_selector'] || $o['fio_selector'] || $o['party_selector'] || $o['email_selector'] || $o['bank_selector'];
    if (!$has_selector) return;

    wp_enqueue_script(
        'dadata-suggestions-lib',
        'https://cdn.jsdelivr.net/gh/hflabs/suggestions-jquery@' . DADATA_SUGG_LIB_VERSION . '/dist/jquery.suggestions.min.js',
        array('jquery'),
        DADATA_SUGG_LIB_VERSION,
        true
    );

    wp_enqueue_script(
        'dadata-suggestions-init',
        plugins_url('assets/dadata-init.js', __FILE__),
        array('dadata-suggestions-lib'),
        '1.1.0',
        true
    );

    wp_localize_script('dadata-suggestions-init', 'dadataSuggestionsSettings', array(
        'token'           => $o['api_key'],
        'address'         => $o['address_selector'],
        'addressPostcode' => $o['address_postcode_selector'],
        'addressCity'     => $o['address_city_selector'],
        'addressRegion'   => $o['address_region_selector'],
        'fio'             => $o['fio_selector'],
        'party'           => $o['party_selector'],
        'email'           => $o['email_selector'],
        'bank'            => $o['bank_selector'],
    ));
});
