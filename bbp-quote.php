<?php
/*
Plugin Name: bbP Quote
Description: Quote forum posts in bbPress with this nifty plugin!
Author: r-a-y
Author URI: http://profiles.wordpress.org/r-a-y
Version: 0.1
License: GPLv2 or later
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'bbP_Quote' ) ) :

class bbP_Quote {
	/**
	 * Init method.
	 */
	public static function init() {
		return new self();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// page injection
		add_action( 'bbp_theme_before_reply_form_submit_wrapper', array( $this, 'javascript' ) );

		// quote links
		add_action( 'bbp_theme_before_topic_admin_links',         array( $this, 'add_quote' ) );
		add_action( 'bbp_theme_before_reply_admin_links',         array( $this, 'add_quote' ) );

		// kses additions for 2.3+
		if ( version_compare( bbp_get_version(), '2.2.4' ) > 0 ) {
			add_filter( 'bbp_kses_allowed_tags',              array( $this, 'allowed_attributes' ) );

		// kses additions for 2.2.x
		} else {
			add_filter( 'bbp_new_reply_pre_content',          array( $this, 'wp_filter_kses' ), 9 );
			add_filter( 'bbp_new_topic_pre_content',          array( $this, 'wp_filter_kses' ), 9 );
		}

		// remove kses additions
		add_action( 'bbp_theme_after_reply_form_content',         array( $this, 'remove_bbp_quote_attributes' ) );

		// inline CSS
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Outputs the javascript.
	 *
	 * @todo Move JS to static file. Localize citation string.
	 */
	public function javascript() {
	?>

		<script type="text/javascript">
			function bbp_insert_quote( user, text, permalink ){
				var content = '<blockquote class="bbp-the-quote" cite="' + permalink + '"><em class="bbp-the-quote-cite"><a href="' + permalink + '">' + user + ' wrote:</a></em>' + text + '</blockquote>' + "\r\n\n";

				// check if tinyMCE is active and visible
				if ( tinyMCE && tinyMCE.activeEditor && ! tinyMCE.activeEditor.isHidden() ) {
					tinyMCE.activeEditor.selection.setContent( content );
					tinyMCE.activeEditor.focus();

				// regular textarea
				} else {
					var textarea = jQuery("#bbp_reply_content");

					// add quote
					textarea.val( textarea.val() + content );

					// scroll to bottom of textarea and focus
					textarea.animate(
						{scrollTop: textarea[0].scrollHeight - textarea.height()},
						800
					).focus();
				}
			}

			jQuery(document).ready( function($) {
				$(".bbp-quote").on("click", function(){
				<?php if ( version_compare( bbp_get_version(), '2.2.4' ) > 0 ) : ?>
					var id        = $(this).closest('.bbp-reply-header').prop('id');
					var permalink = $('#' + id + ' .bbp-reply-permalink').prop('href');
				<?php else : ?>
					var header    = $(this).closest('.bbp-reply-header');
					var id        = header.next().prop('id');
					var permalink = header.find('.bbp-reply-permalink').prop('href');
				<?php endif; ?>
					var author    = $('.' + id + ' .bbp-author-name').text();
					var content   = $('.' + id + ' .bbp-reply-content').html();

					// scroll to form
					$("html, body").animate(
						{scrollTop: $("#new-post").offset().top},
						500
					);

					// insert quote
					bbp_insert_quote( author, content, permalink );
				});

				// when clicking on a citation, do fancy scroll
				$(".bbp-the-quote-cite a").on("click", function(e){
					var id = $(this.hash);

				        e.preventDefault();
					jQuery("html, body").animate(
						{scrollTop: jQuery(id).offset().top},
						500
					);

				        location.hash = id.selector;
				});
			});
		</script>

	<?php
	}

	/**
	 * Add "Quote" link to admin links.
	 */
	public function add_quote() {
		if ( ! is_user_logged_in() )
			return;

		// strip_tags() used for bbP 2.2.x
		$has_admin_links = strip_tags( bbp_get_reply_admin_links() );

	?>

		<span class="bbp-admin-links">
			<?php if ( ! empty( $has_admin_links ) ) : ?>&nbsp;|<?php endif; ?>
			<a class="bbp-quote" href="javascript:;"><?php _e( 'Quote', 'bbp-quote' ); ?></a>
		</span>

	<?php
	}

	/**
	 * Add 'class' attribute to 'blockquote' and 'em' elements.
	 */
	public function allowed_attributes( $retval ) {
		$retval['blockquote']['class'] = array();
		$retval['em']['class']         = array();

		return $retval;
	}

	/**
	 * For the "Allowed Tags" block that shows up for non-admins on the frontend,
	 * remove our custom kses additions from {@link bbP_Quote::allowed_attributes()}
	 * so they will not show up there.
	 */
	public function remove_bbp_quote_attributes() {
		// bbPress 2.3+
		if ( version_compare( bbp_get_version(), '2.2.4' ) > 0 ) {
			remove_filter( 'bbp_kses_allowed_tags',     array( $this, 'allowed_attributes' ) );

		// bbPress <= 2.2.4
		} else {
			remove_filter( 'bbp_new_reply_pre_content', array( $this, 'wp_filter_kses' ), 9 );
		}

	}


	/**
	 * Hack for bbPress 2.2.x.
	 *
	 * bbPress 2.3 has its own kses filtering function - {@link bbp_filter_kses()}.
	 *
	 * Unfortunately, 2.2.x does not and uses WP's old-school {@link wp_filter_kses()}.
	 * Fortunately, we can workaround this with the $allowedtags global.
	 */
	public function wp_filter_kses( $retval = 0 ) {
		global $allowedtags;

		$allowedtags = $this->allowed_attributes( $allowedtags );

		return $retval;
	}

	/**
	 * Enqueue CSS.
	 *
	 * Feel free to disable with the 'bbp_quote_enable_css' filter and roll your
	 * own in your theme's stylesheet.
	 */
	public function enqueue_styles() {
		if ( ! apply_filters( 'bbp_quote_enable_css', true ) )
			return;

		// are we on a topic page?
		$show = bbp_is_single_topic();

		// check for BuddyPress group forum topic page
		if ( empty( $show ) && bbp_is_group_forums_active() && defined( 'BP_VERSION' ) && bp_is_active( 'groups' ) ) {
			$show = bp_is_group_forum_topic();
		}

		// not on a topic page? stop now!
		if ( empty( $show ) ) {
			return;
		}

		wp_enqueue_style( 'bbp-quote', plugins_url( 'style.css', __FILE__ ) );
	}

}

add_action( 'bbp_includes', array( 'bbP_Quote', 'init' ) );

endif;