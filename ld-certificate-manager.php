<?php
/**
 * Plugin Name: LearnDash Certificate Manager
 * Description: A custom plugin to manage and export LearnDash certificates.
 * Version: 1.0
 * Author: Muhammad Shamiq Hussain - ShamiqTheDev
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Hook to add the menu
add_action( 'admin_menu', 'ld_certificate_manager_menu' );

function ld_certificate_manager_menu() {
    add_submenu_page(
        'learndash-lms', // Parent slug
        'WPC Manage Certificates', // Page title
        'WPC Manage Certificates', // Menu title
        'manage_options', // Capability
        'wpc-manage-certificates', // Menu slug
        'ld_certificate_manager_page' // Callback function
    );
}

function ld_certificate_manager_page() {
    ?>
    <div class="wrap">
        <h1>Manage Certificates</h1>
        <form method="post">
            <input type="text" name="search" placeholder="Search..."
                value="<?php echo isset($_POST['search']) ? esc_attr($_POST['search']) : ''; ?>">
            <input type="submit" value="Search" class="button-primary">
            <input type="submit" name="export_csv" value="Export CSV" class="button-secondary">
        </form>
        <?php
        // Handle search
        $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        // Display the table
        ld_certificate_manager_table( $search_query );
        ?>
    </div>
    <?php
}

function get_query($search_query = "") {
    global $wpdb;

    $query = "
        SELECT
            c.ID AS certificate_id,
            u.display_name AS student_name,
            u.user_email,
            c.post_title AS certificate_name,
            um.meta_key AS course_meta_key,
            p.post_title AS course_quiz_title,
            um.meta_value AS date_time
        FROM
            {$wpdb->posts} c
        INNER JOIN
            {$wpdb->usermeta} um ON um.meta_key LIKE CONCAT('course_completed_%')
        INNER JOIN
            {$wpdb->users} u ON u.ID = um.user_id
        LEFT JOIN
            {$wpdb->posts} p ON p.ID = um.meta_value
        WHERE
            c.post_type = 'sfwd-certificates'";

    if ($search_query) {
        $query .= $wpdb->prepare("
            AND (c.post_title LIKE %s
            OR u.display_name LIKE %s
            OR u.user_email LIKE %s
            OR p.post_title LIKE %s)",
            '%' . $wpdb->esc_like($search_query) . '%',
            '%' . $wpdb->esc_like($search_query) . '%',
            '%' . $wpdb->esc_like($search_query) . '%',
            '%' . $wpdb->esc_like($search_query) . '%');
    }

    return $query;
}

function ld_certificate_manager_table( $search_query ) {
    global $wpdb;

    // Query to get certificates
    $query = get_query($search_query);

    $results = $wpdb->get_results( $query );

    if ( ! empty( $results ) ) {


        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Certificate Name</th><th>Student Name/Email</th><th>Course/Quiz Titles</th><th>Date/Time</th></tr></thead>';
        echo '<tbody>';

        foreach ( $results as $row ) {

            $formatted_date_time = date_i18n(get_option('date_format'), strtotime($row->date_time));

            echo '<tr>';
            echo '<td>' . esc_html( $row->certificate_name ) . '</td>';
            echo '<td>' . esc_html( $row->student_name ) . ' / ' . esc_html( $row->user_email ) . '</td>';
            echo '<td>' . esc_html( $row->course_quiz_title ) . '</td>';
            echo '<td>' . esc_html( $formatted_date_time ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p>No certificates found.</p>';
    }
}

// Hook to add export functionality
add_action( 'admin_init', 'ld_certificate_manager_export' );

function ld_certificate_manager_export() {
    if ( isset( $_POST['export_csv'] ) ) {
        global $wpdb;

        // Retrieve search query
        $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        // Query to get certificates
        $query = get_query($search_query);

        $results = $wpdb->get_results( $query );

        if ( ! empty( $results ) ) {
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=certificates.csv' );

            $output = fopen( 'php://output', 'w' );
            fputcsv( $output, array( 'Certificate Name', 'Student Name', 'Student Email', 'Course/Quiz Titles', 'Date/Time' ) );

            foreach ( $results as $row ) {
                fputcsv( $output, array( $row->certificate_name, $row->student_name, $row->user_email, $row->course_quiz_title, $row->date_time ) );
            }

            fclose( $output );
            exit;
        }
    }
}
?>
