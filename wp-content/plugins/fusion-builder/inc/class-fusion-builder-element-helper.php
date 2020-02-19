<?php
/**
 * Fusion Builder Elementer Helper class.
 *
 * @package Fusion-Builder
 * @since 2.1
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Fusion Builder Elementer Helper class.
 *
 * @since 2.1
 */
class Fusion_Builder_Element_Helper {

	/**
	 * Class constructor.
	 *
	 * @since 2.1
	 * @access public
	 */
	public function __construct() {

	}

	/**
	 * Replace placeholders with params.
	 *
	 * @since 2.1
	 * @access public
	 * @param array  $params Element params.
	 * @param string $shortcode Shortcode handle.
	 * @return array
	 */
	public static function placeholders_to_params( $params, $shortcode ) {

		// placeholder => callback.
		$placeholders_to_params = [
			'fusion_animation_placeholder'           => 'get_animation_params',
			'fusion_filter_placeholder'              => 'get_filter_params',
			'fusion_border_radius_placeholder'       => 'get_border_radius_params',
			'fusion_gradient_placeholder'            => 'get_gradient_params',
			'fusion_box_shadow_placeholder'          => 'get_box_shadow_params',
			'fusion_box_shadow_no_inner_placeholder' => 'get_box_shadow_no_inner_params',
		];

		foreach ( $placeholders_to_params as $placeholder => $param_callback ) {

			if ( isset( $params[ $placeholder ] ) ) {

				$placeholder_args              = is_array( $params[ $placeholder ] ) ? $params[ $placeholder ] : [ $params[ $placeholder ] ];
				$placeholder_args['shortcode'] = $shortcode;

				// Get placeholder element position.
				$position = array_search( $placeholder, array_keys( $params ), true );

				// Unset placeholder element as we don't need it anymore.
				unset( $params[ $placeholder ] );

				// Insert animation params.
				if ( is_callable( 'Fusion_Builder_Element_Helper::' . $param_callback ) ) {
					array_splice( $params, $position, 0, call_user_func_array( 'Fusion_Builder_Element_Helper::' . $param_callback, [ $placeholder_args ] ) );
				}
			}
		}

		return $params;
	}

	/**
	 * Get animation params.
	 *
	 * @since 2.1
	 * @access public
	 * @param array $args The placeholder arguments.
	 * @return array
	 */
	public static function get_animation_params( $args ) {

		$selector = isset( $args['preview_selector'] ) ? $args['preview_selector'] : '';

		return [
			[
				'type'        => 'select',
				'heading'     => esc_attr__( 'Animation Type', 'fusion-builder' ),
				'description' => esc_attr__( 'Select the type of animation to use on the element.', 'fusion-builder' ),
				'param_name'  => 'animation_type',
				'value'       => fusion_builder_available_animations(),
				'default'     => '',
				'group'       => esc_attr__( 'Extras', 'fusion-builder' ),
				'preview'     => [
					'selector' => $selector,
					'type'     => 'animation',
				],
			],
			[
				'type'        => 'radio_button_set',
				'heading'     => esc_attr__( 'Direction of Animation', 'fusion-builder' ),
				'description' => esc_attr__( 'Select the incoming direction for the animation.', 'fusion-builder' ),
				'param_name'  => 'animation_direction',
				'default'     => 'left',
				'group'       => esc_attr__( 'Extras', 'fusion-builder' ),
				'dependency'  => [
					[
						'element'  => 'animation_type',
						'value'    => '',
						'operator' => '!=',
					],
				],
				'value'       => [
					'down'   => esc_attr__( 'Top', 'fusion-builder' ),
					'right'  => esc_attr__( 'Right', 'fusion-builder' ),
					'up'     => esc_attr__( 'Bottom', 'fusion-builder' ),
					'left'   => esc_attr__( 'Left', 'fusion-builder' ),
					'static' => esc_attr__( 'Static', 'fusion-builder' ),
				],
				'preview'     => [
					'selector' => $selector,
					'type'     => 'animation',
				],
			],
			[
				'type'        => 'range',
				'heading'     => esc_attr__( 'Speed of Animation', 'fusion-builder' ),
				'description' => esc_attr__( 'Type in speed of animation in seconds (0.1 - 1).', 'fusion-builder' ),
				'param_name'  => 'animation_speed',
				'min'         => '0.1',
				'max'         => '1',
				'step'        => '0.1',
				'value'       => '0.3',
				'group'       => esc_attr__( 'Extras', 'fusion-builder' ),
				'dependency'  => [
					[
						'element'  => 'animation_type',
						'value'    => '',
						'operator' => '!=',
					],
				],
				'preview'     => [
					'selector' => $selector,
					'type'     => 'animation',
				],
			],
			[
				'type'        => 'select',
				'heading'     => esc_attr__( 'Offset of Animation', 'fusion-builder' ),
				'description' => esc_attr__( 'Controls when the animation should start.', 'fusion-builder' ),
				'param_name'  => 'animation_offset',
				'default'     => '',
				'group'       => esc_attr__( 'Extras', 'fusion-builder' ),
				'dependency'  => [
					[
						'element'  => 'animation_type',
						'value'    => '',
						'operator' => '!=',
					],
				],
				'value'       => [
					''                => esc_attr__( 'Default', 'fusion-builder' ),
					'top-into-view'   => esc_attr__( 'Top of element hits bottom of viewport', 'fusion-builder' ),
					'top-mid-of-view' => esc_attr__( 'Top of element hits middle of viewport', 'fusion-builder' ),
					'bottom-in-view'  => esc_attr__( 'Bottom of element enters viewport', 'fusion-builder' ),
				],
			],
		];

	}

