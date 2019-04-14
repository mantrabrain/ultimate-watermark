<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

new Ultimate_Watermark_Settings( );

/**
 * Ultimate Watermark settings class.
 *
 * @class Ultimate_Watermark_Settings
 */
class Ultimate_Watermark_Settings {
	private $image_sizes;
	private $watermark_positions = array(
		'x'	 => array( 'left', 'center', 'right' ),
		'y'	 => array( 'top', 'middle', 'bottom' )
	);

	/**
	 * Class constructor.
	 */
	public function __construct( )	{
		// actions
		add_action( 'admin_init', array( $this, 'register_settings' ), 11 );
		add_action( 'admin_menu', array( $this, 'options_page' ) );
		add_action( 'wp_loaded', array( $this, 'load_image_sizes' ) );
	}

	/**
	 * Load available image sizes.
	 */
	public function load_image_sizes() {
		$this->image_sizes = get_intermediate_image_sizes();
		$this->image_sizes[] = 'full';

		sort( $this->image_sizes, SORT_STRING );
	}

	/**
	 * Get post types.
	 * 
	 * @return array
	 */
	private function get_post_types() {
		return array_merge( array( 'post', 'page' ), get_post_types( array( '_builtin' => false ), 'names' ) );
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'ultimate_watermark_options', 'ultimate_watermark_options', array( $this, 'validate_options' ) );

		// general
		add_settings_section( 'ultimate_watermark_general', __( 'General settings', 'ultimate-watermark' ), '', 'ultimate_watermark_options' );

		// is imagick available?
		if ( isset( Ultimate_Watermark()->extensions['imagick'] ) )
			add_settings_field( 'ulwm_extension', __( 'PHP library', 'ultimate-watermark' ), array( $this, 'ulwm_extension' ), 'ultimate_watermark_options', 'ultimate_watermark_general' );

		add_settings_field( 'ulwm_automatic_watermarking', __( 'Automatic watermarking', 'ultimate-watermark' ), array( $this, 'ulwm_automatic_watermarking' ), 'ultimate_watermark_options', 'ultimate_watermark_general' );
		add_settings_field( 'ulwm_manual_watermarking', __( 'Manual watermarking', 'ultimate-watermark' ), array( $this, 'ulwm_manual_watermarking' ), 'ultimate_watermark_options', 'ultimate_watermark_general' );
		add_settings_field( 'ulwm_enable_for', __( 'Enable watermark for', 'ultimate-watermark' ), array( $this, 'ulwm_enable_for' ), 'ultimate_watermark_options', 'ultimate_watermark_general' );
		add_settings_field( 'ulwm_frontend_watermarking', __( 'Frontend watermarking', 'ultimate-watermark' ), array( $this, 'ulwm_frontend_watermarking' ), 'ultimate_watermark_options', 'ultimate_watermark_general' );
		add_settings_field( 'ulwm_deactivation', __( 'Deactivation', 'ultimate-watermark' ), array( $this, 'ulwm_deactivation' ), 'ultimate_watermark_options', 'ultimate_watermark_general' );

		// watermark position
		add_settings_section( 'ultimate_watermark_position', __( 'Watermark position', 'ultimate-watermark' ), '', 'ultimate_watermark_options' );
		add_settings_field( 'ulwm_alignment', __( 'Watermark alignment', 'ultimate-watermark' ), array( $this, 'ulwm_alignment' ), 'ultimate_watermark_options', 'ultimate_watermark_position' );
		add_settings_field( 'ulwm_offset', __( 'Watermark offset', 'ultimate-watermark' ), array( $this, 'ulwm_offset' ), 'ultimate_watermark_options', 'ultimate_watermark_position' );
		add_settings_field( 'ulwm_offset_unit', __( 'Offset unit', 'ultimate-watermark' ), array( $this, 'ulwm_offset_unit' ), 'ultimate_watermark_options', 'ultimate_watermark_position' );

		// watermark image
		add_settings_section( 'ultimate_watermark_image', __( 'Watermark image', 'ultimate-watermark' ), '', 'ultimate_watermark_options' );
		add_settings_field( 'ulwm_watermark_image', __( 'Watermark image', 'ultimate-watermark' ), array( $this, 'ulwm_watermark_image' ), 'ultimate_watermark_options', 'ultimate_watermark_image' );
		add_settings_field( 'ulwm_watermark_preview', __( 'Watermark preview', 'ultimate-watermark' ), array( $this, 'ulwm_watermark_preview' ), 'ultimate_watermark_options', 'ultimate_watermark_image' );
		add_settings_field( 'ulwm_watermark_size', __( 'Watermark size', 'ultimate-watermark' ), array( $this, 'ulwm_watermark_size' ), 'ultimate_watermark_options', 'ultimate_watermark_image' );
		add_settings_field( 'ulwm_watermark_size_custom', __( 'Watermark custom size', 'ultimate-watermark' ), array( $this, 'ulwm_watermark_size_custom' ), 'ultimate_watermark_options', 'ultimate_watermark_image' );
		add_settings_field( 'ulwm_watermark_size_scaled', __( 'Watermark scale', 'ultimate-watermark' ), array( $this, 'ulwm_watermark_size_scaled' ), 'ultimate_watermark_options', 'ultimate_watermark_image' );
		add_settings_field( 'ulwm_watermark_opacity', __( 'Watermark transparency / opacity', 'ultimate-watermark' ), array( $this, 'ulwm_watermark_opacity' ), 'ultimate_watermark_options', 'ultimate_watermark_image' );
		add_settings_field( 'ulwm_image_quality', __( 'Image quality', 'ultimate-watermark' ), array( $this, 'ulwm_image_quality' ), 'ultimate_watermark_options', 'ultimate_watermark_image' );
		add_settings_field( 'ulwm_image_format', __( 'Image format', 'ultimate-watermark' ), array( $this, 'ulwm_image_format' ), 'ultimate_watermark_options', 'ultimate_watermark_image' );

