<?php
/*
Plugin Name: WooCommerce Custom Coupon Payment Gateway
Description: A custom payment gateway that allows coupon codes as a payment method.
Version: 1.1
Author: Your Name
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Make sure WooCommerce is active.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    function custom_coupon_payment_gateway_init() {

        class WC_Gateway_Custom_Coupon extends WC_Payment_Gateway {

            public function __construct() {
                $this->id = 'custom_coupon_gateway';
                $this->icon = ''; // URL of the icon that will be displayed on the checkout page.
                $this->has_fields = true;
                $this->method_title = __( 'Coupon Code Payment', 'woocommerce' );
                $this->method_description = __( 'Allows customers to pay using a coupon code.', 'woocommerce' );

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->get_option( 'title' );
                $this->description = $this->get_option( 'description' );

                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            }

            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'       => __( 'Enable/Disable', 'woocommerce' ),
                        'label'       => __( 'Enable Coupon Code Payment', 'woocommerce' ),
                        'type'        => 'checkbox',
                        'description' => '',
                        'default'     => 'no',
                    ),
                    'title' => array(
                        'title'       => __( 'Title', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                        'default'     => __( 'Coupon Code Payment', 'woocommerce' ),
                        'desc_tip'    => true,
                    ),
                    'description' => array(
                        'title'       => __( 'Description', 'woocommerce' ),
                        'type'        => 'textarea',
                        'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                        'default'     => __( 'Enter your coupon code to pay for your order.', 'woocommerce' ),
                    ),
                );
            }

            public function payment_fields() {
                ?>
                <p><?php echo esc_html( $this->description ); ?></p>
                <fieldset>
                    <p class="form-row form-row-wide">
                        <label for="custom_coupon_code"><?php _e( 'Coupon Code', 'woocommerce' ); ?> <span class="required">*</span></label>
                        <input type="text" class="input-text" id="custom_coupon_code" name="custom_coupon_code" placeholder="<?php _e( 'Enter your coupon code', 'woocommerce' ); ?>" />
                        <button type="button" id="validate_coupon_code" class="button"><?php _e( 'Validate Coupon', 'woocommerce' ); ?></button>
                        <span id="coupon_validation_message"></span>
                    </p>
                </fieldset>
                <?php
            }

            public function payment_scripts() {
                if ( ! is_checkout() ) {
                    return;
                }

                wp_enqueue_script( 'custom-coupon-gateway', plugin_dir_url( __FILE__ ) . 'js/custom-coupon-gateway.js', array( 'jquery' ), '1.0.0', true );

                wp_localize_script( 'custom-coupon-gateway', 'customCouponGateway', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'custom_coupon_nonce' ),
                ));
            }

            public function validate_fields() {
                if ( empty( $_POST['custom_coupon_code'] ) ) {
                    wc_add_notice( __( 'Please enter a coupon code.', 'woocommerce' ), 'error' );
                    return false;
                }

                $coupon_code = wc_clean( $_POST['custom_coupon_code'] );
                $coupon = new WC_Coupon( $coupon_code );

                if ( ! $coupon->is_valid() ) {
                    wc_add_notice( __( 'Invalid coupon code.', 'woocommerce' ), 'error' );
                    return false;
                }

                $discount = $coupon->get_amount();
                $cart_total = WC()->cart->get_total( 'edit' );

                if ( $discount < $cart_total ) {
                    wc_add_notice( __( 'The coupon does not cover the full amount.', 'woocommerce' ), 'error' );
                    return false;
                }

                return true;
            }

            public function process_payment( $order_id ) {
                $order = wc_get_order( $order_id );

                // Mark as paid.
                $order->payment_complete();

                // Reduce coupon usage count.
                $coupon_code = wc_clean( $_POST['custom_coupon_code'] );
                $coupon = new WC_Coupon( $coupon_code );
                $coupon->set_usage_count( $coupon->get_usage_count() + 1 );
                $coupon->save();

                // Empty the cart.
                WC()->cart->empty_cart();

                // Return thankyou redirect.
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url( $order ),
                );
            }
        }
    }

    add_filter( 'woocommerce_payment_gateways', 'add_custom_coupon_payment_gateway' );

    function add_custom_coupon_payment_gateway( $gateways ) {
        $gateways[] = 'WC_Gateway_Custom_Coupon';
        return $gateways;
    }

    add_action( 'plugins_loaded', 'custom_coupon_payment_gateway_init', 11 );

    // Ajax handler for validating the coupon
    function validate_coupon_code_ajax() {
        check_ajax_referer( 'custom_coupon_nonce', 'nonce' );

        if ( ! isset( $_POST['coupon_code'] ) ) {
            wp_send_json_error( __( 'No coupon code provided.', 'woocommerce' ) );
        }

        $coupon_code = wc_clean( $_POST['coupon_code'] );
        $coupon = new WC_Coupon( $coupon_code );

        if ( ! $coupon->is_valid() ) {
            if ( $coupon->get_date_expires() && $coupon->get_date_expires()->is_past() ) {
                wp_send_json_error( __( 'This coupon has expired.', 'woocommerce' ) );
            } else {
                wp_send_json_error( __( 'Invalid coupon code.', 'woocommerce' ) );
            }
        }

        $discount = $coupon->get_amount();
        $cart_total = WC()->cart->get_total( 'edit' );

        if ( $discount < $cart_total ) {
            wp_send_json_error( __( 'The coupon does not cover the full amount.', 'woocommerce' ) );
        } else {
            wp_send_json_success( __( 'Coupon is valid and covers the full amount.', 'woocommerce' ) );
        }
    }

    add_action( 'wp_ajax_validate_coupon_code', 'validate_coupon_code_ajax' );
    add_action( 'wp_ajax_nopriv_validate_coupon_code', 'validate_coupon_code_ajax' );
}