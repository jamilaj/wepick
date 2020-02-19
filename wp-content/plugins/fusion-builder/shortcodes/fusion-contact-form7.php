<?php
/**
 * Add an element to fusion-builder.
 *
 * @package fusion-builder
 * @since 2.1
 */

if ( defined( 'WPCF7_PLUGIN' ) ) {
	if ( ! function_exists( 'fusion_builder_get_cf7_forms' ) ) {
		/**
		 * Returns array of contactform7 forms.
		 *
		 * @since 2.0
		 * @return array form keys array.
		 */
		function fusion_builder_get_cf7_forms() {

			$form_array = [ 0 => esc_attr__( 'Select a form', 'fusion-builder' ) ];

			$args = [
				'post_type'      => 'wpcf7_contact_form',
				'posts_per_page' => -1,
			];

			$forms = get_posts( $args );

			if ( is_array( $forms ) ) {
				foreach ( $forms as $form ) {
					$form_array[ $form->ID ] = $form->post_title;
				}
			}

			return $form_array;
		}
	}

	/**
	 * Map shortcode to Fusion Builder.
	 */
	function fusion_element_cf7() {
		fusion_builder_map(
			[
				'name'       => esc_attr__( 'Contact Form 7', 'fusion-builder' ),
				'shortcode'  => 'contact-form-7',
				'icon'       => 'fusiona-envelope',
				'preview'    => FUSION_BUILDER_PLUGIN_DIR . 'inc/templates/previews/fusion-contact-form7-preview.php',
				'preview_id' => 'fusion-builder-block-module-contact-form7-preview-template',
				'params'     => [
					[
						'type'        => 'select',
						'heading'     => esc_attr__( 'Select Form', 'fusion-builder' ),
						'description' => esc_attr__( 'Select a form.', 'fusion-builder' ),
						'param_name'  => 'id',
						'value'       => fusion_builder_get_cf7_forms(),
					],
				],
			]
		);
	}
	add_action( 'fusion_builder_before_init', 'fusion_element_cf7' );
}
