<?php
/**
 * Public-facing functionality for the I Recommend This plugin.
 *
 * @package IRecommendThis
 */
class Themeist_IRecommendThis_Public {

	/**
	 * Constructor to initialize the class.
	 *
	 * @param string $plugin_file The main plugin file path.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	/**
	 * Add hooks for the public-facing functionality.
	 */
	public function add_public_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'the_content', array( $this, 'dot_content' ) );
	}

	/**
	 * Enqueue scripts and styles for the plugin.
	 */
	public function enqueue_scripts() {
		// Get plugin settings.
		$options = get_option( 'dot_irecommendthis_settings' );

		// Validate and set default values for CSS options.
		$disable_css     = isset( $options['disable_css'] ) ? intval( $options['disable_css'] ) : 0;
		$recommend_style = isset( $options['recommend_style'] ) ? intval( $options['recommend_style'] ) : 0;

		// Enqueue styles if CSS is not disabled.
		if ( 0 === $disable_css ) {
			$css_file = ( 0 === $recommend_style ) ? 'dot-irecommendthis.css' : 'dot-irecommendthis-heart.css';
			$css_path = plugin_dir_path( $this->plugin_file ) . 'css/' . $css_file;
			$css_url  = plugins_url( 'css/' . $css_file, $this->plugin_file );

			if ( file_exists( $css_path ) ) {
				wp_enqueue_style( 'dot-irecommendthis', $css_url, array(), filemtime( $css_path ) );
			} else {
				wp_enqueue_style( 'dot-irecommendthis', $css_url, array(), THEMEIST_IRT_VERSION );
			}
		}

		// Register and enqueue the main JavaScript file.
		wp_register_script( 'dot-irecommendthis', plugins_url( 'js/dot_irecommendthis.js', $this->plugin_file ), array( 'jquery' ), '2.6.0', true );
		wp_enqueue_script( 'dot-irecommendthis' );

		// Enqueue jQuery, if not already enqueued.
		wp_enqueue_script( 'jquery' );

		// Create a nonce for secure AJAX requests and localize it.
		$nonce = wp_create_nonce( 'dot-irecommendthis-nonce' );
		wp_localize_script(
			'dot-irecommendthis',
			'dot_irecommendthis',
			array(
				'nonce'   => $nonce,
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Filter the content to include the recommendation button.
	 *
	 * @param string $content The original content.
	 * @return string Modified content with the recommendation button.
	 */
	public function dot_content( $content ) {
		if ( is_page_template() || is_page() || is_front_page() ) {
			return $content;
		}

		global $wp_current_filter;
		if ( in_array( 'get_the_excerpt', (array) $wp_current_filter, true ) ) {
			return $content;
		}

		$options      = get_option( 'dot_irecommendthis_settings' );
		$add_to_posts = isset( $options['add_to_posts'] ) ? $options['add_to_posts'] : '0';
		$add_to_other = isset( $options['add_to_other'] ) ? $options['add_to_other'] : '0';

		if ( is_singular( 'post' ) && $add_to_posts ) {
			$content .= Themeist_IRecommendThis_Shortcodes::dot_recommend();
		}
		if ( ( is_home() || is_category() || is_tag() || is_author() || is_date() || is_search() ) && $add_to_other ) {
			$content .= Themeist_IRecommendThis_Shortcodes::dot_recommend();
		}

		return $content;
	}

	/**
	 * Main Process.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $text_zero_suffix Text for zero suffix.
	 * @param string $text_one_suffix Text for one suffix.
	 * @param string $text_more_suffix Text for more suffix.
	 * @param string $action Action to perform.
	 * @return string HTML output for the recommendation button.
	 */
	public static function dot_recommend_this( $post_id, $text_zero_suffix = false, $text_one_suffix = false, $text_more_suffix = false, $action = 'get' ) {
		global $wpdb;

		if ( ! is_numeric( $post_id ) ) {
			return;
		}

		$text_zero_suffix = sanitize_text_field( $text_zero_suffix );
		$text_one_suffix  = sanitize_text_field( $text_one_suffix );
		$text_more_suffix = sanitize_text_field( $text_more_suffix );

		switch ( $action ) {
			case 'get':
				$recommended = (int) get_post_meta( $post_id, '_recommended', true );

				if ( ! $recommended ) {
					$recommended = 0;
					add_post_meta( $post_id, '_recommended', $recommended, true );
				}

				if ( 0 === $recommended ) {
					$suffix = $text_zero_suffix;
				} elseif ( 1 === $recommended ) {
					$suffix = $text_one_suffix;
				} else {
					$suffix = $text_more_suffix;
				}

				$options   = get_option( 'dot_irecommendthis_settings' );
				$hide_zero = isset( $options['hide_zero'] ) ? $options['hide_zero'] : '0';

				if ( 0 === $recommended && 1 === $hide_zero ) {
					$output = '<span class="dot-irecommendthis-count">&nbsp;</span> <span class="dot-irecommendthis-suffix">' . esc_html( $suffix ) . '</span>';
				} else {
					$output = '<span class="dot-irecommendthis-count">' . esc_html( $recommended ) . '</span> <span class="dot-irecommendthis-suffix">' . esc_html( $suffix ) . '</span>';
				}

				return apply_filters( 'dot_irt_before_count', $output );

			case 'update':
				$recommended = get_post_meta( $post_id, '_recommended', true );

				$options          = get_option( 'dot_irecommendthis_settings' );
				$enable_unique_ip = isset( $options['enable_unique_ip'] ) ? $options['enable_unique_ip'] : '0';

				if ( 0 !== $enable_unique_ip ) {
					$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

					if ( isset( $_POST['unrecommend'] ) && isset( $_POST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'dot-irecommendthis-nonce' ) && 'true' === sanitize_text_field( wp_unslash( $_POST['unrecommend'] ) ) ) {
						$sql = $wpdb->prepare( "DELETE FROM {$wpdb->prefix}irecommendthis_votes WHERE post_id = %d AND ip = %s", $post_id, $ip );
						$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
					} else {
						$sql               = $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}irecommendthis_votes WHERE post_id = %d AND ip = %s", $post_id, $ip );
						$vote_status_by_ip = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
						$sql               = $wpdb->prepare( "INSERT INTO {$wpdb->prefix}irecommendthis_votes VALUES ('', NOW(), %d, %s )", $post_id, $ip );
						$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
					}
				}

				if ( isset( $_COOKIE[ 'dot_irecommendthis_' . $post_id ] ) && isset( $_POST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'dot-irecommendthis-nonce' ) && isset( $_POST['unrecommend'] ) && 'false' === sanitize_text_field( wp_unslash( $_POST['unrecommend'] ) ) ) {
					return $recommended;
				}

				if ( isset( $_POST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'dot-irecommendthis-nonce' ) && isset( $_POST['unrecommend'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['unrecommend'] ) ) ) {
					if ( isset( $_COOKIE[ 'dot_irecommendthis_' . $post_id ] ) ) {
						setcookie( 'dot_irecommendthis_' . $post_id, '', time() - 3600, '/' );
					}
					--$recommended;
				} else {
					setcookie( 'dot_irecommendthis_' . $post_id, time(), time() + 3600 * 24 * 365, '/' );
					++$recommended;
				}

				update_post_meta( $post_id, '_recommended', $recommended );

				if ( 0 === $recommended ) {
					$suffix = $text_zero_suffix;
				} elseif ( 1 === $recommended ) {
					$suffix = $text_one_suffix;
				} else {
					$suffix = $text_more_suffix;
				}

				$output = '<span class="dot-irecommendthis-count">' . esc_html( $recommended ) . '</span> <span class="dot-irecommendthis-suffix">' . esc_html( $suffix ) . '</span>';
				return apply_filters( 'dot_irt_before_count', $output );
		}//end switch
	}
}
