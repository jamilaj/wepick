<?php
/**
 * Functions for retrieving dynamic data values.
 *
 * @author     ThemeFusion
 * @copyright  (c) Copyright by ThemeFusion
 * @link       https://theme-fusion.com
 * @package    Fusion Builder
 * @subpackage Core
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * A wrapper for static methods.
 */
class Fusion_Dynamic_Data_Callbacks {

	/**
	 * Class constructor.
	 *
	 * @since 2.1
	 * @access public
	 */
	public function __construct() {
		add_action( 'wp_ajax_ajax_acf_get_field', [ $this, 'ajax_acf_get_field' ] );
		add_action( 'wp_ajax_ajax_get_post_date', [ $this, 'ajax_get_post_date' ] );

		add_action( 'wp_ajax_ajax_dynamic_data_default_callback', [ $this, 'ajax_dynamic_data_default_callback' ] );
	}

	/**
	 * Get ACF field value.
	 *
	 * @since 2.1
	 */
	public function ajax_acf_get_field() {
		check_ajax_referer( 'fusion_load_nonce', 'fusion_load_nonce' );
		$return_data = [];

		if ( isset( $_POST['field'] ) && isset( $_POST['post_id'] ) && function_exists( 'get_field' ) ) {
			$field_value = get_field( wp_unslash( $_POST['field'] ), wp_unslash( $_POST['post_id'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( ! isset( $_POST['image'] ) || ! $_POST['image'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$return_data['content'] = $field_value;
			} elseif ( is_array( $field_value ) && isset( $field_value['url'] ) ) {
				$return_data['content'] = $field_value['url'];
			} elseif ( is_integer( $field_value ) ) {
				$return_data['content'] = wp_get_attachment_url( $field_value );
			} elseif ( is_string( $field_value ) ) {
				$return_data['content'] = $field_value;
			}
		}

		echo wp_json_encode( $return_data );
		wp_die();
	}

	/**
	 * Runs the defined callback.
	 *
	 * @access public
	 * @since 2.1
	 * @return void
	 */
	public function ajax_dynamic_data_default_callback() {
		check_ajax_referer( 'fusion_load_nonce', 'fusion_load_nonce' );
		$return_data = [];

		$callback_function = ( isset( $_GET['callback'] ) ) ? sanitize_text_field( wp_unslash( $_GET['callback'] ) ) : false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( $callback_function && is_callable( 'Fusion_Dynamic_Data_Callbacks::' . $callback_function ) && isset( $_GET['post_id'] ) && isset( $_GET['args'] ) ) {
			$return_data['content'] = call_user_func_array( 'Fusion_Dynamic_Data_Callbacks::' . $callback_function, [ wp_unslash( $_GET['args'] ), wp_unslash( $_GET['post_id'] ) ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}

		echo wp_json_encode( $return_data );
		wp_die();

	}

	/**
	 * Shortcode.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param arrsy $args Arguments.
	 * @return string
	 */
	public static function dynamic_shortcode( $args ) {
		(string) $shortcode_string = isset( $args['shortcode'] ) ? $args['shortcode'] : '';
		return do_shortcode( $shortcode_string );
	}

	/**
	 * Featured image.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param arrsy $args Arguments.
	 * @return string
	 */
	public static function post_featured_image( $args ) {
		return get_the_post_thumbnail_url();
	}

	/**
	 * Post title.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args Arguments.
	 * @return string
	 */
	public static function fusion_get_title( $args ) {
		return get_the_title();
	}

	/**
	 * Post ID.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args Arguments.
	 * @return int
	 */
	public static function fusion_get_post_id( $args ) {
		return get_the_ID();
	}

	/**
	 * Post excerpt.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args Arguments.
	 * @return int
	 */
	public static function fusion_get_post_excerpt( $args ) {
		return get_the_excerpt();
	}

	/**
	 * Post date.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args    Arguments.
	 * @param int   $post_id The post-ID.
	 * @return string
	 */
	public static function fusion_get_post_date( $args, $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$format = isset( $args['format'] ) ? $args['format'] : '';
		return 'modified' === $args['type'] ? get_the_modified_date( $format, $post_id ) : get_the_date( $format, $post_id );
	}

	/**
	 * Post time.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args    Arguments.
	 * @param int   $post_id The post-ID.
	 * @return string
	 */
	public static function fusion_get_post_time( $args, $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$format = isset( $args['format'] ) && '' !== $args['format'] ? $args['format'] : 'U';
		return get_post_time( $format, false, $post_id );
	}

	/**
	 * Post terms.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args    Arguments.
	 * @param int   $post_id The post-ID.
	 * @return string
	 */
	public static function fusion_get_post_terms( $args, $post_id = 0 ) {
		$output = '';
		if ( ! isset( $args['type'] ) ) {
			return $output;
		}

		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$terms = wp_get_object_terms( $post_id, $args['type'] );
		if ( ! is_wp_error( $terms ) ) {
			$separator   = isset( $args['separator'] ) ? $args['separator'] : '';
			$should_link = isset( $args['link'] ) && 'no' === $args['link'] ? false : true;

			foreach ( $terms as $term ) {
				if ( $should_link ) {
					$output .= '<a href="' . get_term_link( $term->slug, $args['type'] ) . '" title="' . esc_attr( $term->name ) . '">';
				}

				$output .= esc_html( $term->name );

				if ( $should_link ) {
					$output .= '</a>';
				}

				$output .= $separator;
			}

			return '' !== $separator ? rtrim( $output, $separator ) : $output;
		}

		return $output;
	}

	/**
	 * Post meta.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args Arguments.
	 * @return string
	 */
	public static function fusion_get_post_custom_field( $args ) {
		$post_meta = get_post_meta( get_the_ID(), $args['key'], true );

		if ( ! is_array( $post_meta ) ) {
			return $post_meta;
		}
	}

	/**
	 * Site title.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args Arguments.
	 * @return string
	 */
	public static function fusion_get_site_title( $args ) {
		return get_bloginfo( 'name' );
	}

	/**
	 * Site tagline.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args Arguments.
	 * @return string
	 */
	public static function fusion_get_site_tagline( $args ) {
		return get_bloginfo( 'description' );
	}

	/**
	 * ACF text field.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args Arguments.
	 * @return string
	 */
	public static function acf_get_field( $args ) {
		if ( ! isset( $args['field'] ) ) {
			return '';
		}
		return get_field( $args['field'] );
	}

	/**
	 * ACF get image field.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args Arguments.
	 * @return string
	 */
	public static function acf_get_image_field( $args ) {
		if ( ! isset( $args['field'] ) ) {
			return '';
		}
		$image_data = get_field( $args['field'] );

		if ( is_array( $image_data ) && isset( $image_data['url'] ) ) {
			return $image_data['url'];
		} elseif ( is_integer( $image_data ) ) {
			return wp_get_attachment_url( $image_data );
		} elseif ( is_string( $image_data ) ) {
			return $image_data;
		}

		return '';
	}

	/**
	 * Get product price.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args    Arguments.
	 * @param int   $post_id The post-ID.
	 * @return string
	 */
	public static function woo_get_price( $args, $post_id = 0 ) {

		if ( ! isset( $args['format'] ) ) {
			$args['format'] = '';
		}

		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$_product = wc_get_product( $post_id );
		$price    = '';

		if ( '' === $args['format'] ) {
			$price = $_product->get_price_html();
		}

		if ( 'original' === $args['format'] ) {
			$price = wc_price( wc_get_price_to_display( $_product, [ 'price' => $_product->get_regular_price() ] ) );
		}

		if ( 'sale' === $args['format'] ) {
			$price = wc_price( wc_get_price_to_display( $_product, [ 'price' => $_product->get_sale_price() ] ) );
		}

		return $price;
	}

	/**
	 * Get product SKU.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args    Arguments.
	 * @param int   $post_id The post-ID.
	 * @return string
	 */
	public static function woo_get_sku( $args, $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$_product = wc_get_product( $post_id );

		return $_product->get_sku();
	}

	/**
	 * Get product stock.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args    Arguments.
	 * @param int   $post_id The post-ID.
	 * @return string
	 */
	public static function woo_get_stock( $args, $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$_product = wc_get_product( $post_id );
		$stock    = $_product->get_stock_quantity();

		return null !== $stock ? $stock : '';
	}

	/**
	 * Get product rating.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param array $args    Arguments.
	 * @param int   $post_id The post-ID.
	 * @return string
	 */
	public static function woo_get_rating( $args, $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$_product = wc_get_product( $post_id );

		if ( '' === $args['format'] ) {
			$output = $_product->get_average_rating();
		}

		if ( 'rating' === $args['format'] ) {
			$output = $_product->get_rating_count();
		}

		if ( 'review' === $args['format'] ) {
			$output = $_product->get_review_count();
		}

		return $output;
	}
}
