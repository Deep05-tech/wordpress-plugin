<?php

defined('ABSPATH') || exit;

/**
 * VCPG_Inquiry_Handler
 *
 * Handles inquiry form submissions from generated pages.
 * Sends email notifications to configured recipients.
 */
class VCPG_Inquiry_Handler
{

    /**
     * Email addresses that receive inquiry notifications.
     */
    private $notification_emails = array(
        'ga@vispansolutions.com',
        'contact@vispansolutions.com',
    );


    public function __construct()
    {
        // Register AJAX handlers for both logged-in and public visitors
        add_action('wp_ajax_vcpg_submit_inquiry', array($this, 'handle_submission'));
        add_action('wp_ajax_nopriv_vcpg_submit_inquiry', array($this, 'handle_submission'));
    }


    /**
     * Handle the AJAX form submission.
     * Validates fields, sends emails, returns JSON response.
     */
    public function handle_submission()
    {
        // Rate limiting: 1 submission per IP per 30 seconds
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'unknown';
        $rate_key = 'vcpg_inquiry_' . md5($ip);
        if (get_transient($rate_key)) {
            wp_send_json_error(array('message' => 'Please wait before submitting another inquiry.'));
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

        // Set rate limit (30 seconds)
        set_transient($rate_key, true, 30);

        // Format budget for display
        $budget_labels = array(
            'under1k' => 'Under $1,000',
            '1k-5k'   => '$1,000 - $5,000',
            '5k-10k'  => '$5,000 - $10,000',
            '10k+'    => '$10,000+',
        );
        $budget_display = isset($budget_labels[$budget]) ? $budget_labels[$budget] : $budget;

        // Build email
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
        $body .= $this->email_row('Phone', esc_html($country_code . ' ' . $phone));
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
            'Reply-To: ' . $fullname . ' <' . $email . '>',
        );

        // Send to all notification emails
        $sent = false;
        foreach ($this->notification_emails as $to) {
            $result = wp_mail($to, $subject, $body, $headers);
            if ($result) {
                $sent = true;
            }
        }

        if ($sent) {
            error_log('VCPG INQUIRY: New inquiry from ' . $fullname . ' (' . $email . ') for service: ' . $service);
            wp_send_json_success(array('message' => 'Thank you! Your proposal request has been submitted successfully. We will get back to you shortly.'));
        } else {
            error_log('VCPG INQUIRY ERROR: wp_mail() failed for inquiry from ' . $fullname . ' (' . $email . ')');
            wp_send_json_error(array('message' => 'There was an issue sending your request. Please try again or contact us directly.'));
        }
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
