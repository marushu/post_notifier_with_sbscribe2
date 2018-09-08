<?php
/**
 * Plugin Name: Post Notifier
 * Version: 0.5.0
 * Description: Send information to the specified e-mail address when the post published.
 * Author: Shuhei Nishimura
 * Author URI: http://private.hibou-web.com
 * Plugin URI: https://github.com/marushu/post-notifier
 * Text Domain: post-notifier
 * Domain Path: /languages
 * @package Post Notifier
 */

if ( class_exists( 'Post_Notifier' ) ) {
	$post_notifier = new Post_Notifier();
}

/**
 * Summary.
 *
 * @since  0.1.0
 * @access public
 */
class Post_Notifier {

	/**
	 * Post_Notifier constructor.
	 */
	function __construct() {

		add_action( 'transition_post_status', array( $this, 'post_published_notification' ), 10, 3 );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

	}

	/**
	 * Activation.
	 */
	public function activate() {

		$post_notifier_settings = get_option( 'post_notifier_settings' );
		if ( empty( $post_notifier_settings ) ) {
			$default_value = array(
				'email_field'        => array(),
				'post_type_field'    => array(),
				'sender_email_field' => '',
			);
			update_option( 'post_notifier_settings', $default_value );
		}

	}

	/**
	 * Load textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'post-notifier',
			false,
			plugin_basename( dirname( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Send specific e-mail when post status is published.
	 *
	 * @param string $new_status after published.
	 * @param string $old_status before published.
	 * @param object $post       post object.
	 */
	public function post_published_notification( $new_status, $old_status, $post ) {

		$options      = get_option( 'post_notifier_settings' );
		$emails       = isset( $options['email_field'] )
			? $options['email_field']
			: '';
		$post_types   = isset( $options['post_type_field'] )
			? $options['post_type_field']
			: '';
		$sender_email = isset( $options['sender_email_field'] )
			? $options['sender_email_field']
			: get_option( 'admin_email' );
		$signature    = isset( $options['signature_field'] )
            ? $options['signature_field']
            : '';

		$title       = wp_trim_words( esc_html( $post->post_title ), 100, '…' );
		$permalink   = esc_url( get_permalink( intval( $post->ID ) ) );
		$message     = '';
		$attachments = array();

		/**
		 * Get post thumbnail
		 */
		if ( has_post_thumbnail() ) {
			$post_thumbnail_id    = get_post_thumbnail_id( $post->ID );
			$post_thumbnail_datas = wp_get_attachment_image_src( $post_thumbnail_id, 'full' );
			$attachments[]        = esc_url( $post_thumbnail_datas[0] );

			/**
			 * If use ifttt and throw twitter with attachent, must get full path of post thumbnail...
			 */
			$upload_dir    = wp_upload_dir();
			$basedir       = $upload_dir['basedir'];
			$for_full_path = esc_url( $post_thumbnail_datas[0] );

			if ( ! empty( $for_full_path ) ) {
				$for_full_path = explode( '/uploads', $for_full_path );
				$full_path     = $basedir . $for_full_path[1];

				$attachments[] = $full_path;
			}
		} else {
			$attachments[] = '';
		}

		//$post_content = wp_strip_all_tags( $post->post_content );
		$post_content  = $post->post_content;
		$post_content  = sanitize_textarea_field( $post_content );
		$post_content .= "\n\n";
		$post_content .= $signature;

		//$post_content = wp_trim_words( $post_content, 50, '…' );
		$subject = sprintf( '%s' . PHP_EOL, trim( $title ) );
		$message .= sprintf( '%s' . PHP_EOL, trim( $post_content ) );
		$headers[] = 'From:' . sanitize_email( $sender_email );

		foreach ( (array) $emails as $email ) {

			if ( ! is_email( $email ) ) {
				return;
			}

			$to[]      = sprintf( '%s', sanitize_email( $email ) );

		}

		foreach ( $to as $each_email ) {

			/**
			 * Check post status if post status is 'publish' is no fire
			 */
			if ( in_array( $post->post_type, $post_types, true ) && 'publish' === $new_status && 'publish' !== $old_status ) {

				add_filter( 'wp_mail_from', function( $sender_email ) {
					return sanitize_email( $sender_email );
				} );

				wp_mail( $each_email, $subject, $message, $headers, $attachments );

			}

        }

	}

