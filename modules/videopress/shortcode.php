<?php

/**
 * VideoPress Shortcode Handler
 *
 * This file may or may not be included from the Jetpack VideoPress module.
 */

/**
 * Translate a 'videopress' or 'wpvideo' shortcode and arguments into a video player display.
 *
 * Expected input formats:
 *
 * [videopress OcobLTqC]
 * [wpvideo OcobLTqC]
 *
 * @link http://codex.wordpress.org/Shortcode_API Shortcode API
 * @param array $attr shortcode attributes
 * @return string HTML markup or blank string on fail
 */
function videopress_shortcode_callback( $attr ) {
	global $content_width;

	/**
	 * We only accept GUIDs as a first unnamed argument.
	 */
	$guid = $attr[0];

	/**
	 * Make sure the GUID passed in matches how actual GUIDs are formatted.
	 */
	if ( ! videopress_is_valid_guid( $guid ) ) {
		return '';
	}

	/**
	 * Set the defaults
	 */
	$defaults = array(
		'w'               => 0,     // Width of the video player, in pixels
		'at'              => 0,     // How many seconds in to initially seek to
		'hd'              => false, // Whether to display a high definition version
		'loop'            => false, // Whether to loop the video repeatedly
		'freedom'         => false, // Whether to use only free/libre codecs
		'autoplay'        => false, // Whether to autoplay the video on load
		'permalink'       => true,  // Whether to display the permalink to the video
		'flashonly'       => false, // Whether to support the Flash player exclusively
		'defaultlangcode' => false, // Default language code
	);

	$attr = shortcode_atts( $defaults, $attr, 'videopress' );

	/**
	 * Cast the attributes, post-input.
	 */
	$attr['width']   = absint( $attr['w'] );
	$attr['hd']      = (bool) $attr['hd'];
	$attr['freedom'] = (bool) $attr['freedom'];

	/**
	 * If the provided width is less than the minimum allowed
	 * width, or greater than `$content_width` ignore.
	 */
	if ( $attr['width'] < VIDEOPRESS_MIN_WIDTH ) {
		$attr['width'] = 0;
	} elseif ( isset( $content_width ) && $content_width > VIDEOPRESS_MIN_WIDTH && $attr['width'] > $content_width ) {
		$attr['width'] = 0;
	}

	/**
	 * If there was an invalid or unspecified width, set the width equal to the theme's `$content_width`.
	 */
	if ( 0 === $attr['width'] && isset( $content_width ) && $content_width >= VIDEOPRESS_MIN_WIDTH ) {
		$attr['width'] = $content_width;
	}

	/**
	 * If the width isn't an even number, reduce it by one (making it even).
	 */
	if ( 1 === ( $attr['width'] % 2 ) ) {
		$attr['width'] --;
	}

	/**
	 * Filter the default VideoPress shortcode options.
	 *
	 * @module videopress
	 *
	 * @since 2.5.0
	 *
	 * @param array $args Array of VideoPress shortcode options.
	 */
	$options = apply_filters( 'videopress_shortcode_options', array(
		'at'              => (int) $attr['at'],
		'hd'              => $attr['hd'],
		'loop'            => $attr['autoplay'] || $attr['loop'],
		'freedom'         => $attr['freedom'],
		'autoplay'        => $attr['autoplay'],
		'permalink'       => $attr['permalink'],
		'force_flash'     => (bool) $attr['flashonly'],
		'defaultlangcode' => $attr['defaultlangcode'],
		'forcestatic'     => false, // This used to be a displayed option, but now is only
		// accessible via the `videopress_shortcode_options` filter.
	) );

	// Register VideoPress scripts
	wp_register_script( 'videopress', 'https://v0.wordpress.com/js/videopress.js', array( 'jquery', 'swfobject' ), '1.09' );

	require_once( dirname( __FILE__ ) . '/class.videopress-video.php' );
	require_once( dirname( __FILE__ ) . '/class.videopress-player.php' );

	$player = new VideoPress_Player( $guid, $attr['width'], $options );

	if ( is_feed() ) {
		return $player->asXML();
	} else {
		return $player->asHTML();
	}
}
add_shortcode( 'videopress', 'videopress_shortcode_callback' );
add_shortcode( 'wpvideo',    'videopress_shortcode_callback' );