	/**
	 * Get gradient params.
	 *
	 * @since 2.1
	 * @access public
	 * @param array $args The placeholder arguments.
	 * @return array
	 */
	public static function get_gradient_params( $args ) {
		$fusion_settings = fusion_get_fusion_settings();
		$selector        = isset( $args['selector'] ) ? $args['selector'] : '';
		$defaults        = isset( $args['defaults'] ) ? $args['defaults'] : '';

		return [
			[
				'type'        => 'colorpickeralpha',
				'heading'     => esc_attr__( 'Gradient Start Color', 'fusion-builder' ),
				'param_name'  => 'gradient_start_color',
				'default'     => isset( $defaults ) ? $fusion_settings->get( 'full_width_gradient_start_color' ) : '',
				'description' => esc_attr__( 'Select start color for gradient.', 'fusion-builder' ),
				'group'       => esc_attr__( 'BG', 'fusion-builder' ),
				'subgroup'    => [
					'name' => 'background_type',
					'tab'  => 'gradient',
				],
				'callback'    => [
					'function' => 'fusion_update_gradient_style',
					'args'     => [
						'selector' => $selector,
					],
				],
			],
			[
				'type'        => 'colorpickeralpha',
				'heading'     => esc_attr__( 'Gradient End Color', 'fusion-builder' ),
				'param_name'  => 'gradient_end_color',
				'default'     => isset( $defaults ) ? $fusion_settings->get( 'full_width_gradient_end_color' ) : '',
				'description' => esc_attr__( 'Select end color for gradient.', 'fusion-builder' ),
				'group'       => esc_attr__( 'BG', 'fusion-builder' ),
				'subgroup'    => [
					'name' => 'background_type',
					'tab'  => 'gradient',
				],
				'callback'    => [
					'function' => 'fusion_update_gradient_style',
					'args'     => [
						'selector' => $selector,
					],
				],
			],
			[
				'type'        => 'range',
				'heading'     => esc_attr__( 'Gradient Start Position', 'fusion-builder' ),
				'description' => esc_attr__( 'Select start position for gradient.', 'fusion-builder' ),
				'param_name'  => 'gradient_start_position',
				'value'       => '0',
				'min'         => '0',
				'max'         => '100',
				'group'       => esc_attr__( 'BG', 'fusion-builder' ),
				'subgroup'    => [
					'name' => 'background_type',
					'tab'  => 'gradient',
				],
				'callback'    => [
					'function' => 'fusion_update_gradient_style',
					'args'     => [
						'selector' => $selector,
					],
				],
			],
			[
				'type'        => 'range',
				'heading'     => esc_attr__( 'Gradient End Position', 'fusion-builder' ),
				'description' => esc_attr__( 'Select end position for gradient.', 'fusion-builder' ),
				'param_name'  => 'gradient_end_position',
				'value'       => '100',
				'min'         => '0',
				'max'         => '100',
				'group'       => esc_attr__( 'BG', 'fusion-builder' ),
				'subgroup'    => [
					'name' => 'background_type',
					'tab'  => 'gradient',
				],
				'callback'    => [
					'function' => 'fusion_update_gradient_style',
					'args'     => [
						'selector' => $selector,
					],
				],
			],
			[
				'type'        => 'radio_button_set',
				'heading'     => esc_attr__( 'Gradient Type', 'fusion-builder' ),
				'description' => esc_attr__( 'Controls gradient type.', 'fusion-builder' ),
				'param_name'  => 'gradient_type',
				'default'     => 'linear',
				'group'       => esc_attr__( 'BG', 'fusion-builder' ),
				'subgroup'    => [
					'name' => 'background_type',
					'tab'  => 'gradient',
				],
				'callback'    => [
					'function' => 'fusion_update_gradient_style',
					'args'     => [
						'selector' => $selector,
					],
				],
				'value'       => [
					'linear' => esc_attr__( 'Linear', 'fusion-builder' ),
					'radial' => esc_attr__( 'Radial', 'fusion-builder' ),
				],
			],
			[
				'type'        => 'select',
				'heading'     => esc_attr__( 'Radial Direction', 'fusion-builder' ),
				'description' => esc_attr__( 'Select direction for radial gradient.', 'fusion-builder' ),
				'param_name'  => 'radial_direction',
				'default'     => 'center',
				'group'       => esc_attr__( 'BG', 'fusion-builder' ),
				'subgroup'    => [
					'name' => 'background_type',
					'tab'  => 'gradient',
				],
				'value'       => [
					'bottom'        => esc_attr__( 'Bottom', 'fusion-builder' ),
					'bottom center' => esc_attr__( 'Bottom Center', 'fusion-builder' ),
					'center'        => esc_attr__( 'Center', 'fusion-builder' ),
					'center left'   => esc_attr__( 'Center Left', 'fusion-builder' ),
					'left'          => esc_attr__( 'Left', 'fusion-builder' ),
					'left top'      => esc_attr__( 'Left Top', 'fusion-builder' ),
					'right'         => esc_attr__( 'Right', 'fusion-builder' ),
					'right top'     => esc_attr__( 'Right Top', 'fusion-builder' ),
					'top'           => esc_attr__( 'Top', 'fusion-builder' ),
				],
				'dependency'  => [
					[
						'element'  => 'gradient_type',
						'value'    => 'radial',
						'operator' => '==',
					],
				],
				'callback'    => [
					'function' => 'fusion_update_gradient_style',
					'args'     => [
						'selector' => $selector,
					],
				],
			],
			[
				'type'        => 'range',
				'heading'     => esc_attr__( 'Gradient Angle', 'fusion-builder' ),
				'description' => esc_attr__( 'Controls the gradient angle. In degrees.', 'fusion-builder' ),
				'param_name'  => 'linear_angle',
				'value'       => '180',
				'min'         => '0',
				'max'         => '360',
				'group'       => esc_attr__( 'BG', 'fusion-builder' ),
				'subgroup'    => [
					'name' => 'background_type',
					'tab'  => 'gradient',
				],
				'dependency'  => [
					[
						'element'  => 'gradient_type',
						'value'    => 'linear',
						'operator' => '==',
					],
				],
				'callback'    => [
					'function' => 'fusion_update_gradient_style',
					'args'     => [
						'selector' => $selector,
					],
				],
			],
		];
	}