	/**
	 * Data sanitize.
	 *
	 * @param email , post-types $input Posted datas.
	 * @return mixed
	 */
	public function data_sanitize( $input ) {

		/**
		 * Email
		 */
		$this->options = get_option( 'post_notifier_settings' );
		$new_input     = array();
		$shaped_emails = array();

		$options = get_option( 'post_notifier_settings' );
		$emails = explode( ',', $input['email_field'] );
		if ( ! empty( $emails ) ) {
			foreach ( (array) $emails as $email ) {

				$email = sanitize_email( $email );

				if ( ! is_email( $email ) || empty( $email ) ) {

					add_settings_error(
						'post_notifier_settings',
						'email_field',
						__( "Check your email({$email}) address.", 'post-notifier' ),
						'error'
					);
					$new_input['email_field'] = isset( $this->options['email_field'] ) ? $shaped_emails : '';

				} else { // Success!

					$shaped_emails[]            = $email;
					$new_input['email_field'] = isset( $this->options['email_field'] ) ? $shaped_emails : '';

				}
			}
		}

		/**
		 * Post type
		 */
		$post_types = $input['post_type_field'];
		if ( ! empty( $post_types ) ) {

			$selected_post_types = array();
			foreach ( $post_types as $post_type ) {

				$selected_post_types[] = $post_type;

			}

			$new_input['post_type_field'] = isset( $this->options['post_type_field'] ) ? $selected_post_types : '';

		} else {

			add_settings_error(
				'post_notifier_settings',
				'post_type_field',
				__( 'Select the post type', 'post-notifier' ),
				'error'
			);
			$new_input['post_type_field'] = '';

		}

		/**
		 * Sender email
		 */
		$sender_email = isset( $input['sender_email_field'] )
            ? sanitize_email( $input['sender_email_field'] )
            : '';
		$new_input['sender_email_field'] = ! empty( $sender_email )
            ? $sender_email
            : '';

		$signature = isset( $input['signature_field'] )
            ? esc_textarea( $input['signature_field'] )
            : '';

		$new_input['signature_field'] = ! empty( $signature )
            ? $signature
            : '';


		return $new_input;

	}

