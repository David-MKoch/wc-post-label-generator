<?php
/*
Plugin Name: برچسب پستی
Description: تولید برچسب پستی برای سفارشات ووکامرس
Version: 1.0
Author: neodesign
*/

// Add custom action button in WooCommerce orders page
add_filter('woocommerce_admin_order_actions', 'add_shipping_label_action_button', 10, 2);
function add_shipping_label_action_button($actions, $order) {
    $actions['shipping_label'] = [
        'url'       => wp_nonce_url(admin_url('admin-ajax.php?action=generate_shipping_label&order_id=' . $order->get_id()), 'generate_shipping_label'),
        'name'      => 'برچسب پستی',
        'action'    => 'shipping_label',
        'target'    => '_blank',
//		'icon'      => '<span class="dashicons dashicons-admin-page"></span>',
    ];
    return $actions;
}
//woocommerce_order_item_add_action_buttons
add_action( 'woocommerce_admin_order_data_after_shipping_address', function($order) {
	?>
	<a class="button" id="my-post-label" href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=generate_shipping_label&order_id=' . $order->get_id()), 'generate_shipping_label'); ?>" target="_blank">برچسب پستی</a>
	<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.edit_address').each(function() {
				var $editAddress = $(this);
				var $targetField = $editAddress.children('._shipping_puiw_invoice_track_id_field');

				if ($targetField.length) {
					// ایجاد المنت <p> با کلاس form-field form-field-wide
					var $customElement = $('<p>', {
						class: 'form-field form-field-wide'
					});

					// انتقال #my-post-label به داخل این المنت
					$('#my-post-label').appendTo($customElement);

					// قرار دادن المنت جدید قبل از $targetField
					$customElement.insertBefore($targetField);
				}
			});
		});
	</script>
<?php
} );

// Enqueue JavaScript to ensure the link opens in a new tab
add_action('admin_enqueue_scripts', 'enqueue_shipping_label_script');
function enqueue_shipping_label_script($hook) {
    // Check if we are on the WooCommerce orders page
    if ($hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order') {
		wp_register_script(
            'shipping-label-admin-js',
            plugin_dir_url(__FILE__) . 'assets/admin-orders.js',
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script(
            'shipping-label-admin-js',
            'shippingLabelVars',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('bulk_shipping_action_nonce'),
            ]
        );

        wp_enqueue_script('shipping-label-admin-js');
    }
}
add_action('admin_head', 'add_shipping_label_icon_css');
function add_shipping_label_icon_css() {
    echo '<style>
        .wc-action-button-shipping_label::after {
            content: "\f481";
        }
    </style>';
}

/**
 * Add bulk_shipping_label action to order status dropdown
 */
add_filter( 'bulk_actions-edit-shop_order', function( $bulk_actions ) {//add_custom_bulk_action_to_order_status
    $bulk_actions['bulk_shipping_label'] = 'چاپ برچسب پستی (گروهی)';//__( '', 'textdomain' );
    return $bulk_actions;
} );

/**
 * Process the bulk_shipping_label action
 */
add_action( 'wp_ajax_bulk_shipping_action', function() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'bulk_shipping_action_nonce' ) ) {
		wp_die( 'خطای امنیتی: درخواست نامعتبر.' );
	}
	if ( current_user_can("manage_options") && isset($_GET["post_ids"]) && !empty(trim(sanitize_text_field($_GET["post_ids"]))) ) {
		$order_ids = (array) explode(",", $_GET["post_ids"]);
		$order_ids = array_map("trim", $order_ids);
		
		process_shipping_label($order_ids);
	}
} );

// Handle the AJAX request to generate the shipping label
add_action('wp_ajax_generate_shipping_label', 'generate_shipping_label');
function generate_shipping_label() {
/*    if (!current_user_can('manage_woocommerce') || !check_admin_referer('generate_shipping_label')) {
        wp_die(__('You are not allowed to access this page.', 'woocommerce'));
    }*/

    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    if (!$order_id) {
        wp_die(__('Invalid order ID.', 'woocommerce'));
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die(__('Order not found.', 'woocommerce'));
    }

	process_shipping_label($order);
}

