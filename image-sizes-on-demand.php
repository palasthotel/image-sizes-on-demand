<?php

/**
 * Plugin Name: Image Sizes on Demand
 * Description: Creates the new size of an image, if it is requested and does not already exist.
 * Version:     1.0
 * Author: PALASTHOTEL by Julia Krischik <jk@palasthotel.de>
 * Author URI: http://palasthotel.de/
 */
class ImageSizesOnDemand {

	public function __construct() {
		add_action('template_redirect', array($this, 'lost_request_handler'));
	}


	/**
	 * Regenerate image sizes when the 404 handler is invoked and it's for a non-existant image
	 */
	function lost_request_handler() {
		if ( !is_404() ) return;

		//check if this request is for an image
		if (preg_match('/wp-content(\/[^\/]+(\/[0-9]{4}\/[0-9]{2})?\/){1}(.*)-([0-9]+)x([0-9]+)?\.(jpg|png|gif)/i',$_SERVER['REQUEST_URI'],$matches)) {
			$folder = $matches[2];
			$filename = $matches[3];
			$width = $matches[4];
			$height = $matches[5];
			$type = $matches[6];

			$this->generate_image_size($filename, $type, $width."x".$height, $folder);
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

		$image_id = attachment_url_to_postid($upload_dir['baseurl'] . $folder . '/' . $file_name . '.' . $image_type);

		$fullsizepath = get_attached_file( $image_id );

		if ( false === $fullsizepath || ! file_exists( $fullsizepath ) )
			return;

		@set_time_limit( 60 ); // time limit one minute

		include_once( ABSPATH . 'wp-admin/includes/image.php' );

		$metadata = wp_generate_attachment_metadata( $image_id, $fullsizepath );

		if ( is_wp_error( $metadata ) )
			return;
		if ( empty( $metadata ) )
			return;

		// If this fails, then it just means that nothing was changed (old value == new value)
		wp_update_attachment_metadata( $image_id, $metadata );

		//return the image
		$name       = $upload_dir['basedir'] . $folder . '/' . $file_name . '-' . $image_size . '.' . $image_type;
		$fp         = fopen( $name, 'rb' );

		header( "Content-Type: image/jpg" );
		header( "Content-Length: " . filesize( $name ) );

		fpassthru( $fp );
		exit;
	}
}

new ImageSizesOnDemand();
