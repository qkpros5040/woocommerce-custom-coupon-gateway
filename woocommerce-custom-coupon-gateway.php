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
				<fieldset style="padding: 5px 0;">

					<!-- Coupon Code Section -->
					<label for="custom_coupon_code"><?php _e( 'Budget card code', 'woocommerce' ); ?> <span class="required">*</span></label>
					<div style="display: flex; align-items: center;">
						<p class="form-row form-row-wide" style="margin: 0; width: 75%">
							<input type="text" class="input-text" id="custom_coupon_code" name="custom_coupon_code" placeholder="<?php _e( 'Enter your Budget card code', 'woocommerce' ); ?>" />
						</p>
						<button type="button" id="validate_coupon_code" class="button" style="width: 25%;padding: 9px 0;"><?php _e( 'Validate Coupon', 'woocommerce' ); ?></button>
					</div>
					<span id="coupon_validation_message"></span>

					<!-- Approuvé par le directeur Section -->
					<div style="margin-top: 20px;">
						<label for="approved_by_director"><?php _e( 'Approved by Director (name)', 'woocommerce' ); ?> <span class="required">*</span></label>
						<p class="form-row form-row-wide" style="margin: 0;">
							<input type="text" class="input-text" id="approved_by_director" name="approved_by_director" placeholder="<?php _e( 'Enter director\'s name', 'woocommerce' ); ?>" required />
						</p>
					</div>

				</fieldset>
				<?php
			}

            public function payment_scripts() {
                if ( ! is_checkout() ) {
                    return;
                }

                wp_enqueue_script( 'custom-coupon-gateway', plugin_dir_url( __FILE__ ) . 'custom-coupon-gateway.js', array( 'jquery' ), '1.0.0'.rand(), true );

                wp_localize_script( 'custom-coupon-gateway', 'customCouponGateway', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'custom_coupon_nonce' ),
					'textDomain' => array(
						'enterCoupon' => __( 'Please enter a coupon code.', 'woocommerce' ),
					)
                ));
            }

            public function validate_fields() {
				
				 // Check if the selected payment method is 'custom_coupon_gateway'
				if ( 'custom_coupon_gateway' !== WC()->session->get('chosen_payment_method') ) {
					return; // Exit if it's not the custom payment method.
				}
				
				
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

//             public function process_payment( $order_id ) {
//                 $order = wc_get_order( $order_id );

//                 // Mark as paid.
//                 $order->payment_complete();

//                 // Reduce coupon usage count.
//                 $coupon_code = wc_clean( $_POST['custom_coupon_code'] );
//                 $coupon = new WC_Coupon( $coupon_code );
//                 $coupon->set_usage_count( $coupon->get_usage_count() + 1 );
//                 $coupon->save();
// 				// Add coupon code to order meta
// 				update_post_meta( $order_id, '_custom_coupon_code', $coupon_code );

// 				// Add a note to the order with the coupon code
// 				$order->add_order_note( sprintf( __( 'Order paid using coupon code: %s', 'woocommerce' ), $coupon_code ) );

//                 // Empty the cart.
//                 WC()->cart->empty_cart();

//                 // Return thankyou redirect.
//                 return array(
//                     'result'   => 'success',
//                     'redirect' => $this->get_return_url( $order ),
//                 );
//             }
//         }
//     }

		public function process_payment( $order_id ) {
			// Check if the selected payment method is 'custom_coupon_gateway'
			if ( 'custom_coupon_gateway' !== WC()->session->get('chosen_payment_method') ) {
				return; // Exit if it's not the custom payment method.
			}
			$order = wc_get_order( $order_id );

			// Get the coupon code from the order
			$coupon_code = wc_clean( $_POST['custom_coupon_code'] );
			$coupon = new WC_Coupon( $coupon_code );

			// Get the current discount amount of the coupon
			$discount = $coupon->get_amount();
			$cart_total = WC()->cart->get_total( 'edit' );

			// Subtract the cart total from the coupon amount
			$new_discount = $discount - $cart_total;

			if ( $new_discount > 0 ) {
				// Update the coupon with the new amount
				update_post_meta( $coupon->get_id(), 'coupon_amount', $new_discount );
			} else {
				// If the new discount is zero or less, delete the coupon or set it to zero
				update_post_meta( $coupon->get_id(), 'coupon_amount', 0 );
			}

			// Mark as paid if the coupon covers the full amount
			$order->payment_complete();

			// Reduce coupon usage count
			$coupon->set_usage_count( $coupon->get_usage_count() + 1 );
			$coupon->save();

			// Add coupon code to order meta
			update_post_meta( $order_id, '_custom_coupon_code', $coupon_code );

			// Add a note to the order with the coupon code
			$order->add_order_note( sprintf( __( 'Order paid using coupon code: %s. Remaining coupon amount: %s', 'woocommerce' ), $coupon_code, wc_price($new_discount) ) );

			// Empty the cart
			WC()->cart->empty_cart();

			// Return thank you redirect
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
	
	
	// Validate the custom checkout field (Approuvé par le directeur)
		add_action('woocommerce_checkout_process', 'validate_director_approval_field');

		function validate_director_approval_field() {
			
			// Check if the selected payment method is 'custom_coupon_gateway'
			if ( 'custom_coupon_gateway' !== WC()->session->get('chosen_payment_method') ) {
				return; // Exit if it's not the custom payment method.
			}
			
			if (empty($_POST['approved_by_director'])) {
				wc_add_notice(__('Please enter the name of the director who approved this.', 'woocommerce'), 'error');
			}
		}
	
			// Save the custom field (Approuvé par le directeur) to order meta
		add_action('woocommerce_checkout_update_order_meta', 'save_director_approval_field');

		function save_director_approval_field($order_id) {
			if (!empty($_POST['approved_by_director'])) {
				update_post_meta($order_id, '_approved_by_director', sanitize_text_field($_POST['approved_by_director']));
			}
		}
	
		// Display the coupon code on the thank you page
		add_action( 'woocommerce_thankyou', 'display_coupon_code_on_thankyou_page', 20 );

		function display_coupon_code_on_thankyou_page( $order_id ) {
			$order = wc_get_order( $order_id );
			$coupon_code = get_post_meta( $order_id, '_custom_coupon_code', true );

			if ( ! empty( $coupon_code ) ) {
				echo '<p><strong>' . __( 'Coupon Code Used:', 'woocommerce' ) . '</strong> ' . esc_html( $coupon_code ) . '</p>';
			}
		}

				// Add coupon // Add coupon code and "Approved by Director" to the "Détails de la commande" section on the thank you page and emails
		add_action( 'woocommerce_order_item_meta_end', 'add_meta_to_order_details', 10, 4 );

		function add_meta_to_order_details( $item_id, $item, $order, $plain_text ) {
			// Get the coupon code and "Approved by Director" from order meta
			$coupon_code = get_post_meta( $order->get_id(), '_custom_coupon_code', true );
			$approved_by_director = get_post_meta( $order->get_id(), '_approved_by_director', true );

			// Check if there is a coupon code to display
			if ( ! empty( $coupon_code ) ) {
				echo '<p><strong>' . __( 'Coupon Code:', 'woocommerce' ) . '</strong> ' . esc_html( $coupon_code ) . '</p>';
			}

			// Check if there is a director approval to display
			if ( ! empty( $approved_by_director ) ) {
				echo '<p><strong>' . __( 'Approved by Director:', 'woocommerce' ) . '</strong> ' . esc_html( $approved_by_director ) . '</p>';
			}
		}

		// Add coupon code and "Approved by Director" to the order edit page in the WooCommerce admin
		add_action( 'woocommerce_admin_order_data_after_order_details', 'display_meta_in_admin_order_meta', 10, 1 );

		function display_meta_in_admin_order_meta( $order ) {
			// Get the coupon code and "Approved by Director" from order meta
			$coupon_code = get_post_meta( $order->get_id(), '_custom_coupon_code', true );
			$approved_by_director = get_post_meta( $order->get_id(), '_approved_by_director', true );

			// Check if there is a coupon code to display
			if ( ! empty( $coupon_code ) ) {
				echo '<p class="form-field form-field-wide wc-customer-user"><strong>' . __( 'Coupon Code Used:', 'woocommerce' ) . '</strong> ' . esc_html( $coupon_code ) . '</p>';
			}

			// Check if there is a director approval to display
			if ( ! empty( $approved_by_director ) ) {
				echo '<p class="form-field form-field-wide wc-customer-user"><strong>' . __( 'Approved by Director:', 'woocommerce' ) . '</strong> ' . esc_html( $approved_by_director ) . '</p>';
			}
		}
}

