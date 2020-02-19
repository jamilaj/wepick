<?php
/**
 * Fusion Dynamic Data class.
 *
 * @package Fusion-Builder
 * @since 2.1
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Fusion Dynamic Data class.
 *
 * @since 2.1
 */
class Fusion_Dynamic_Data {

	/**
	 * Array of dynamic param definitions.
	 *
	 * @access private
	 * @since 2.1
	 * @var array
	 */
	private $params = [];

	/**
	 * Array of dynamic param values and arguments.
	 *
	 * @access private
	 * @since 2.1
	 * @var array
	 */
	private $values = [];

	/**
	 * Array of text fields.
	 *
	 * @access private
	 * @since 2.1
	 * @var array
	 */
	private $text_fields = [ 'textfield', 'textarea', 'tinymce' ];

	/**
	 * Array of image fields.
	 *
	 * @access private
	 * @since 2.1
	 * @var array
	 */
	private $image_fields = [ 'upload' ];

	/**
	 * Class constructor.
	 *
	 * @since 2.1
	 * @access public
	 */
	public function __construct() {
		if ( ! apply_filters( 'fusion_load_dynamic_data', true ) ) {
			return;
		}
		add_filter( 'fusion_pre_shortcode_atts', [ $this, 'filter_dynamic_args' ], 10, 4 );
		add_filter( 'fusion_shortcode_content', [ $this, 'filter_dynamic_content' ], 10, 4 );
		add_filter( 'fusion_app_preview_data', [ $this, 'filter_preview_data' ], 10, 3 );
		add_filter( 'fusion_dynamic_override', [ $this, 'extra_output_filter' ], 10, 5 );
		add_action( 'fusion_builder_admin_scripts_hook', [ $this, 'backend_builder_data' ], 10 );
		$this->include_and_init_callbacks();
	}

	/**
	 * Require callbacks class.
	 *
	 * @since 2.1
	 * @access public
	 */
	public function include_and_init_callbacks() {
		require_once FUSION_BUILDER_PLUGIN_DIR . 'inc/class-fusion-dynamic-data-callbacks.php';
		new Fusion_Dynamic_Data_Callbacks();
	}

	/**
	 * Filter the shortcode content.
	 *
	 * @since 2.1
	 * @access public
	 * @param string $content Shortcode element content.
	 * @param string $shortcode Shortcode name.
	 * @param array  $args Shortcode parameters.
	 * @return array
	 */
	public function filter_dynamic_content( $content, $shortcode, $args ) {
		if ( ! isset( $args['dynamic_params'] ) ) {
			return $content;
		}

		$dynamic_args = $this->convert( $args['dynamic_params'] );
		$dynamic_arg  = $dynamic_args && isset( $dynamic_args['element_content'] ) ? $dynamic_args['element_content'] : false;

		if ( ! $dynamic_arg ) {
			return $content;
		}

		$value = $this->get_value( $dynamic_arg );

		if ( false === $value ) {
			return $content;
		}

		return $value;
	}

	/**
	 * Filter full output array.
	 *
	 * @since 2.1
	 * @access public
	 * @param array  $out Array to filter.
	 * @param array  $dynamic_arg Args for dynamic param.
	 * @param string $param_id ID for param in element.
	 * @param string $shortcode Name of shortcode.
	 * @param mixed  $value Value being set to that param.
	 * @return array
	 */
	public function extra_output_filter( $out, $dynamic_arg, $param_id, $shortcode, $value ) {
		$dynamic_id = $dynamic_arg['data'];

		switch ( $dynamic_id ) {
			case 'post_featured_image':
				if ( 'fusion_imageframe' === $shortcode && 'element_content' === $param_id ) {
					$out['image_id'] = get_post_thumbnail_id();
				} else {
					$out[ $param_id . '_id' ] = get_post_thumbnail_id();
				}
				break;
			case 'acf_image':
				$image_id   = false;
				$image_data = isset( $dynamic_arg['field'] ) ? get_field( $dynamic_arg['field'] ) : false;

				if ( is_array( $image_data ) && isset( $image_data['url'] ) ) {
					$image_id = $image_data['ID'];
				} elseif ( is_integer( $image_data ) ) {
					$image_id = $image_data;
				}

				if ( 'fusion_imageframe' === $shortcode && 'element_content' === $param_id ) {
					$out['image_id'] = $image_id;
				} else {
					$out[ $param_id . '_id' ] = $image_id;
				}
				break;
		}
		return $out;
	}

