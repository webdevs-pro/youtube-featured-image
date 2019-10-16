<?php
/*
Plugin Name: YouTube featured image for Gutenberg
Plugin URI: https://github.com/webdevs-pro/youtube-featured-image/
Description: This plugin automatically set post thumbnail by YouTube video URL in Gutenberg post editor
Version: 1.0
Author: Alex Ischenko
Author URI: https://github.com/webdevs-pro/
*/
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

function ai_youtube_featured_enqueue() {
   wp_enqueue_script(
       'youtube-featured-image-script',
       plugins_url( 'youtube-featured-image.js', __FILE__ ),
       array( 'wp-i18n', 'wp-blocks', 'wp-edit-post', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-plugins', 'wp-edit-post' )
   );
}
add_action( 'enqueue_block_editor_assets', 'ai_youtube_featured_enqueue' );

function ai_youtube_featured_register_meta() {
   register_meta( 'post', 'ai_youtube_featured_url', array(
      'type'		=> 'string',
      'single'	=> true,
      'show_in_rest'	=> true,
   ) );
}
add_action( 'init', 'ai_youtube_featured_register_meta' );


function ai_set_youtube_featured_image( $post, $request ) {

   $youtube_field = get_post_meta($post->ID, 'ai_youtube_featured_url', true);

   if ($youtube_field) {
   
      $parsedURL = parse_url($youtube_field);
      $youtube_id = str_replace('v=', '', $parsedURL['query']);

      
      // get thumbnail
      $file_headers = get_headers( 'http://img.youtube.com/vi/' . $youtube_id . '/maxresdefault.jpg' );
      if (strpos($file_headers[0], '404 Not Found' ) == false ) {
         $image_url = 'http://img.youtube.com/vi/' . $youtube_id . '/maxresdefault.jpg';
      } else {
         $image_url = 'http://img.youtube.com/vi/' . $youtube_id . '/sddefault.jpg';         
      }


      // save temp image to server
      $image_extension = pathinfo( $image_url, PATHINFO_EXTENSION);
      $temp_file_name = $youtube_id . '.' . $image_extension;
      $response = wp_remote_get( $image_url );
      $image_contents = $response['body'];
      file_put_contents( $_SERVER['DOCUMENT_ROOT'] . $temp_file_name, $image_contents);

      // crop and resize image
      add_filter( 'image_resize_dimensions', 'ch_image_resize_dimensions', 1, 6);	
      $image = wp_get_image_editor( $_SERVER['DOCUMENT_ROOT'] . $temp_file_name );
      if ( !is_wp_error( $image ) ) {
         $old_size = $image->get_size();
         $old_width = $old_size['width'];
         $new_height = $old_width / 16 * 9;
         $image->resize( $old_width, $new_height, true );
         $image->resize( $old_width, $new_height, true );
         $image->resize( 1280, 720, false );
         $image->save( $_SERVER['DOCUMENT_ROOT'] . $temp_file_name );
      }
      remove_filter( 'image_resize_dimensions', 'ch_image_resize_dimensions', 1, 6);	

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

      

      //wp_set_object_terms( $attach_id, 91, WPMF_TAXO, false);


   }

}
add_action('rest_after_insert_post', 'ai_set_youtube_featured_image', 10, 2);




// allow upscale image
function ch_image_resize_dimensions( $nonsense, $orig_w, $orig_h, $dest_w, $dest_h, $crop = false) {

   if ( $crop ) {
      // crop the largest possible portion of the original image that we can size to $dest_w x $dest_h
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
      // don't crop, just resize using $dest_w x $dest_h as a maximum bounding box
      $crop_w = $orig_w;
      $crop_h = $orig_h;

      $s_x = 0;
      $s_y = 0;

      /* wp_constrain_dimensions() doesn't consider higher values for $dest :( */
      /* So just use that function only for scaling down ... */
      if ($orig_w >= $dest_w && $orig_h >= $dest_h ) {
         list( $new_w, $new_h ) = wp_constrain_dimensions( $orig_w, $orig_h, $dest_w, $dest_h );
      } else {
         $ratio = $dest_w / $orig_w;
         $w = intval( $orig_w  * $ratio );
         $h = intval( $orig_h * $ratio );
         list( $new_w, $new_h ) = array( $w, $h );
      }
   }

   // if the resulting image would be the same size or larger we don't want to resize it
   // Now WE need larger images ...
   //if ( $new_w >= $orig_w && $new_h >= $orig_h )
   if ( $new_w == $orig_w && $new_h == $orig_h )
      return false;

   // the return array matches the parameters to imagecopyresampled()
   // int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h
   return array( 0, 0, (int) $s_x, (int) $s_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h );

}