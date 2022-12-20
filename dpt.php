<?php
/*
 * Plugin Name: Custom Post Time Field
 * Description: Adds a custom time field to the "New Post" area and sets the post to draft status if the specified time has passed.
 * Version: 1.0
 * Author: Martin Urban
 * Author URI: https://github.com/kartoffelkaese/draft-posts-after-time
 */

function custom_post_time_field() {
  add_meta_box('custom_post_time_field', 'Draft Post at', 'custom_post_time_field_callback', 'post', 'side', 'high');
}
add_action( 'add_meta_boxes', 'custom_post_time_field' );

function custom_post_time_field_callback($post) {
  wp_nonce_field(basename(__FILE__), 'custom_post_time_nonce');
  $custom_post_time = get_post_meta($post->ID, 'custom_post_time', true);
  ?>
  <p>
    <input type="time" id="custom-post-time" name="custom-post-time" value="<?php echo esc_attr($custom_post_time); ?>">
  </p>
  <?php
}

function custom_post_time_save($post_id) {
  $is_autosave = wp_is_post_autosave($post_id);
  $is_revision = wp_is_post_revision($post_id);
  $is_valid_nonce = (isset( $_POST['custom_post_time_nonce'] ) && wp_verify_nonce($_POST['custom_post_time_nonce'], basename(__FILE__ ))) ? 'true' : 'false';

  if ($is_autosave || $is_revision || !$is_valid_nonce) {
    return;
  }

  if (isset( $_POST['custom-post-time'])) {
    update_post_meta($post_id, 'custom_post_time', sanitize_text_field($_POST['custom-post-time']));
  }
}
add_action( 'save_post', 'custom_post_time_save' );

function custom_post_time_check($new_status, $old_status, $post) {
  if ($old_status != 'publish' && $new_status == 'publish') {
    $custom_post_time = get_post_meta($post->ID, 'custom_post_time', true);
    $current_time = current_time( 'H:i' );
    if (strtotime($custom_post_time) < strtotime($current_time)) {
      $post->post_status = 'draft';
      wp_update_post($post);
    }
  }
}
add_action('transition_post_status', 'custom_post_time_check', 10, 3);
