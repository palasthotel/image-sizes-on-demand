<?php

/**
 * Plugin Name: Image Sizes on Demand
 * Description: Creates the new size of an image, if it is requested and does not already exist.
 * Version:     1.2
 * Author: PALASTHOTEL by Julia Krischik <jk@palasthotel.de>
 * Author URI: http://palasthotel.de/
 */
class ImageSizesOnDemand {

	private $additional_sizes;
	private $accepted_mime_types = array( 'image/jpg', 'image/jpeg', 'image/png', 'image/gif' );
	private $disable_generating_custom_image_sizes_key = 'disable_generating_custom_image_size';
	private $namespace = 'image-sizes-on-demand';

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'lost_request_handler' ) );

		//settings page
		add_action( 'admin_menu', array( $this, 'menu_pages' ) );

		//prevent generation of custom image sizes on upload if setting is active
		$disable_generating_custom_image_sizes = get_option( $this->disable_generating_custom_image_sizes_key, false );
		if ( $disable_generating_custom_image_sizes
		     && isset( $_REQUEST['action'] )
		     && $_REQUEST['action'] === "upload-attachment"
		) {
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'save_attachment_metadata_sizes' ), 10, 2 );
			add_filter( 'wp_handle_upload_prefilter', array( $this, 'save_and_clear_additional_sizes' ) );
			add_filter( 'attachment_thumbnail_args', array( $this, 'restore_additional_sizes' ) );
		}
	}

	public function menu_pages() {
		add_submenu_page( 'options-general.php', 'Image Sizes On Demand', 'Image Sizes On Demand', 'manage_options', $this->namespace, array(
			$this,
			"render_settings"
		) );
	}

	/**
	 *  renders settings page
	 */
	public function render_settings() {
		if ( isset( $_POST['submit'] ) ) {
			if ( isset( $_POST[ $this->disable_generating_custom_image_sizes_key ] ) ) {
				update_option( $this->disable_generating_custom_image_sizes_key, true );
			} else {
				delete_option( $this->disable_generating_custom_image_sizes_key );
			}
		}
		$disable_generating_custom_image_sizes = get_option( $this->disable_generating_custom_image_sizes_key, false );

		require plugin_dir_path( __FILE__ ) . '/settings.php';
	}


	function save_and_clear_additional_sizes( $upload ) {
		//only do this stuff on upload
		if ( ! isset( $_REQUEST['action'] )
		     || $_REQUEST['action'] !== "upload-attachment"
		     || ! in_array( $upload['type'], $this->accepted_mime_types )
		) {
			return $upload;
		}

		global $_wp_additional_image_sizes;

		$this->additional_sizes     = $_wp_additional_image_sizes;
		$_wp_additional_image_sizes = array();

		return $upload;
	}

	function restore_additional_sizes( $meta ) {
		//only do this if we have previously saved something
		if ( empty( $this->additional_sizes ) ) {
			return;
		}

		global $_wp_additional_image_sizes;

		$_wp_additional_image_sizes = $this->additional_sizes;

		return $meta;
	}

	function save_attachment_metadata_sizes( $metadata, $attachment_id ) {
		//only do this stuff on upload
		if ( ! isset( $_REQUEST['action'] ) || $_REQUEST['action'] !== "upload-attachment" ) {
			return $metadata;
		}

		//get the file
		$file      = get_attached_file( $attachment_id );
		$mime_type = mime_content_type( $file );

		//only do this for images
		if ( ! in_array( $mime_type, $this->accepted_mime_types ) ) {
			return $metadata;
		}

		//get file editor
		$editor = wp_get_image_editor( $file );

		if ( ! is_wp_error( $editor ) ) {

			//get file extension
			$extension = strtolower( pathinfo( $metadata['file'], PATHINFO_EXTENSION ) );

			//iterate over sizes, build their data and add to file metadata
			foreach ( $this->additional_sizes as $size => $size_data ) {

				//$resize = $editor->resize($size_data['width'], $size_data['height'], $size_data['crop']);
				$dims = image_resize_dimensions( $metadata['width'], $metadata['height'], $size_data['width'], $size_data['height'], $size_data['crop'] );

				//only add size if image is big enough
				if ( $dims ) {
					$new_width  = $dims[4];
					$new_height = $dims[5];

					$filename = basename( $editor->generate_filename( "{$new_width}x{$new_height}", null, $extension ) );

					$metadata['sizes'][ $size ] = array(
						"file"      => $filename,
						"width"     => $size_data['width'],
						"height"    => $size_data['height'],
						"mime-type" => $mime_type
					);
				}

			}
		}

		return $metadata;
	}


	/**
	 * Regenerate image sizes when the 404 handler is invoked and it's for a non-existant image
	 */
	function lost_request_handler() {
		if ( ! is_404() ) {
			return;
		}

		//check if this request is for an image
		if ( preg_match( '/wp-content(\/[^\/]+(\/[0-9]{4}\/[0-9]{2})?\/){1}(.*)-([0-9]+)x([0-9]+)?\.(jpg|jpeg|png|gif)/i', $_SERVER['REQUEST_URI'], $matches ) ) {
			$folder   = $matches[2];
			$filename = $matches[3];
			$width    = $matches[4];
			$height   = $matches[5];
			$type     = $matches[6];

			$this->generate_image_size( $filename, $type, $width . "x" . $height, $folder );
		}
	}

	/**
	 * Generate image sizes
	 *
	 * @param $file_name
	 * @param $image_type
	 * @param $image_size
	 * @param $folder
	 */
	public function generate_image_size( $file_name, $image_type, $image_size, $folder ) {

		$upload_dir = wp_upload_dir();

		$image_id = attachment_url_to_postid( $upload_dir['baseurl'] . $folder . '/' . $file_name . '.' . $image_type );

		$fullsizepath = get_attached_file( $image_id );

		if ( false === $fullsizepath || ! file_exists( $fullsizepath ) ) {
			return;
		}

		@set_time_limit( 60 ); // time limit one minute

		include_once( ABSPATH . 'wp-admin/includes/image.php' );

		$metadata = wp_generate_attachment_metadata( $image_id, $fullsizepath );

		if ( is_wp_error( $metadata ) ) {
			return;
		}
		if ( empty( $metadata ) ) {
			return;
		}

		// If this fails, then it just means that nothing was changed (old value == new value)
		wp_update_attachment_metadata( $image_id, $metadata );

		//return the image
		$name = $upload_dir['basedir'] . $folder . '/' . $file_name . '-' . $image_size . '.' . $image_type;
		$fp   = fopen( $name, 'rb' );

		header( "Content-Type: image/jpg" );
		header( "Content-Length: " . filesize( $name ) );

		fpassthru( $fp );
		exit;
	}
}

new ImageSizesOnDemand();
