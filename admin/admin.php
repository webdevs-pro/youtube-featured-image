<?php 

// plugin settings link in plugins admin page
add_filter('plugin_action_links_' . YFI_PLUGIN_BASENAME, function ( $links ) {
	$settings_link = '<a href="' . admin_url( 'options-general.php?page=yfi_options' ) . '">' . __('Settings') . '</a>';

	array_unshift( $links, $settings_link );
	return $links;
});

/* ----------------------------------------------------------------------------- */
/* Add Menu Page */
/* ----------------------------------------------------------------------------- */ 


// create custom plugin settings menu
add_action('admin_menu', 'yfi_create_menu');

function yfi_create_menu() {

	//create new top-level menu
	//add_menu_page('My Cool Plugin Settings', 'Cool Settings', 'administrator', __FILE__, 'yfi_settings_page' , plugins_url('/images/icon.png', __FILE__) );
   add_options_page(
      __('YouTube Featured Image Settings','youtube-featured-image'), 
      __('YFI settings','youtube-featured-image'), 
      'manage_options', 
      'yfi_options', 
      'yfi_settings_page'
   );
	//call register settings function
	add_action( 'admin_init', 'register_yfi_settings' );
}


function register_yfi_settings() {
	//register our settings
   register_setting( 'yfi-settings-group', 'yfi_post_types' );
   register_setting( 'yfi-settings-group', 'yfi_wpmf_taxonomy' );
   if ( get_option('yfi_post_types') === false ) {
      update_option( 'yfi_post_types', array('post') ); // default checked
   } 
   
}

function yfi_settings_page() {
?>
<div class="wrap">
<h1><?php echo __('YouTube Featured Image Settings','youtube-featured-image') ?></h1>

<form method="post" action="options.php">
   <?php settings_fields( 'yfi-settings-group' ); ?>
   <?php do_settings_sections( 'yfi-settings-group' ); ?>
   <table class="form-table">

      <tr valign="top">
      <th scope="row">Post types</th>
      <td>
         <?php
         $post_types = get_post_types(['public'=>true]);

         $value = get_option('yfi_post_types');

         foreach ( $post_types as $post_type ) :

         $checked = '';

         $checked = ( @in_array( $post_type , $value ) ) ? 'checked="checked"': '';?>

         <label><input type="checkbox" name="yfi_post_types[]" value="<?php echo $post_type; ?>" <?php echo $checked; ?> /> <?php echo $post_type; ?></label><br />

         <?php endforeach; ?>
      </td>
      </tr>

      
      <tr valign="top">
      <th scope="row">WP Media Folder plugin folder ID for uploaded images</th>
      <td><input type="text" name="yfi_wpmf_taxonomy" value="<?php echo esc_attr( get_option('yfi_wpmf_taxonomy') ); ?>" /></td>
      </tr>
    </table>


    
    <?php submit_button(); ?>

</form>
</div>
<?php }