	/**
	 * Generate gradient string.
	 *
	 * @since 2.1
	 * @param array  $args The parameters for the option.
	 * @param string $type The section type for which gradient string is required.
	 * @return string
	 */
	public static function get_gradient_string( $args, $type = '' ) {
		$fusion_settings = fusion_get_fusion_settings();
		$lazy_load       = $fusion_settings->get( 'lazy_load' );
		$lazy_load       = ( ! $args['background_image'] || '' === $args['background_image'] ? false : $lazy_load );
		$style           = '';

		if ( ! empty( $args['gradient_start_color'] ) || ! empty( $args['gradient_end_color'] ) ) {
			if ( 'linear' === $args['gradient_type'] ) {
				$style .= 'linear-gradient(' . $args['linear_angle'] . 'deg, ';
			} elseif ( 'radial' === $args['gradient_type'] ) {
				$style .= 'radial-gradient(circle at ' . $args['radial_direction'] . ', ';
			}

			$style .= $args['gradient_start_color'] . ' ' . $args['gradient_start_position'] . '%,';
			$style .= $args['gradient_end_color'] . ' ' . $args['gradient_end_position'] . '%)';

			switch ( $type ) {
				case 'main_bg':
				case 'parallax':
					if ( ! empty( $args['background_image'] ) && 'yes' !== $args['fade'] && ! $lazy_load ) {
						$style .= ',url(' . esc_url_raw( $args['background_image'] ) . ');';
					} else {
						$style .= ';';
					}
					break;
				case 'fade':
					if ( ! empty( $args['background_image'] ) && ! $lazy_load ) {
						$style .= ',url(' . esc_url_raw( $args['background_image'] ) . ');';
					} else {
						$style .= ';';
					}
					break;
				case 'column':
					if ( ! empty( $args['background_image'] ) ) {
						$style .= ',url(' . esc_url_raw( $args['background_image'] ) . ');';
					} else {
						$style .= ';';
					}
					break;
			}
		}

		return $style;
	}