		// watermark protection
		add_settings_section( 'ultimate_watermark_protection', __( 'Image protection', 'ultimate-watermark' ), '', 'ultimate_watermark_options' );
		add_settings_field( 'ulwm_protection_right_click', __( 'Right click', 'ultimate-watermark' ), array( $this, 'ulwm_protection_right_click' ), 'ultimate_watermark_options', 'ultimate_watermark_protection' );
		add_settings_field( 'ulwm_protection_drag_drop', __( 'Drag and drop', 'ultimate-watermark' ), array( $this, 'ulwm_protection_drag_drop' ), 'ultimate_watermark_options', 'ultimate_watermark_protection' );
		add_settings_field( 'ulwm_protection_logged', __( 'Logged-in users', 'ultimate-watermark' ), array( $this, 'ulwm_protection_logged' ), 'ultimate_watermark_options', 'ultimate_watermark_protection' );

		// Backup
		add_settings_section( 'ultimate_watermark_backup', __( 'Image backup', 'ultimate-watermark' ), '', 'ultimate_watermark_options' );
		add_settings_field( 'ulwm_backup_image', __( 'Backup full size image', 'ultimate-watermark' ), array( $this, 'ulwm_backup_image' ), 'ultimate_watermark_options', 'ultimate_watermark_backup' );
		add_settings_field( 'ulwm_backup_image_quality', __( 'Backup image quality', 'ultimate-watermark' ), array( $this, 'ulwm_backup_image_quality' ), 'ultimate_watermark_options', 'ultimate_watermark_backup' );
	}

	/**
	 * Create options page in menu.
	 */
	public function options_page() {
		add_options_page(
			__( 'Ultimate Watermark Options', 'ultimate-watermark' ), __( 'Watermark', 'ultimate-watermark' ), 'manage_options', 'watermark-options', array( $this, 'options_page_output' )
		);
	}

	/**
	 * Options page output.
	 */
	public function options_page_output() {

		if ( ! current_user_can( 'manage_options' ) )
			return;

		echo '
		<div class="wrap">
			<h2>' . __( 'Ultimate Watermark', 'ultimate-watermark' ) . '</h2>';

		echo '
			<div class="ultimate-watermark-settings metabox-holder">
				<form action="options.php" method="post">
					<div id="main-sortables" class="meta-box-sortables ui-sortable">';
		settings_fields( 'ultimate_watermark_options' );
		$this->do_settings_sections( 'ultimate_watermark_options' );

		echo '
					<p class="submit">';
		submit_button( '', 'primary', 'save_ultimate_watermark_options', false );

		echo ' ';

		submit_button( __( 'Reset to defaults', 'ultimate-watermark' ), 'secondary', 'reset_ultimate_watermark_options', false );

		echo '
					</p>
					</div>
				</form>
			</div>
			<div class="clear"></div>
		</div>';
		?>
			<script type="text/javascript">
				//<![CDATA[
				jQuery(document).ready( function ($) {
					// close postboxes that should be closed
					$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
					// postboxes setup
					postboxes.add_postbox_toggles('watermark-options');
				});
				//]]>
			</script>
		<?php
	}

	/**
	 * Validate options.
	 * 
	 * @param array $input
	 * @return array
	 */
	public function validate_options( $input ) {

		if ( ! current_user_can( 'manage_options' ) )
			return $input;

		if ( isset( $_POST['save_ultimate_watermark_options'] ) ) {
			$input['watermark_image']['plugin_off'] = isset( $_POST['ulwm_options']['watermark_image']['plugin_off'] ) ? ((bool) $_POST['ulwm_options']['watermark_image']['plugin_off'] == 1 ? true : false) : Ultimate_Watermark()->defaults['options']['watermark_image']['plugin_off'];
			$input['watermark_image']['manual_watermarking'] = isset( $_POST['ulwm_options']['watermark_image']['manual_watermarking'] ) ? ((bool) $_POST['ulwm_options']['watermark_image']['manual_watermarking'] == 1 ? true : false) : Ultimate_Watermark()->defaults['options']['watermark_image']['manual_watermarking'];

			$watermark_on = array();

			if ( isset( $_POST['ulwm_options']['watermark_on'] ) && is_array( $_POST['ulwm_options']['watermark_on'] ) ) {
				foreach ( $this->image_sizes as $size ) {
					if ( in_array( $size, array_keys( $_POST['ulwm_options']['watermark_on'] ) ) ) {
						$watermark_on[$size] = 1;
					}
				}
			}

			$input['watermark_on'] = $watermark_on;

			$input['watermark_cpt_on'] = Ultimate_Watermark()->defaults['options']['watermark_cpt_on'];

			if ( isset( $_POST['ulwm_options']['watermark_cpt_on'] ) && in_array( esc_attr( $_POST['ulwm_options']['watermark_cpt_on'] ), array( 'everywhere', 'specific' ) ) ) {
				if ( $_POST['ulwm_options']['watermark_cpt_on'] === 'specific' ) {
					if ( isset( $_POST['ulwm_options']['watermark_cpt_on_type'] ) ) {
						$tmp = array();

						foreach ( $this->get_post_types() as $cpt ) {
							if ( in_array( $cpt, array_keys( $_POST['ulwm_options']['watermark_cpt_on_type'] ) ) ) {
								$tmp[$cpt] = 1;
							}
						}

						if ( count( $tmp ) > 0 ) {
							$input['watermark_cpt_on'] = $tmp;
						}
					}
				}
			}

			// extension
			$input['watermark_image']['extension'] = isset( $_POST['ulwm_options']['watermark_image']['extension']) ? sanitize_text_field($_POST['ulwm_options']['watermark_image']['extension']) : Ultimate_Watermark()->defaults['options']['watermark_image']['extension'];

			$input['watermark_image']['frontend_active'] = isset( $_POST['ulwm_options']['watermark_image']['frontend_active'] ) ? ((bool) $_POST['ulwm_options']['watermark_image']['frontend_active'] == 1 ? true : false) : Ultimate_Watermark()->defaults['options']['watermark_image']['frontend_active'];
			$input['watermark_image']['deactivation_delete'] = isset( $_POST['ulwm_options']['watermark_image']['deactivation_delete'] ) ? ((bool) $_POST['ulwm_options']['watermark_image']['deactivation_delete'] == 1 ? true : false) : Ultimate_Watermark()->defaults['options']['watermark_image']['deactivation_delete'];


			$positions = array();

			foreach ( $this->watermark_positions['y'] as $position_y ) {
				foreach ( $this->watermark_positions['x'] as $position_x ) {
					$positions[] = $position_y . '_' . $position_x;
				}
			}
			$input['watermark_image']['position'] = isset( $_POST['ulwm_options']['watermark_image']['position'] ) && in_array( sanitize_text_field( $_POST['ulwm_options']['watermark_image']['position'] ), $positions ) ? sanitize_text_field( $_POST['ulwm_options']['watermark_image']['position'] ) : Ultimate_Watermark()->defaults['options']['watermark_image']['position'];

			$input['watermark_image']['offset_unit'] = isset( $_POST['ulwm_options']['watermark_image']['offset_unit'] ) && in_array( $_POST['ulwm_options']['watermark_image']['offset_unit'], array( 'pixels', 'percentages' ), true ) ? sanitize_text_field($_POST['ulwm_options']['watermark_image']['offset_unit']) : Ultimate_Watermark()->defaults['options']['watermark_image']['offset_unit'];
			$input['watermark_image']['offset_width'] = isset( $_POST['ulwm_options']['watermark_image']['offset_width'] ) ? (int) $_POST['ulwm_options']['watermark_image']['offset_width'] : Ultimate_Watermark()->defaults['options']['watermark_image']['offset_width'];
			$input['watermark_image']['offset_height'] = isset( $_POST['ulwm_options']['watermark_image']['offset_height'] ) ? (int) $_POST['ulwm_options']['watermark_image']['offset_height'] : Ultimate_Watermark()->defaults['options']['watermark_image']['offset_height'];
			$input['watermark_image']['attachment_id'] = isset( $_POST['ulwm_options']['watermark_image']['attachment_id'] ) ? (int) $_POST['ulwm_options']['watermark_image']['attachment_id'] : Ultimate_Watermark()->defaults['options']['watermark_image']['attachment_id'];
			$input['watermark_image']['watermark_size_type'] = isset( $_POST['ulwm_options']['watermark_image']['watermark_size_type'] ) ? (int) $_POST['ulwm_options']['watermark_image']['watermark_size_type'] : Ultimate_Watermark()->defaults['options']['watermark_image']['watermark_size_type'];
			$input['watermark_image']['absolute_width'] = isset( $_POST['ulwm_options']['watermark_image']['absolute_width'] ) ? (int) $_POST['ulwm_options']['watermark_image']['absolute_width'] : Ultimate_Watermark()->defaults['options']['watermark_image']['absolute_width'];
			$input['watermark_image']['absolute_height'] = isset( $_POST['ulwm_options']['watermark_image']['absolute_height'] ) ? (int) $_POST['ulwm_options']['watermark_image']['absolute_height'] : Ultimate_Watermark()->defaults['options']['watermark_image']['absolute_height'];
			$input['watermark_image']['width'] = isset( $_POST['ulwm_options']['watermark_image']['width'] ) ? (int) $_POST['ulwm_options']['watermark_image']['width'] : Ultimate_Watermark()->defaults['options']['watermark_image']['width'];
			$input['watermark_image']['transparent'] = isset( $_POST['ulwm_options']['watermark_image']['transparent'] ) ? (int) $_POST['ulwm_options']['watermark_image']['transparent'] : Ultimate_Watermark()->defaults['options']['watermark_image']['transparent'];
			$input['watermark_image']['quality'] = isset( $_POST['ulwm_options']['watermark_image']['quality'] ) ? (int) $_POST['ulwm_options']['watermark_image']['quality'] : Ultimate_Watermark()->defaults['options']['watermark_image']['quality'];
			$input['watermark_image']['jpeg_format'] = isset( $_POST['ulwm_options']['watermark_image']['jpeg_format'] ) && in_array( esc_attr( $_POST['ulwm_options']['watermark_image']['jpeg_format'] ), array( 'baseline', 'progressive' ) ) ? sanitize_text_field( $_POST['ulwm_options']['watermark_image']['jpeg_format'] ) : Ultimate_Watermark()->defaults['options']['watermark_image']['jpeg_format'];

			$input['image_protection']['rightclick'] = isset( $_POST['ulwm_options']['image_protection']['rightclick'] ) ? ((bool) $_POST['ulwm_options']['image_protection']['rightclick'] == 1 ? true : false) : Ultimate_Watermark()->defaults['options']['image_protection']['rightclick'];
			$input['image_protection']['draganddrop'] = isset( $_POST['ulwm_options']['image_protection']['draganddrop'] ) ? ((bool) $_POST['ulwm_options']['image_protection']['draganddrop'] == 1 ? true : false) : Ultimate_Watermark()->defaults['options']['image_protection']['draganddrop'];
			$input['image_protection']['forlogged'] = isset( $_POST['ulwm_options']['image_protection']['forlogged'] ) ? ((bool) $_POST['ulwm_options']['image_protection']['forlogged'] == 1 ? true : false) : Ultimate_Watermark()->defaults['options']['image_protection']['forlogged'];

			$input['backup']['backup_image'] = isset( $_POST['ulwm_options']['backup']['backup_image'] );
			$input['backup']['backup_quality'] = isset( $_POST['ulwm_options']['backup']['backup_quality'] ) ? (int) $_POST['ulwm_options']['backup']['backup_quality'] : Ultimate_Watermark()->defaults['options']['backup']['backup_quality'];

			add_settings_error( 'ulwm_settings_errors', 'ulwm_settings_saved', __( 'Settings saved.', 'ultimate-watermark' ), 'updated' );
		} elseif ( isset( $_POST['reset_ultimate_watermark_options'] ) ) {

			$input = Ultimate_Watermark()->defaults['options'];

			add_settings_error( 'ulwm_settings_errors', 'ulwm_settings_reset', __( 'Settings restored to defaults.', 'ultimate-watermark' ), 'updated' );
		}

		if ( $input['watermark_image']['plugin_off'] != 0 || $input['watermark_image']['manual_watermarking'] != 0 ) {
			if ( empty( $input['watermark_image']['attachment_id'] ) )
				add_settings_error( 'ulwm_settings_errors', 'ulwm_image_not_set', __( 'Watermark will not be applied when watermark image is not set.', 'ultimate-watermark' ), 'error' );

			if ( empty( $input['watermark_on'] ) )
				add_settings_error( 'ulwm_settings_errors', 'ulwm_sizes_not_set', __( 'Watermark will not be applied when no image sizes are selected.', 'ultimate-watermark' ), 'error' );
		}

		return $input;
	}

	/**
	 * PHP extension.
	 * 
	 * @return mixed
	 */
	public function ulwm_extension() {
		echo '
		<div id="ulwm_extension">
			<fieldset>
				<select name="ulwm_options[watermark_image][extension]">';

		foreach ( Ultimate_Watermark()->extensions as $extension => $label ) {
			echo '
					<option value="' . esc_attr( $extension ) . '" ' . selected( $extension, Ultimate_Watermark()->options['watermark_image']['extension'], false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '
				</select>
				<p class="description">' . esc_html__( 'Select extension.', 'wp-media-folder' ) . '</p>
			</fieldset>
		</div>';
	}

	/**
	 * Automatic watermarking option.
	 * 
	 * @return mixed
	 */
	public function ulwm_automatic_watermarking() {
		?>
		<label for="ulwm_automatic_watermarking">
			<input id="ulwm_automatic_watermarking" type="checkbox" <?php checked( ( ! empty( Ultimate_Watermark()->options['watermark_image']['plugin_off'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="ulwm_options[watermark_image][plugin_off]">
<?php echo __( 'Enable watermark for uploaded images.', 'ultimate-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Manual watermarking option.
	 * 
	 * @return mixed
	 */
	public function ulwm_manual_watermarking() {
		?>
		<label for="ulwm_manual_watermarking">
			<input id="ulwm_manual_watermarking" type="checkbox" <?php checked( ( ! empty( Ultimate_Watermark()->options['watermark_image']['manual_watermarking'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="ulwm_options[watermark_image][manual_watermarking]">
<?php echo __( 'Enable Apply Watermark option for Media Library images.', 'ultimate-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Enable watermark for option.
	 * 
	 * @return mixed
	 */
	public function ulwm_enable_for() {
		?>
		<fieldset id="ulwm_enable_for">
			<div id="thumbnail-select">
				<?php
                /*echo '<pre>';
                print_r(Ultimate_Watermark()->options);exit;*/
				foreach ( $this->image_sizes as $image_size ) {
					?>
					<input name="ulwm_options[watermark_on][<?php echo $image_size; ?>]" type="checkbox" id="<?php echo $image_size; ?>" value="1" <?php echo (in_array( $image_size, array_keys( Ultimate_Watermark()->options['watermark_on'] ) ) ? ' checked="checked"' : ''); ?> />
					<label for="<?php echo $image_size; ?>"><?php echo $image_size; ?></label>
					<?php
				}
				?>
			</div>
			<p class="description">
				<?php echo __( 'Check the image sizes watermark will be applied to.', 'ultimate-watermark' ); ?><br />
				<?php echo __( '<strong>IMPORTANT:</strong> checking full size is NOT recommended as it\'s the original image. You may need it later - for removing or changing watermark, image sizes regeneration or any other image manipulations. Use it only if you know what you are doing.', 'ultimate-watermark' ); ?>
			</p>
			
			<?php
			$watermark_cpt_on = array_keys( Ultimate_Watermark()->options['watermark_cpt_on'] );

			if ( in_array( 'everywhere', $watermark_cpt_on ) && count( $watermark_cpt_on ) === 1 ) {
				$first_checked = true;
				$second_checked = false;
				$watermark_cpt_on = array();
			} else {
				$first_checked = false;
				$second_checked = true;
			}
			?>
			
			<div id="cpt-specific">
				<input id="df_option_everywhere" type="radio" name="ulwm_options[watermark_cpt_on]" value="everywhere" <?php echo ($first_checked === true ? 'checked="checked"' : ''); ?>/><label for="df_option_everywhere"><?php _e( 'everywhere', 'ultimate-watermark' ); ?></label>
				<input id="df_option_cpt" type="radio" name="ulwm_options[watermark_cpt_on]" value="specific" <?php echo ($second_checked === true ? 'checked="checked"' : ''); ?> /><label for="df_option_cpt"><?php _e( 'on selected post types only', 'ultimate-watermark' ); ?></label>
			</div>
			
			<div id="cpt-select" <?php echo ($second_checked === false ? 'style="display: none;"' : ''); ?>>
			<?php
			foreach ( $this->get_post_types() as $cpt ) {
				?>
				<input name="ulwm_options[watermark_cpt_on_type][<?php echo $cpt; ?>]" type="checkbox" id="<?php echo $cpt; ?>" value="1" <?php echo (in_array( $cpt, $watermark_cpt_on ) ? ' checked="checked"' : ''); ?> />
				<label for="<?php echo $cpt; ?>"><?php echo $cpt; ?></label>
				<?php
			}
				?>
			</div>
			
			<p class="description"><?php echo __( 'Check custom post types on which watermark should be applied to uploaded images.', 'ultimate-watermark' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Frontend watermarking option.
	 * 
	 * @return mixed
	 */
	public function ulwm_frontend_watermarking() {
		?>
		<label for="ulwm_frontend_watermarking">
			<input id="ulwm_frontend_watermarking" type="checkbox" <?php checked( ( ! empty( Ultimate_Watermark()->options['watermark_image']['frontend_active'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="ulwm_options[watermark_image][frontend_active]">
<?php echo __( 'Enable frontend image uploading. (uploading script is not included, but you may use a plugin or custom code).', 'ultimate-watermark' ); ?>
		</label>
		<span class="description"><?php echo __( '<br /><strong>Notice:</strong> This functionality works only if uploaded images are processed using WordPress native upload methods.', 'ultimate-watermark' ); ?></span>
		<?php
	}

	/**
	 * Remove data on deactivation option.
	 * 
	 * @return mixed
	 */
	public function ulwm_deactivation() {
		?>
		<label for="ulwm_deactivation">
			<input id="ulwm_deactivation" type="checkbox" <?php checked( ( ! empty( Ultimate_Watermark()->options['watermark_image']['deactivation_delete'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="ulwm_options[watermark_image][deactivation_delete]">
<?php echo __( 'Delete all database settings on plugin deactivation.', 'ultimate-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Watermark alignment option.
	 * 
	 * @return mixed
	 */
	public function ulwm_alignment() {
		?>
		<fieldset id="ulwm_alignment">
			<table id="watermark_position" border="1">
			<?php
			$watermark_position = Ultimate_Watermark()->options['watermark_image']['position'];

			foreach ( $this->watermark_positions['y'] as $y ) {
			?>
				<tr>
				<?php
				foreach ( $this->watermark_positions['x'] as $x ) {
				?>
					<td title="<?php echo ucfirst( $y . ' ' . $x ); ?>">
						<input name="ulwm_options[watermark_image][position]" type="radio" value="<?php echo $y . '_' . $x; ?>"<?php echo ($watermark_position == $y . '_' . $x ? ' checked="checked"' : NULL); ?> />
					</td>
					<?php }
					?>
				</tr>
				<?php
			}
		?>
			</table>
			<p class="description"><?php echo __( 'Select the watermark alignment.', 'ultimate-watermark' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Watermark offset unit option.
	 * 
	 * @return void
	 */
	public function ulwm_offset_unit() {
		?>
		<fieldset id="ulwm_offset_unit">
			<input type="radio" id="offset_pixels" value="pixels" name="ulwm_options[watermark_image][offset_unit]" <?php checked( Ultimate_Watermark()->options['watermark_image']['offset_unit'], 'pixels', true ); ?> /><label for="offset_pixels"><?php _e( 'pixels', 'ultimate-watermark' ); ?></label>
			<input type="radio" id="offset_percentages" value="percentages" name="ulwm_options[watermark_image][offset_unit]" <?php checked( Ultimate_Watermark()->options['watermark_image']['offset_unit'], 'percentages', true ); ?> /><label for="offset_percentages"><?php _e( 'percentages', 'ultimate-watermark' ); ?></label>
			<p class="description"><?php _e( 'Select the watermark offset unit.', 'ultimate-watermark' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Watermark offset option.
	 * 
	 * @return void
	 */
	public function ulwm_offset() {
		?>
		<fieldset id="ulwm_offset">
			<?php echo __( 'x:', 'ultimate-watermark' ); ?> <input type="number" class="small-text" name="ulwm_options[watermark_image][offset_width]" value="<?php echo Ultimate_Watermark()->options['watermark_image']['offset_width']; ?>">
			<br />
			<?php echo __( 'y:', 'ultimate-watermark' ); ?> <input type="number" class="small-text" name="ulwm_options[watermark_image][offset_height]" value="<?php echo Ultimate_Watermark()->options['watermark_image']['offset_height']; ?>">
			<p class="description"><?php _e( 'Enter watermark offset value.', 'ultimate-watermark' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Watermark image option.
	 * 
	 * @return void
	 */
	public function ulwm_watermark_image() {
		if ( Ultimate_Watermark()->options['watermark_image']['attachment_id'] !== NULL && Ultimate_Watermark()->options['watermark_image']['attachment_id'] != 0 ) {
			$image = wp_get_attachment_image_src( Ultimate_Watermark()->options['watermark_image']['attachment_id'], array( 300, 300 ), false );
			$image_selected = true;
		} else {
			$image_selected = false;
		}
		?>
		<div class="ulwm_watermark_image">
			<input id="ulwm_upload_image" type="hidden" name="ulwm_options[watermark_image][attachment_id]" value="<?php echo (int) Ultimate_Watermark()->options['watermark_image']['attachment_id']; ?>" />
			<input id="ulwm_upload_image_button" type="button" class="button button-secondary" value="<?php echo __( 'Select image', 'ultimate-watermark' ); ?>" />
			<input id="ulwm_turn_off_image_button" type="button" class="button button-secondary" value="<?php echo __( 'Remove image', 'ultimate-watermark' ); ?>" <?php if ( $image_selected === false ) echo 'disabled="disabled"'; ?>/>
			<p class="description"><?php _e( 'You have to save changes after the selection or removal of the image.', 'ultimate-watermark' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Watermark image preview.
	 * 
	 * @return mixed
	 */
	public function ulwm_watermark_preview() {
		if ( Ultimate_Watermark()->options['watermark_image']['attachment_id'] !== NULL && Ultimate_Watermark()->options['watermark_image']['attachment_id'] != 0 ) {
			$image = wp_get_attachment_image_src( Ultimate_Watermark()->options['watermark_image']['attachment_id'], array( 300, 300 ), false );
			$image_selected = true;
		} else
			$image_selected = false;
		?>
		<fieldset id="ulwm_watermark_preview">
			<div id="previewImg_imageDiv">
			<?php
				if ( $image_selected ) {
					$image = wp_get_attachment_image_src( Ultimate_Watermark()->options['watermark_image']['attachment_id'], array( 300, 300 ), false );
					?>
					<img id="previewImg_image" src="<?php echo $image[0]; ?>" alt="" width="300" />
				<?php } else { ?>
					<img id="previewImg_image" src="" alt="" width="300" style="display: none;" />
				<?php }
			?>
			</div>
			<p id="previewImageInfo" class="description">
			<?php
			if ( ! $image_selected ) {
				_e( 'Watermak has not been selected yet.', 'ultimate-watermark' );
			} else {
				$image_full_size = wp_get_attachment_image_src( Ultimate_Watermark()->options['watermark_image']['attachment_id'], 'full', false );

				echo __( 'Original size', 'ultimate-watermark' ) . ': ' . $image_full_size[1] . ' ' . __( 'px', 'ultimate-watermark' ) . ' / ' . $image_full_size[2] . ' ' . __( 'px', 'ultimate-watermark' );
			}
		?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Watermark size option.
	 * 
	 * @return mixed
	 */
	public function ulwm_watermark_size() {
		?>
		<fieldset id="ulwm_watermark_size">
			<div id="watermark-type">
				<input type="radio" id="type1" value="0" name="ulwm_options[watermark_image][watermark_size_type]" <?php checked( Ultimate_Watermark()->options['watermark_image']['watermark_size_type'], 0, true ); ?> /><label for="type1"><?php _e( 'original', 'ultimate-watermark' ); ?></label>
				<input type="radio" id="type2" value="1" name="ulwm_options[watermark_image][watermark_size_type]" <?php checked( Ultimate_Watermark()->options['watermark_image']['watermark_size_type'], 1, true ); ?> /><label for="type2"><?php _e( 'custom', 'ultimate-watermark' ); ?></label>
				<input type="radio" id="type3" value="2" name="ulwm_options[watermark_image][watermark_size_type]" <?php checked( Ultimate_Watermark()->options['watermark_image']['watermark_size_type'], 2, true ); ?> /><label for="type3"><?php _e( 'scaled', 'ultimate-watermark' ); ?></label>
			</div>
			<p class="description"><?php _e( 'Select method of aplying watermark size.', 'ultimate-watermark' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Watermark custom size option.
	 * 
	 * @return mixed
	 */
	public function ulwm_watermark_size_custom() {
		?>
		<fieldset id="ulwm_watermark_size_custom">
			<?php _e( 'x:', 'ultimate-watermark' ); ?> <input type="text" size="5"  name="ulwm_options[watermark_image][absolute_width]" value="<?php echo Ultimate_Watermark()->options['watermark_image']['absolute_width']; ?>"> <?php _e( 'px', 'ultimate-watermark' ); ?>
			<br />
			<?php _e( 'y:', 'ultimate-watermark' ); ?> <input type="text" size="5"  name="ulwm_options[watermark_image][absolute_height]" value="<?php echo Ultimate_Watermark()->options['watermark_image']['absolute_height']; ?>"> <?php _e( 'px', 'ultimate-watermark' ); ?>
		</fieldset>
		<p class="description"><?php _e( 'Those dimensions will be used if "custom" method is selected above.', 'ultimate-watermark' ); ?></p>
		<?php
	}

	/**
	 * Watermark scaled size option.
	 * 
	 * @return mixed
	 */
	public function ulwm_watermark_size_scaled() {
		?>
		<fieldset id="ulwm_watermark_size_scaled">
			<div>
				<input type="text" id="ulwm_size_input" maxlength="3" class="hide-if-js" name="ulwm_options[watermark_image][width]" value="<?php echo Ultimate_Watermark()->options['watermark_image']['width']; ?>" />
				<div class="wplike-slider">
					<span class="left hide-if-no-js">0</span><span class="middle" id="ulwm_size_span" title="<?php echo Ultimate_Watermark()->options['watermark_image']['width']; ?>"><span class="ulwm-current-value" style="left: <?php echo Ultimate_Watermark()->options['watermark_image']['width']; ?>%;"><?php echo Ultimate_Watermark()->options['watermark_image']['width']; ?></span></span><span class="right hide-if-no-js">100</span>
				</div>
			</div>
		</fieldset>
		<p class="description"><?php _e( 'Enter a number ranging from 0 to 100. 100 makes width of watermark image equal to width of the image it is applied to.', 'ultimate-watermark' ); ?></p>
		<?php
	}

	/**
	 * Watermark custom size option.
	 * 
	 * @return mixed
	 */
	public function ulwm_watermark_opacity() {
		?>
		<fieldset id="ulwm_watermark_opacity">
			<div>
				<input type="text" id="ulwm_opacity_input" maxlength="3" class="hide-if-js" name="ulwm_options[watermark_image][transparent]" value="<?php echo Ultimate_Watermark()->options['watermark_image']['transparent']; ?>" />
				<div class="wplike-slider">
					<span class="left hide-if-no-js">0</span><span class="middle" id="ulwm_opacity_span" title="<?php echo Ultimate_Watermark()->options['watermark_image']['transparent']; ?>"><span class="ulwm-current-value" style="left: <?php echo Ultimate_Watermark()->options['watermark_image']['transparent']; ?>%;"><?php echo Ultimate_Watermark()->options['watermark_image']['transparent']; ?></span></span><span class="right hide-if-no-js">100</span>
				</div>
			</div>
		</fieldset>
		<p class="description"><?php _e( 'Enter a number ranging from 0 to 100. 0 makes watermark image completely transparent, 100 shows it as is.', 'ultimate-watermark' ); ?></p>
		<?php
	}

	/**
	 * Image quality option.
	 * 
	 * @return mixed
	 */
	public function ulwm_image_quality() {
		?>
		<fieldset id="ulwm_image_quality">
			<div>
				<input type="text" id="ulwm_quality_input" maxlength="3" class="hide-if-js" name="ulwm_options[watermark_image][quality]" value="<?php echo Ultimate_Watermark()->options['watermark_image']['quality']; ?>" />
				<div class="wplike-slider">
					<span class="left hide-if-no-js">0</span><span class="middle" id="ulwm_quality_span" title="<?php echo Ultimate_Watermark()->options['watermark_image']['quality']; ?>"><span class="ulwm-current-value" style="left: <?php echo Ultimate_Watermark()->options['watermark_image']['quality']; ?>%;"><?php echo Ultimate_Watermark()->options['watermark_image']['quality']; ?></span></span><span class="right hide-if-no-js">100</span>
				</div>
			</div>
		</fieldset>
		<p class="description"><?php _e( 'Set output image quality.', 'ultimate-watermark' ); ?></p>
		<?php
	}

	/**
	 * Image format option.
	 * 
	 * @return mixed
	 */
	public function ulwm_image_format() {
		?>
		<fieldset id="ulwm_image_format">
			<div id="jpeg-format">
				<input type="radio" id="baseline" value="baseline" name="ulwm_options[watermark_image][jpeg_format]" <?php checked( Ultimate_Watermark()->options['watermark_image']['jpeg_format'], 'baseline', true ); ?> /><label for="baseline"><?php _e( 'baseline', 'ultimate-watermark' ); ?></label>
				<input type="radio" id="progressive" value="progressive" name="ulwm_options[watermark_image][jpeg_format]" <?php checked( Ultimate_Watermark()->options['watermark_image']['jpeg_format'], 'progressive', true ); ?> /><label for="progressive"><?php _e( 'progressive', 'ultimate-watermark' ); ?></label>
			</div>
		</fieldset>
		<p class="description"><?php _e( 'Select baseline or progressive image format.', 'ultimate-watermark' ); ?></p>
		<?php
	}

	/**
	 * Right click image protection option.
	 * 
	 * @return mixed
	 */
	public function ulwm_protection_right_click() {
		?>
		<label for="ulwm_protection_right_click">
			<input id="ulwm_protection_right_click" type="checkbox" <?php checked( ( ! empty( Ultimate_Watermark()->options['image_protection']['rightclick'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="ulwm_options[image_protection][rightclick]">
<?php _e( 'Disable right mouse click on images', 'ultimate-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Drag and drop image protection option.
	 * 
	 * @return mixed
	 */
	public function ulwm_protection_drag_drop() {
		?>
		<label for="ulwm_protection_drag_drop">
			<input id="ulwm_protection_drag_drop" type="checkbox" <?php checked( ( ! empty( Ultimate_Watermark()->options['image_protection']['draganddrop'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="ulwm_options[image_protection][draganddrop]">
<?php _e( 'Prevent drag and drop', 'ultimate-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Logged-in users image protection option.
	 * 
	 * @return mixed
	 */
	public function ulwm_protection_logged() {
		?>
		<label for="ulwm_protection_logged">
			<input id="ulwm_protection_logged" type="checkbox" <?php checked( ( ! empty( Ultimate_Watermark()->options['image_protection']['forlogged'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="ulwm_options[image_protection][forlogged]">
<?php _e( 'Enable image protection for logged-in users also', 'ultimate-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Backup the original image
	 * 
	 * @return mixed
	 */
	public function ulwm_backup_image() {
		?>
		<label for="ulwm_backup_size_full">
			<input id="ulwm_backup_size_full" type="checkbox" <?php checked( ! empty( Ultimate_Watermark()->options['backup']['backup_image'] ), true, true ); ?> value="1" name="ulwm_options[backup][backup_image]">
<?php echo __( 'Backup the full size image.', 'ultimate-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Image backup quality option.
	 * 
	 * @return mixed
	 */
	public function ulwm_backup_image_quality() {
		?>
		<fieldset id="ulwm_backup_image_quality">
			<div>
				<input type="text" id="ulwm_backup_quality_input" maxlength="3" class="hide-if-js" name="ulwm_options[backup][backup_quality]" value="<?php echo Ultimate_Watermark()->options['backup']['backup_quality']; ?>" />
				<div class="wplike-slider">
					<span class="left hide-if-no-js">0</span><span class="middle" id="ulwm_backup_quality_span" title="<?php echo Ultimate_Watermark()->options['backup']['backup_quality']; ?>"><span class="ulwm-current-value" style="left: <?php echo Ultimate_Watermark()->options['backup']['backup_quality']; ?>%;"><?php echo Ultimate_Watermark()->options['backup']['backup_quality']; ?></span></span><span class="right hide-if-no-js">100</span>
				</div>
			</div>
		</fieldset>
		<p class="description"><?php _e( 'Set output image quality.', 'ultimate-watermark' ); ?></p>
		<?php
	}
	
	/**
	 * This function is similar to the function in the Settings API, only the output HTML is changed.
	 * Print out the settings fields for a particular settings section
	 *
	 * @global $wp_settings_fields Storage array of settings fields and their pages/sections
	 *
	 * @since 0.1
	 *
	 * @param string $page Slug title of the admin page who's settings fields you want to show.
	 * @param string $section Slug title of the settings section who's fields you want to show.
	 */
	function do_settings_sections( $page ) {
		global $wp_settings_sections, $wp_settings_fields;
	 
		if ( ! isset( $wp_settings_sections[$page] ) )
			return;
	 
		foreach ( (array) $wp_settings_sections[$page] as $section ) {
			echo '<div id="" class="postbox '.$section['id'].'">';
			echo '<button type="button" class="handlediv button-link" aria-expanded="true"><span class="screen-reader-text">' . __('Toggle panel', 'ultimate-watermark') . '</span><span class="toggle-indicator" aria-hidden="true"></span></button>';
			if ( $section['title'] )
				echo "<h3 class=\"hndle\"><span>{$section['title']}</span></h3>\n";
	 
			if ( $section['callback'] )
				call_user_func( $section['callback'], $section );
	 
			if ( ! isset( $wp_settings_fields ) || !isset( $wp_settings_fields[$page] ) || !isset( $wp_settings_fields[$page][$section['id']] ) )
				continue;
			echo '<div class="inside"><table class="form-table">';
			do_settings_fields( $page, $section['id'] );
			echo '</table></div>';
			echo '</div>';
		}
	}

}
