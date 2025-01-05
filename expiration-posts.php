<?php
/**
 * Plugin Name: Expiration Posts
 * Plugin URI:  https://sesolibre.com/
 * Description: Plugin para agregar una fecha de caducidad a los posts y cambiar su estado a borrador o privado.
 * Version:     1.0.0
 * Author:      Eduardo Llaguno
 * Author URI:  https://sesolibre.com/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Evitar acceso directo al archivo
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Agregar metabox para la fecha de caducidad
function expiration_posts_add_meta_box() {
    add_meta_box(
        'expiration_posts_meta_box',
        'Fecha de Caducidad',
        'expiration_posts_meta_box_callback',
        'post',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes', 'expiration_posts_add_meta_box' );

// Contenido del metabox
function expiration_posts_meta_box_callback( $post ) {
    wp_nonce_field( 'expiration_posts_save_meta_box_data', 'expiration_posts_meta_box_nonce' );
    $expiration_date = get_post_meta( $post->ID, '_expiration_date', true );
    $expiration_status = get_post_meta( $post->ID, '_expiration_status', true );
    ?>
    <label for="expiration_date">Fecha de Caducidad:</label>
    <input type="date" id="expiration_date" name="expiration_date" value="<?php echo esc_attr( $expiration_date ); ?>">
    <br><br>
    <label for="expiration_status">Estado al Caducar:</label>
    <select id="expiration_status" name="expiration_status">
        <option value="draft" <?php selected( $expiration_status, 'draft' ); ?>>Borrador</option>
        <option value="private" <?php selected( $expiration_status, 'private' ); ?>>Privado</option>
    </select>
    <?php
}

// Guardar la data del metabox
function expiration_posts_save_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['expiration_posts_meta_box_nonce'] ) || ! wp_verify_nonce( wp_unslash($_POST['expiration_posts_meta_box_nonce']), 'expiration_posts_save_meta_box_data' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( isset( $_POST['expiration_date'] ) ) {
        update_post_meta( $post_id, '_expiration_date', sanitize_text_field( wp_unslash($_POST['expiration_date'] ) ) );
    }
    if ( isset( $_POST['expiration_status'] ) ) {
        update_post_meta( $post_id, '_expiration_status', sanitize_text_field( wp_unslash( $_POST['expiration_status'] ) ) );
    }
}
add_action( 'save_post', 'expiration_posts_save_meta_box_data' );

// Programar evento para verificar las fechas de caducidad
function expiration_posts_schedule_check() {
    if ( ! wp_next_scheduled( 'expiration_posts_check_event' ) ) {
        wp_schedule_event( time(), 'hourly', 'expiration_posts_check_event' );
    }
}
add_action( 'wp', 'expiration_posts_schedule_check' );

// Función para verificar y actualizar el estado de los posts
function expiration_posts_check() {
    $args = array(
        'post_type' => 'post',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_expiration_date',
                'compare' => '!=',
                'value' => '0000-00-00',
            ),
        ),
    );
    $posts = get_posts( $args );
    foreach ( $posts as $post ) {
        $expiration_date = get_post_meta( $post->ID, '_expiration_date', true );
        $expiration_status = get_post_meta( $post->ID, '_expiration_status', true );

        if ( $expiration_date && strtotime( $expiration_date ) <= time() ) {
            $update_post = array(
                'ID' => $post->ID,
                'post_status' => $expiration_status,
            );
            wp_update_post( $update_post );
        }
    }
}
add_action( 'expiration_posts_check_event', 'expiration_posts_check' );

// Desactivación del Plugin
function expiration_posts_deactivation() {
	wp_clear_scheduled_hook( 'expiration_posts_check_event' );
}
register_deactivation_hook( __FILE__, 'expiration_posts_deactivation' );