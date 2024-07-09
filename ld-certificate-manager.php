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

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class LDCertificateManagerTable extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => __('Certificate', 'ldc'),
            'plural'   => __('Certificates', 'ldc'),
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        $columns = [
            'cb'                => '<input type="checkbox" />',
            'certificate_name'  => __('Certificate Name', 'ldc'),
            'student_name'      => __('Student Name', 'ldc'),
            'user_email'        => __('Student Email', 'ldc'),
            'course_quiz_title' => __('Course/Quiz Titles', 'ldc'),
            'date_time'         => __('Date/Time', 'ldc')
        ];
        return $columns;
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'certificate_name':
            case 'student_name':
            case 'user_email':
            case 'course_quiz_title':
            case 'date_time':
                return $item[$column_name];
            default:
                return print_r($item, true);
        }
    }

    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['certificate_id']
        );
    }

    protected function get_sortable_columns() {
        $sortable_columns = [
            'certificate_name'  => ['certificate_name', true],
            'student_name'      => ['student_name', false],
            'user_email'        => ['user_email', false],
            'course_quiz_title' => ['course_quiz_title', false],
            'date_time'         => ['date_time', false]
        ];
        return $sortable_columns;
    }

    public function prepare_items() {
        global $wpdb;

        $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $per_page = 10;
        $current_page = $this->get_pagenum();

        $query = get_query($search_query);
        $total_items = $wpdb->query($query);

        $query .= " LIMIT " . ($per_page * ($current_page - 1)) . ", " . $per_page;
        $results = $wpdb->get_results($query, ARRAY_A);

        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        usort($results, [$this, 'usort_reorder']);

        $this->items = $results;

        // Set the pagination arguments
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }


    protected function usort_reorder($a, $b) {
        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'certificate_name';
        $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc';
        $result = strcmp($a[$orderby], $b[$orderby]);
        return ($order === 'asc') ? $result : -$result;
    }
}

// Hook to add the menu
add_action( 'admin_menu', 'ld_certificate_manager_menu' );

function ld_certificate_manager_menu() {
    add_submenu_page(
        'learndash-lms',
        'WPC Manage Certificates',
        'WPC Manage Certificates',
        'manage_options',
        'wpc-manage-certificates',
        'ld_certificate_manager_page'
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
        $certificateTable = new LDCertificateManagerTable();
        $certificateTable->prepare_items();
        $certificateTable->display();
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

add_action( 'admin_init', 'ld_certificate_manager_export' );

function ld_certificate_manager_export() {
    if (isset($_POST['export_csv'])) {
        global $wpdb;

        $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $query = get_query($search_query);
        $results = $wpdb->get_results($query, ARRAY_A);

        if (!empty($results)) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=certificates.csv');

            $output = fopen('php://output', 'w');
            fputcsv($output, array('Certificate Name', 'Student Name', 'Student Email', 'Course/Quiz Titles', 'Date/Time'));

            foreach ($results as $row) {
                fputcsv($output, array($row['certificate_name'], $row['student_name'], $row['user_email'], $row['course_quiz_title'], $row['date_time']));
            }

            fclose($output);
            exit;
        }
    }
}
?>