/**
 * By explicitly declaring the provider here, we can speed things up by not relying on oEmbed discovery.
 */
wp_oembed_add_provider( '#^https?://videopress.com/v/.*#', 'http://public-api.wordpress.com/oembed/1.0/', true );

/**
 * Adds a `for` query parameter to the oembed provider request URL.
 * @param String $oembed_provider
 * @return String $ehnanced_oembed_provider
 */
function videopress_add_oembed_for_parameter( $oembed_provider ) {
	if ( false === stripos( $oembed_provider, 'videopress.com' ) ) {
		return $oembed_provider;
	}
	return add_query_arg( 'for', parse_url( home_url(), PHP_URL_HOST ), $oembed_provider );
}
add_filter( 'oembed_fetch_url', 'videopress_add_oembed_for_parameter' );

/**
 * WordPress Shortcode Editor View JS Code
 */
function videopress_handle_editor_view_js() {
	global $content_width;
	$current_screen = get_current_screen();
	if ( ! isset( $current_screen->id ) || $current_screen->base !== 'post' ) {
		return;
	}

	add_action( 'admin_print_footer_scripts', 'videopress_editor_view_js_templates' );

	wp_enqueue_script( 'videopress-editor-view', plugins_url( 'js/editor-view.js', __FILE__ ), array( 'wp-util', 'jquery' ), false, true );
	wp_localize_script( 'videopress-editor-view', 'vpEditorView', array(
		'home_url_host'     => parse_url( home_url(), PHP_URL_HOST ),
		'min_content_width' => VIDEOPRESS_MIN_WIDTH,
		'content_width'     => $content_width,
		'modal_labels'      => array(
			'title'     => __( 'VideoPress Shortcode', 'jetpack' ),
			'guid'      => __( 'Video ID', 'jetpack' ),
			'w'         => __( 'Video width (in pixels)', 'jetpack' ),
			'at'        => __( 'Start video after (in seconds)', 'jetpack' ),
			'hd'        => __( 'High definition on by default', 'jetpack' ),
			'permalink' => __( 'Link the video title to its URL on VideoPress.com', 'jetpack' ),
			'autoplay'  => __( 'Autoplay video on page load', 'jetpack' ),
			'loop'      => __( 'Loop video playback', 'jetpack' ),
			'freedom'   => __( 'Use only Open Source codecs (may degrade performance)', 'jetpack' ),
			'flashonly' => __( 'Use legacy Flash Player (not recommended)', 'jetpack' ),
		)
	) );
}
add_action( 'admin_notices', 'videopress_handle_editor_view_js' );

/**
 * WordPress Editor Views
 */
function videopress_editor_view_js_templates() {
	/**
	 * This template uses the following parameters, and displays the video as an iframe:
	 *  - data.guid     // The guid of the video.
	 *  - data.width    // The width of the iframe.
	 *  - data.height   // The height of the iframe.
	 *  - data.urlargs  // Arguments serialized into a get string.
	 *
	 * In addition, the calling script will need to ensure that the following
	 * JS file is added to the header of the editor iframe:
	 *  - https://s0.wp.com/wp-content/plugins/video/assets/js/next/videopress-iframe.js
	 */
	?>
	<script type="text/html" id="tmpl-videopress_iframe_vnext">
		<div class="tmpl-videopress_iframe_next">
			<iframe style="display: block;" width="{{ data.width }}" height="{{ data.height }}" src="https://videopress.com/embed/{{ data.guid }}?{{ data.urlargs }}" frameborder='0' allowfullscreen></iframe>
		</div>
	</script>
	<?php
}