	/**
	 * Get filter params.
	 *
	 * @since 2.1
	 * @access public
	 * @param array $args The placeholder arguments.
	 * @return array
	 */
	public static function get_filter_params( $args ) {

		$selector_base = isset( $args['selector_base'] ) ? $args['selector_base'] : '';

		$states         = [ 'regular', 'hover' ];
		$filter_options = [
			[
				'type'             => 'subgroup',
				'heading'          => esc_attr__( 'Filter Type', 'fusion-builder' ),
				'description'      => esc_attr__( 'Use filters to see specific type of content.', 'fusion-builder' ),
				'param_name'       => 'filter_type',
				'default'          => 'regular',
				'group'            => esc_attr__( 'Extras', 'fusion-builder' ),
				'remove_from_atts' => true,
				'value'            => [
					'regular' => esc_attr__( 'Regular', 'fusion-builder' ),
					'hover'   => esc_attr__( 'Hover', 'fusion-builder' ),
				],
				'icons'            => [
					'regular' => '<span class="fusiona-regular-state" style="font-size:18px;"></span>',
					'hover'   => '<span class="fusiona-hover-state" style="font-size:18px;"></span>',
				],
			],
		];

		foreach ( $states as $key ) {
			$filter_options = array_merge(
				$filter_options,
				[
					[
						'type'        => 'range',
						'heading'     => esc_attr__( 'Hue', 'fusion-builder' ),
						'description' => esc_attr__( 'Filter hue.', 'fusion-builder' ),
						'param_name'  => 'filter_hue' . ( 'regular' !== $key ? '_' . $key : '' ),
						'value'       => '0',
						'min'         => '0',
						'max'         => '359',
						'default'     => '',
						'group'       => esc_attr__( 'Extras', 'fusion-builder' ),
						'subgroup'    => [
							'name' => 'filter_type',
							'tab'  => $key,
						],
						'callback'    => [
							'function' => 'fusion_update_filter_style',
							'args'     => [
								'selector_base' => $selector_base,
							],
						],
					],
					[
						'type'        => 'range',
						'heading'     => esc_attr__( 'Saturation', 'fusion-builder' ),
						'description' => esc_attr__( 'Filter saturation.', 'fusion-builder' ),
						'param_name'  => 'filter_saturation' . ( 'regular' !== $key ? '_' . $key : '' ),
						'value'       => '100',
						'min'         => '0',
						'max'         => '200',
						'default'     => '',
						'group'       => esc_attr__( 'Extras', 'fusion-builder' ),
						'subgroup'    => [
							'name' => 'filter_type',
							'tab'  => $key,
						],
						'callback'    => [
							'function' => 'fusion_update_filter_style',
							'args'     => [
								'selector_base' => $selector_base,
							],
						],
					],
					[
						'type'        => 'range',
						'heading'     => esc_attr__( 'Brightness', 'fusion-builder' ),
						'description' => esc_attr__( 'Filter brightness.', 'fusion-builder' ),
						'param_name'  => 'filter_brightness' . ( 'regular' !== $key ? '_' . $key : '' ),
						'value'       => '100',
						'min'         => '0',
						'max'         => '200',
						'default'     => '',
						'group'       => esc_attr__( 'Extras', 'fusion-builder' ),
						'subgroup'    => [
							'name' => 'filter_type',
							'tab'  => $key,
						],
						'callback'    => [
							'function' => 'fusion_update_filter_style',
							'args'     => [
								'selector_base' => $selector_base,
							],
						],
					],
					[
						'type'        => 'range',
						'heading'     => esc_attr__( 'Contrast', 'fusion-builder' ),
						'description' => esc_attr__( 'Filter contrast.', 'fusion-builder' ),
						'param_name'  => 'filter_contrast' . ( 'regular' !== $key ? '_' . $key : '' ),
						'value'       => '100',
						'min'         => '0',
						'max'         => '200',
						'default'     => '',
						'group'       => esc_attr__( 'Extras', 'fusion-builder' ),
						'subgroup'    => [
							'name' => 'filter_type',
							'tab'  => $key,
						],
						'callback'    => [
							'function' => 'fusion_update_filter_style',
							'args'     => [
								'selector_base' => $selector_base,
							],
						],
					],
					[
						'type'        => 'range',
						'heading'     => esc_attr__( 'Invert', 'fusion-builder' ),
						'description' => esc_attr__( 'Filter invert.', 'fusion-builder' ),
						'param_name'  => 'filter_invert' . ( 'regular' !== $key ? '_' . $key : '' ),
						'value'       => '0',
						'min'         => '0',
						'max'         => '100',
						'default'     => '',
						'group'       => esc_attr__( 'Extras', 'fusion-builder' ),
						'subgroup'    => [
							'name' => 'filter_type',
							'tab'  => $key,
						],
						'callback'    => [
							'function' => 'fusion_update_filter_style',
							'args'     => [
								'selector_base' => $selector_base,
							],
						],
					],
					[
						'type'        => 'range',
						'heading'     => esc_attr__( 'Sepia', 'fusion-builder' ),
						'description' => esc_attr__( 'Filter sepia.', 'fusion-builder' ),
						'param_name'  => 'filter_sepia' . ( 'regular' !== $key ? '_' . $key : '' ),
						'value'       => '0',
						'min'         => '0',
						'max'         => '100',
						'default'     => '',
						'group'       => esc_attr__( 'Extras', 'fusion-builder' ),
						'subgroup'    => [
							'name' => 'filter_type',
							'tab'  => $key,
						],
						'callback'    => [
							'function' => 'fusion_update_filter_style',
							'args'     => [
								'selector_base' => $selector_base,
							],
						],
					],
					[
						'type'        => 'range',
						'heading'     => esc_attr__( 'Opacity', 'fusion-builder' ),
						'description' => esc_attr__( 'Filter opacity.', 'fusion-builder' ),
						'param_name'  => 'filter_opacity' . ( 'regular' !== $key ? '_' . $key : '' ),
						'value'       => '100',
						'min'         => '0',
						'max'         => '100',
						'default'     => '',
						'group'       => esc_attr__( 'Extras', 'fusion-builder' ),
						'subgroup'    => [
							'name' => 'filter_type',
							'tab'  => $key,
						],
						'callback'    => [
							'function' => 'fusion_update_filter_style',
							'args'     => [
								'selector_base' => $selector_base,
							],
						],
					],
					[
						'type'        => 'range',
						'heading'     => esc_attr__( 'Blur', 'fusion-builder' ),
						'description' => esc_attr__( 'Filter blur.  In pixels.', 'fusion-builder' ),
						'param_name'  => 'filter_blur' . ( 'regular' !== $key ? '_' . $key : '' ),
						'value'       => '0',
						'min'         => '0',
						'max'         => '50',
						'default'     => '',
						'group'       => esc_attr__( 'Extras', 'fusion-builder' ),
						'subgroup'    => [
							'name' => 'filter_type',
							'tab'  => $key,
						],
						'callback'    => [
							'function' => 'fusion_update_filter_style',
							'args'     => [
								'selector_base' => $selector_base,
							],
						],
					],
				]
			);
		}

		return $filter_options;
	}

