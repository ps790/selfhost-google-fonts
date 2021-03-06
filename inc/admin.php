<?php

namespace Sphere\SGF;

/**
 * Admin initialization for SGCF plugin
 * 
 * @author  asadkn
 * @since   1.0.0
 * @package Sphere\SGF
 */
class Admin 
{
	/**
	 * Setup hooks
	 */
	public function init()
	{
		add_action('cmb2_admin_init', array($this, 'setup_options'));

		// Enqueue at a lower priority to be after CMB2
		add_action('admin_enqueue_scripts', array($this, 'register_assets'), 99);

		/**
		 * Add notice for plugin activation if needed
		 */
		if (!Plugin::options()->enabled) {
			add_action('admin_notices', array($this, 'active_notice'));
		}

		// Page to download database
		add_action('admin_menu', function() {
			add_submenu_page(
				null, 
				'Delete Cache', 
				'Delete Cache', 
				'manage_options', 
				'sgf-delete-cache', 
				array($this, 'delete_cache')
			);
		});
	}

	/**
	 * Register admin assets
	 */
	public function register_assets()
	{
		// Specific assets for option pages only
		if (!empty($_GET['page']) && $_GET['page'] == 'sgf_options') {

			wp_enqueue_script(
				'sgf-cmb2-conditionals', 
				Plugin::get_instance()->dir_url . 'js/admin/cmb2-conditionals.js', 
				array('jquery'),
				Plugin::VERSION
			);

			wp_enqueue_style(
				'sgf-admin-cmb2',
				Plugin::get_instance()->dir_url . 'css/admin/cmb2.css',
				array(),
				Plugin::VERSION
			);
		}
	}

	/**
	 * Delete Cache page
	 */
	public function delete_cache()
	{
		check_admin_referer('sgf_delete_cache');
		delete_transient(Process::CSS_URLS_CACHE);

		echo "Cache cleared.";
	}

	/**
	 * Admin notice about geolocation database
	 */
	public function active_notice()
	{
		if (!empty($_GET['page']) && $_GET['page'] == 'sgf_options') {
			return;
		}

		/**
		 * Filter can be used to override the notice
		 */
		if (!apply_filters('sgf/admin/active_notice', true)) {
			return false;
		}

		?>
		<div class="error notice">
		
		<h3><?php esc_html_e('Inactive: Self-Hosted Google Fonts', 'sphere-sgf'); ?></h3>
		<p>
		<?php 
		esc_html_e(
			"This plugin is activated but has not been enabled to process anything in settings. If you don't need to auto self-host Google Fonts, you should deactivate this plugin.", 
			'sphere-sgf'
		); 
		?>
		</p>
			
		<p><a href="<?php echo admin_url('options-general.php?page=sgf_options'); ?>" class="button button-primary"><?php esc_html_e('Plugin Settings', 'sphere-sgf'); ?></a></p>
		
		</div>
		<?php
	}

	/**
	 * Setup admin options with CMB2
	 */
	public function setup_options()
	{

$instructions = <<<EOF
<div>
<h4>Important Info About Self-Hosted Fonts</h4>
<p>
	Once Processing is enabled, the plugin will scan for Google Fonts on your site and download them to your server. These fonts are downloaded from 
	<code>fonts.gstatic.com</code> and have <a href="https://fonts.google.com/attribution">opensource licenses</a> (SIL v1.1 or compatible).
</p>
</div>
EOF;

$instructions .= $this->cache_info();

		// Configure admin options
		$options = new_cmb2_box(array(
			'id'           => 'sgf_options',
			'title'        => esc_html__('Self-Hosted Google Fonts Settings', 'sphere-sgf'),
			'object_types' => array('options-page'),
			'option_key'   => 'sgf_options',
			'parent_slug'  => 'options-general.php',
			'menu_title'   => esc_html__('Self-Hosted Google Fonts', 'sphere-sgf'),
			'classes'      => 'sphere-cmb2-wrap'
		));

		// Instructions
		$options->add_field(array(
			'name'    => '',
			'description' => $instructions,
			'type'    => 'title',
			'id'      => '_instructions',
		));

		$options->add_field(array(
			'name'    => esc_html__('Enable Processing', 'sphere-sgf'),
			'desc'    => esc_html__('Once this is enabled, fonts will be downloaded from Google Fonts server.', 'sphere-sgf'),
			'id'      => 'enabled',
			'type'    => 'select',
			'options' => array(
				'' => esc_html__('Disabled', 'sphere-sgf'),
				1  => esc_html__('Yes, Enable', 'sphere-sgf')
			),
			'default' => '',
		));

		$options->add_field(array(
			'name'    => esc_html__('Disable for Admins', 'sphere-sgf'),
			'desc'    => esc_html__('Disable processing for logged in admin users or any user with capability "manage_options". (Useful if using a pagebuilder that conflicts)', 'sphere-sgf'),
			'id'      => 'disable_for_admins',
			'type'    => 'checkbox',
			'default' => 0,
			'attributes' => array('data-conditional-id' => 'enabled'),
		));

		$options->add_field(array(
			'name'    => esc_html__('Process Enqueues', 'sphere-sgf'),
			'desc'    => esc_html__('Process properly enqueued Google Fonts. This should be enough for most themes and plugins.', 'sphere-sgf'),
			'id'      => 'process_enqueues',
			'type'    => 'checkbox',
			'default' => 1,
			'attributes' => array('data-conditional-id' => 'enabled'),
		));

		$options->add_field(array(
			'name'    => esc_html__('Process CSS Files', 'sphere-sgf'),
			'desc'    => esc_html__('Scan all local CSS files in HTML. Use if processing enqueus is not enough for your themes and plugins. Has slight performance impact - best used with cache plugins.', 'sphere-sgf'),
			'id'      => 'process_css_files',
			'type'    => 'checkbox',
			'default' => 1,
			'attributes' => array('data-conditional-id' => 'enabled'),
		));

		$options->add_field(array(
			'name'    => esc_html__('Process Inline CSS', 'sphere-sgf'),
			'desc'    => esc_html__('Scan all inline CSS. Has slight performance impact - best used with cache plugins.', 'sphere-sgf'),
			'id'      => 'process_css_inline',
			'type'    => 'checkbox',
			'default' => 1,
			'attributes' => array('data-conditional-id' => 'enabled'),
		));

	}

	public function cache_info()
	{
		$cache = array_filter((array) Plugin::process()->get_cache());

		if (empty($cache)) {
			return;
		}

		ob_start();
		?>

		<p class="cache-info" style="margin-top: 20px;">
			<?php printf(esc_html__('Cache Status: %d Items', 'sphere-sgf'), count($cache)); ?>
			<a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sgf-delete-cache'), 'sgf_delete_cache'); ?>" 
				class="button button-secondary" style="margin-left: 10px;">
				<?php echo esc_html('Delete Cache', 'sphere-sgf'); ?>
			</a>
		</p>

		<?php

		return ob_get_clean();
	}

}