<?php
/**
 * Plugin Name:     Native Lazy Load
 * Plugin URI:      https://github.com/wphandle/native-lazy-load
 * Description:     Native image lazy-loading for the web!
 * Author:          WP Handle, Mustafa Uysal
 * Author URI:      https://wphandle.com
 * Text Domain:     native-lazy-load
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * Ported form https://github.com/Angrycreative/bj-lazy-load
 *
 * ############# Extremely Experimental #############
 *
 * @package         Native_Lazy_Load
 */


class WPH_Native_Lazy_Load {

	function __construct() {
		add_action( 'wp', array( $this, 'init' ), PHP_INT_MAX ); // run this as late as possible
	}

	public static function load_type() {
		return apply_filters( 'native_lazy_load_load_type', 'lazy' );
	}

	public function init() {
		add_filter( 'native_lazy_load_filter', array( __CLASS__, 'filter_images' ) );
		add_filter( 'native_lazy_load_filter', array( __CLASS__, 'filter_iframes' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter' ), 200 );
		add_filter( 'widget_text', array( __CLASS__, 'filter' ), 200 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'filter' ), 200 );
		add_filter( 'get_avatar', array( __CLASS__, 'filter' ), 200 );
		add_filter( 'native_lazy_load_html', array( __CLASS__, 'filter' ) );
	}

	public static function filter_images( $content ) {
		$match_content = self::_get_content_haystack( $content );
		$matches       = array();
		preg_match_all( '/<img[\s\r\n]+.*?>/is', $match_content, $matches );
		foreach ( $matches[0] as $imgHTML ) {
			// don't to the replacement if the image is a data-uri
			if ( ! preg_match( "/src=['\"]data:image/is", $imgHTML ) ) {
				$lazy_load_attr = array( 'loading="' . self::load_type() . '" src=', 'loading="' . self::load_type() . '" srcset=', 'class="lazyload ' );
				$replace        = str_replace( array( 'src=', 'srcset=', 'class="' ), $lazy_load_attr, $imgHTML );
				$content        = str_replace( $imgHTML, $replace, $content );
			}
		}

		return $content;
	}

	public static function filter( $content ) {
		// Last chance to bail out before running the filter
		$run_filter = apply_filters( 'native_lazy_load_run_filter', true );
		if ( ! $run_filter ) {
			return $content;
		}
		/**
		 * Filter the content
		 *
		 * @param string $content The HTML string to filter
		 */
		$content = apply_filters( 'native_lazy_load_filter', $content );

		return $content;
	}


	/**
	 * Replace iframes with placeholders in the content
	 *
	 * @param string $content The HTML to do the filtering on
	 *
	 * @return string The HTML with the iframes replaced
	 */
	public static function filter_iframes( $content ) {
		$content = str_replace( '<iframe', '<iframe loading="' . self::load_type() . '"', $content );

		return $content;
	}

	protected static function _get_content_haystack( $content ) {
		$content = self::remove_noscript( $content );
		$content = self::remove_skip_classes_elements( $content );

		return $content;
	}

	/**
	 * Remove <noscript> elements from HTML string
	 *
	 * @author sigginet
	 *
	 * @param string $content The HTML string
	 *
	 * @return string The HTML string without <noscript> elements
	 */
	public static function remove_noscript( $content ) {
		return preg_replace( '/<noscript.*?(\/noscript>)/i', '', $content );
	}


	/**
	 * Remove HTML elements with certain classnames (or IDs) from HTML string
	 *
	 * @param string $content The HTML string
	 *
	 * @return string The HTML string without the unwanted elements
	 */
	public static function remove_skip_classes_elements( $content ) {
		$skip_classes = self::_get_skip_classes( 'html' );
		/*
		http://stackoverflow.com/questions/1732348/regex-match-open-tags-except-xhtml-self-contained-tags/1732454#1732454
		We can’t do this, but we still do it.
		*/
		$skip_classes_quoted = array_map( 'preg_quote', $skip_classes );
		$skip_classes_ORed   = implode( '|', $skip_classes_quoted );
		$regex               = '/<\s*\w*\s*class\s*=\s*[\'"]?(|.*\s)?' . $skip_classes_ORed . '(|\s.*)?[\'"]?.*?>/isU';

		return preg_replace( $regex, '', $content );
	}

	/**
	 * Get the skip classes
	 *
	 * @param string $content_type The content type (image/iframe etc)
	 *
	 * @return array An array of strings with the class names
	 */
	protected static function _get_skip_classes( $content_type ) {
		/**
		 * Filter the class names to skip
		 *
		 * @param array  $skip_classes The current classes to skip
		 * @param string $content_type The current content type
		 */
		$skip_classes = apply_filters( 'native_lazy_load_skip_classes', array( 'lazy' ), $content_type );

		return $skip_classes;
	}


}

new WPH_Native_Lazy_Load();

add_action( 'wp_footer', 'native_lazy_load_compat_js' );

function native_lazy_load_compat_js() {
	?>
	<script>
        (async() => {
            if('loading' in HTMLImageElement.prototype
        )
        {
            const images = document.querySelectorAll("img.lazyload");
            images.forEach(img => {
                img.src = img.dataset.src;
        })
            ;
        }
        else
        {
            // Dynamically import the LazySizes library
            const lazySizesLib = await import('https://cdnjs.cloudflare.com/ajax/libs/lazysizes/4.1.5/lazysizes.min.js');
            // Initiate LazySizes (reads data-src & class=lazyload)
            lazySizes.init(); // lazySizes works off a global.
        }
        })
        ();
	</script>
	<?php
}