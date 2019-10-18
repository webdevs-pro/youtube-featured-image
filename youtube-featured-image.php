<?php
/*
Plugin Name: YouTube featured image for Gutenberg
Plugin URI: https://github.com/webdevs-pro/youtube-featured-image/
Description: This plugin automatically setup post thumbnail based on YouTube video URL in Gutenberg post editor
Version: 1.2.1
Author: Alex Ischenko
Author URI: https://github.com/webdevs-pro/
Text Domain:  youtube-featured-image
*/


include( plugin_dir_path( __FILE__ ) . 'admin/admin.php');

// register meta
function yfi_register_meta() {
   register_meta( 'post', 'yfi_url', array(
      'type'		=> 'string',
      'single'	=> true,
      'show_in_rest'	=> true,
   ) );
}
add_action( 'init', 'yfi_register_meta' );


define("YFI_ASPECT_X", 16);
define("YFI_ASPECT_Y", 9);
define("YFI_WIDTH", 1280);
define("YFI_HEIGHT", 720);
define("YFI_CROP", true);
define("YFI_WPMFTAX", intval(get_option('yfi_wpmf_taxonomy')));



load_plugin_textdomain( 'youtube-featured-image', false, basename( dirname( __FILE__ ) ) . '/languages' ); 


// setup field on gutenberg editor page
add_action( 'admin_init', function() {

   $post_types = get_option('yfi_post_types');
   if ( $post_types === false ) {
      $post_types = array('post');
   } 

   if ($post_types) {

      global $pagenow;

      if ((!empty($pagenow) && ('post-new.php' === $pagenow || 'post.php' === $pagenow )) && in_array(get_post_type( $_GET['post']), $post_types)) {

            // enqueue script
            function yfi_enqueue() {
               wp_enqueue_script(
                  'youtube-featured-image-script',
                  plugins_url( 'youtube-featured-image.js', __FILE__ ),
                  array( 'wp-i18n', 'wp-blocks', 'wp-edit-post', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-plugins', 'wp-edit-post' )
               );
               // translation strings to pass to script
               $translation_strings = array(
                  'label' => __("YouTube video link", 'youtube-featured-image'),
                  'help' => __("Paste URL to YouTube video to fetch image and set it as post featured image.", 'youtube-featured-image'),
               );
               wp_localize_script( 'youtube-featured-image-script', 'translation_strings', $translation_strings );
            }
            add_action( 'enqueue_block_editor_assets', 'yfi_enqueue' );
         
            // custom admin styles for daily reading
            function yfi_control_style_admin_head() {
               echo '<style type="text/css">
                  .yfi-control-container {
                     margin-top: 20px;
                  }
               </style>';
            }
            add_action('admin_head', 'yfi_control_style_admin_head');
      
      }

   }

});


// upload, resize and set featured image
function ai_set_youtube_featured_image( $post, $request ) {

   $youtube_field = get_post_meta($post->ID, 'yfi_url', true);
   
   preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $youtube_field, $match);

   if( has_post_thumbnail($post->ID) || !$youtube_field || !$match[1] ) {
      return;
   }

   $youtube_id = $match[1];

   // get thumbnail
   $file_headers = get_headers( 'http://img.youtube.com/vi/' . $youtube_id . '/maxresdefault.jpg' );
   if (strpos($file_headers[0], '404 Not Found' ) == false ) {
      $image_url = 'http://img.youtube.com/vi/' . $youtube_id . '/maxresdefault.jpg';
   } else {
      $image_url = 'http://img.youtube.com/vi/' . $youtube_id . '/sddefault.jpg';         
   }

   require_once ABSPATH . 'wp-admin/includes/media.php';
   require_once ABSPATH . 'wp-admin/includes/file.php';
   require_once ABSPATH . 'wp-admin/includes/image.php';

   // save temp image to server
   $image_extension = pathinfo( $image_url, PATHINFO_EXTENSION);
   $temp_file_name = $youtube_id . '.' . $image_extension;
   $response = wp_remote_get( $image_url );
   $image_contents = $response['body'];
   file_put_contents( $_SERVER['DOCUMENT_ROOT'] . $temp_file_name, $image_contents);

   // crop and resize image
   add_filter( 'image_resize_dimensions', 'yfi_image_resize_dimensions', 1, 6);	
   $image = wp_get_image_editor( $_SERVER['DOCUMENT_ROOT'] . $temp_file_name );
   if ( !is_wp_error( $image ) ) {
      $old_size = $image->get_size();
      $old_width = $old_size['width'];
      $new_height = $old_width / YFI_ASPECT_X * YFI_ASPECT_Y;
      // $image->resize( $old_width, $new_height, true );
      $image->resize( $old_width, $new_height, YFI_CROP );
      $image->resize( YFI_WIDTH, YFI_HEIGHT, false );
      $image->save( $_SERVER['DOCUMENT_ROOT'] . $temp_file_name );
   }
   remove_filter( 'image_resize_dimensions', 'yfi_image_resize_dimensions', 1, 6);	

   $image_contents = file_get_contents($_SERVER['DOCUMENT_ROOT'] . $temp_file_name);
   $upload = wp_upload_bits( $temp_file_name, null, $image_contents );
   wp_delete_file( $_SERVER['DOCUMENT_ROOT'] . $temp_file_name );

   $wp_filetype = wp_check_filetype( basename( $upload['file'] ), null );

   $upload = apply_filters( 'wp_handle_upload', array(
      'file' => $upload['file'],
      'url'  => $upload['url'],
      'type' => $wp_filetype['type']
   ), 'sideload' );

   $attachment = array(
      'post_mime_type'	=> $upload['type'],
      'post_title'		=> get_the_title( $post->ID ),
      'post_content'		=> '',
      'post_status'		=> 'inherit'
   );

   $attach_id = wp_insert_attachment( $attachment, $upload['file'], $post->ID );
   $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
   wp_update_attachment_metadata( $attach_id, $attach_data );
   set_post_thumbnail( $post->ID, $attach_id );

   // set WP Media Folder taxonomy term for uploaded image
   if (YFI_WPMFTAX) {
      wp_set_object_terms( $attach_id, YFI_WPMFTAX, WPMF_TAXO, false);
   }
   
}
// perform only on allowed post types
$post_types = get_option('yfi_post_types');
if ( $post_types === false ) {
   $post_types = array('post');
}
if ($post_types) {
   foreach ($post_types as $post_type) {
      $hook_name = 'rest_after_insert_' . $post_type;
      add_action($hook_name, 'ai_set_youtube_featured_image', 10, 2);
   }
}