	/**
	 * Get filter styles
	 *
	 * @since 2.1
	 * @access public
	 * @param array  $atts The filter parameters.
	 * @param string $state Element state, regular or hover.
	 * @return string
	 */
	public static function get_filter_styles( $atts, $state = 'regular' ) {

		$state_suffix       = 'regular' === $state ? '' : '_hover';
		$other_state_suffix = 'regular' === $state ? '_hover' : '';

		$filters = [
			'filter_hue'        => [
				'property' => 'hue-rotate',
				'unit'     => 'deg',
				'default'  => '0',
			],
			'filter_saturation' => [
				'property' => 'saturate',
				'unit'     => '%',
				'default'  => '100',
			],
			'filter_brightness' => [
				'property' => 'brightness',
				'unit'     => '%',
				'default'  => '100',
			],
			'filter_contrast'   => [
				'property' => 'contrast',
				'unit'     => '%',
				'default'  => '100',
			],
			'filter_invert'     => [
				'property' => 'invert',
				'unit'     => '%',
				'default'  => '0',
			],
			'filter_sepia'      => [
				'property' => 'sepia',
				'unit'     => '%',
				'default'  => '0',
			],
			'filter_opacity'    => [
				'property' => 'opacity',
				'unit'     => '%',
				'default'  => '100',
			],
			'filter_blur'       => [
				'property' => 'blur',
				'unit'     => 'px',
				'default'  => '0',
			],
		];

		$filter_style = '';
		foreach ( $filters as $filter_id => $filter ) {
			$filter_id_state = $filter_id . $state_suffix;
			$filter_id_other = $filter_id . $other_state_suffix;
			if ( $filter['default'] !== $atts[ $filter_id_state ] || $filter['default'] !== $atts[ $filter_id_other ] ) {
				$filter_style .= $filter['property'] . '(' . $atts[ $filter_id_state ] . $filter['unit'] . ') ';
			}
		}

		return trim( $filter_style );
	}