	/**
	 * Add admin menu
	 */
	public function admin_menu() {
		add_options_page(
			'Post Notifier',
			'Post Notifier',
			'manage_options',
			'post_notifier',
			array( $this, 'post_notifier_options_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function settings_init() {

		register_setting(
			'notifierpage',
			'post_notifier_settings',
			array( $this, 'data_sanitize' )
		);

		add_settings_section(
			'post_notifier_notifierpage_section',
			__( 'Post Notifier settings', 'post-notifier' ),
			array( $this, 'post_notifier_settings_section_callback' ),
			'notifierpage'
		);

		add_settings_field(
			'email_field',
			__( 'Set e-mail<br /><p>(Input with new line.)</p>', 'post-notifier' ),
			array( $this, 'email_field_render' ),
			'notifierpage',
			'post_notifier_notifierpage_section'
		);

		add_settings_field(
			'post_type_field',
			__( 'Select the post type', 'post-notifier' ),
			array( $this, 'post_type_render' ),
			'notifierpage',
			'post_notifier_notifierpage_section'
		);

		add_settings_field(
			'sender_email_field',
			__( 'Set sender email', 'post-notifier' ),
			array( $this, 'from_email_render' ),
			'notifierpage',
			'post_notifier_notifierpage_section'
		);

		add_settings_field(
			'signature_field',
			__( 'Set sender signature', 'post-notifier' ),
			array( $this, 'from_signnature_render' ),
			'notifierpage',
			'post_notifier_notifierpage_section'
		);

	}

	/**
	 * Add description of Post Notifier.
	 */
	public function post_notifier_settings_section_callback() {

		echo esc_attr__( 'Set e-mail, select post-type', 'post-notifier' );

	}

	/**
	 * Output text field.
	 */
	public function email_field_render() {

		$options = get_option( 'post_notifier_settings' );
		$emails  = isset( $options['email_field'] ) ? $options['email_field'] : '';

		global $wpdb;
		$prepared_sql = $wpdb->prepare(
			"SELECT email
                        FROM wp_subscribe2
                        WHERE active = %s",
			1
		);
		//$emails = $wpdb->get_col( $prepared_sql );
		$emails  = array_unique( $emails );
		$emails_num = count( $emails );
		$emails  = ! empty( $emails ) && is_array( $emails )
            ? implode( ', ', $emails )
            : '';
		?>
        <span><?php echo intval( $emails_num ); ?>通のメールへ送信</span>
		<textarea name="post_notifier_settings[email_field]" id="post_notifier_settings[email_field]" cols="100" width="auto" height="auto" rows="5"><?php echo esc_html( $emails ); ?></textarea>
		<?php

//		global $wpdb;
//		$prepared_sql = $wpdb->prepare(
//		        "SELECT email
//                        FROM wp_subscribe2
//                        WHERE active = %s",
//                        1
//            );
//         $subscribe_active_emails = $wpdb->get_col( $prepared_sql );


	}

	/**
	 * Output post-type checkbox.
	 */
	public function post_type_render() {

		$options             = get_option( 'post_notifier_settings' );
		$selected_post_types = isset( $options['post_type_field'] ) ? $options['post_type_field'] : '';

		$args       = array(
			'public' => false,
            'show_ui' => true,
		);
		$output     = 'names';
		$post_types = array_values( get_post_types( $args, $output ) );
		$count      = intval( count( $post_types ) );

		if ( ! empty( $post_types ) ) {

			for ( $i = 0; $i < $count; $i++ ) {
				if ( 'attachment' !== $post_types[ $i ] ) {
					?>

					<p>
						<input value="<?php echo esc_html( $post_types[ $i ] ); ?>" name="post_notifier_settings[post_type_field][]"
									 type="checkbox"
									 id="check-<?php echo esc_html( $post_types[ $i ] ); ?>"<?php if ( ! empty( $selected_post_types ) && in_array( $post_types[ $i ], $selected_post_types, true ) ) { echo 'checked="selected"'; } ?>>
						<label
							for="check-<?php echo esc_html( $post_types[ $i ] ); ?>"><?php echo esc_html( $post_types[ $i ] ); ?></label>
					</p>

					<?php
				}
			}
		}

	}

	/**
	 * Output Sender e-mail field.
	 */
	public function from_email_render() {

		$options      = get_option( 'post_notifier_settings' );
		$sender_email = isset( $options['sender_email_field'] ) ? sanitize_email( $options['sender_email_field'] ) : '';
		?>

		<input type="text" name="post_notifier_settings[sender_email_field]"
					 value="<?php echo esc_html( $sender_email ); ?>" size="30" maxlength="30">

		<?php

	}

	/**
	 * Output Sender e-mail signature.
	 */
	public function from_signnature_render() {

		$options = get_option( 'post_notifier_settings' );
		$signature  = isset( $options['signature_field'] ) ? $options['signature_field'] : '';

	?>

        <textarea name="post_notifier_settings[signature_field]" id="post_notifier_settings[signature_field]" cols="100" width="auto" height="auto" rows="5"><?php echo esc_html( $signature ); ?></textarea>

    <?php

    }

	/**
	 * Output Post Notifier page form.
	 */
	public function post_notifier_options_page() {

		?>
		<form action='options.php' method='post'>

			<?php
			settings_fields( 'notifierpage' );
			do_settings_sections( 'notifierpage' );
			submit_button();
			?>

		</form>
		<?php
	}
}
