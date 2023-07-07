<?php
/**
 * Plugin Name: WooCommerce Promoted Product
 * Description: Promote an individual product with WooCommerce!
 * Version: 1.0.0
 * Author: Progressus | Moacir Sant'anna
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WooCommercePromotedProduct
 */
class WooCommercePromotedProduct {
    /**
     * The single instance of the class.
     *
     * @var WooCommercePromotedProduct|null
     */
    private static $instance;

    /**
     * Get the single instance of the class.
     *
     * @return WooCommercePromotedProduct
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * WooCommercePromotedProduct constructor.
     */
    private function __construct() {
        add_action('admin_notices', array($this, 'check_woocommerce_activation'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_head', array($this, 'display_promoted_product'));
        add_filter('woocommerce_get_sections_products', array($this, 'add_custom_section'));
        add_filter('woocommerce_get_settings_products', array($this, 'add_custom_settings'), 10, 2);
        add_action('woocommerce_settings_tabs_products', array($this, 'display_current_promoted_product'));
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_custom_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_custom_fields'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_js'));
        add_action('save_post', array($this, 'update_promote_product_checkbox'), 10, 3);
        add_action('init', array($this, 'check_promoted_product_expiration'));
    }

    /**
     * Check if WooCommerce is activated.
     */
    public function check_woocommerce_activation() {
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $message = 'WooCommerce Promoted Product requires WooCommerce to be active.';
            echo '<div class="notice notice-error is-dismissible"><p>' . $message . '</p></div>';
        }
    }

    /**
     * Enqueue styles.
     */
    public function enqueue_styles() {
        wp_enqueue_style('wc-pp-css', plugin_dir_url(__FILE__) . 'css/style.css');
    }

    /**
     * Display the promoted product.
     */
    public function display_promoted_product() {
        $productIDs = get_transient('wc_promoted_ids');
        $firstID = key($productIDs);
        if ($firstID) {
            $bgColor = get_option('wc_promoted_bg_color');
            $txtColor = get_option('wc_promoted_text_color');

            $sectionTitle = get_option('wc_promoted_title');
            $sectionTitle = ($sectionTitle) ? $sectionTitle : 'Promoted Product';

            $customProductTitle = get_post_meta($firstID, 'custom_title', true);
            $productTitle = ($customProductTitle) ? $customProductTitle : get_the_title($firstID);

            ?>
            <div class="promotedProduct" style="<?php if ($bgColor) { ?>background-color: <?php echo "$bgColor;"; } ?>">
                <div class="container">
                    <div class="content" style="<?php if ($txtColor) { ?>color: <?php echo "$txtColor;"; } ?>">
                        <h2>📢 <a href="<?php the_permalink($firstID); ?>"><b><?php echo $sectionTitle; ?></b>: <?php echo $productTitle; ?></a></h2>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Add custom section to WooCommerce settings.
     *
     * @param array $sections Existing sections.
     * @return array Modified sections.
     */
    public function add_custom_section($sections) {
        $sections['wcpromoted'] = __('Promoted Product', 'progressus');
        return $sections;
    }

    /**
     * Add custom settings to WooCommerce settings.
     *
     * @param array  $settings        Existing settings.
     * @param string $current_section Current section.
     * @return array Modified settings.
     */
    public function add_custom_settings($settings, $current_section) {
        if ('wcpromoted' == $current_section) {
            $custom_settings = array(
                array(
                    'name' => __('Promoted product'),
                    'type' => 'title',
                ),
                array(
                    'name' => __('Title of the promoted product'),
                    'type' => 'text',
                    'desc' => __('e.g. "FLASH SALE:"'),
                    'id'   => 'wc_promoted_title'
                ),
                array(
                    'name' => __('Background color'),
                    'type' => 'color',
                    'id'   => 'wc_promoted_bg_color'
                ),
                array(
                    'name' => __('Text color'),
                    'type' => 'color',
                    'id'   => 'wc_promoted_text_color'
                ),
                array('type' => 'sectionend', 'id' => 'wcpromoted')
            );
            return $custom_settings;
        } else {
            return $settings;
        }
    }

    /**
     * Display the current promoted product in the settings.
     */
    public function display_current_promoted_product() {
        if (isset($_GET['section']) && 'wcpromoted' == sanitize_text_field($_GET['section'])) {
            $productIDs = get_transient('wc_promoted_ids');
            $firstID = key($productIDs);
            $output = ($firstID) ? '<a href="' . get_edit_post_link($firstID) . '">' . get_the_title($firstID) . '</a>' : 'None';
            echo '<h2>' . __('Current promoted product', 'progressus') . '</h2>';
            echo '<p>' . $output . '</p>';
        }
    }

    /**
     * Add custom fields to product options.
     */
    public function add_custom_fields() {
        global $product_object;
        woocommerce_wp_checkbox(
            array(
                'id'          => 'promote_product',
                'label'       => __('Promote this product', 'progressus'),
                'description' => __('Activate this product as "promoted" when checked', 'progressus'),
                'value'       => get_post_meta($product_object->get_id(), 'promote_product', true),
            )
        );
        woocommerce_wp_text_input(
            array(
                'id'          => 'custom_title',
                'label'       => __('Custom Title', 'progressus'),
                'placeholder' => __('Leave empty to use product title', 'progressus'),
                'value'       => get_post_meta($product_object->get_id(), 'custom_title', true),
            )
        );
        woocommerce_wp_checkbox(
            array(
                'id'          => 'expiration_checkbox',
                'label'       => __('Set Expiration Date and Time', 'progressus'),
                'description' => __('Add an expiration date and time for the promotion', 'progressus'),
                'value'       => get_post_meta($product_object->get_id(), 'expiration_checkbox', true),
            )
        );
        woocommerce_wp_text_input(
            array(
                'id'          => 'expiration_datetime',
                'label'       => __('Expiration Date and Time', 'progressus'),
                'description' => __('Select the expiration date and time for the promotion', 'progressus'),
                'value'       => get_post_meta($product_object->get_id(), 'expiration_datetime', true),
                'type'        => 'datetime-local',
            )
        );
    }

    /**
     * Save custom fields when product meta is updated.
     *
     * @param int $product_id Product ID.
     */
    public function save_custom_fields($product_id) {
        $checkbox_value = isset($_POST['promote_product']) ? 'yes' : 'no';
        update_post_meta($product_id, 'promote_product', $checkbox_value);

        $custom_title = isset($_POST['custom_title']) ? sanitize_text_field($_POST['custom_title']) : '';
        update_post_meta($product_id, 'custom_title', $custom_title);

        $expiration_checkbox = isset($_POST['expiration_checkbox']) ? 'yes' : 'no';
        update_post_meta($product_id, 'expiration_checkbox', $expiration_checkbox);

        $expiration_datetime = isset($_POST['expiration_datetime']) ? sanitize_text_field($_POST['expiration_datetime']) : '';
        update_post_meta($product_id, 'expiration_datetime', $expiration_datetime);
    }

    /**
     * Enqueue JavaScript scripts.
     */
    public function enqueue_js() {
        if (is_admin() && isset($_GET['post']) && get_post_type($_GET['post']) === 'product' || isset($_GET['post_type']) && $_GET['post_type'] == 'product') {
            wp_enqueue_script('wc-promoted-js', plugin_dir_url(__FILE__) . 'js/main.js', array('jquery'), '1.0', true);
        }
    }

    /**
     * Update the promote product transient when a product is added or edited.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated or not.
     */
    public function update_promote_product_checkbox($post_id, $post, $update) {
        if (get_post_type($post_id) === 'product') {
            $productIDs = get_transient('wc_promoted_ids');
            if (isset($_POST['promote_product']) && $_POST['promote_product'] == 'yes') {
                if (!in_array($post_id, $productIDs)) {
                    if ($productIDs) {
                        $newProduct = array(
                            'has_expiration'       => get_post_meta($post_id, 'expiration_checkbox', true),
                            'expiration_datetime'  => get_post_meta($post_id, 'expiration_datetime', true)
                        );

                        $updatedIDs = array_reverse($productIDs, true);
                        $updatedIDs[$post_id] = $newProduct;
                        $updatedIDs = array_reverse($updatedIDs, true);

                        set_transient('wc_promoted_ids', $updatedIDs);
                    } else {
                        $idsArray = array();
                        $idsArray[$post_id] = array(
                            'has_expiration'       => get_post_meta($post_id, 'expiration_checkbox', true),
                            'expiration_datetime'  => get_post_meta($post_id, 'expiration_datetime', true)
                        );
                        set_transient('wc_promoted_ids', $idsArray);
                    }
                }
            } else {
                if ($productIDs) {
                    unset($productIDs[$post_id]);
                    set_transient('wc_promoted_ids', $productIDs);
                }
            }
        }
    }

    /**
     * Check for promoted product expiration.
     */
    public function check_promoted_product_expiration() {
        $productIDs = get_transient('wc_promoted_ids');
        if (is_array($productIDs)) {
            foreach ($productIDs as $key => $product) {
                if ($product['has_expiration'] === 'yes') {
                    $expiration_datetime = $product['expiration_datetime'];
                    $current_datetime = current_time('Y-m-d\TH:i');

                    if ($expiration_datetime < $current_datetime) {
                        unset($productIDs[$key]);
                        update_post_meta($key, 'promote_product', '');
                        update_post_meta($key, 'custom_title', '');
                        update_post_meta($key, 'expiration_checkbox', '');
                        update_post_meta($key, 'expiration_datetime', '');
                    }
                }
            }
            set_transient('wc_promoted_ids', $productIDs);
        }
    }
}

// Instantiate the class
WooCommercePromotedProduct::get_instance();
