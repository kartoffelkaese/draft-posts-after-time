<?php
/*
Plugin Name: Draft Posts after Time
Plugin URI: https://github.com/kartoffelkaese/draft-posts-after-time
Description: Adds a custom time field to the "New Post" area and sets the post to draft status if the specified time has passed.
Version: 1.0
Author: Martin Urban
Author URI: https://github.com/kartoffelkaese/
*/

function draft_posts_date_box() {
  add_meta_box(
    'draft-posts-meta-box', // ID for the meta box
    'Draft Date', // Title for the meta box
    'draft_posts_date_box_callback', // Callback function
    'post', // Post type
    'side', // Context
    'default' // Priority
  );
}
add_action( 'add_meta_boxes', 'draft_posts_date_box' );

function draft_posts_date_box_callback( $post ) {
  wp_nonce_field( basename( __FILE__ ), 'draft_posts_date_box_nonce' );
  $expiration_date = get_post_meta( $post->ID, 'expiration_date', true );
  ?>
  <p>
    <label for="expiration-date">An diesem Datum wird der Beitrag auf "Entwurf" gesetzt:</label><br>
    <input type="date" id="expiration-date" name="expiration_date" value="<?php echo esc_attr( $expiration_date ); ?>">
  </p>
  <?php
}

function draft_posts_box_save( $post_id ) {
  if ( ! isset( $_POST['draft_posts_date_box_nonce'] ) || ! wp_verify_nonce( $_POST['draft_posts_date_box_nonce'], basename( __FILE__ ) ) ) {
    return;
  }

  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
    return;
  }

  if ( ! current_user_can( 'edit_post', $post_id ) ) {
    return;
  }

  if ( ! isset( $_POST['expiration_date'] ) ) {
    return;
  }

  $expiration_date = sanitize_text_field( $_POST['expiration_date'] );
  update_post_meta( $post_id, 'expiration_date', $expiration_date );
}
add_action( 'save_post', 'draft_posts_box_save' );

function draft_posts_schedule_event() {
    if ( ! wp_next_scheduled( 'draft_posts_hook' ) ) {
      wp_schedule_event( time(), 'hourly', 'draft_posts_hook' );
    }
  }
  add_action( 'wp', 'draft_posts_schedule_event' );
  
  function draft_posts_event_callback() {
    $posts = get_posts( array(
      'post_type' => 'post',
      'post_status' => 'publish',
      'meta_key' => 'expiration_date',
      'orderby' => 'meta_value',
      'order' => 'ASC',
    ) );
  
    foreach ( $posts as $post ) {
      $expiration_date = get_post_meta( $post->ID, 'expiration_date', true );
  
      if ( ! empty( $expiration_date ) && strtotime( $expiration_date ) < time() ) {
        $result = wp_update_post( array(
          'ID' => $post->ID,
          'post_status' => 'draft',
        ) 
      );
  
        if ( is_wp_error( $result ) ) {
          error_log( 'Error updating post ' . $post->ID . ': ' . $result->get_error_message() );
        }
      }
    }
  }
  add_action( 'draft_posts_hook', 'draft_posts_event_callback' );
  