function process_shipping_label($orders){
	// Get WooCommerce store address
    //$store_address = WC()->countries->get_base_address() . ', ' . WC()->countries->get_base_city() . ', ' . WC()->countries->get_base_state() . ', ' . WC()->countries->get_base_postcode();
    $store_address = 'ایران، خراسان رضوی، مشهد، ابتدای جاده شاندیز، بین ویرانی ۳۳ و ۳۱';
    $store_phone = '90006525 | 09159219756';//get_option('woocommerce_store_phone', 'Your Store Phone Number');
	$site_logo = get_custom_logo();
	if(empty($site_logo))  $site_logo = '<h2>Your Store Logo</h2>';
	
	$order_number = '';
	if ( $orders instanceof WC_Order ) {
        $order_number = $orders->get_order_number();
    }
	
	$css_url = plugin_dir_url(__FILE__) . 'assets/label-style.css';
	require_once plugin_dir_path(__FILE__) . 'libs/picqer-php-barcode/src/BarcodeGeneratorSVG.php';
	require_once plugin_dir_path(__FILE__) . 'libs/picqer-php-barcode/src/Helpers/ColorHelper.php';
    require_once plugin_dir_path(__FILE__) . 'libs/picqer-php-barcode/src/Renderers/RendererInterface.php';
    require_once plugin_dir_path(__FILE__) . 'libs/picqer-php-barcode/src/Renderers/SvgRenderer.php';
    
	?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<title>برچسب پستی <?php echo $order_number;?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<link rel="stylesheet" href="<?php echo $css_url;?>">
</head>
<body><?php
	
	if ( $orders instanceof WC_Order ) {
        html_content_shipping_label( $orders, $store_address, $store_phone, $site_logo );
    } 
    elseif ( is_array( $orders ) ) {
        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                html_content_shipping_label( $order, $store_address, $store_phone, $site_logo );
				echo '<div class="page-break"></div>';
            }
        }
    } 
    else {
        wc_get_logger()->error( 'ورودی نامعتبر برای تابع process_shipping_label().' );
    }
	
	echo '</body></html>';
	exit;
}

function html_content_shipping_label(WC_Order $order, $store_address, $store_phone, $site_logo){
	// Retrieve order details
    
    $order_date = wp_date('Y/m/d', strtotime($order->get_date_created()));
    $order_number = $order->get_order_number();
    //$shipping_method = $order->get_shipping_method();
    $billing_address = str_replace('<br/>', ', ', $order->get_formatted_billing_address());
    //$shipping_address = str_replace('<br/>', ', ', $order->get_formatted_shipping_address());
    $customer_name = $order->get_formatted_billing_full_name();
    $customer_phone = $order->get_billing_phone();
	$customer_note = $order->get_customer_note();

	$order_totals = $order->get_order_item_totals();
    //$total_amount = wc_price($order->get_total());
    //$paid_amount = wc_price($order->get_total() - $order->get_total_refunded());
    //$cod_amount = $order->get_payment_method() === 'cod' ? wc_price($order->get_total()) : wc_price(0);
    $generator = new Picqer\Barcode\BarcodeGeneratorSVG();
	$barcode_text = $order->get_meta('_mnsjay_tipax_barcode');
    // Display the label content
    ?>
<div class="label-border">
	<div class="label-body">
		<div class="header">
			<div class="mylogo">
				<?php
				echo $site_logo;
				if($barcode_text){
				    try {
                        $barcode_svg = $generator->getBarcode($barcode_text, $generator::TYPE_CODE_128);
                        echo '<div class="barcode-container">'.$barcode_svg.$barcode_text.'</div>';
                    } catch (Exception $e) {
                        error_log('خطا در تولید بارکد: ' .$barcode_text.' '. $e->getMessage());
                    }
				}
				?>
			</div>
			<div class="order-data">
				<strong>تاریخ سفارش: </strong><?php echo $order_date;?><br>
				<strong>شماره سفارش: </strong><?php echo $order_number;?><br>
				<strong><?php echo $order_totals['payment_method']['label'];?></strong> <?php echo $order_totals['payment_method']['value'];?><br>
				<strong><?php echo $order_totals['shipping']['label'];?></strong> <?php echo $order_totals['shipping']['value'];?><br>
			</div>
		</div>
		<div class="label-data">
			<div class="sender">
				<hr>
				<strong>فرستنده: </strong><?php echo $store_address;?><br>
				<strong>تلفن: </strong><?php echo $store_phone;?>
			</div>
			<div class="recipient">
				<hr>
				<strong>گیرنده: </strong><?php echo $customer_name;?>, <?php echo $billing_address;?><br>
				<strong>تلفن: </strong><?php echo $customer_phone;?><br><hr>
				<strong> <?php echo $order_totals['order_total']['label'];?> </strong> <?php echo $order_totals['order_total']['value'];?><br>
				<?php 
				if(isset($order_totals['cod_amount'])){
					echo "<strong> {$order_totals['prepayment_amount']['label']} </strong> {$order_totals['prepayment_amount']['value']}<br>";
					echo "<strong> {$order_totals['cod_amount']['label']} (COD) </strong> {$order_totals['cod_amount']['value']}<br>";
				}else{
				    $pre_cod = $order->get_meta('_partial_payment_amount');
	                if(!empty($pre_cod)){
	                    $pre_cod = wc_price($pre_cod);
	                    $cod = wc_price($order->get_meta('_cod_amount'));
	                    echo "<strong> پیش پرداخت: </strong> {$pre_cod}<br>";
					    echo "<strong> پرداخت در محل (COD): </strong> {$cod}<br>";
	                }
				}
				if ($customer_note) {
					echo "<hr><strong>یادداشت مشتری:</strong><br>$customer_note<br>";
				}
				?>
			</div>
		</div>
	</div>
</div>

    <?php
}