<?php
namespace ZIOR\WP\FilePond;

use Mimey\MimeTypes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retrieves the FilePond WP Integration configuration settings.
 *
 * This function fetches stored options related to file handling and
 * applies the 'wp_filepond_configuration' filter for customization.
 *
 * @return array An associative array of configuration settings.
 */
function get_configuration(): array {
	$configuration = array(
		'ajaxUrl'            => admin_url('admin-ajax.php'),
		'labelIdle'          => get_option( 'wp_fp_button_label', 'Browse Image' ),
		'allowImagePreview'  => (bool) get_option( 'wp_fp_enable_preview', false ),
		'imagePreviewHeight' => (int) get_option( 'wp_fp_preview_height', 100 ),
		'labelMaxFileSize'   => apply_filters( 'wp_filepond_label_max_file_size', '' ),
		'nonce'              => wp_create_nonce( 'wp_filepond_upload_nonce' ),
	);
	
	$file_type_error = get_option( 'wp_fp_file_type_error', '' );

	if ( ! empty( $file_type_error ) ) {
		$configuration['labelFileTypeNotAllowed']  = $file_type_error;
	}

	$file_size_error = get_option( 'wp_fp_file_size_error', '' );

	if ( ! empty( $file_size_error ) ) {
		$configuration['labelMaxFileSizeExceeded'] = $file_size_error;
	}

	return apply_filters( 'wp_filepond_configuration', $configuration );
}

/**
 * Retrieves the MIME type for a given file extension.
 *
 * Uses the MimeTypes class to determine the appropriate MIME type.
 *
 * @param string $ext The file extension (e.g., 'jpg', 'png', 'pdf').
 * @return string The corresponding MIME type or an empty string if unknown.
 */
function get_mime_type( string $ext ): string {
	$mimes = new MimeTypes();

	return $mimes->getMimeType( $ext ) ?? '';
}

function decrypt_data( $data ) {
    $data = base64_decode( $data );
	
	return json_decode( $data, true );
}
