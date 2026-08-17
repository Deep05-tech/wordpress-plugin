<?php

defined('ABSPATH') || exit;

/**
 * VCPG_Inquiry_Handler
 *
 * Handles inquiry form submissions from generated pages.
 * - Stores all inquiries in custom DB table `wp_vcpg_inquiries`
 * - Sends email notifications to configured recipients
 * - Provides an admin dashboard view for managing leads
 */
class VCPG_Inquiry_Handler
{

    /**
     * Email addresses that receive inquiry notifications.
     */
    private $notification_emails = array(
        'ga@vispansolutions.com',
        'contact@vispansolutions.com',
        'dip.vispan@gmail.com',
    );


    public function __construct()
    {
        // Ensure table exists
        $this->create_table();

        // Register AJAX handlers for both logged-in and public visitors
        add_action('wp_ajax_vcpg_submit_inquiry', array($this, 'handle_submission'));
        add_action('wp_ajax_nopriv_vcpg_submit_inquiry', array($this, 'handle_submission'));

        // Register Admin Submenu Page for viewing Inquiries
        add_action('admin_menu', array($this, 'register_admin_menu'), 100);
    }


    /**
     * Create the inquiries database table if it doesn't exist.
     */
    public function create_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vcpg_inquiries';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                fullname varchar(255) NOT NULL,
                email varchar(255) NOT NULL,
                country_code varchar(20) DEFAULT '',
                phone varchar(50) NOT NULL,
                service varchar(255) NOT NULL,
                company varchar(255) NOT NULL,
                website varchar(255) DEFAULT '',
                budget varchar(100) DEFAULT '',
                details text,
                page_url text,
                ip_address varchar(100) DEFAULT '',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }
    }


    /**
     * Register Admin Submenu Page under City Page Generator
     */
    public function register_admin_menu()
    {
        add_submenu_page(
            'vispan-city-generator',
            'Lead Inquiries',
            'Lead Inquiries',
            'manage_options',
            'vcpg-inquiries',
            array($this, 'render_admin_page')
        );
    }


    /**
     * Handle the AJAX form submission.
     * Stores inquiry in DB + sends email notifications.
     */
    public function handle_submission()
    {
        // Rate limiting: 1 submission per IP per 30 seconds
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'unknown';
        $rate_key = 'vcpg_inquiry_' . md5($ip);
        if (get_transient($rate_key)) {
            wp_send_json_error(array('message' => 'Please wait a few seconds before submitting another request.'));
        }

        // Validate required fields
        $fullname     = isset($_POST['fullname']) ? sanitize_text_field($_POST['fullname']) : '';
        $email        = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $country_code = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : '';
        $phone        = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $service      = isset($_POST['service']) ? sanitize_text_field($_POST['service']) : '';
        $company      = isset($_POST['company']) ? sanitize_text_field($_POST['company']) : '';
        $website      = isset($_POST['website']) ? esc_url_raw($_POST['website']) : '';
        $budget       = isset($_POST['budget']) ? sanitize_text_field($_POST['budget']) : '';
        $details      = isset($_POST['details']) ? sanitize_textarea_field($_POST['details']) : '';
        $page_url     = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';

        if (empty($fullname) || empty($email) || empty($phone) || empty($service) || empty($company)) {
            wp_send_json_error(array('message' => 'Please fill in all required fields.'));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Please provide a valid email address.'));
        }

        // Set rate limit transient (30s)
        set_transient($rate_key, true, 30);

        // 1. SAVE TO DATABASE
        global $wpdb;
        $table_name = $wpdb->prefix . 'vcpg_inquiries';

        $db_inserted = $wpdb->insert(
            $table_name,
            array(
                'fullname'     => $fullname,
                'email'        => $email,
                'country_code' => $country_code,
                'phone'        => $phone,
                'service'      => $service,
                'company'      => $company,
                'website'      => $website,
                'budget'       => $budget,
                'details'      => $details,
                'page_url'     => $page_url,
                'ip_address'   => $ip,
                'created_at'   => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        // 2. SEND EMAIL NOTIFICATIONS
        $budget_labels = array(
            'under1k' => 'Under $1,000',
            '1k-5k'   => '$1,000 - $5,000',
            '5k-10k'  => '$5,000 - $10,000',
            '10k+'    => '$10,000+',
        );
        $budget_display = isset($budget_labels[$budget]) ? $budget_labels[$budget] : $budget;

        $site_name = get_bloginfo('name');
        $subject   = '🔔 New Inquiry from ' . $fullname . ' — ' . $site_name;

        $body  = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;color:#1E293B;line-height:1.6;max-width:600px;margin:0 auto;'>";
        $body .= "<div style='background:#02426A;padding:24px 30px;border-radius:12px 12px 0 0;'>";
        $body .= "<h2 style='margin:0;color:#FFFFFF;font-size:20px;'>📩 New Marketing Proposal Request</h2>";
        $body .= "</div>";
        $body .= "<div style='background:#FFFFFF;padding:28px 30px;border:1px solid #E2E8F0;border-top:none;border-radius:0 0 12px 12px;'>";
        $body .= "<table style='width:100%;border-collapse:collapse;font-size:14px;'>";
        $body .= $this->email_row('Full Name', $fullname);
        $body .= $this->email_row('Email', '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>');
        $body .= $this->email_row('Phone', esc_html(($country_code ? $country_code . ' ' : '') . $phone));
        $body .= $this->email_row('Service', esc_html($service));
        $body .= $this->email_row('Company', esc_html($company));
        if (!empty($website)) {
            $body .= $this->email_row('Website', '<a href="' . esc_url($website) . '">' . esc_html($website) . '</a>');
        }
        if (!empty($budget_display)) {
            $body .= $this->email_row('Budget (Last 30 Days)', esc_html($budget_display));
        }
        if (!empty($details)) {
            $body .= $this->email_row('Additional Details', nl2br(esc_html($details)));
        }
        if (!empty($page_url)) {
            $body .= $this->email_row('Submitted From', '<a href="' . esc_url($page_url) . '">' . esc_html($page_url) . '</a>');
        }
        $body .= $this->email_row('Submitted At', current_time('F j, Y \a\t g:i A'));
        $body .= $this->email_row('IP Address', esc_html($ip));
        $body .= "</table>";
        $body .= "</div>";
        $body .= "</body></html>";

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Vispan Solutions <noreply@' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'vispansolutions.com') . '>',
            'Reply-To: ' . $fullname . ' <' . $email . '>',
        );

        $mail_sent = false;
        foreach ($this->notification_emails as $to) {
            if (wp_mail($to, $subject, $body, $headers)) {
                $mail_sent = true;
            }
        }

        error_log("VCPG INQUIRY: Recorded lead #{$wpdb->insert_id} from {$fullname} ({$email}). Mail sent: " . ($mail_sent ? 'YES' : 'NO'));

        wp_send_json_success(array('message' => 'Thank you! Your proposal request has been submitted successfully. We will get back to you shortly.'));
    }


    /**
     * Render the Admin "Lead Inquiries" Page inside WP Dashboard.
     */
    public function render_admin_page()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vcpg_inquiries';

        // Delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['inquiry_id'])) {
            $id = intval($_GET['inquiry_id']);
            check_admin_referer('delete_inquiry_' . $id);
            $wpdb->delete($table_name, array('id' => $id));
            echo '<div class="notice notice-success"><p>Inquiry deleted successfully.</p></div>';
        }

        // Fetch all inquiries
        $inquiries = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Lead Inquiries</h1>
            <p class="description">All form submissions captured from generated landing pages are stored here in real-time and emailed to your configured email addresses.</p>

            <table class="widefat striped" style="margin-top:15px;">
                <thead>
                    <tr>
                        <th style="width:40px;">ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Service</th>
                        <th>Company</th>
                        <th>Website</th>
                        <th>Budget</th>
                        <th>Submitted At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($inquiries)) : ?>
                        <?php foreach ($inquiries as $inq) : ?>
                            <tr>
                                <td><strong>#<?php echo esc_html($inq->id); ?></strong></td>
                                <td><strong><?php echo esc_html($inq->fullname); ?></strong></td>
                                <td><a href="mailto:<?php echo esc_attr($inq->email); ?>"><?php echo esc_html($inq->email); ?></a></td>
                                <td><?php echo esc_html(($inq->country_code ? $inq->country_code . ' ' : '') . $inq->phone); ?></td>
                                <td><span class="badge" style="background:#E2E8F0;padding:2px 8px;border-radius:4px;font-size:12px;"><?php echo esc_html($inq->service); ?></span></td>
                                <td><?php echo esc_html($inq->company); ?></td>
                                <td>
                                    <?php if ($inq->website) : ?>
                                        <a href="<?php echo esc_url($inq->website); ?>" target="_blank" rel="noopener">Visit Site ↗</a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($inq->budget ?: '—'); ?></td>
                                <td><?php echo esc_html($inq->created_at); ?></td>
                                <td>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vcpg-inquiries&action=delete&inquiry_id=' . $inq->id), 'delete_inquiry_' . $inq->id); ?>" onclick="return confirm('Delete this inquiry?');" style="color:#d63638;">Delete</a>
                                </td>
                            </tr>
                            <?php if (!empty($inq->details)) : ?>
                                <tr style="background:#F8FAFC;">
                                    <td colspan="2"></td>
                                    <td colspan="8" style="padding-bottom:12px;">
                                        <strong>Details:</strong> <em>"<?php echo esc_html($inq->details); ?>"</em>
                                        <?php if ($inq->page_url) : ?>
                                            <br><small style="color:#64748B;">From page: <a href="<?php echo esc_url($inq->page_url); ?>" target="_blank"><?php echo esc_html($inq->page_url); ?></a></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="10" style="text-align:center;padding:20px;color:#64748B;">No inquiries received yet. Submit a test form on any generated page to test!</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }


    /**
     * Helper: build a styled table row for the email.
     */
    private function email_row($label, $value)
    {
        return "<tr>"
             . "<td style='padding:10px 12px;font-weight:700;color:#475569;border-bottom:1px solid #F1F5F9;width:35%;vertical-align:top;'>" . esc_html($label) . "</td>"
             . "<td style='padding:10px 12px;color:#1E293B;border-bottom:1px solid #F1F5F9;'>" . $value . "</td>"
             . "</tr>";
    }

}