	/**
	 * Filter the arguments.
	 *
	 * @since 2.1
	 * @access public
	 * @param array  $out Array to filter.
	 * @param array  $defaults Defaults for shortcode.
	 * @param array  $args Arguments for shortcode.
	 * @param stirng $shortcode Shortcode name.
	 * @return array
	 */
	public function filter_dynamic_args( $out, $defaults, $args, $shortcode ) {
		if ( ! isset( $out['dynamic_params'] ) ) {
			return $out;
		}

		$dynamic_args = $this->convert( $out['dynamic_params'] );

		foreach ( $dynamic_args as $id => $dynamic_arg ) {

			$value = $this->get_value( $dynamic_arg );

			if ( false === $value ) {
				continue;
			}

			$out[ $id ] = $value;

			$out = apply_filters( 'fusion_dynamic_override', $out, $dynamic_arg, $id, $shortcode, $value );
		}
		return $out;
	}

	/**
	 * Get the dynamic value.
	 *
	 * @since 2.1
	 * @access public
	 * @param array $dynamic_arg Array of arguments.
	 * @return mixed
	 */
	public function get_value( $dynamic_arg ) {
		$param             = isset( $dynamic_arg['data'] ) ? $this->get_param( $dynamic_arg['data'] ) : false;
		$fallback          = isset( $dynamic_arg['fallback'] ) ? $dynamic_arg['fallback'] : false;
		$callback          = $param && isset( $param['callback'] ) ? $param['callback'] : false;
		$callback_function = $callback && isset( $callback['function'] ) ? $callback['function'] : false;
		$callback_exists   = $callback_function && ( is_callable( 'Fusion_Dynamic_Data_Callbacks::' . $callback_function ) || is_callable( $callback_function ) ) ? true : false;

		if ( ! $param || ( ! $fallback && ! $callback_exists ) ) {
			return false;
		}

		if ( ! $callback_exists ) {
			return $fallback;
		}

		$value = is_callable( 'Fusion_Dynamic_Data_Callbacks::' . $callback_function ) ? call_user_func_array( 'Fusion_Dynamic_Data_Callbacks::' . $callback_function, [ $dynamic_arg ] ) : call_user_func_array( $callback_function, [ $dynamic_arg ] );
		if ( ( ! $value || '' === $value ) && $fallback ) {
			return $fallback;
		}

		(string) $before_string = isset( $dynamic_arg['before'] ) ? $dynamic_arg['before'] : '';
		(string) $after_string  = isset( $dynamic_arg['after'] ) ? $dynamic_arg['after'] : '';

		$this->maybe_store_value( $value, $dynamic_arg );

		return $before_string . $value . $after_string;
	}

	/**
	 * If a live editor load then we store.
	 *
	 * @since 2.1
	 * @access public
	 * @param mixed $value Dynamic value.
	 * @param array $dynamic_arg The arguments for specific dynamic value.
	 * @return void
	 */
	public function maybe_store_value( $value, $dynamic_arg ) {
		if ( function_exists( 'fusion_is_preview_frame' ) && fusion_is_preview_frame() ) {
			$this->values[ $dynamic_arg['data'] ][] = [
				'value' => $value,
				'args'  => $dynamic_arg,
			];
		}
	}

	/**
	 * Add in dynamic data values to live editor data.
	 *
	 * @since 2.1
	 * @access public
	 * @param array  $data Existing data.
	 * @param string $page_id The ID of the page.
	 * @param string $post_type The post type of the page.
	 * @return array
	 */
	public function filter_preview_data( $data, $page_id, $post_type ) {
		$data['dynamicValues']  = $this->values;
		$data['dynamicOptions'] = $this->get_params();
		$data['dynamicCommon']  = $this->get_common();
		$data['site_title']     = get_bloginfo( 'name' );
		$data['site_tagline']   = get_bloginfo( 'description' );
		return $data;
	}

