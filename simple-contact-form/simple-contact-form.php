<?php
/**
 * Plugin Name: Simple Contact Form
 * Description: A basic contact form with email sending, spam filtering, and admin dashboard.
 * Version: 1.0
 * Author: Cryptoball cryptoball7@gmail.com
 */

if (!defined('ABSPATH')) exit;

class SimpleContactForm {
    public function __construct() {
        add_shortcode('simple_contact_form', [$this, 'render_form']);
        add_action('init', [$this, 'handle_form']);
        add_action('admin_menu', [$this, 'register_admin_page']);
    }

    public function render_form() {
        ob_start();
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('scf_submit', 'scf_nonce'); ?>
            <input type="text" name="scf_name" placeholder="Your Name" required><br>
            <input type="email" name="scf_email" placeholder="Your Email" required><br>
            <textarea name="scf_message" placeholder="Your Message" required></textarea><br>

            <!-- Honeypot anti-spam field -->
            <input type="text" name="scf_honeypot" style="display:none" autocomplete="off">

            <button type="submit" name="scf_submit">Send</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public function handle_form() {
        if (!isset($_POST['scf_submit'])) return;

        if (!isset($_POST['scf_nonce']) || !wp_verify_nonce($_POST['scf_nonce'], 'scf_submit')) {
            return;
        }

        if (!empty($_POST['scf_honeypot'])) return; // Spam trap

        $name = sanitize_text_field($_POST['scf_name']);
        $email = sanitize_email($_POST['scf_email']);
        $message = sanitize_textarea_field($_POST['scf_message']);

        if (empty($name) || empty($email) || empty($message)) {
            return;
        }

        $to = get_option('admin_email');
        $subject = "New contact form submission from $name";
        $body = "Name: $name\nEmail: $email\n\nMessage:\n$message";
        $headers = ['Content-Type: text/plain; charset=UTF-8', "Reply-To: $email"];

        wp_mail($to, $subject, $body, $headers);

        // Store submission in the database
        global $wpdb;
        $table = $wpdb->prefix . 'scf_messages';
        $wpdb->insert($table, [
            'name' => $name,
            'email' => $email,
            'message' => $message,
            'submitted_at' => current_time('mysql')
        ]);
    }

    public function register_admin_page() {
        add_menu_page('Contact Submissions', 'Contact Form', 'manage_options', 'scf_submissions', [$this, 'admin_page']);
    }

    public function admin_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'scf_messages';
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY submitted_at DESC");
        ?>
        <div class="wrap">
            <h1>Contact Form Submissions</h1>
            <table class="widefat">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Message</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row->name); ?></td>
                            <td><?php echo esc_html($row->email); ?></td>
                            <td><?php echo esc_html($row->message); ?></td>
                            <td><?php echo esc_html($row->submitted_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

function scf_create_db() {
    global $wpdb;
    $table = $wpdb->prefix . 'scf_messages';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        message text NOT NULL,
        submitted_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'scf_create_db');

new SimpleContactForm();