	/**
	 * Get filter style element.
	 *
	 * @since 2.1
	 * @access public
	 * @param array  $atts The filter parameters.
	 * @param string $selector Element selector.
	 * @return string
	 */
	public static function get_filter_style_element( $atts, $selector ) {

		$filter_style = self::get_filter_styles( $atts, 'regular' );
		if ( '' !== $filter_style ) {
			$filter_style = $selector . '{filter: ' . $filter_style . ';}';
		}

		$filter_style_hover = self::get_filter_styles( $atts, 'hover' );
		if ( '' !== $filter_style_hover ) {

			// Add transition.
			$filter_style = str_replace( '}', 'transition: filter 0.3s ease;}', $filter_style );

			// Hover state.
			$filter_style .= $selector . ':hover{filter: ' . $filter_style_hover . ';}';
		}

		return '' !== $filter_style ? '<style type="text/css">' . $filter_style . '</style>' : '';
	}

	/**
	 * Get box-shadow params.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param  array $args The placeholder arguments.
	 * @return array
	 */
	public static function get_box_shadow_params( $args ) {
		return [
			[
				'type'        => 'radio_button_set',
				'heading'     => esc_attr__( 'Box Shadow', 'fusion-builder' ),
				'description' => esc_attr__( 'Set to "Yes" to enable box shadows.', 'fusion-builder' ),
				'param_name'  => 'box_shadow',
				'default'     => 'no',
				'group'       => esc_attr__( 'Design', 'fusion-builder' ),
				'value'       => [
					'yes' => esc_attr__( 'Yes', 'fusion-builder' ),
					'no'  => esc_attr__( 'No', 'fusion-builder' ),
				],
			],
			[
				'type'             => 'dimension',
				'remove_from_atts' => true,
				'heading'          => esc_attr__( 'Box Shadow Position', 'fusion-builder' ),
				'description'      => esc_attr__( 'Set the vertical and horizontal position of the box shadow. Positive values put the shadow below and right of the box, negative values put it above and left of the box. In pixels, ex. 5px.', 'fusion-builder' ),
				'param_name'       => 'dimension_box_shadow',
				'value'            => [
					'box_shadow_vertical'   => '',
					'box_shadow_horizontal' => '',
				],
				'group'            => esc_attr__( 'Design', 'fusion-builder' ),
				'dependency'       => [
					[
						'element'  => 'box_shadow',
						'value'    => 'yes',
						'operator' => '==',
					],
				],
			],
			[
				'type'        => 'range',
				'heading'     => esc_attr__( 'Box Shadow Blur Radius', 'fusion-builder' ),
				'description' => esc_attr__( 'Set the blur radius of the box shadow. In pixels.', 'fusion-builder' ),
				'param_name'  => 'box_shadow_blur',
				'value'       => '0',
				'min'         => '0',
				'max'         => '100',
				'step'        => '1',
				'group'       => esc_attr__( 'Design', 'fusion-builder' ),
				'dependency'  => [
					[
						'element'  => 'box_shadow',
						'value'    => 'yes',
						'operator' => '==',
					],
				],
			],
			[
				'type'        => 'range',
				'heading'     => esc_attr__( 'Box Shadow Spread Radius', 'fusion-builder' ),
				'description' => esc_attr__( 'Set the spread radius of the box shadow. A positive value increases the size of the shadow, a negative value decreases the size of the shadow. In pixels.', 'fusion-builder' ),
				'param_name'  => 'box_shadow_spread',
				'value'       => '0',
				'min'         => '-100',
				'max'         => '100',
				'step'        => '1',
				'group'       => esc_attr__( 'Design', 'fusion-builder' ),
				'dependency'  => [
					[
						'element'  => 'box_shadow',
						'value'    => 'yes',
						'operator' => '==',
					],
				],
			],
			[
				'type'        => 'colorpickeralpha',
				'heading'     => esc_attr__( 'Box Shadow Color', 'fusion-builder' ),
				'description' => esc_attr__( 'Controls the color of the box shadow.', 'fusion-builder' ),
				'param_name'  => 'box_shadow_color',
				'value'       => '',
				'group'       => esc_attr__( 'Design', 'fusion-builder' ),
				'dependency'  => [
					[
						'element'  => 'box_shadow',
						'value'    => 'yes',
						'operator' => '==',
					],
				],
			],
			[
				'type'        => 'radio_button_set',
				'heading'     => esc_attr__( 'Box Shadow Style', 'fusion-builder' ),
				'description' => esc_attr__( 'Set the style of the box shadow to either be an outer or inner shadow.', 'fusion-builder' ),
				'param_name'  => 'box_shadow_style',
				'default'     => '',
				'group'       => esc_attr__( 'Design', 'fusion-builder' ),
				'value'       => [
					''      => esc_attr__( 'Outer', 'fusion-builder' ),
					'inset' => esc_attr__( 'Inner', 'fusion-builder' ),
				],
				'dependency'  => [
					[
						'element'  => 'box_shadow',
						'value'    => 'yes',
						'operator' => '==',
					],
				],
			],
		];
	}

