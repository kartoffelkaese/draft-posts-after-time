<?php
declare(strict_types=1);

/*
Plugin Name: Draft Posts after Time
Plugin URI: https://github.com/kartoffelkaese/draft-posts-after-time
Description: Adds a custom time field to the "New Post" area and sets the post to draft status if the specified time has passed.
Version: 2.1
Author: Martin Urban
Author URI: https://github.com/kartoffelkaese/
Requires PHP: 8.3
*/

function draft_posts_date_box(): void {
    foreach (['post', 'event'] as $post_type) {
        add_meta_box(
            'draft-posts-meta-box',
            'Draft Date',
            'draft_posts_date_box_callback',
            $post_type,
            'side',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'draft_posts_date_box');

function draft_posts_date_box_callback(WP_Post $post): void {
    wp_nonce_field(basename(__FILE__), 'draft_posts_date_box_nonce');
    $expiration_date = get_post_meta($post->ID, 'expiration_date', true);
    ?>
    <p>
        <label for="expiration-date">An diesem Datum wird der Beitrag auf "Entwurf" gesetzt:</label><br>
        <input type="date" 
               id="expiration-date" 
               name="expiration_date" 
               value="<?php echo esc_attr((string)$expiration_date); ?>">
    </p>
    <?php
}

function draft_posts_box_save(int $post_id): void {
    if (!isset($_POST['draft_posts_date_box_nonce']) || 
        !wp_verify_nonce(
            $_POST['draft_posts_date_box_nonce'], 
            basename(__FILE__)
        )) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (!isset($_POST['expiration_date'])) {
        return;
    }

    $expiration_date = sanitize_text_field($_POST['expiration_date']);
    update_post_meta($post_id, 'expiration_date', $expiration_date);
}
add_action('save_post', 'draft_posts_box_save');

function draft_posts_schedule_event(): void {
    if (!wp_next_scheduled('draft_posts_hook')) {
        wp_schedule_event(time(), 'daily', 'draft_posts_hook');
    }
}
add_action('wp', 'draft_posts_schedule_event');

function draft_posts_event_callback(): void {
    $today = date('Y-m-d');

    foreach (['post', 'event'] as $post_type) {
        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'meta_key' => 'expiration_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'expiration_date',
                    'value' => $today,
                    'compare' => '=',
                    'type' => 'DATE',
                ]
            ],
        ]);

        foreach ($posts as $post) {
            $result = wp_update_post([
                'ID' => $post->ID,
                'post_status' => 'draft',
            ]);

            if (is_wp_error($result)) {
                error_log(sprintf(
                    'Error updating post %d: %s',
                    $post->ID,
                    $result->get_error_message()
                ));
            }
        }
    }
}
add_action('draft_posts_hook', 'draft_posts_event_callback');

// Cleanup on deactivation
register_deactivation_hook(__FILE__, function(): void {
    wp_clear_scheduled_hook('draft_posts_hook');
});