	/**
	 * Add in dynamic data values to live editor data.
	 *
	 * @since 2.1
	 * @access public
	 */
	public function backend_builder_data() {
		$script = FUSION_BUILDER_DEV_MODE ? 'fusion_builder_app_js' : 'fusion_builder';
		wp_localize_script(
			$script,
			'fusionDynamicData',
			[
				'dynamicOptions'      => $this->get_params(),
				'commonDynamicFields' => $this->get_common(),
			]
		);
	}

	/**
	 * Convert from encoded string to array.
	 *
	 * @since 2.1
	 * @access public
	 * @param string $param_string Encoded param string.
	 * @return array
	 */
	public function convert( $param_string ) {
		(array) $params = json_decode( fusion_decode_if_needed( $param_string ), true );
		return $params;
	}

	/**
	 * Get param map.
	 *
	 * @since 2.1
	 * @access public
	 * @return array
	 */
	public function get_params() {
		if ( empty( $this->params ) ) {
			$this->set_params();
		}
		return $this->params;
	}

	/**
	 * Get single param.
	 *
	 * @since 2.1
	 * @access public
	 * @param string $id Param ID.
	 * @return mixed
	 */
	public function get_param( $id ) {
		if ( empty( $this->params ) ) {
			$this->set_params();
		}
		return is_array( $this->params ) && isset( $this->params[ $id ] ) ? $this->params[ $id ] : false;
	}

	/**
	 * Common shared fields.
	 *
	 * @since 2.1
	 * @access public
	 * @return array
	 */
	public function get_common() {
		return [
			'before'   => [
				'label'       => esc_html__( 'Before', 'fusion-builder' ),
				'description' => esc_html__( 'Text before value.' ),
				'id'          => 'before',
				'default'     => '',
				'type'        => 'text',
				'value'       => '',
			],
			'after'    => [
				'label'       => esc_html__( 'After', 'fusion-builder' ),
				'description' => esc_html__( 'Text after value.' ),
				'id'          => 'after',
				'default'     => '',
				'type'        => 'text',
				'value'       => '',
			],
			'fallback' => [
				'label'       => esc_html__( 'Fallback', 'fusion-builder' ),
				'description' => esc_html__( 'Fallback if no value found.' ),
				'id'          => 'fallback',
				'default'     => '',
				'type'        => 'text',
				'value'       => '',
			],
		];
	}

