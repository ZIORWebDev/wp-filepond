<?php
namespace ZIOR\WP\FilePond\Elementor;

use ElementorPro\Modules\Forms\Fields\Field_Base;
use Elementor\Controls_Manager;
use ElementorPro\Plugin;
use ElementorPro\Modules\Forms\Classes;
use ElementorPro\Modules\Forms\Widgets\Form;
use ElementorPro\Core\Utils;

use function ZIOR\WP\FilePond\get_mime_type;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class FilePondUpload extends Field_Base {
	/**
	 * Retrieves FilePond fields from the given fields array and sets the 'attachment_type'.
	 *
	 * This function filters the provided fields array to return only those with
	 * 'field_type' set to 'filepond-upload'. Additionally, it assigns the value
	 * of 'wp_fp_attachment_type' to 'attachment_type' if it exists.
	 *
	 * @param array $fields The array of form fields.
	 * @return array The filtered array containing only FilePond fields with updated 'attachment_type'.
	 */
	private function get_filepond_fields( array $fields ): array {
		$filepond_fields = [];

		foreach ( $fields as $field ) {
			// Ensure the field is a FilePond upload field
			if ( ! isset( $field['field_type'] ) || 'filepond-upload' !== $field['field_type'] ) {
				$filepond_fields[] = $field;

				continue;
			}

			// Set 'attachment_type' to 'wp_fp_attachment_type' if it exists
			if ( isset( $field['wp_fp_attachment_type'] ) ) {
				$field['attachment_type'] = $field['wp_fp_attachment_type'];
			}

			$filepond_fields[] = $field;
		}

		return $filepond_fields;
	}

	/**
	 * creates array of upload sizes based on server limits
	 * to use in the file_sizes control
	 * @return array
	 */
	private function get_upload_file_size_options() {
		$max_file_size = wp_max_upload_size() / pow( 1024, 2 ); //MB

		$sizes = [];

		for ( $file_size = 1; $file_size <= $max_file_size; $file_size++ ) {
			$sizes[ $file_size ] = $file_size . 'MB';
		}

		return $sizes;
	}

	/**
	 * Constructor.
	 * Hooks AJAX actions for handling file uploads.
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'elementor_pro/forms/process', array( $this, 'set_file_fields_values' ), 20, 2 );
	}

	public function get_type() {
		return 'filepond-upload';
	}

	public function get_name() {
		return esc_html__( 'FilePond Upload', 'filepond-wp-integration' );
	}

	/**
	 * @param Widget_Base $widget
	 */
	public function update_controls( $widget ) {
		$elementor    = Plugin::elementor();
		$control_data = $elementor->controls_manager->get_control_from_stack( $widget->get_unique_name(), 'form_fields' );

		if ( is_wp_error( $control_data ) ) {
			return;
		}

		$field_controls = array(
			'wp_fp_max_file_size' => array(
				'name'         => 'wp_fp_max_file_size',
				'label'        => esc_html__( 'Max. File Size', 'filepond-wp-integration' ),
				'type'         => Controls_Manager::SELECT,
				'condition'    => array(
					'field_type' => $this->get_type(),
				),
				'default'      => get_option( 'wp_fp_max_file_size', 100 ),
				'options'      => $this->get_upload_file_size_options(),
				'description'  => esc_html__( 'If you need to increase max upload size please contact your hosting.', 'filepond-wp-integration' ),
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_content_tab',
				'tabs_wrapper' => 'form_fields_tabs',
			),
			'wp_fp_file_types' => array(
				'name'         => 'wp_fp_file_types',
				'label'        => esc_html__( 'Allowed File Types', 'filepond-wp-integration' ),
				'label_block'  => true,
				'type'         => Controls_Manager::TEXT,
				'default'      => get_option( 'wp_fp_file_types_allowed', '' ), 
				'ai'           => array(
					'active' => false,
				),
				'condition'    => array(
					'field_type' => $this->get_type(),
				),
				'description'  => esc_html__( 'Enter the allowed file types, separated by a comma (jpg, gif, pdf, etc).', 'filepond-wp-integration' ),
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_content_tab',
				'tabs_wrapper' => 'form_fields_tabs',
			),
			'wp_fp_allow_multiple_upload' => array(
				'name'         => 'wp_fp_allow_multiple_upload',
				'label'        => esc_html__( 'Multiple Files', 'filepond-wp-integration' ),
				'type'         => Controls_Manager::SWITCHER,
				'condition'    => array(
					'field_type' => $this->get_type(),
				),
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_content_tab',
				'tabs_wrapper' => 'form_fields_tabs',
			),
			'wp_fp_max_files' => array(
				'name'         => 'wp_fp_max_files',
				'label'        => esc_html__( 'Max. Files', 'filepond-wp-integration' ),
				'type'         => Controls_Manager::NUMBER,
				'condition'    => array(
					'field_type'           => $this->get_type(),
					'wp_fp_allow_multiple_upload' => 'yes',
				),
				'tab'          => 'content',
				'inner_tab'    => 'form_fields_content_tab',
				'tabs_wrapper' => 'form_fields_tabs',
			),
		);

		$control_data['fields'] = $this->inject_field_controls( $control_data['fields'], $field_controls );

		$widget->update_control( 'form_fields', $control_data );
	}

	/**
	 * Render the file upload input field.
	 *
	 * @param array  $item        The field settings array.
	 * @param int    $item_index  The index of the field in the form.
	 * @param object $form        The form object responsible for rendering.
	 */
	public function render( $item, $item_index, $form ) {
		// Add base attributes for the file upload field.
		$form->add_render_attribute( 'input' . $item_index, 'class', 'filepond-wp-integration-upload' );
		$form->add_render_attribute( 'input' . $item_index, 'type', 'file', true );

		// Handle multiple file uploads.
		if ( ! empty( $item['wp_fp_allow_multiple_upload'] ) ) {
			$form->add_render_attribute( 'input' . $item_index, 'multiple', 'multiple' );
			$form->add_render_attribute(
				'input' . $item_index,
				'name',
				$form->get_attribute_name( $item ) . '[]',
				true
			);
		}

		$file_types      = array();
		$file_extensions = array_map( 'trim', explode( ',', $item['wp_fp_file_types'] ) );

		foreach( $file_extensions as $extension ) {
			$file_type = get_mime_type( $extension );

			if ( empty( $file_type ) ) {
				continue;
			}

			$file_types[] = $file_type;
		}

		$file_types   = array_filter( $file_types );
		$default_size = wp_max_upload_size() / 1024 / 1024;
		$attributes   = array(
				'data-filesize'  => esc_attr( $item['wp_fp_max_file_size'] ?? $default_size ),
				'data-filetypes' => esc_attr( ! empty( $file_types ) ? implode( ',', $file_types ) : '' ),
				'data-label'     => esc_attr( $item['field_label'] ?? '' ),
				'data-maxfiles'  => esc_attr( $item['wp_fp_max_files'] ?? '' ),
			);

		$form->add_render_attribute( 'input' . $item_index, $attributes );

		echo '<input ' . $form->get_render_attribute_string( 'input' . $item_index ) . '>';
	}

	/**
	 * validate uploaded file field
	 *
	 * @param array                $field
	 * @param Classes\Form_Record  $record
	 * @param Classes\Ajax_Handler $ajax_handler
	 */
	public function validation( $field, Classes\Form_Record $record, Classes\Ajax_Handler $ajax_handler ) {
		// is the file required and missing?
		if ( $field['required'] && empty( $field['value'] ) ) {
			$ajax_handler->add_error( $field['id'], __( 'Upload a valid file', 'filepond-wp-integration' ) );

			return;
		}

		return true;
	}

	/**
	 * Used to set the upload filed values with
	 * value => file url
	 * raw_value => file path
	 *
	 * @param Classes\Form_Record  $record
	 * @param Classes\Ajax_Handler $ajax_handler
	 */
	public function set_file_fields_values( Classes\Form_Record $record, Classes\Ajax_Handler $ajax_handler ) {
		$files = $record->get( 'files' );

		if ( empty( $files ) ) {
			return;
		}

		foreach ( $files as $id => $files_array ) {
			$record->update_field( $id, 'value', implode( ' , ', $files_array['url'] ) );
			$record->update_field( $id, 'raw_value', implode( ' , ', $files_array['path'] ) );
		}
	}
}
