<?php 

/*
 * Call settings page
 */ 
	/* Example Settings Page */
	function funkhaus2013_exsettings_page() { ?>

		<style>
			#sync_exp_page { max-width: 400px; }
		</style>

		<div class="wrap">
			<h2>Export Options</h2>
			<form action="options.php" method="post" id="funkhaus2013_examplesettings">
				<?php settings_fields('ss-settings-reg'); ?>
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row"><label for="sync_exp_max">Max Posts:</label></th>
							<td>
								<input name="sync_exp_max" type="number" step="1" min="0" id="sync_exp_max" value="<?php echo get_option('sync_exp_max'); ?>" class="small-text">
								<p class="description">The maximum number of posts to export at one time (0 will export all)</p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="sync_exp_page">Page Sync</label></th>
							<td>
								<?php 
								$args = array(
								    'id'            	=> 'sync_exp_page',
								    'name'          	=> 'sync_exp_page',
								    'selected'			=>  get_option('sync_exp_page'),
								    'show_option_none'  => 'None',
								    'option_none_value' => 0
								);
								wp_dropdown_pages( $args ); ?>
								<p>Selected page and all children will be synced</p>
								<p class="description">(must be enabled on import side)</p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="sync_exp_page">Export Category</label></th>
							<td>
							<?php 
								$args = array(
									'selected'           => get_option('sync_exp_cat'),
									'name'               => 'sync_exp_cat',
									'id'                 => 'sync_exp_cat',
								    'show_option_none'  => 'All Categories',
									'hide_if_empty'      => false,
									'hide_empty'      => false
								);
								wp_dropdown_categories( $args ); ?>
								<p>Select a Category to Export</p>
								<p class="description"></p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label>Export URL:</label></th>
							<td>
								<code><?php echo site_url('/wp-admin/admin-ajax.php?action=funkexporter_init'); ?></code>
								<p class="description">Use this as the source URL for the importing blog</p>
							</td>
						</tr>
						<tr valign="top">
							<th>
								<h2>Import Options</h2>
							</th>
						</tr>
						<tr valign="top" >
							<th scope="row"><label for="sync_imp_url">Source URL:</label></th>
							<td>
								<input name="sync_imp_url" type="text" id="sync_imp_url" value="<?php echo esc_attr(get_option('sync_imp_url')); ?>" class="regular-text">
								<p class="description">Export URL of source blog</p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label for="sync_imp_cat">Import Category</label></th>
							<td>
							<?php 
								$args = array(
									'selected'			=> get_option('sync_imp_cat'),
									'name'				=> 'sync_imp_cat',
									'id'				=> 'sync_imp_cat',
									'orderby'            => 'NAME',
								    'show_option_none'  => 'None',
								    'hide_empty'		=> false,
									'hide_if_empty'		=> false
								);
								wp_dropdown_categories( $args ); ?>
								<p>All imported posts will be placed in this category</p>
								<p class="description"></p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><label>Cron URL:</label></th>
							<td>
								<code><?php echo site_url('/wp-admin/admin-ajax.php?action=funkimporter_init'); ?></code>
								<p class="description">Set up a cron with this URL to run importer automatically</p>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
				</p>
			</form>
		</div><!-- END Wrap -->

		<?php
	}

	/* Save Takeover Settings */
	function funkhaus2013_exsettings_init(){
		register_setting('ss-settings-reg', 'sync_exp_max');
		register_setting('ss-settings-reg', 'sync_exp_page');
		register_setting('ss-settings-reg', 'sync_exp_cat');
		register_setting('ss-settings-reg', 'sync_imp_url');
		register_setting('ss-settings-reg', 'sync_imp_cat');
	}
	add_action('admin_init', 'funkhaus2013_exsettings_init');

	function funkhaus2013_add_exsettings_options_page() {
		add_submenu_page( 'tools.php', 'Site Sync', 'Site Sync', 'manage_options', 'funkhaus_settings', 'funkhaus2013_exsettings_page' );
	}

	add_action('admin_menu','funkhaus2013_add_exsettings_options_page');

?>