<?php
/*
Plugin Name: WP Swift: Customer Area FTP Importer
Plugin URI: https://github.com/wp-swift-wordpress/wp-swift-customer-area-ftp-importer
Description: Parses the WP Customer Area assigned FTP folder and facilitates the importing of PDFs into WP Customer Area private files.
Version: 1
Author: Gary Swift
Author URI: https://github.com/wp-swift-wordpress-plugins
License: GPL2
*/
class WPSwiftCustomerAreaFTPImporter {
	private $wp_swift_customer_area_ftp_importer_options;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'wp_swift_customer_area_ftp_importer_add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'wp_swift_customer_area_ftp_importer_page_init' ) );
	}

	public function wp_swift_customer_area_ftp_importer_add_plugin_page() {
		add_management_page(
			'WP Swift: Customer Area FTP Importer', // page_title
			'File Importer', // menu_title
			'manage_options', // capability
			'wp-swift-customer-area-ftp-importer', // menu_slug
			array( $this, 'wp_swift_customer_area_ftp_importer_create_admin_page' ) // function
		);
	}

	public function wp_swift_customer_area_ftp_importer_create_admin_page() {
		$this->wp_swift_customer_area_ftp_importer_options = get_option( 'wp_swift_customer_area_ftp_importer_option_name' ); 
		?>

		<div class="wrap">
			<h2>WP Swift: Customer Area FTP Importer</h2>
			<p>Parses the WP Customer Area assigned FTP folder and facilitates the importing of PDFs into WP Customer Area private files.</p>

			<?php if (!isset($_POST["filenames"])): ?>

				<?php if ($this->get_files()): ?>
					<div style="background-color: #F9F9F9; padding: 1rem; padding: 1rem">
					<form method="post" action="tools.php?page=wp-swift-customer-area-ftp-importer">
						<?php
							settings_fields( 'wp_swift_customer_area_ftp_importer_option_group' );
							do_settings_sections( 'wp-swift-customer-area-ftp-importer-admin' );
							submit_button("Import PDFs as Customer Area Files");
						?>
					</form>	
				</div>
				<?php endif ?>

			<?php 
			else: 
				$this->wp_swift_save_available_files();
			endif; ?>
		</div>
	<?php }

	public function wp_swift_customer_area_ftp_importer_page_init() {
		register_setting(
			'wp_swift_customer_area_ftp_importer_option_group', // option_group
			'wp_swift_customer_area_ftp_importer_option_name', // option_name
			array( $this, 'wp_swift_customer_area_ftp_importer_sanitize' ) // sanitize_callback
		);

		add_settings_section(
			'wp_swift_customer_area_ftp_importer_setting_section', // id
			'File Importer', // title
			array( $this, 'wp_swift_customer_area_ftp_importer_section_info' ), // callback
			'wp-swift-customer-area-ftp-importer-admin' // page
		);

		add_settings_field(
			'wp_swift_available_files', // id
			'Available Files', // title
			array( $this, 'wp_swift_available_files_callback' ), // callback
			'wp-swift-customer-area-ftp-importer-admin', // page
			'wp_swift_customer_area_ftp_importer_setting_section' // section
		);
		add_settings_field(
			'wp_swift_available_groups', // id
			'User Groups', // title
			array( $this, 'wp_swift_available_groups_callback' ), // callback
			'wp-swift-customer-area-ftp-importer-admin', // page
			'wp_swift_customer_area_ftp_importer_setting_section' // section
		);	

		add_settings_field(
			'wp_swift_available_users', // id
			'Users', // title
			array( $this, 'wp_swift_available_users_callback' ), // callback
			'wp-swift-customer-area-ftp-importer-admin', // page
			'wp_swift_customer_area_ftp_importer_setting_section' // section
		);	
	}

	public function wp_swift_customer_area_ftp_importer_sanitize($input) {
		$sanitary_values = array();
		if ( isset( $input['wp_swift_available_files'] ) ) {
			$sanitary_values['wp_swift_available_files'] = sanitize_text_field( $input['wp_swift_available_files'] );
		}

		return $sanitary_values;
	}

	public function wp_swift_customer_area_ftp_importer_section_info() {
		
	}

	private function get_path() {
		return WP_CONTENT_DIR.'/customer-area/ftp-uploads/';
	}

	private function get_files() {
		$path = $this->get_path();
		$filenames = null;

		if (is_dir($path)) {
			if ($handle = opendir($path)) {
				$filenames = array();
			    while (false !== ($entry = readdir($handle))) {

			        if ($entry != "." && $entry != ".." && $entry != ".DS_Store") {		     
			            $info = new SplFileInfo($entry);
						if ($info->getExtension() === 'pdf') {
							$filenames[] = $entry;
						}
			        }
			    }
			    closedir($handle);
			}
		}
		else {
			echo "<p>Invalid path!</p>";
			echo "<pre>"; var_dump($path); echo "</pre>";
		}
		return $filenames;
	}

	public function wp_swift_available_files_callback() {
		$path = $this->get_path();
		$filenames = $this->get_files();

		if (count($filenames)): $i = 0; ?>
			
			<p>Parsing FTP directory:</p><br>
			<div><input type="text" value="<?php echo $path ?>" name="path" style="width: 100%" readonly></div><br>
			<p>The following files are available for import:</p><br>
			<?php foreach ($filenames as $filename): ?>
				 	<div><label for="filename-<?php echo $i ?>"><input type="checkbox" value="<?php echo $filename ?>" id="filename-<?php echo $i ?>" name="filenames[]" style="" checked><?php echo $filename ?></label></div>			
			<?php endforeach; ?>

		<?php else: ?>
			<p>No files in FTP directory.</p>
		<?php endif;
	}

	private function wp_swift_save_available_files() {
		$filenames = $_POST["filenames"];
		$usr = array();
		$grp = array();
		$debug = false;
		$user_id = get_current_user_id();
		$time = date('Y-m-d h:m:s');
		$path = $this->get_path();

		if (isset($_POST["group-ids"])): 
			$grp = $_POST["group-ids"];
		endif;
		if (isset($_POST["user-ids"])): 
			$usr = $_POST["user-ids"];
		endif;				

		if (count($filenames)): ?>
			<div style="background-color: #F9F9F9; padding: 1rem">
				<h3>Creating Files</h3>
				<p>The following files have been save as <b>Customer Area</b> files.</p>
			
				<?php foreach ($filenames as $filename): ?>
					<?php 
						$postdata = array(
						    'post_title'   => $filename,
						    'post_content' => '',//'This is the content ',
							'post_status' => 'publish',
							'post_author' => $user_id
						);
						$owners = array(
						    'usr' => $usr,
						    'grp'  =>$grp,
						);
						$files = array(array(
							'name' => $filename,
							'path' => $path,
							'method' => 'move'
						));
					 ?>
					 <?php if (file_exists( $path.$filename )): ?>
							<?php if (!$debug): ?>
								<?php $data = cuar_create_private_file( $postdata, $owners, $files ); ?>
								<?php if ($data): ?>
									<a href="<?php echo admin_url( "post.php?post=$data&action=edit&post_type=cuar_private_file" ); ?>" target="_blank"><?php echo $filename; ?></a>
								<?php endif ?>
							<?php endif; ?>
					 <?php endif ?>		

				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<p>The</p>
		<?php endif;
	}	

	public function wp_swift_available_groups_callback() {
		$posts = get_posts(array(
			'posts_per_page'	=> -1,
			'post_type'			=> 'cuar_user_group',
		));
		
		if( $posts ): ?>

			<?php foreach( $posts as $post ): ?>

				<label for="group-id-<?php echo $post->ID ?>">
					<input type="checkbox" name="group-ids[]" value="<?php echo $post->ID ?>" id="group-id-<?php echo $post->ID ?>"><?php echo $post->post_title ?>
				</label>
			
			<?php endforeach; ?>
		
		<?php endif;		
	}

	public function wp_swift_available_users_callback() {
		$args = array();
		$users = get_users( $args );
		
		if( $users ): ?>

			<?php foreach( $users as $user ): ?>

				<label for="user-id-<?php echo $user->ID ?>">
					<input type="checkbox" name="user-ids[]" value="<?php echo $user->ID ?>" id="user-id-<?php echo $user->ID ?>"><?php echo $user->data->display_name ?>
				</label>
			
			<?php endforeach; ?>
		
		<?php endif;			
	}		
}


function my_plugin_action_links( $links ) {
   $links[] = '<a href="'. esc_url( get_admin_url(null, 'tools.php?page=wp-swift-customer-area-ftp-importer') ) .'">Settings</a>';
   return $links;
}

if ( ! function_exists('write_log')) {
   function write_log ( $log )  {
      if ( is_array( $log ) || is_object( $log ) ) {
         error_log( print_r( $log, true ) );
      } else {
         error_log( $log );
      }
   }
}
if ( is_admin() ) {
	$wp_swift_customer_area_ftp_importer = new WPSwiftCustomerAreaFTPImporter();
	add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'my_plugin_action_links' );
}