	/**
	 * Get box-shadow params.
	 * Same as the get_box_shadow_params, but with the "box_shadow_style" parameter removed.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param  array $args The placeholder arguments.
	 * @return array
	 */
	public static function get_box_shadow_no_inner_params( $args ) {
		$params = self::get_box_shadow_params( $args );
		foreach ( $params as $key => $param ) {
			if ( 'box_shadow_style' === $param['param_name'] ) {
				unset( $params[ $key ] );
			}
		}
		return $params;
	}

	/**
	 * Get box-shadow styles.
	 *
	 * @since 2.1
	 * @access public
	 * @param array $params The box-shadow parameters.
	 * @return string
	 */
	public static function get_box_shadow_styles( $params ) {
		$style  = fusion_library()->sanitize->get_value_with_unit( $params['box_shadow_horizontal'] );
		$style .= ' ' . fusion_library()->sanitize->get_value_with_unit( $params['box_shadow_vertical'] );
		$style .= ' ' . fusion_library()->sanitize->get_value_with_unit( $params['box_shadow_blur'] );
		$style .= ' ' . fusion_library()->sanitize->get_value_with_unit( $params['box_shadow_spread'] );
		$style .= ' ' . $params['box_shadow_color'];
		if ( isset( $params['box_shadow_style'] ) && $params['box_shadow_style'] ) {
			$style .= ' ' . $params['box_shadow_style'];
		}
		$style .= ';';

		return $style;
	}

