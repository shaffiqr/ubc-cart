<?php
/**
* Plugin Name: UBC Cart
* Gravity Forms UBC Cart Custom Form Field
* Plugin URI: http://isit.adm.arts.ubc.ca
* Description: Gravity Forms Shopping Cart field for use in Gravity Forms
* Version: 1.0
* Author: Shaffiq Rahemtulla
* Author URI: http://isit.adm.arts.ubc.ca
* License: GPLv2
*
*/
// Exit if accessed directly
if ( ! class_exists( 'GFForms' ) ) {
	//deactivate if GF not active - has to be done out of class as class is an extension
	deactivate_plugins( plugin_basename( __FILE__ ) );
	wp_die( 'The <strong>UBC Cart</strong> plugin requires the Gravity Forms plugin (v 1.9 >)to be installed and activated - <strong>DEACTIVATED</strong> ' );
} else {
	// Add settings link on plugin page
	function cart_plugin_settings_link($links) {
		$settings_link = '<a href="admin.php?page=ubc_cart_options">Settings</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
	$plugin = plugin_basename( __FILE__ );
	add_filter( 'plugin_action_links_$plugin', 'cart_plugin_settings_link' );
}

add_filter( 'plugins_loaded', '_ubc_cart' );

// -- Class Name : UBC_CART
// -- Purpose : 0. Activates session manager
// --           1. Cart manipulation functions (Ajax)
// --           2. Creates CPT with taxonomy and fields
// --           3. Creates advanced field in GF using filters
// --           4. Creates merge tag in GF using filters
// --           5. Options and Settings as a Gravity Forms Addon
// -- Created On : March 21st 2015
class UBC_CART extends GFAddOn
{

	public $session;
	public $admin_settings;
	public $cart_fields;
	public static $filter_option = '';
	public static $merge_tag = '{ubccart_subtotal}';
	public static $merge_tag_shipping = '{ubccart_shipping}';
	public static $merge_tag_shippingint = '{ubccart_shippingint}';

	// -- Function Name : __construct
	// -- Params : None
	// -- Purpose : New Instance
	function __construct( ) {

		//error_reporting(E_ERROR | E_WARNING | E_PARSE);
		//ini_set('display_errors', 'On');

		$this->setup_constants();

		$this->includes();

		//Setup Custom Post Type
		$this-> createUBCProductsType();

		// load custom archive template for ubc_product
		add_filter( 'template_include', array( &$this, 'ubc_product_template' ) );

		add_filter( 'the_content', array( &$this, 'add_to_cart_button' ) );

		//Setup shortcode for mini cart
		add_shortcode( 'show-cart', array( &$this, 'show_ubc_cart' ) );

		// Add our custom UBC Product Shortcodes
		add_action( 'init', array( $this, 'init__add_shortcodes' ) );

		//Setup shortcode for add-to-cart button
		//add_shortcode('addcart', array(&$this ,'add_to_cart_shortcode' ));

		//Start up Session Manager
		$this->session  = new UBCCART_Session();

		//Add plugin js
		add_action( 'wp_enqueue_scripts', array( &$this, 'cart_script' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'archive_page_script' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_cart_script' ) );

		//Add Ajax Actions
		$this->add_ajax_actions();

		//Gravity Forms Hooks
		$this->add_gf_hooks();

		//Options & Settings
		$this->admin_settings  = new GFCartAddOn();
	}

	// -- Function Name : init__add_shortcodes
	// -- Params :
	// -- Purpose : List products and list related products
	public function init__add_shortcodes() {
		add_shortcode( 'ubc-product', array( $this, 'add_shortcode__ubc_product' ) );
		add_shortcode( 'ubc-product-related', array( $this, 'add_shortcode__ubc_product_related' ) );
	}

	// -- Function Name : init__add_shortcodes
	// -- Params : Parameters ID/Title/Price - [linked to product page] + Add to cart Button
	// -- Default : Show settings columns - explicitly set columns [price] Add to Cart button
	// -- Purpose : Show product with AddtoCart button
	// -- To be used within CTLT Loop Query shortcode
	// -- @return (string) markup for this shortcode
	public function add_shortcode__ubc_product_related( $atts ) {
		global $post;
		$atts = shortcode_atts(
			array(
				'show_headings' => 'true',
				'linked' => 'true',
				'link_target' => '_self',
				'show_thumbnail' => 'false',
				'thumbsize' => '',
				'show_id' => 'true',
				'show_title' => 'true',
				'show_excerpt' => 'true',
				'show_price' => 'true',
				'show_button' => 'true',
				'orderby' => '',
				'order' => 'DESC',
			),
			$atts,
			'ubc-product'
		);
		$shortcode_output = '';
		//**MOD
		if ( isset( $atts['orderby'] ) ) {
			$orderby = $atts['orderby'];
		}
		if ( isset( $atts['order'] ) ) {
			$order = $atts['order'];
		}
		$tags = get_the_tags();
		if ( $tags ) {
			$tag_ids = array();
			foreach ( $tags as $individual_tag ) {
				$tag_ids[] = $individual_tag->term_id;
			}
			$args = array(
					'post_type' => 'ubc_product',
					'tag__in' => $tag_ids,
					'posts_per_page' => 4,
					'orderby' => $orderby,
					'order' => $order,
					'caller_get_posts' => 1,
			);
			$related_query = new wp_query( $args );
			$shortcode_header .= '<thead><tr class="product-heading-tr">';
			$columns = 0;
			if ( 'true' === $atts['show_thumbnail'] ) {
				$shortcode_header .= '<th class="prodimg">&nbsp;</th>';
				$columns ++;
			}
			if ( 'true' === $atts['show_id'] ) {
				$shortcode_header .= '<th class="prodid">ID</th>';
				$columns ++;
			}
			if ( 'true' === $atts['show_title'] ) {
				$shortcode_header .= '<th class="prodtitle">Title</th>';
				$columns ++;
			}
			if ( 'true' === $atts['show_price'] ) {
				$shortcode_header .= '<th class="prodprice">Price</th>';
				$columns ++;
			}
			if ( 'true' === $atts['show_button'] ) {
				$shortcode_header .= '<th class="prodbutton">&nbsp;</th>';
				$columns ++;
			}
			$shortcode_header .= '</tr></thead>';
			$shortcode_body = '<tbody>';
			while ( $related_query->have_posts() ) {
				$related_query->the_post();
				$shortcode_body .= '<tr class="product-tr">';
				if ( 'true' === $atts['show_thumbnail'] ) {
					$thumb_url = '<img src="'.wp_get_attachment_url( get_post_thumbnail_id( get_the_ID() ) ).'" />';
					$shortcode_body .= '<td class="product-thumbnail" data-title="ID">'.$thumb_url.'</td>';
				}
				if ( 'true' === $atts['show_id'] ) {
					$shortcode_body .= '<td class="product-id">'.get_the_ID().'</td>';
				}
				if ( 'true' === $atts['show_title'] ) {
					if ( 'true' === $atts['linked'] ) {
						$shortcode_body .= '<td class="product-title"><a href="'.get_the_permalink( get_the_ID() ).'" target="'.$atts['link_target'].'">'.get_the_title().'</a></td>';
					} else {
						$shortcode_body .= '<td class="product-title" data-title="Title">'.get_the_title().'</td>';
					}
				}
				if ( 'true' === $atts['show_excerpt'] ) {
					$shortcode_description = '<p>'.$post->post_excerpt.'</p>';
				}
				if ( 'true' === $atts['show_price'] ) {
					$shortcode_body .= '<td class="product-price" data-title="Price">$'.get_post_meta( get_the_ID(), 'price', true ).'</td>';
				}
				if ( 'true' === $atts['show_button'] ) {
					$cartoptions = get_option( 'ubc_cart_options' );
					$filter_id = $cartoptions['filter'];
					$filter_term = get_term( $filter_id, 'ubc_product_type' );
					$filter_option = $filter_term->slug;
					$terms_list = wp_get_post_terms( get_the_ID(), 'ubc_product_type', array( 'fields' => 'slugs' ) );
					if ( $filter_option ) {
						if ( in_array( $filter_option,$terms_list ) ) {
							$shortcode_body .= '<td class="product-button" text-align="right"><button class="cartbtn small pid_'.get_the_ID().'" href="#"  onclick="addtocart(this,'.get_the_ID().')"><i class="icon-shopping-cart"></i> Add to Cart</button></td>';
						} else {
							$shortcode_body .= '<td class="product-button" text-align="right"><button class="disabled by-filter cartbtn small pid_'.get_the_ID().'" href="#"  onclick="addtocart(this,'.get_the_ID().')"><i class="icon-shopping-cart"></i> Add to Cart</button></td>';
						}
					} else {
						$shortcode_body .= '<td class="product-button" text-align="right"><button class="cartbtn small pid_'.get_the_ID().'" href="#"  onclick="addtocart(this,'.get_the_ID().')"><i class="icon-shopping-cart"></i> Add to Cart</button></td>';
					}
				}
				$shortcode_body .= '</tr>';
				$shortcode_body .= '<tr><td colspan="'.$columns.'">'.$shortcode_description.'</td></tr>';
			}
			$shortcode_body .= '</tbody>';
			if ( 'true' === $atts['show_headings'] ) {
				$shortcode_body = $shortcode_header . $shortcode_body;
			}
			$shortcode_output .= '<table class="ubc-product-related-sc">'.$shortcode_body.'</table>';
		}
		wp_reset_query();
		return $shortcode_output;
	}


	// -- Function Name : init__add_shortcodes
	// -- Params : Parameters ID/Title/Price - [linked to product page] + Add to cart Button
	// -- Default : Show settings columns - explicitly set columns [price] Add to Cart button
	// -- Purpose : Show product with AddtoCart button
	// -- To be used within CTLT Loop Query shortcode
	// -- @return (string) markup for this shortcode
	public function add_shortcode__ubc_product( $atts ) {
		$atts = shortcode_atts(
			array(
				'show_headings' => 'true',
				'linked' => 'true',
				'link_target' => '_self',
				'show_thumbnail' => 'true',
				'thumbsize' => '',
				'show_id' => 'true',
				'show_title' => 'true',
				'show_excerpt' => 'true',
				'show_price' => 'true',
				'show_button' => 'true',
			),
			$atts,
			'ubc-product'
		);
		$shortcode_output = '';
		$shortcode_header .= '<thead><tr class="product-heading-tr">';
		$shortcode_body = '<tbody><tr class="product-tr">';
		if ( 'true' === $atts['show_thumbnail'] ) {
			$shortcode_header .= '<th class="prodimg">&nbsp;</th>';
			$thumb_url = '<img src="'.wp_get_attachment_url( get_post_thumbnail_id( get_the_ID() ) ).'" />';
			$shortcode_body .= '<td class="product-thumbnail" data-title="ID">'.$thumb_url.'</td>';
		}
		if ( 'true' === $atts['show_id'] ) {
			$shortcode_header .= '<th class="prodid">ID</th>';
			$shortcode_body .= '<td class="product-id">'.get_the_ID().'</td>';
		}
		if ( 'true' === $atts['show_title'] ) {
			$shortcode_header .= '<th class="prodtitle">Title</th>';
			if ( 'true' === $atts['linked'] ) {
				$shortcode_body .= '<td class="product-title"><a href="'.get_the_permalink( get_the_ID() ).'" target="'.$atts['link_target'].'">'.get_the_title().'</a></td>';
			} else {
				$shortcode_body .= '<td class="product-title" data-title="Title">'.get_the_title().'</td>';
			}
		}
		if ( 'true' === $atts['show_excerpt'] ) {
			//$shortcode_header .= '<th class="Description-header">Desc.</th>';
			$shortcode_description .= '<p>'.get_the_excerpt().'</p>';
		}
		if ( 'true' === $atts['show_price'] ) {
			$shortcode_header .= '<th class="prodprice">Price</th>';
			$shortcode_body .= '<td class="product-price" data-title="Price">$'.get_post_meta( get_the_ID(), 'price', true ).'</td>';
		}
		if ( 'true' === $atts['show_button'] ) {
			$shortcode_header .= '<th class="prodbutton">&nbsp;</th>';
			$cartoptions = get_option( 'ubc_cart_options' );
			$filter_id = $cartoptions['filter'];
			$filter_term = get_term( $filter_id, 'ubc_product_type' );
			$filter_option = $filter_term->slug;
			$terms_list = wp_get_post_terms( get_the_ID(), 'ubc_product_type', array( 'fields' => 'slugs' ) );
			if ( $filter_option ) {
				if ( in_array( $filter_option,$terms_list ) ) {
					$shortcode_body .= '<td class="product-button" text-align="right"><button class="cartbtn small pid_'.get_the_ID().'" href="#"  onclick="addtocart(this,'.get_the_ID().')"><i class="icon-shopping-cart"></i> Add to Cart</button></td>';
				} else {
					$shortcode_body .= '<td class="product-button" text-align="right"><button class="disabled by-filter cartbtn small pid_'.get_the_ID().'" href="#"  onclick="addtocart(this,'.get_the_ID().')"><i class="icon-shopping-cart"></i> Add to Cart</button></td>';
				}
			} else {
				$shortcode_body .= '<td class="product-button" text-align="right"><button class="cartbtn small pid_'.get_the_ID().'" href="#"  onclick="addtocart(this,'.get_the_ID().')"><i class="icon-shopping-cart"></i> Add to Cart</button></td>';
			}
		}
		$shortcode_header .= '</tr></thead>';
		$shortcode_body .= '</tr></tbody>';
		if ( 'true' === $atts['show_headings'] ) {
			$shortcode_body .= $shortcode_header;
		}
		$shortcode_output .= '<table class="ubc-product-sc">'.$shortcode_body.'</table>'.$shortcode_description;
		return $shortcode_output;
	}

	// -- Function Name : show_ubc_cart
	// -- Params : $atts
	// -- Purpose : Use create_table() function as shortcode
	function show_ubc_cart( $atts ) {
		$this->cart_script( true );
		return ' <div id="cart-details">'.$this->create_table().'</div>';
	}


	// -- Function Name : add_to_cart_button
	// -- Params : $content
	// -- Purpose : Add the Add to Cart Button to single post display
	function add_to_cart_button( $content ) {
		global $post;
		if ( (is_single()) && $post->post_type == 'ubc_product' ) {
			//**********************************
			//*    CART OPTIONS                *
			//**********************************
			$cartoptions = get_option( 'ubc_cart_options' );
			$filter_id = $cartoptions['filter'];
			if ( has_term( $filter_id, 'ubc_product_type' ,$post->ID ) ) {
				$content = the_post_thumbnail( array( 150, 150 ) ).$content . '<button class="cartbtn pid_'.$post->ID.'" href="#" onclick="addtocart(this,'.$post->ID.')"><i class="icon-shopping-cart"></i> Add to Cart</button><button style="margin-left:5px;" onclick="window.location.href=\''.site_url( '/checkout/' ).'\'" class="cartbtn"><i class="icon-circle-arrow-right"></i> Go to Checkout</button>';
			}
		}
		return $content;
	}

	// -- Function Name : add_gf_hooks
	// -- Params : None
	// -- Purpose : All Gravity Forms Hooks
	private function add_gf_hooks( ) {

		// Add a custom field button to the advanced to the field editor
		add_filter( 'gform_add_field_buttons', array( &$this, 'ubc_cart_add_field' ) );

		// Adds title to GF custom field
		add_filter( 'gform_field_type_title' , array( &$this, 'ubc_cart_title' ), 1 );

		// Save to DB as serialized array
		add_filter( 'gform_save_field_value', array( &$this, 'ubc_cart_get_value' ), 10, 4 );

		//Read from cart as serialized array and create entry
		//add_filter("gform_entry_field_value", array(&$this, 'ubc_cart_field_display'), 10, 4);

		//Fix labels in notification
		add_filter( 'gform_pre_submission', array( &$this, 'ubc_cart_field_email' ), 10, 3 );

		// Adds the input area to the external side - used for the editor and fe display.
		add_action( 'gform_field_input' , array( &$this, 'ubc_cart_field_input' ), 9, 5 );

		// Now we execute some javascript technicalitites for the field to load correctly
		add_action( 'gform_editor_js', array( &$this, 'ubc_cart_gform_editor_js' ) );

		// Set default values
		add_action( 'gform_editor_js_set_default_values', array( &$this, 'set_defaults' ) );

		// Add a custom setting to the cart advanced field
		add_action( 'gform_field_advanced_settings' , array( &$this, 'ubc_cart_settings' ) , 10, 2 );

		// Reset Cart after form submission
		add_action( 'gform_after_submission' , array( &$this, 'ubc_cart_reset' ) , 10, 2 );

		//Filter to add a new tooltip
		add_filter( 'gform_tooltips', array( &$this, 'ubc_cart_add_tooltips' ) );

		// Add a custom class to the field li
		add_action( 'gform_field_css_class', array( &$this, 'custom_class' ), 10, 3 );

		// Merge tag for subtotal and shipping
		add_filter( 'gform_pre_render', array( $this, 'maybe_replace_subtotal_merge_tag' ) );
		add_filter( 'gform_pre_validation', array( $this, 'maybe_replace_subtotal_merge_tag_submission' ) );
		add_filter( 'gform_admin_pre_render', array( $this, 'add_merge_tags' ) );
	}

	// -- Function Name : add_ajax_actions
	// -- Params : None
	// -- Purpose : All ajax actions.
	private function add_ajax_actions( ) {

		//Delete all items in cart and reset session
		add_action( 'wp_ajax_cart_delete_action', array( &$this, 'cart_delete_action_ajax_handler' ) );
		add_action( 'wp_ajax_nopriv_cart_delete_action', array( &$this, 'cart_delete_action_ajax_handler' ) );

		//Add item to Cart
		add_action( 'wp_ajax_cart_add_action', array( &$this, 'cart_add_action_ajax_handler' ) );
		add_action( 'wp_ajax_nopriv_cart_add_action', array( &$this, 'cart_add_action_ajax_handler' ) );

		//Display Cart contents
		add_action( 'wp_ajax_cart_show_action', array( &$this, 'cart_show_action_ajax_handler' ) );
		add_action( 'wp_ajax_nopriv_cart_show_action', array( &$this, 'cart_show_action_ajax_handler' ) );

		//Delete or reduce quantity of Cart item
		add_action( 'wp_ajax_cart_delete_item_action', array( &$this, 'cart_delete_item_action_ajax_handler' ) );
		add_action( 'wp_ajax_nopriv_cart_delete_item_action', array( &$this, 'cart_delete_item_action_ajax_handler' ) );

		//Adjust Cart Column order
		add_action( 'wp_ajax_cart_columns_action', array( &$this, 'cart_columns_action_ajax_handler' ) );

		//Settings switch GF form used as checkout
		add_action( 'wp_ajax_cart_switch_form_action', array( &$this, 'cart_switch_form_action_ajax_handler' ) );

		//Switch tax term used to filter items
		add_action( 'wp_ajax_cart_filter_action', array( &$this, 'cart_filter_action_ajax_handler' ) );

		//Toggle show cart in menu
		add_action( 'wp_ajax_cart_menu_action', array( &$this, 'cart_menu_action_ajax_handler' ) );

		//Save Cart Label
		add_action( 'wp_ajax_cart_savename_action', array( &$this, 'cart_savename_action_ajax_handler' ) );

		//Reset Cart Settings
		add_action( 'wp_ajax_cart_reset_settings_action', array( &$this, 'cart_reset_settings_action_ajax_handler' ) );
	}

	// -- Function Name : archive_page_script
	// -- Params : None
	// -- Purpose : Add JS (Frontend only if on product archive page).
	function archive_page_script( ) {
		if ( is_archive() && get_post_type( ) == 'ubc_product' ) {
			$url = plugins_url( '/assets/js/isotope.pkgd.min.js' , __FILE__ );
			wp_enqueue_script( 'archive_page_script', $url , array( 'jquery' ), '1.0' );
		}
	}

	// -- Function Name : cart_script
	// -- Params : None
	// -- Purpose : Add plugin JS (Frontend). All ajax actions nonced.
	function cart_script( $skip = false ) {
		$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
		if ( '' != $cartoptions['showcartmenu'] ) {
			$skip = true;
		}
		if ( ( ( is_single()||is_archive() ) && get_post_type( ) == 'ubc_product' ) || $skip ) {
			$url = plugins_url( '/assets/js/gform_cart.js' , __FILE__ );
			wp_enqueue_script( 'cart_script', $url , array( 'jquery' ), '1.0' );
			wp_localize_script('cart_script', 'cart_script_vars',
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'cart_add_action_nonce' => wp_create_nonce( 'cart_add_action' ),
					'cart_show_action_nonce' => wp_create_nonce( 'cart_show_action' ),
					'cart_delete_action_nonce' => wp_create_nonce( 'cart_delete_action' ),
					'cart_delete_item_action_nonce' => wp_create_nonce( 'cart_delete_item_action' ),
					'pluginsUrl' => plugins_url( ),
					'cartmenu' => $cartoptions['showcartmenu'],
					'cartitems' => $this->cart_calculate_items(),
					'formid' => $cartoptions['formid'],
					'maxitems' => $this->cart_calculate_maxitems(),
				)
			);
		}
	}

	// -- Function Name : cart_script
	// -- Params : None
	// -- Purpose : Add plugin JS (Admin). All ajax actions nonced.
	function admin_cart_script( ) {
		$screen = get_current_screen( );
		//var_dump($screen);
		if ( 'forms_page_ubc_cart_options' == $screen->base ) {
				$url = plugins_url( '/assets/js/gform_cart.js' , __FILE__ );
				wp_enqueue_script( 'jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.3/jquery-ui.min.js', array( 'jquery' ), false );
				wp_enqueue_script( 'cart_script', $url , array( 'jquery' ), '1.0' );
				wp_localize_script('cart_script', 'cart_script_vars',
					array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'cart_add_action_nonce' => wp_create_nonce( 'cart_add_action' ),
					'cart_show_action_nonce' => wp_create_nonce( 'cart_show_action' ),
					'cart_delete_action_nonce' => wp_create_nonce( 'cart_delete_action' ),
					'cart_columns_action_nonce' => wp_create_nonce( 'cart_columns_action' ),
					'cart_delete_item_action_nonce' => wp_create_nonce( 'cart_delete_item_action' ),
					'cart_switch_form_action_nonce' => wp_create_nonce( 'cart_switch_form_action' ),
					'cart_filter_action_nonce' => wp_create_nonce( 'cart_filter_action' ),
					'cart_menu_action_nonce' => wp_create_nonce( 'cart_menu_action' ),
					'cart_savename_action_nonce' => wp_create_nonce( 'cart_savename_action' ),
					'cart_reset_settings_action_nonce' => wp_create_nonce( 'cart_reset_settings_action' ),
					'pluginsUrl' => plugins_url(),
					)
				);
		}
	}

	// -- Function Name : cart_reset_settings_action_ajax_handler
	// -- Params : None
	// -- Purpose : Resets options on settings page to default.
	// --
	public function cart_reset_settings_action_ajax_handler( ) {
		if ( wp_verify_nonce( $_POST['cart_reset_settings_action_nonce'], 'cart_reset_settings_action' ) ) {
			//**********************************
			//*    RESET CART OPTIONS                *
			//**********************************;
			if ( $this->admin_settings->is_cartoption_valid( $this->admin_settings->default_options ) ) {
				update_option( 'ubc_cart_options' , $this->admin_settings->default_options );
			}
			die();
		}
	}

	// -- Function Name : cart_savename_action_ajax_handler
	// -- Params : None
	// -- Purpose : Saves Cart page title
	// --
	public function cart_savename_action_ajax_handler( ) {
		if ( wp_verify_nonce( $_POST['cart_savename_action_nonce'], 'cart_savename_action' ) ) {
			$cartname = sanitize_text_field( $_POST['js_data_for_php'] );
			//**********************************
			//*    CART OPTIONS                *
			//**********************************;
			$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
			$cartoptions['cartname'] = $cartname;
			if ( $this->admin_settings->is_cartoption_valid( $cartoptions ) ) {
				update_option( 'ubc_cart_options' , $cartoptions );
			}
			// Now change the page title
			$this->set_cart_page();
			die();
		}
	}

	// -- Function Name : cart_menu_action_ajax_handler
	// -- Params : None
	// -- Purpose : Toggles the cart to show in the menu
	// -- used to enable/disable Add to btn as well as filter in archive page
	public function cart_menu_action_ajax_handler( ) {
		if ( wp_verify_nonce( $_POST['cart_menu_action_nonce'], 'cart_menu_action' ) ) {
			$showmenu = $_POST['js_data_for_php'];
			$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
			if ( 1 == $showmenu ) {
				$locations = get_nav_menu_locations();
				$itemtitle = $cartoptions['cartname'];
				$itempid = $cartoptions['cartpid'];
				if ( ! isset( $locations['primary'] ) ) {
					//Check if 'Mainmenu' exists
					$menu_obj = wp_get_nav_menu_object( 'Mainmenu' );
					if ( ! $menu_obj ) {
						$menu_id = wp_create_nav_menu( 'Mainmenu' );
					} else {
						$menu_id = $menu_obj->term_id;
					}
						$locations = get_theme_mod( 'nav_menu_locations' );
						$locations['primary'] = $menu_id;
						set_theme_mod( 'nav_menu_locations', $locations );
				} else { //primary exists
					//Does primary not have a menu assigned? Create if needed and assign.
					if ( ! has_nav_menu( 'primary' ) ) {
						//Check if 'Mainmenu' exists
						$menu_obj = wp_get_nav_menu_object( 'Mainmenu' );
						if ( ! $menu_obj ) {
							$menu_id = wp_create_nav_menu( 'Mainmenu' );
						} else {
							$menu_id = $menu_obj->term_id;
						}
							$locations = get_theme_mod( 'nav_menu_locations' );
							$locations['primary'] = $menu_id;
						set_theme_mod( 'nav_menu_locations', $locations );
					}
				}
				$menu_id = $locations['primary'];
				$new_menu_obj = array();
				$cartmenu = wp_update_nav_menu_item($menu_id, 0,  array(
					'menu-item-title' => $itemtitle,
					'menu-item-object' => 'page',
					'menu-item-parent-id' => '',
					'menu-item-classes' => 'cart_menu_item',
					'menu-item-object-id' => $itempid,//get_page_by_path( $itemslug )->ID,
					'menu-item-type' => 'post_type',
					'menu-item-status' => 'publish',
				) );
			} else {
				wp_delete_post( $cartoptions['showcartmenu'] );
				$cartmenu = '';
			}
			$cartoptions['showcartmenu'] = $cartmenu;
			if ( $this->admin_settings->is_cartoption_valid( $cartoptions ) ) {
				update_option( 'ubc_cart_options', $cartoptions );
			}
			die();
		}
	}
	// -- Function Name : cart_filter_action_ajax_handler
	// -- Params : None
	// -- Purpose : Toggles the UBC Product Type taxonomy filter
	// -- used to enable/disable Add to btn as well as filter in archive page
	public function cart_filter_action_ajax_handler( ) {
		if ( wp_verify_nonce( $_POST['cart_filter_action_nonce'], 'cart_filter_action' ) ) {
			$filter = $_POST['js_data_for_php'];
			//**********************************
			//*    CART OPTIONS                *
			//**********************************;
			$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
			$cartoptions['filter'] = $filter;
			if ( $this->admin_settings->is_cartoption_valid( $cartoptions ) ) {
				update_option( 'ubc_cart_options' , $cartoptions );
			}
			//Set the option for filter here - you do NOT need to send this back!!!
			//$data_for_javascript = 'Filter is  - '.$filter;
			//echo $data_for_javascript;
			die();
		}
	}


	// -- Function Name : cart_switch_form_action_ajax_handler
	// -- Params : None
	// -- Purpose : Stores the Gravity Form id that is used on the check out page
	// -- Calls set_chkout_page() to switch the code on the page
	public function cart_switch_form_action_ajax_handler( ) {
		if ( wp_verify_nonce( $_POST['cart_switch_form_action_nonce'], 'cart_switch_form_action' ) ) {
			$formid = $_POST['js_data_for_php'];
			//**********************************
			//*    CART OPTIONS                *
			//**********************************;
			$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
			$cartoptions['formid'] = $formid;
			if ( $this->admin_settings->is_cartoption_valid( $cartoptions ) ) {
				update_option( 'ubc_cart_options', $cartoptions );
			}
			//Set the option for formid here
			$this->set_chkout_page( $formid );
			//$data_for_javascript = 'Switched Form almost - '.$formid;
			//echo $data_for_javascript;
			die();
		}
	}

	// -- Function Name : set_chkout_page
	// -- Params : None
	// -- Purpose : Gets Form id to be used and switches the shortcode on the page
	// -- If page doesn't exist creates a new page called 'Checkout'
	private function set_chkout_page( $formid ) {
		//**********************************
		//*    CART OPTIONS                *
		//**********************************
		//$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
		//$formid = $cartoptions['formid'];
		//Set shortcode string here
		$form_shortcode = '[gravityform id="'.$formid.'" title="true" description="true"]';
		$page = get_page_by_title( 'Checkout' );
		if ( is_null( $page ) ) {
			//create the page
			global $user_ID;
			$page->post_type    = 'page';
			$page->post_content = $form_shortcode;
			$page->post_parent  = 0;
			$page->post_author  = $user_ID;
			$page->post_status  = 'publish';
			$page->post_title   = 'Checkout';
			$page = apply_filters( 'ubc_cart_add_new_page', $page, 'teams' );
			$pageid = wp_insert_post( $page );
			if ( 0 != $pageid ) {
				$page->post_content = $form_shortcode;
				wp_update_post( $page );
			}
		} else {
			$page->post_content = $form_shortcode;
			wp_update_post( $page );
		}
	}

	// -- Function Name : set_cart_page
	// -- Params : None
	// -- Purpose : Sets cart page id and label to be used and adds the shortcode on the page
	// -- If page doesn't exist creates a new page with label from options
	private function set_cart_page( ) {
		$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
		$cartpid = $cartoptions['cartpid'];
		$cartname = $cartoptions['cartname'];
		$showcartmenu = $cartoptions['showcartmenu'];
		$cart_shortcode = '[show-cart]';
		$page = get_post( $cartpid );
		if ( is_null( $page ) || $page->post_status != 'publish' ) {
			$cart_post = array(
				  'post_title'    => $cartname,
				  'post_content'  => $cart_shortcode,
				  'post_status'   => 'publish',
				'post_type'   => 'page',
			);
			$pageid = wp_insert_post( $cart_post );
			if ( 0 != $pageid ) {
				$cartoptions['cartpid'] = $pageid;
				update_option( 'ubc_cart_options', $cartoptions );
			}
		} else {
			$page->post_title   = $cartname;
			$page->post_content = $cart_shortcode;
			wp_update_post( $page );
		}
		if ( $showcartmenu ) {
			$menu_id = $locations['primary'];
			$cartmenu = wp_update_nav_menu_item($menu_id, $showcartmenu,  array(
				'menu-item-title' => $cartname,
				'menu-item-object' => 'page',
				'menu-item-parent-id' => '',
				'menu-item-classes' => 'cart_menu_item',
				'menu-item-object-id' => $cartpid,//get_page_by_path( $itemslug )->ID,
				'menu-item-type' => 'post_type',
				'menu-item-status' => 'publish',
			) );
		}

	}

	// -- Function Name : cart_delete_item_action_ajax_handler
	// -- Params : None
	// -- Purpose : Either deletes the item from cart if quantity is 1 or reduces quantity by 1
	function cart_delete_item_action_ajax_handler( ) {
		$itemnum = absint( $_POST['js_data_for_php'] );
		$cart = $this->session->get( 'ubc-cart' );
		$jsaction = '';
		$quantcol = -100;
		$maxpostid = '';
		$theid = $cart[ $itemnum - 1 ]['prodid'];
		$prodpost = get_post( $theid );
		$post_meta_data = get_post_custom( $prodid );
		if ( $prodpost && $prodpost->post_type == 'ubc_product' ) {
			if ( array_key_exists( 'maxitems', $post_meta_data ) ) {
				$maxitems = $post_meta_data['maxitems'][0];
			} else {
				$maxitems = '100';
			}
		}
		//** Check if id exists in cart then **check for single
		if ( $cart[ $itemnum - 1 ]['prodquantity'] > 1 ) {
			$cart[ $itemnum - 1 ]['prodquantity'] --;
			if ( $cart[ $itemnum - 1 ]['prodquantity'] < $maxitems ) {
				$cart[ $itemnum - 1 ]['prodmaxed'] = 0;
				$maxpostid = $theid;
			}
			$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
			$colstr = $cartoptions['cartColumns'];
			if ( ! empty( $colstr ) ) {
				$colarr = explode( ',',$colstr );
				$quantcol = array_search( 'prodquantity',$colarr );
				$jsaction = 'reduce';
			}
		} else {
			//Remove itemnum from cart - remember index starts at 1
			//check for maxpostid quantity is 1
			//if ($maxitems == 1) {
				$maxpostid = $theid;
			//}
			array_splice( $cart,($itemnum - 1),1 );
			$jsaction = 'remove';
		}
		$this->session->set( 'ubc-cart',$cart );
		$data_for_javascript = $jsaction.'*'.$quantcol.'*'.$itemnum;
		echo wp_kses_post( $data_for_javascript.'*'.$this->create_table().'*'.$this->cart_calculate_items( ).'*'.$maxpostid );
		die();
	}

	// -- Function Name : cart_columns_action_ajax_handler
	// -- Params : None
	// -- Purpose : Sets columns according to drag & drop interface from settings page
	public function cart_columns_action_ajax_handler( ) {
		if ( wp_verify_nonce( $_POST['cart_columns_action_nonce'], 'cart_columns_action' ) ) {
			$columnstring = $_POST['js_data_for_php'];
			if ( 'reset' == $columnstring ) {
				$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
				//keep old cartpageid and menuitem in temp vars.
				$cartpageid = $cartoptions['cartpid'];
				$cartpagemenuid = $cartoptions['showcartmenu'];
				//reset to defaults
				$cartoptions = $this->admin_settings->default_options;
				//put back cartpage and menuitem
				$cartoptions['cartpid'] = $cartpageid;
				$cartoptions['showcartmenu'] = $cartpagemenuid;
				if ( $this->admin_settings->is_cartoption_valid( $cartoptions ) ) {
					update_option( 'ubc_cart_options', $cartoptions );
				}
				//At this point all OK except the default cart name needs to be set
				$this->set_cart_page( );
			} else {
				$columnarr = explode( '*',$columnstring );
				$colstr = $columnarr[0];
				$colstroff = $columnarr[1];
				//**********************************
				//*    CART OPTIONS                *
				//**********************************
				$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
				$cartoptions['cartColumns'] = $colstr;
				$cartoptions['cartColumnsoff'] = $colstroff;
				if ( $this->admin_settings->is_cartoption_valid( $cartoptions ) ) {
					update_option( 'ubc_cart_options', $cartoptions );
				}
			}
			$data_for_javascript = 'Changed Columns - '.$colstr.'*'.$colstroff;
			echo wp_kses_post( $this->create_table() );
			die();
		}
	}

	// -- Function Name : cart_add_action_ajax_handler
	// -- Params : None
	// -- Purpose : Adds an item to the cart or increases quantity by 1
	// -- Item can be any post (needs 'price' custom field for pricing) OR a taxonomy term for special cases (canlit)
	public function cart_add_action_ajax_handler() {
		if ( wp_verify_nonce( $_POST['cart_add_action_nonce'], 'cart_add_action' ) ) {
			$theid = $_POST['js_data_for_php'];
			$prodpost = get_post( $theid );
			//If valid post - note this can be any post conditional below traps for "ubc_product" post
			if ( $prodpost && $prodpost->post_type == 'ubc_product' ) {
				$prodtype = $prodpost->post_type;
				$prodid = $prodpost->ID;
				$prodtitle = $prodpost->post_title;
				$prodexcerpt = $prodpost->post_excerpt;
				$post_meta_data = get_post_custom( $prodid );
				//what if price field does not exist?
				if ( array_key_exists( 'price', $post_meta_data ) ) {
					$prodprice = $post_meta_data['price'][0];
				} else {
					$prodprice = '0.0';
				}
				if ( array_key_exists( 'shipping', $post_meta_data ) ) {
					$prodshipping = $post_meta_data['shipping'][0];
				} else {
					$prodshipping = '0.0';
				}
				if ( array_key_exists( 'shippingint', $post_meta_data ) ) {
					$prodshippingint = $post_meta_data['shippingint'][0];
				} else {
					$prodshippingint = '0.0';
				}
				if ( array_key_exists( 'maxitems', $post_meta_data ) ) {
					$maxitems = $post_meta_data['maxitems'][0];
				} else {
					$maxitems = '100';
				}
			} else {
				//check if taxonomy id for other special use cases
				//In this case, check for IssueM plugin and issue_price in tax meta
				if ( class_exists( 'IssueM' ) ) {
					$term = get_term_by( 'id',$theid,'issuem_issue' );
					if ( $term ) {
						$issue_meta = get_option( 'issuem_issue_'.$theid.'_meta' );
						$prodtype = 'ubc_product';
						$prodid = $theid;
						$prodtitle = $term->name;
						$prodexcerpt = $term->description;
						$prodprice = $issue_meta['issue_price'];
						$prodshipping = $issue_meta['issue_shipping'];
						$prodshippingint = $issue_meta['issue_shippingint'];
					}
				}
			}
			$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
			$cart = $this->session->get( 'ubc-cart' );
			if ( 'ubc_product' == $prodtype ) {
				//** Check if id exists in cart then **check for single
				$maxid = '';
				$cartkey = false;
				if ( $cart ) {
					foreach ( $cart as $rkey => $crow ) {
						foreach ( $crow as $ckey => $ccol ) {
							if ( $crow['prodid'] == $prodid ) {
								$cartkey = $rkey + 1;
								break;
							}
						}
					}
				}
				if ( $cartkey ) {
					if ( 1 != $cart[ $cartkey - 1 ]['prodmaxed'] ) {
						$cart[ $cartkey - 1 ]['prodquantity'] ++;
						if ( $cart[ $cartkey - 1 ]['prodquantity'] >= $maxitems ) {
							$cart[ $cartkey - 1 ]['prodmaxed'] = 1;
							$maxid = $cart[ $cartkey - 1 ]['prodid'];
						}
					}
				} else {
					$prodmaxed = 0;
					if ( $maxitems <= 1 ) {
							$prodmaxed = 1;
							$maxid = $prodid;
					}
					if ( $cartoptions['ubcepayment'] ) {
						$cart[] = array( 'prodid' => $prodid,'prodtitle' => $prodtitle,'prodexcerpt' => $prodexcerpt,'prodquantity' => '1','prodmaxed' => $prodmaxed,'prodprice' => $prodprice, 'prodshipping' => $prodshipping, 'prodshippingint' => $prodshippingint );
					} else {
						$cart[] = array( 'prodid' => $prodid,'prodtitle' => $prodtitle,'prodexcerpt' => $prodexcerpt,'prodquantity' => '1','prodmaxed' => $prodmaxed );
					}
				}
			} else {
				if ( $cartoptions['ubcepayment'] ) {
					$cart[] = array( 'prodid' => 43,'prodtitle' => 'Dummy from Debug','prodexcerpt' => 'The excerpt should display here.','prodquantity' => '1','prodprice' => '0.0','prodshipping' => '0.0','prodshippingint' => '0.0' );
				} else {
					$cart[] = array( 'prodid' => 43,'prodtitle' => 'Dummy from Debug','prodexcerpt' => 'The excerpt should display here.','prodquantity' => '1' );
				}
			}
			$this->session->set( 'ubc-cart',$cart );
			$data_for_javascript = 'Added to cart - '.serialize( $this->session );
			echo wp_kses_post( $this->create_table().'*'.$this->cart_calculate_items( ).'*'.$maxid );
			die();
		}
	}

	// -- Function Name : cart_delete_action_ajax_handler
	// -- Params : None
	// -- Purpose : Deletes items in cart and resets session
	public function cart_delete_action_ajax_handler( ) {
		$cart_items = $this->session->get( 'ubc-cart' );
		if ( $cart_items ) {
			$cart_items = array();
			$this->session->set( 'ubc-cart',$cart_items );
			$data_for_javascript = 'Cart deleted - '.serialize( $this->session );
		}
		echo wp_kses_post( $this->create_table() );
		die();
	}

	// -- Function Name : ubc_cart_reset
	// -- Params : GF
	// -- Purpose : Resets/flushes cart vars after form submission
	public function ubc_cart_reset( $entry, $form ) {
		$cart_items = $this->session->get( 'ubc-cart' );
		if ( $cart_items ) {
			$cart_items = array();
			$this->session->set( 'ubc-cart',$cart_items );
		}
	}


	// -- Function Name : cart_show_action_ajax_handler
	// -- Params : None
	// -- Purpose : Display cart - used on settings page (no shortcode)
	public function cart_show_action_ajax_handler( ) {
		$cart_items = $this->session->get( 'ubc-cart' );
		if ( $cart_items ) {
			$arrstring = '<table>';
			foreach ( $cart_items as $key => $val ) {
				$arrstring .= '<tr>';
				$arrstring .= "<td>$key = $val</td>";
				foreach ( $val as $wkey => $wval ) {
					$arrstring .= '<td>';
					$arrstring .= "$wkey = $wval\n";
					$arrstring .= '</td>';
				}
				$arrstring .= '</tr>';
			}
			$arrstring .= '</table>';
			echo wp_kses_post( $this->create_table() );
		} else {
			echo wp_kses_post( $this->create_table() );
		}
		die();
	}

	// -- Function Name : cart_calculate_total
	// -- Params : $formatted
	// -- Purpose : Calculates the subtotal of items in the cart (item*quant)
	// -- Returns formatted or not.
	public function cart_calculate_total( $formatted ) {
		if ( class_exists( 'UBC_CBM' ) ) {
			$cart_total = 0;
			$fmt = '%.2n';
			$cart = $this->session->get( 'ubc-cart' );
			if ( $cart ) {
				foreach ( $cart as $cartrow => $itemrow ) {
					$cart_total = $cart_total + ($itemrow['prodprice'] * $itemrow['prodquantity']);
				}
			}
			if ( $formatted ) {
				return '$'.money_format( $fmt,$cart_total );
			} else {
				return $cart_total;
			}
		} else {
			$this->admin_settings->remove_price_column();
		}
	}

	// -- Function Name : cart_calculate_items
	// -- Purpose : Calculates the number of items in the cart (item)
	public function cart_calculate_items(  ) {
		$cart_items = 0;
		$cart = $this->session->get( 'ubc-cart' );
		if ( $cart ) {
			foreach ( $cart as $cartrow => $itemrow ) {
				$cart_items = $cart_items + ($itemrow['prodquantity']);
			}
		}
		return $cart_items;
	}

	// -- Function Name : cart_calculate_maxitems
	// -- Purpose : Calculates the number of items in the cart (item)
	public function cart_calculate_maxitems(  ) {
		$cart_maxitems = array();
		$cart = $this->session->get( 'ubc-cart' );
		if ( $cart ) {
			foreach ( $cart as $cartrow => $itemrow ) {
				if ( 1 == $itemrow['prodmaxed'] ) {
					$cart_maxitems[] = $itemrow['prodid'];
				}
			}
			return implode( ',',$cart_maxitems );
		}
		return '';
	}

	// -- Function Name : create_table
	// -- Params : None
	// -- Purpose : Sets up the cart data in table format for display
	private function create_table( ) {
		$sessionid = $this->session->get_id();
		$reset_margin = '36';
		//**********************************
		//*    CART OPTIONS                *
		//**********************************
		$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
		$colstr = $cartoptions['cartColumns'];
		$colarr = array();
		if ( ! empty( $colstr ) ) {
			$colarr = explode( ',',$colstr );
		}
		$columns = array();
		$order = array();
		foreach ( $colarr as $key => $coltxt ) {
			$order_key = array_search( $coltxt, $this->admin_settings->field_order );
			$columns[ $key ]['text'] = $this->admin_settings->field_labels[ $order_key ];
			$order[] = $order_key;
		}
		$cart = $this->session->get( 'ubc-cart' );
		if ( $cart ) {
			$value = $cart;
			foreach (   $cart as $cartrow => $itemrow   ) {
				$colnum = 0;
				foreach ( $itemrow as $colkey => $colval ) {
							$value[ $cartrow ][ $colkey ] = $cart[ $cartrow ][ $this->admin_settings->field_order[ $order[ $colnum ] ] ];
					$colnum++;
				}
			}
		}
		$cart_display = '<div class="cartinput_container cartinput_list"><h3><i class="icon-shopping-cart"></i> Cart Details</h3>';
		$cart_display .= '<table class="cartfield_list"><colgroup>';
		for ( $colnum = 0; $colnum < count( $columns ); $colnum++ ) {
				$cart_display .= '<col id="cartfield_list_col_'.$columns[ $colnum ]['text'].'" class="cartfield_list_col" />';
		}
		//$cart_display .= '<col class="cartfield_list_col_icon" />';
		$cart_display .= '</colgroup>';
		$cart_display .= '<thead><tr>';
		foreach ( $columns as $column ) {
				$cart_display .= "<th class='".$column['text']."-header'>" . $column['text'] . '</th>';
		}

		$rownum = 1;
		$maxcolnum = count( $columns );
		if ( $value ) {
			$cart_display .= '<th class="cartfield_list_col_icon">&nbsp;&nbsp;</th></tr></thead><tbody>';
			foreach ( $value as $item ) {
				$cart_display .= "<tr class='cartfield_list_row'>";
				$colnum = 0;
				foreach ( $item as $key => $column ) {
					$cart_display .= "<td data-title='".$columns[ $colnum ]['text']."' class='".$colarr[ $colnum ]."-cell'><p>".$column.'</p></td>';
					$colnum++;
					if ( $colnum == $maxcolnum ) { break;}
				}
				$cart_display .= "<td class='cartfield_list_icons'>";
				$cart_display .= "<img  src='".plugins_url( 'gravityforms/images/remove.png' )."' title='Remove this row' class='delete_list_item' style='cursor:pointer;width:16px;height:16px;' onclick='cart_delete_item(this, {$rownum},false)' />";
				$cart_display .= '</td></tr>';
				$rownum++;
			}//foreach
		} //empty cart
		else {
			$reset_margin = '0';
			$cart_display .= '<tbody>';
			$cart_display .= '<tr><td colspan="'.count( $columns ).'" class="empty_cell">Your Cart is empty.</td></tr>';
		}
		$tagline = '';
		if ( class_exists( 'UBC_CBM' ) ) {
			$tagline = "<p style='font-size:10px;margin-top:-5px;'>Total = ".$this->cart_calculate_total( true ).' <a class="reset" style="margin-right:'.$reset_margin.'px;" onclick="deletecart()" >reset cart</a></p>';
		} else {
			$tagline = "<p style='font-size:10px;margin-top:-5px;'>Items = ".$this->cart_calculate_items( ).' <a class="reset" style="margin-right:'.$reset_margin.'px;" onclick="deletecart()" >reset cart</a></p>';
		}
		$cart_display .= '</tbody></table>'.$tagline.'</div>';
		$cart_display .= '<button class="cartbtn" onclick="window.location.href=\''.site_url( '/checkout/' ).'\'" class="checkout">Checkout <i class="icon-chevron-right"></i><i class="icon-chevron-right"></i></button>';
		global $allowedposttags;
		$allowedposttags['input'] = array( 'class' => array(),'readonly' => array(),'value' => array(), 'type' => array() );
		$allowedposttags['td'] = array( 'data-title' => array(), 'class' => array(), 'colspan' => array() );
		$allowedposttags['a'] = array( 'onclick' => array(), 'style' => array(), 'href' => array(), 'class' => array() );
		$allowedposttags['button'] = array( 'onclick' => array(),'style' => array(), 'class' => array() );
		$allowedposttags['img'] = array( 'onclick' => array(),'class' => array(),'style' => array(),'title' => array(),'src' => array() );
		return $cart_display;
	}

	// -- Function Name : setup_constants
	// -- Params : None
	// -- Purpose : Plugin constant (just paths for now)
	private function setup_constants() {
		if ( ! defined( 'UBCCART_PLUGIN_DIR' ) ) {
			define( 'UBCCART_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}
		define( 'UBCCART_PLUGIN_URI', plugins_url( '', __FILE__ ) );
	}

	// -- Function Name : includes
	// -- Params :
	// -- Purpose : All php to be included
	private function includes() {
		global $admin_settings;

		//Class that handles carts within sessions - uses WP Session Manager
		require_once UBCCART_PLUGIN_DIR . 'includes/class-ubccart-session.php';

		//Class that creates CPT and Taxonomy
		require_once UBCCART_PLUGIN_DIR . 'includes/class-ubccart-post-type.php';

		//Class that creates custom fields
		require_once UBCCART_PLUGIN_DIR . 'includes/class-ubccart-fields.php';

		//Class that sets up options and settings page
		require_once UBCCART_PLUGIN_DIR . 'includes/class-gfaddon.php';
	}

	// -- Function Name : createUBCProductsType
	// -- Params : None
	// -- Purpose : Creates
	// --                   1. new Custom Post Type 'ubc_product'
	// --                   2. new taxonomy 'ubc_product_type' with default term 'Available'
	// --                   3. new custom fields (just price field for now)
	// --                   4. loads js and css used with isotope
	private function createUBCProductsType() {
		// create a product custom post type
		$ubcproducts = new UBCCARTCPT( 'ubc_product' , array(
									'supports' => array( 'title', 'editor', 'thumbnail', 'comments', 'excerpt' ),
									'taxonomies' => array( 'post_tag' ),
									'has_archive' => true,
		) );
		// create a genre taxonomy
		$ubcproducts->register_taxonomy( 'ubc_product_type' );
		//Create taxterm here
		$ubcproducts->set_default_term( 'Available','ubc_product_type' );
		//Create Custom Field
		$this->cart_fields  = new UBCCARTCustomFields( 'ubc_product' );
		// define the columns to appear on the admin edit screen
		$ubcproducts->columns( array(
			'cb' => '<input type="checkbox" />',
			'title' => __( 'Title' ),
			'prod_description' => __( 'Product Excerpt' ),
			'ubc_product_type' => __( 'UBC Product Type' ),
			'featured-thumbnail' => __( 'Image' ),
			'date' => __( 'Date' ),
		) );
		//*create the shipping column here*
		$ubcproducts->columns['shipping'] = __( 'Shipping' );
		//populate the shipping column
		$ubcproducts->populate_column( 'shipping', function($column, $post) {
			$post_meta_data = get_post_custom( $post->ID );
			echo esc_html( '$'.$post_meta_data['shipping'][0] );
		});
		//*create the shipping column here*
		$ubcproducts->columns['shippingint'] = __( 'Shipping International' );
		//populate the shipping column
		$ubcproducts->populate_column( 'shippingint', function( $column, $post ) {
			$post_meta_data = get_post_custom( $post->ID );
			echo esc_html( '$'.$post_meta_data['shippingint'][0] );
		});
		//*create the price column here*
		$ubcproducts->columns['price'] = __( 'Price' );
		//populate the price column
		$ubcproducts->populate_column( 'price', function($column, $post) {
			$post_meta_data = get_post_custom( $post->ID );
			echo  esc_html( '$'.$post_meta_data['price'][0] );
		});
		//make price and shipping columns sortable
		$ubcproducts->sortable(array(
								'price' => array( 'price', true ),
								'shipping' => array( 'shipping', true ),
								'shippingint' => array( 'shippingint', true ),
		));
		$ubcproducts->populate_column( 'featured-thumbnail',function( $column, $post ) {
			echo the_post_thumbnail( array( 50, 50 ) );
		});
		$ubcproducts->populate_column( 'prod_description',function( $column, $post ) {
			the_excerpt();
		});
		// use "shopping cart" icon for post type
		$ubcproducts->menu_icon( 'dashicons-cart' );
		// register js for archive page
		wp_register_script( 'ubc-product-isotope', UBCCART_PLUGIN_URI . '/assets/isotope.pkgd.min.js' );
		//Load CSS for archive page
		wp_register_style( 'ubc-product-styles', UBCCART_PLUGIN_URI . '/assets/css/demos.css' );
		wp_register_style( 'ubc-product-styles', UBCCART_PLUGIN_URI . '/assets/css/layout.css' );
		wp_enqueue_style( 'ubc-product-styles' );
	}

	// -- Function Name : ubc_product_enqueue_scripts
	// -- Params : None
	// -- Purpose : Queues up isotope for display formatting on archive page.
	function ubc_product_enqueue_scripts() {
		wp_enqueue_script( 'ubc-product-isotope' );
	}


	// -- Function Name : ubc_product_template
	// -- Params : $template
	// -- Purpose : add plugin template file for archive page
	function ubc_product_template( $template ) {
		if ( is_post_type_archive( 'ubc_product' ) ) {
			$theme_files = array( '/assets/archive-ubc-product.php', 'archive-ubc-product.php' );
			$exists_in_theme = locate_template( $theme_files, false );
			if ( '' != $exists_in_theme ) {
				return $exists_in_theme;
			} else {
				return plugin_dir_path( __FILE__ ) . '/assets/archive-ubc-product.php';
			}
		}
		return $template;
	}

	// -- Function Name : ubc_cart_add_field
	// -- Params : $field_groups
	// -- Purpose : Adds the advanced field button to Gravity Forms editor
	function ubc_cart_add_field( $field_groups ) {
		foreach ( $field_groups as &$group ) {
			if ( 'advanced_fields' == $group['name'] ) {
				$group['fields'][] = array(
									'class' => 'button',
									'data-type' => 'list',
									'value' => __( 'UBC Cart', 'gravityforms' ),
									'onclick' => "StartAddField( 'cart' );",
				);
				break;
			}
		}
		return $field_groups;
	}

	// -- Function Name : ubc_cart_title
	// -- Params : $type
	// -- Purpose : Adds the name/type of field that shows in editor
	function ubc_cart_title( $type ) {
		if ( 'cart' == $type ) {
			return __( 'UBC Cart' , 'gravityforms' );
		}
	}

	// -- Function Name : ubc_cart_get_value
	// -- Params : $value, $lead, $field, $form
	// -- Purpose : Calls get_cart_data($field) to get the data values from cart
	function ubc_cart_get_value( $value, $lead, $field, $form ) {
		if ( 'cart' == $field['type'] ) {
			$value = $this->get_cart_data( $field );
		}
		return $value;
	}


	// -- Function Name : ubc_cart_gform_editor_js
	// -- Params :
	// -- Purpose : Sets up the settings for the ubc cart field in the Gravity Forms editor
	// -- It is just a list field with settings hidden
	function ubc_cart_gform_editor_js() {

		?>

		<script type='text/javascript'>
		jQuery(document).ready(function($) {
			fieldSettings["cart"] = " .cart_setting";
			//binding to the load field settings event to initialize the status box and hide unwanted fields
			$(document).bind("gform_load_field_settings", function(event, field, form){
				jQuery("#field_cart").val("<?php echo esc_html( 'SessionID::'.$this->session->get_id() ); ?>");
				$("#field_cart_value").val(field["cart"]);
				if (field.type=='cart') {
					$('.maxrows_setting').hide();
					$('.rules_setting').hide();
					$('.admin_label_setting').hide();
					$('.css_class_setting').hide();
					$('.visibility_setting').hide();
					$('.prepopulate_field_setting').hide();
					$('.conditional_logic_field_setting').hide();
					$('.add_icon_url_setting').hide();
					$('.delete_icon_url_setting').hide();
					$('.columns_setting').hide();
					//Added to hide messy columns
					$('.columns_setting #field_columns_enabled').hide();
					$('.columns_setting >label').hide();
					$('.columns_setting >label').css('visibilty','hidden');
					$('#gform_tab_1 .columns_setting .inline').hide();
					$('.columns_setting .field-choice-handle').hide();
					$('.columns_setting #gfield_settings_columns_container li .gf_insert_field_choice').hide();
					$('.columns_setting #gfield_settings_columns_container li .gf_delete_field_choice').hide();
				}
			}
			);
		}
		);
		</script>
		<?php
	}

	// -- Function Name : set_defaults
	// -- Params :
	// -- Purpose : Defaults when field is added to the form
	// -- Note if there are no fields to display on the Settings page, an error happens
	// -- By default the id field is displayed on activation to get around this.
	function set_defaults() {
		$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
		$colstr = $cartoptions['cartColumns'];
		if ( ! empty( $colstr ) ) {
			$colarr = explode( ',',$colstr );
			foreach ( $colarr as $key => $coltxt ) {
				$defaultStr .= 'new Choice( \''.$this->admin_settings->field_labels[ array_search( $coltxt, $this->admin_settings->field_order ) ].'\'),';
			}
			$defaultStr = rtrim( $defaultStr,',' );
		}
		$test = 'new Choice("Title"), new Choice("Quantity"), new Choice("Price(CAD)")';
		?>

		//this hook is fired in the middle of a switch statement,
		//so we need to add a case for our new field type
	case "cart" :
		field.label = "Shopping Cart";

		//setting the default field label
		field.inputType = "list";

		//impersonate a list field
		field.isRequired = true;
		field.enableColumns = true;
		field.enableChoiceValue = true;
		field.allowsPrepopulate = false;
		jQuery("#field_columns_enabled").prop("checked", true);
		field.choices = new Array(<?php echo wp_kses_post( $defaultStr ); ?>);
		break;
		<?php
	}

	// -- Function Name : ubc_cart_settings
	// -- Params : $position, $form_id
	// -- Purpose : Creates a custom setting field in the ubc cart field
	// -- In our case, we just use this to displaya session id (quick check if session manager is running)
	function ubc_cart_settings( $position, $form_id ) {
		if ( 50 == $position ) {
			?>

			<li class="cart_setting field_setting">

			<input type="text" id="field_cart" readonly/>
			<label for="field_cart" class="inline">
			<?php esc_attr_e( 'Session Status', 'gravityforms' );
			?>
			<?php gform_tooltip( 'form_field_cart' );
			?>
			</label>

			</li>
			<?php
		}
	}

	// -- Function Name : ubc_cart_add_tooltips
	// -- Params : $tooltips
	// -- Purpose : Tooltip on the custom ubc cart settings field
	function ubc_cart_add_tooltips( $tooltips ) {
		$tooltips['form_field_cart'] = '<h6>Session Status</h6>If you do not see a valid session id here, there is something amiss';
		return $tooltips;
	}

	// -- Function Name : ubc_cart_gform_enqueue_scripts
	// -- Params : $form, $ajax
	// -- Purpose : Add Cart JS
	function ubc_cart_gform_enqueue_scripts( $form, $ajax ) {
		// cycle through fields to see if cart is being used
		foreach ( $form['fields'] as $field ) {
			if ( ( 'cart' == $field['type'] ) ) {
				$url = plugins_url( 'assets/js/gform_cart.js' , __FILE__ );
				wp_enqueue_script( 'gform_cart_script', $url , array( 'jquery' ), '1.0' );
				break;
			}
		}
	}

	// -- Function Name : custom_class
	// -- Params : $classes, $field, $form
	// -- Purpose : Adds a custom class to the cart field
	function custom_class( $classes, $field, $form ) {
		if ( 'cart' == $field['type'] ) {
			$classes .= ' gform_cart';
		}
		return $classes;
	}

	// -- Function Name : ubc_cart_field_input
	// -- Params : $input, $field, $value, $lead_id, $form_id
	// -- Purpose : Used to display the cart BOTH internally (in the editor) as well
	// -- as in the front end - uses GF is_form_editor() function
	function ubc_cart_field_input ( $input, $field, $value, $lead_id, $form_id ) {
		if ( 'cart' == $field['type'] ) {
			if ( ! GFCommon::is_form_editor() ) {
				if ( ! class_exists( 'UBC_CBM' ) ) {
					$this->admin_settings->remove_price_column();
				}
				$value = $this->get_cart_data( $field );
			}
			if ( ! empty( $value ) ) {
				$empty_cart = false;
				$value = maybe_unserialize( $value );
			}
			if ( ! is_array( $value ) ) {
				$value = array( array() );
				$empty_cart = true;
			}
			$has_columns = is_array( rgar( $field, 'choices' ) );
			//**********************************
			//*    CART OPTIONS                *
			//**********************************
			$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
			$cartpid = $cartoptions['cartpid'];
			$colstr = $cartoptions['cartColumns'];
			$colarr = array();
			if ( ! empty( $colstr ) ) {
				$colarr = explode( ',',$colstr );
			}
			$columns = array();
			if ( ! GFCommon::is_form_editor() ) {
				$edit_cart_link = "<a href='".get_permalink( $cartpid )."' style='float:right;margin:0.625em 10% 0.5em;line-height:1.3;font-weight:700;'>Edit Cart</a>";
			} else { $edit_cart_link = ''; }
			foreach ( $colarr as $key => $coltxt ) {
				$columns[ $key ]['text'] = $coltxt;//$this->admin_settings->field_labels[$order_key];
			}
			$field->choices = $columns; //Filled Field choices!!
			$has_columns = true;
			$field->isRequired = true;
			$field->allowsPrepopulate = false;
			$columns = $has_columns ? rgar( $field, 'choices' ) : array( array() );
			$shim_style = is_rtl() ? 'position:absolute;left:999em;' : 'position:absolute;left:-999em;';
			$label_target_shim = sprintf( '<input type=\'text\' id=\'input_%1$s_%2$s_shim\' style=\'%3$s\' onfocus=\'jQuery( "#field_%1$s_%2$s table tr td:first-child input" ).focus();\' />', $form_id, $field['id'], $shim_style );
			$list = $edit_cart_link."<div class='ginput_container ginput_list'>" .$label_target_shim .'<table class="gfield_list">';
			$class_attr = '';
			if ( $has_columns ) {
				$list .= '<colgroup>';
				for ( $colnum = 1; $colnum <= count( $columns ) + 1; $colnum++ ) {
					$odd_even = ( $colnum % 2 ) == 0 ? 'even' : 'odd';
					$list .= sprintf( "<col id='gfield_list_%d_col_%d' class='".$columns[ $colnum - 1 ]['text']." gfield_list_col_%s' />", $field['id'], $colnum, $odd_even );
				}
				$list .= '</colgroup>';
				$list .= '<thead><tr>';
				foreach ( $columns as $column ) {
					$coltxt = esc_html( $column['text'] );
					$order_key = array_search( $coltxt, $this->admin_settings->field_order );
					$list .= '<th>' . $this->admin_settings->field_labels[ $order_key ] . '</th>';
				}
				$list .= '<th>&nbsp;</th></tr></thead>';
			} else {
				//$list .=
					//'<colgroup>' .
						//"<col id='gfield_list_{$field['id']}_col1' class='gfield_list_col_odd' />" .
						//"<col id='gfield_list_{$field['id']}_col2' class='gfield_list_col_even' />" .
					//'</colgroup>';
				$list = '<colgroup><col id="gfield_list_{$field["id"]}_col1" class="gfield_list_col_odd" /><col id="gfield_list_{$field["id"]}_col2" class="gfield_list_col_even" /></colgroup>';
			}
			$delete_display = count( $value ) == 1 ? 'visibility:hidden;' : '';
			$maxRow = intval( rgar( $field, 'maxRows' ) );
			$disabled_icon_class = ! empty( $maxRow ) && count( $value ) >= $maxRow ? 'gfield_icon_disabled' : '';
			$list .= '<tbody>';
			$rownum = 1;
			foreach ( $value as $item ) {
				$odd_even = ( $rownum % 2 ) == 0 ? 'even' : 'odd';
				$list .= "<tr class='gfield_list_row_{$odd_even}'>";
				$colnum = 1;
				if ( ( ! $empty_cart ) || ( GFCommon::is_form_editor() ) ) {
					foreach ( $columns as $column ) {
						//getting value. taking into account columns being added/removed from form meta
						if ( is_array( $item ) ) {
							if ( $has_columns ) {
								$val = rgar( $item, $column['text'] );
							} else {
								$vals = array_values( $item );
								$val = rgar( $vals, 0 );
							}
						} else {
							$val = 1 == $colnum ? $item : '';
						}
						$list .= "<td class='gfield_list_cell gfield_list_{$field["id"]}_cell{$colnum} ".$column['text']."'>" .$this->cart_get_list_input( $field, $has_columns, $column, $val, $form_id ) . '</td>';
						$colnum++;
					}
				} //empty cart
				else {
					$list .= '<td colspan='.count( $columns )." class='gfield_list_cell gfield_list_{$field["id"]}_cell{$colnum}'>" .'<input type="text" name="input_{$field["id"]}[]" value="Empty Cart"  readonly/></td';
				}
				$add_icon = ! rgempty( 'addIconUrl', $field ) ? $field['addIconUrl'] : GFCommon::get_base_url() . '/images/add.png';
				$delete_icon = ! rgempty( 'deleteIconUrl', $field ) ? $field['deleteIconUrl'] : GFCommon::get_base_url() . '/images/remove.png';
				$on_click = IS_ADMIN && RG_CURRENT_VIEW != 'entry' ? '' : "onclick='gformAddListItem(this, {$maxRow})'";
				//$list .="<td class='gfield_list_icons'>";
				//$list .="   <img src='{$delete_icon}'  title='" . __("Remove this row", "gravityforms") . "' alt='" . __("Remove this row", "gravityforms") . "' class='delete_list_item' style='cursor:pointer; {$delete_display} visibility:visible;' onclick='cart_delete_item(this, {$rownum}, true)' />";
				//$list .="</td>";
				//$list .= "</tr>";
				if ( ! empty( $maxRow ) && $rownum >= $maxRow ) {
					break;
				}
				$rownum++;
			}
			$list .= '</tbody></table></div>';
		}//if field type
		return $list;
	}

	// -- Function Name : ubc_cart_field_email
	// -- Params : $form
	// -- Purpose : Try getting the headers showing on the columns
	function ubc_cart_field_email( $form ) {
		foreach ( $form['fields'] as &$field ) {
			if ( 'cart' == $field['type'] ) {
				foreach ( $field->choices as $key => $choice ) {
					$old_label = $choice[ $key ];
					$order_key = array_search( $old_label, $this->admin_settings->field_order );
					$new_label = $this->admin_settings->field_labels[ $order_key ];
					$choice[ $key ] = $new_label;
				}
			}
		}
		return $form;
	}

	private function cart_get_list_input($field, $has_columns, $column, $value, $form_id) {

		$tabindex = GFCommon::get_tabindex();

		$column_index = 1;
		if ( $has_columns && is_array( rgar( $field, 'choices' ) ) ) {
			foreach ( $field['choices'] as $choice ) {
				if ( $choice['text'] == $column['text'] ) {
					break;
				}
				$column_index++;
			}
		}
		$input_info = array( 'type' => 'text' );
		$input_info = apply_filters( "gform_column_input_{$form_id}_{$field["id"]}_{$column_index}", apply_filters( 'gform_column_input', $input_info, $field, rgar( $column, 'text' ), $value, $form_id ), $field, rgar( $column, 'text' ), $value, $form_id );

		switch ( $input_info['type'] ) {
			case 'select' :
				$input = "<select name='input_{$field["id"]}[]' {$tabindex} >";
				if ( ! is_array( $input_info['choices'] ) ) {
					$input_info['choices'] = explode( ',', $input_info['choices'] );
				}
				foreach ( $input_info['choices'] as $choice ) {
					if ( is_array( $choice ) ) {
						$choice_value = rgar( $choice,'value' );
						$choice_text = rgar( $choice,'text' );
						$choice_selected = rgar( $choice,'isSelected' );
					} else {
						$choice_value = $choice;
						$choice_text = $choice;
						$choice_selected = false;
					}
					$is_selected = empty( $value ) ? $choice_selected : $choice_value == $value;
					$selected = $is_selected ? "selected='selected'" : '';
					$input .= "<option value='" . esc_attr( $choice_value ) . "' {$selected}>" . esc_html( $choice_text ) . '</option>';
				}
				$input .= '</select>';

			break;

			default :
				$input = "<input type='text' name='input_{$field["id"]}[]' value='" . esc_attr( $value ) . "' {$tabindex} readonly/>";
			break;
		}

		return apply_filters( "gform_column_input_content_{$form_id}_{$field["id"]}_{$column_index}", apply_filters( 'gform_column_input_content', $input, $input_info, $field, rgar( $column, 'text' ), $value, $form_id ), $input_info, $field, rgar( $column, 'text' ), $value, $form_id );
	}

	// -- Function Name : get_cart_data
	// -- Params : $field
	// -- Purpose : returns cart data in serialized array
	private function get_cart_data( $field ) {
		//**********************************
		//*    CART OPTIONS                *
		//**********************************
		$cartoptions = get_option( 'ubc_cart_options',$this->admin_settings->default_options );
		$colstr = $cartoptions['cartColumns'];
		$colarr = array();
		if ( ! empty( $colstr ) ) {
			$colarr = explode( ',',$colstr );
		}
		$columns = array();
		$order = array();
		foreach ( $colarr as $key => $coltxt ) {
					$order_key = array_search( $coltxt, $this->admin_settings->field_order );
					$columns[ $key ]['text'] = $coltxt;
					$order[] = $coltxt;
		}
		$field->choices = $columns;
		//Filled field choices here!!
		$choices = $field->choices;
		//Get the cart here and fill value!!!!
		$value = array();
		$cart_items = $this->session->get( 'ubc-cart' );
		if ( $cart_items ) {
			foreach ( $cart_items as $key => $cart_item ) {
				$choicesarr = array();
				$colnum = 0;
				foreach ( $cart_item as $colkey => $colval ) {
					$choicesarr[ $choices[ $colnum ]['text'] ] = $cart_items[ $key ][ $order[ $colnum ] ];
					$colnum ++;
				}
				array_push( $value,$choicesarr );
			}
		}
		if ( ! empty( $value ) ) {
			$value = serialize( $value );
		} else {
			$value = '';
		}
		return $value;
	}

	// -- Function Name : instance
	// -- Params :
	// -- Purpose : Instantiates class on global variable
	static function instance() {
		global $UBC_CART;
		// Only instantiate the Class if it hasn't been already
		if ( ! isset( $UBC_CART ) ) {
			$UBC_CART = new UBC_CART();
		}
	}

	// -- Function Name : maybe_replace_subtotal_merge_tag
	// -- Params : $form, $filter_tags = false
	// -- Purpose : If form has the merge tag, then replace with subtotal
	// -- Calls get_subtotal_merge_tag_string to do this.
	function maybe_replace_subtotal_merge_tag( $form, $filter_tags = false ) {
		$tag_array = array();
		array_push( $tag_array,self::$merge_tag );
		array_push( $tag_array,self::$merge_tag_shipping );
		array_push( $tag_array,self::$merge_tag_shippingint );
		foreach ( $form['fields'] as &$field ) {
			$subtotal_merge_tags = array();
			if ( current_filter() == 'gform_pre_render' && rgar( $field, 'origCalculationFormula' ) ) {
				$field['calculationFormula'] = $field['origCalculationFormula'];
			}
			if ( ! self::has_subtotal_merge_tag( $field ) ) {
				continue;
			}
			//$subtotal_merge_tags = self::get_subtotal_merge_tag_string($form, $field, $filter_tags );
			array_push( $subtotal_merge_tags,self::get_subtotal_merge_tag_string( $form, $field, $filter_tags ) );
			array_push( $subtotal_merge_tags,self::get_shipping_merge_tag_string( $form, $field, $filter_tags ) );
			array_push( $subtotal_merge_tags,self::get_shippingint_merge_tag_string( $form, $field, $filter_tags ) );
			$field['origCalculationFormula'] = $field['calculationFormula'];
			//$field['calculationFormula'] = str_replace(self::$merge_tag, $subtotal_merge_tags, $field['calculationFormula'] );
			$field['calculationFormula'] = str_replace( $tag_array, $subtotal_merge_tags, $field['calculationFormula'] );
		}
		return $form;
	}

	// -- Function Name : maybe_replace_subtotal_merge_tag_submission
	// -- Params : $form
	// -- Purpose :
	function maybe_replace_subtotal_merge_tag_submission( $form ) {
		return $this->maybe_replace_subtotal_merge_tag( $form, true );
	}

	// -- Function Name : get_subtotal_merge_tag_string
	// -- Params : $form, $current_field, $filter_tags = false
	// -- Purpose : Returns a subtotal from cart
	function get_subtotal_merge_tag_string( $form, $current_field, $filter_tags = false ) {
		$cart_total = 0;
		if ( class_exists( 'UBC_CBM' ) ) {
			$cart = $this->session->get( 'ubc-cart' );
			if ( $cart ) {
				foreach ( $cart as $cartrow => $itemrow ) {
					$cart_total = $cart_total + ( $itemrow['prodprice'] * $itemrow['prodquantity'] );
				}
			}
		}
		return $cart_total;
	}

	// -- Function Name : get_shipping_merge_tag_string
	// -- Params : $form, $current_field, $filter_tags = false
	// -- Purpose : Returns a shipping subtotal from cart
	function get_shipping_merge_tag_string( $form, $current_field, $filter_tags = false ) {
		$cart_total = 0;
		if ( class_exists( 'UBC_CBM' ) ) {
			$cart = $this->session->get( 'ubc-cart' );
			if ( $cart ) {
				foreach ( $cart as $cartrow => $itemrow ) {
					$cart_total = $cart_total + ( $itemrow['prodshipping'] * $itemrow['prodquantity'] );
				}
			}
		}
		return $cart_total;
	}

	// -- Function Name : get_shippingint_merge_tag_string
	// -- Params : $form, $current_field, $filter_tags = false
	// -- Purpose : Returns a shippingint subtotal from cart
	function get_shippingint_merge_tag_string( $form, $current_field, $filter_tags = false ) {
		$cart_total = 0;
		if ( class_exists( 'UBC_CBM' ) ) {
			$cart = $this->session->get( 'ubc-cart' );
			if ( $cart ) {
				foreach ( $cart as $cartrow => $itemrow ) {
					$cart_total = $cart_total + ( $itemrow['prodshippingint'] * $itemrow['prodquantity'] );
				}
			}
		}
		return $cart_total;
	}

	// -- Function Name : add_merge_tags
	// -- Params : $form
	// -- Purpose : Adds the merge tag to calculation fields drop down
	function add_merge_tags( $form ) {

		$label = __( 'UBC Cart Subtotal', 'gravityforms' );
		$label_shipping = __( 'UBC Cart Shipping', 'gravityforms' );
		$label_shippingint = __( 'UBC Cart Shipping International', 'gravityforms' );
		?>
				<script type="text/javascript">

				// for the future (not yet supported for calc field)
				gform.addFilter("gform_merge_tags", "ubccart_add_merge_tags");
				function ubccart_add_merge_tags(mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option )
				{
					mergeTags["pricing"].tags.push({
					tag: '<?php echo esc_html( self::$merge_tag ); ?>', label: '<?php echo esc_html( $label ); ?>' }
					);
					mergeTags["pricing"].tags.push({
					tag: '<?php echo esc_html( self::$merge_tag_shipping ); ?>', label_shipping: '<?php echo esc_html( $label_shipping ); ?>' }
					);
					mergeTags["pricing"].tags.push({
					tag: '<?php echo esc_html( self::$merge_tag_shippingint ); ?>', label_shippingint: '<?php echo esc_html( $label_shippingint ); ?>' }
					);
					return mergeTags;
				}

				// hacky, but only temporary
				jQuery(document).ready(function($){

					var calcMergeTagSelect = $('#field_calculation_formula_variable_select');
					calcMergeTagSelect.find('optgroup').eq(0).append('<option value="<?php echo esc_html( self::$merge_tag ); ?>"><?php echo esc_html( $label ); ?></option><option value="<?php echo esc_html( self::$merge_tag_shipping ); ?>"><?php echo esc_html( $label_shipping ); ?></option><option value="<?php echo esc_html( self::$merge_tag_shippingint ); ?>"><?php echo esc_html( $label_shippingint ); ?></option>' );

				}
				);

				</script>

		<?php

		return $form;
	}


	// -- Function Name : has_subtotal_merge_tag
	// -- Params : $field
	// -- Purpose : If field is using the merge tag
	static function has_subtotal_merge_tag( $field ) {
		// check if form is passed
		if ( isset( $field['fields'] ) ) {
			$form = $field;
			foreach ( $form['fields'] as $field ) {
				if ( self::has_subtotal_merge_tag( $field ) ) {
					return true;
				}
			}
		} else {
			if ( isset( $field['calculationFormula'] ) && strpos( $field['calculationFormula'], self::$merge_tag ) !== false || isset( $field['calculationFormula'] ) && strpos( $field['calculationFormula'], self::$merge_tag_shipping ) !== false  || isset( $field['calculationFormula'] ) && strpos( $field['calculationFormula'], self::$merge_tag_shippingint ) !== false ) {
						return true;
			}
		}
		return false;
	}
}

function _ubc_cart() {
	return UBC_CART::instance();
}

if ( ! isset( $UBC_CART ) ) {
	UBC_CART::instance();
}