// some functions
// allow upscale image
function yfi_image_resize_dimensions( $nonsense, $orig_w, $orig_h, $dest_w, $dest_h, $crop = false) {
   if ( $crop ) {
      $aspect_ratio = $orig_w / $orig_h;
      $new_w = min($dest_w, $orig_w);
      $new_h = min($dest_h, $orig_h);
      if ( !$new_w ) {
         $new_w = intval($new_h * $aspect_ratio);
      }
      if ( !$new_h ) {
         $new_h = intval($new_w / $aspect_ratio);
      }
      $size_ratio = max($new_w / $orig_w, $new_h / $orig_h);
      $crop_w = round($new_w / $size_ratio);
      $crop_h = round($new_h / $size_ratio);
      $s_x = floor( ($orig_w - $crop_w) / 2 );
      $s_y = floor( ($orig_h - $crop_h) / 2 );
   } else {
      $crop_w = $orig_w;
      $crop_h = $orig_h;
      $s_x = 0;
      $s_y = 0;
      if ($orig_w >= $dest_w && $orig_h >= $dest_h ) {
         list( $new_w, $new_h ) = wp_constrain_dimensions( $orig_w, $orig_h, $dest_w, $dest_h );
      } else {
         $ratio = $dest_w / $orig_w;
         $w = intval( $orig_w  * $ratio );
         $h = intval( $orig_h * $ratio );
         list( $new_w, $new_h ) = array( $w, $h );
      }
   }
   if ( $new_w == $orig_w && $new_h == $orig_h )
      return false;
   return array( 0, 0, (int) $s_x, (int) $s_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h );
}


// plugin updates
require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/webdevs-pro/youtube-featured-image',
	__FILE__,
	'youtube-featured-image'
);