	/**
	 * Get border radius params.
	 *
	 * @static
	 * @access public
	 * @since 2.1
	 * @param  array $args The placeholder arguments.
	 * @return array
	 */
	public static function get_border_radius_params( $args ) {

		return [
			[
				'type'             => 'dimension',
				'remove_from_atts' => true,
				'heading'          => esc_attr__( 'Border Radius', 'fusion-builder' ),
				'description'      => __( 'Enter values including any valid CSS unit, ex: 10px.', 'fusion-builder' ),
				'param_name'       => 'border_radius',
				'group'            => esc_attr__( 'Design', 'fusion-builder' ),
				'value'            => [
					'border_radius_top_left'     => '',
					'border_radius_top_right'    => '',
					'border_radius_bottom_right' => '',
					'border_radius_bottom_left'  => '',
				],
			],
		];
	}

	/**
	 * Checks if all border radius values are defined and adds a unit if needed.
	 * Sets default value if value is not set.
	 *
	 * @param  array $border_radius Border radius values.
	 * @return array
	 */
	public static function get_border_radius_array_with_fallback_value( $border_radius ) {

		return [
			'top_left'     => isset( $border_radius['top_left'] ) ? fusion_library()->sanitize->get_value_with_unit( $border_radius['top_left'] ) : '0px',
			'top_right'    => isset( $border_radius['top_right'] ) ? fusion_library()->sanitize->get_value_with_unit( $border_radius['top_right'] ) : '0px',
			'bottom_right' => isset( $border_radius['bottom_right'] ) ? fusion_library()->sanitize->get_value_with_unit( $border_radius['bottom_right'] ) : '0px',
			'bottom_left'  => isset( $border_radius['bottom_left'] ) ? fusion_library()->sanitize->get_value_with_unit( $border_radius['bottom_left'] ) : '0px',
		];
	}
}


// Add replacement filter.
add_filter( 'fusion_builder_element_params', 'Fusion_Builder_Element_Helper::placeholders_to_params', 10, 2 );