	/**
	 * Get builder status.
	 *
	 * @since 2.1
	 * @return bool
	 */
	private function get_builder_status() {
		global $pagenow;

		$allowed_post_types = class_exists( 'FusionBuilder' ) ? FusionBuilder()->allowed_post_types() : [];
		$post_type          = get_post_type();

		return ( function_exists( 'fusion_is_preview_frame' ) && fusion_is_preview_frame() || ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) ) && $post_type && in_array( $post_type, $allowed_post_types, true );
	}

	/**
	 * Set param map.
	 *
	 * @since 2.1
	 * @access public
	 * @return void
	 */
	public function set_params() {
		$post_taxonomies = [];
		$post_meta       = [];
		$post_type       = get_post_type();
		$single_label    = esc_html__( 'Post', 'fusion-builder' );

		if ( $this->get_builder_status() ) {
			// Get all registered taxonomies.
			$object_tax_slugs = get_object_taxonomies( $post_type );

			// Create key value pairs.
			foreach ( $object_tax_slugs as $tax_slug ) {
				$tax = get_taxonomy( $tax_slug );
				if ( false !== $tax && $tax->public ) {
					$post_taxonomies[ $tax_slug ] = $tax->labels->name;
				}
			}

			// Get all custom fields.
			$meta_fields = get_post_custom( get_the_ID() );

			// Create key value pairs.
			foreach ( $meta_fields as $key => $value ) {
				$post_meta[ $key ] = $key;
			}
		}

		$post_type_object = get_post_type_object( $post_type );
		if ( is_object( $post_type_object ) ) {
			$single_label = $post_type_object->labels->singular_name;
		}

		$params = [
			'post_title'          => [
				/* translators: Single post type title. */
				'label'     => sprintf( esc_html__( '%s Title', 'fusion-builder' ), $single_label ),
				'id'        => 'post_title',
				'group'     => $single_label,
				'options'   => $this->text_fields,
				'callback'  => [
					'function' => 'fusion_get_title',
					'ajax'     => false,
				],
				'listeners' => [
					'post_title' => [
						'location' => 'postDetails',
					],
				],
			],
			'post_time'           => [
				/* translators: Single post type time. */
				'label'    => sprintf( esc_html__( '%s Time', 'fusion-builder' ), $single_label ),
				'id'       => 'post_time',
				'group'    => $single_label,
				'options'  => $this->text_fields,
				'callback' => [
					'function' => 'fusion_get_post_time',
					'ajax'     => true,
				],
				'fields'   => [
					'format' => [
						'heading'     => esc_html__( 'Format', 'fusion-builder' ),
						'description' => __( 'Time format to use.  <a href="https://wordpress.org/support/article/formatting-date-and-time/" target="_blank" rel="noopener noreferrer">Formatting Date and Time</a>' ),
						'param_name'  => 'format',
						'value'       => get_option( 'time_format' ),
						'type'        => 'text',
					],
				],
			],
			'post_terms'          => ! empty( $post_taxonomies ) || ! $this->get_builder_status() ? [
				/* translators: Single post type terms. */
				'label'    => sprintf( esc_html__( '%s Terms', 'fusion-builder' ), $single_label ),
				'id'       => 'post_terms',
				'group'    => $single_label,
				'options'  => $this->text_fields,
				'callback' => [
					'function' => 'fusion_get_post_terms',
					'ajax'     => true,
				],
				'fields'   => [
					'type'      => [
						'heading'     => esc_html__( 'Taxonomy', 'fusion-builder' ),
						'description' => esc_html__( 'Taxonomy to use.' ),
						'param_name'  => 'type',
						'default'     => '',
						'type'        => 'select',
						'value'       => $post_taxonomies,
					],
					'separator' => [
						'heading'     => esc_html__( 'Separator', 'fusion-builder' ),
						'description' => esc_html__( 'Separator between post terms.' ),
						'param_name'  => 'separator',
						'value'       => ',',
						'type'        => 'textfield',
					],
					'link'      => [
						'type'        => 'radio_button_set',
						'heading'     => esc_html__( 'Link', 'fusion-builder' ),
						'description' => esc_html__( 'Whether each term should link to term page.' ),
						'param_name'  => 'link',
						'default'     => 'yes',
						'value'       => [
							'yes' => esc_attr__( 'Yes', 'fusion-builder' ),
							'no'  => esc_attr__( 'No', 'fusion-builder' ),
						],
					],
				],
			] : false,
			'post_id'             => [
				/* translators: Single post type ID. */
				'label'    => sprintf( esc_html__( '%s ID', 'fusion-builder' ), $single_label ),
				'id'       => 'post_id',
				'group'    => $single_label,
				'options'  => $this->text_fields,
				'callback' => [
					'function' => 'fusion_get_post_id',
					'ajax'     => false,
				],
			],
			'post_excerpt'        => [
				/* translators: Single post type excerpt. */
				'label'    => sprintf( esc_html__( '%s Excerpt', 'fusion-builder' ), $single_label ),
				'id'       => 'post_excerpt',
				'group'    => $single_label,
				'options'  => $this->text_fields,
				'callback' => [
					'function' => 'fusion_get_post_excerpt',
					'ajax'     => false,
				],
			],
			'post_date'           => [
				/* translators: Single post type date. */
				'label'    => sprintf( esc_html__( '%s Date', 'fusion-builder' ), $single_label ),
				'id'       => 'post_date',
				'group'    => $single_label,
				'options'  => $this->text_fields,
				'callback' => [
					'function' => 'fusion_get_post_date',
					'ajax'     => true,
				],
				'fields'   => [
					'type'   => [
						'heading'     => esc_html__( 'Format', 'fusion-builder' ),
						'description' => esc_html__( 'Date format to use.' ),
						'param_name'  => 'type',
						'default'     => '',
						'type'        => 'select',
						'value'       => [
							''         => esc_html__( 'Post Published', 'fusion-builder' ),
							'modified' => esc_html__( 'Post Modified', 'fusion-builder' ),
						],
					],
					'format' => [
						'heading'     => esc_html__( 'Format', 'fusion-builder' ),
						'description' => __( 'Date format to use.  <a href="https://wordpress.org/support/article/formatting-date-and-time/" target="_blank" rel="noopener noreferrer">Formatting Date and Time</a>' ),
						'param_name'  => 'format',
						'value'       => get_option( 'date_format' ),
						'type'        => 'text',
					],
				],
			],
			'post_custom_field'   => [
				/* translators: Single post type custom field. */
				'label'    => sprintf( esc_html__( '%s Custom Field', 'fusion-builder' ), $single_label ),
				'id'       => 'post_custom_field',
				'group'    => $single_label,
				'options'  => $this->text_fields,
				'callback' => [
					'function' => 'fusion_get_post_custom_field',
					'ajax'     => false,
				],
				'fields'   => [
					'key' => [
						'heading'     => esc_html__( 'Key', 'fusion-builder' ),
						'description' => esc_html__( 'Custom field ID key.' ),
						'param_name'  => 'key',
						'default'     => '',
						'type'        => 'select',
						'value'       => $post_meta,
					],
				],
			],
			'post_featured_image' => [
				'label'     => esc_html__( 'Featured Image', 'fusion-builder' ),
				'id'        => 'post_featured_image',
				'group'     => $single_label,
				'options'   => $this->image_fields,
				'callback'  => [
					'function' => 'post_featured_image',
					'ajax'     => true,
				],
				'exclude'   => [ 'before', 'after' ],
				'options'   => [ 'upload' ],
				'listeners' => [
					'_thumbnail_id' => [
						'location' => 'postMeta',
					],
				],
			],
			'site_title'          => [
				'label'    => esc_html__( 'Site Title', 'fusion-builder' ),
				'id'       => 'site_title',
				'group'    => esc_attr__( 'Site', 'fusion-builder' ),
				'options'  => $this->text_fields,
				'callback' => [
					'function' => 'fusion_get_site_title',
					'ajax'     => true,
				],
			],
			'site_tagline'        => [
				'label'    => esc_html__( 'Site Tagline', 'fusion-builder' ),
				'id'       => 'site_tagline',
				'group'    => esc_attr__( 'Site', 'fusion-builder' ),
				'options'  => $this->text_fields,
				'callback' => [
					'function' => 'fusion_get_site_tagline',
					'ajax'     => true,
				],
			],
			'shortcode'           => [
				'label'    => esc_html__( 'Shortcode', 'fusion-builder' ),
				'id'       => 'shortcode',
				'group'    => esc_attr__( 'Other', 'fusion-builder' ),
				'options'  => $this->text_fields,
				'callback' => [
					'function' => 'dynamic_shortcode',
					'ajax'     => true,
				],
				'fields'   => [
					'shortcode' => [
						'heading'    => esc_html__( 'Shortcode', 'fusion-builder' ),
						'param_name' => 'shortcode',
						'type'       => 'textarea',
						'value'      => '',
					],
				],
			],
		];

		$params = $this->maybe_add_acf_fields( $params, $post_type );
		$params = $this->maybe_add_woo_fields( $params, $post_type );

		$this->params = apply_filters( 'fusion_set_dynamic_params', $params );

	}

	/**
	 * Add ACF fields if they exist.
	 *
	 * @since 2.1
	 * @access public
	 * @param array  $params    Params being used.
	 * @param string $post_type The current post type.
	 * @return array
	 */
	public function maybe_add_acf_fields( $params, $post_type ) {
		if ( class_exists( 'ACF' ) ) {
			$fields              = $this->get_builder_status() ? get_field_objects() : [];
			$text_options        = false;
			$image_options       = false;
			$string_option_types = [ 'text', 'textarea', 'number', 'range', 'wysiwyg' ];

			if ( $fields && is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					if ( in_array( $field['type'], $string_option_types, true ) ) {
						$text_options[ $field['name'] ] = $field['label'];
					} elseif ( 'image' === $field['type'] ) {
						$image_options[ $field['name'] ] = $field['label'];
					}
				}
			}

			if ( ! $this->get_builder_status() || $text_options ) {
				$params['acf_text'] = [
					'label'    => esc_html__( 'ACF Text', 'fusion-builder' ),
					'id'       => 'acf_text',
					'group'    => esc_attr__( 'Advanced Custom Fields', 'fusion-builder' ),
					'options'  => $this->text_fields,
					'callback' => [
						'function' => 'acf_get_field',
						'ajax'     => true,
					],
					'fields'   => [
						'field' => [
							'heading'     => esc_html__( 'Field', 'fusion-builder' ),
							'description' => esc_html__( 'Which field you want to use.', 'fusion-builder' ),
							'param_name'  => 'field',
							'default'     => '',
							'type'        => 'select',
							'value'       => $text_options,
						],
					],
				];
			}

			if ( ! $this->get_builder_status() || $image_options ) {
				$params['acf_image'] = [
					'label'    => esc_html__( 'ACF Image', 'fusion-builder' ),
					'id'       => 'acf_image',
					'group'    => esc_attr__( 'Advanced Custom Fields', 'fusion-builder' ),
					'callback' => [
						'function' => 'acf_get_image_field',
						'ajax'     => true,
					],
					'exclude'  => [ 'before', 'after', 'fallback' ],
					'options'  => $this->image_fields,
					'fields'   => [
						'field' => [
							'heading'     => esc_html__( 'Field', 'fusion-builder' ),
							'description' => esc_html__( 'Which field you want to use.', 'fusion-builder' ),
							'param_name'  => 'field',
							'default'     => '',
							'type'        => 'select',
							'value'       => $image_options,
						],
					],
				];
			}
		}

		return $params;
	}

	/**
	 * Add WooCommerce single product fields if they exist.
	 *
	 * @since 2.1
	 * @access public
	 * @param array  $params    Params being used.
	 * @param string $post_type The current post type.
	 * @return array
	 */
	public function maybe_add_woo_fields( $params, $post_type ) {
		if ( ( function_exists( 'is_product' ) && is_product() ) || ( is_admin() && 'product' === $post_type ) ) {
			$params['woo_price'] = [
				'label'    => esc_html__( 'Product Price', 'fusion-builder' ),
				'id'       => 'woo_price',
				'group'    => esc_attr__( 'WooCommerce', 'fusion-builder' ),
				'options'  => $this->text_fields,
				'callback' => [
					'function' => 'woo_get_price',
					'ajax'     => true,
				],
				'fields'   => [
					'format' => [
						'heading'     => esc_html__( 'Format', 'fusion-builder' ),
						'description' => esc_html__( 'Format of price to display.', 'fusion-builder' ),
						'param_name'  => 'format',
						'default'     => '',
						'type'        => 'select',
						'value'       => [
							''         => esc_html__( 'Both', 'fusion-builder' ),
							'original' => esc_html__( 'Original Only', 'fusion-builder' ),
							'sale'     => esc_html__( 'Sale Only', 'fusion-builder' ),
						],
					],
				],
			];

			$params['woo_rating'] = [
				'label'    => esc_html__( 'Product Rating', 'fusion-builder' ),
				'id'       => 'woo_rating',
				'group'    => esc_attr__( 'WooCommerce', 'fusion-builder' ),
				'options'  => $this->text_fields,
				'callback' => [
					'function' => 'woo_get_rating',
					'ajax'     => true,
				],
				'fields'   => [
					'format' => [
						'heading'     => esc_html__( 'Format', 'fusion-builder' ),
						'description' => esc_html__( 'Format of rating to display.', 'fusion-builder' ),
						'param_name'  => 'format',
						'default'     => '',
						'type'        => 'select',
						'value'       => [
							''       => esc_html__( 'Average Rating', 'fusion-builder' ),
							'rating' => esc_html__( 'Rating Count', 'fusion-builder' ),
							'review' => esc_html__( 'Review Count', 'fusion-builder' ),
						],
					],
				],
			];

			$params['woo_sku'] = [
				'label'    => esc_html__( 'Product SKU', 'fusion-builder' ),
				'id'       => 'woo_sku',
				'group'    => esc_attr__( 'WooCommerce', 'fusion-builder' ),
				'options'  => $this->text_fields,
				'callback' => [
					'function' => 'woo_get_sku',
					'ajax'     => true,
				],
			];

			$params['woo_stock'] = [
				'label'    => esc_html__( 'Product Stock', 'fusion-builder' ),
				'id'       => 'woo_stock',
				'group'    => esc_attr__( 'WooCommerce', 'fusion-builder' ),
				'options'  => $this->text_fields,
				'callback' => [
					'function' => 'woo_get_stock',
					'ajax'     => true,
				],
			];
		}

		return $params;
	}
}
