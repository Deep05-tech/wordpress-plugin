<?php

defined('ABSPATH') || exit;

/**
 * VCPG_Inquiry_Handler
 *
 * Handles inquiry form submissions from generated pages.
 * - Stores all inquiries in custom DB table `wp_vcpg_inquiries`
 * - Sends email notifications to configured recipients
 * - Provides an admin dashboard view for managing leads
 * - Includes optional built-in SMTP settings & diagnostic email test tool
 */
class VCPG_Inquiry_Handler
{

    /**
     * Default notification email addresses.
     */
    private $default_emails = array(
        'ga@vispansolutions.com',
        'contact@vispansolutions.com',
        'dip.vispan@gmail.com',
    );


    public function __construct()
    {
        // Ensure table exists
        $this->create_table();

        // Register AJAX handlers for form submissions
        add_action('wp_ajax_vcpg_submit_inquiry', array($this, 'handle_submission'));
        add_action('wp_ajax_nopriv_vcpg_submit_inquiry', array($this, 'handle_submission'));

        // Register Admin Submenu Page for viewing Inquiries & SMTP settings
        add_action('admin_menu', array($this, 'register_admin_menu'), 100);

        // Configure PHPMailer if SMTP is enabled
        add_action('phpmailer_init', array($this, 'configure_phpmailer'));

        // Inject global script on frontend so all existing pages automatically capture leads
        add_action('wp_footer', array($this, 'inject_global_inquiry_script'));
    }


    /**
     * Inject global fallback JS script into footer of all frontend pages.
     * Ensures existing generated pages automatically capture leads without regenerating.
     */
    public function inject_global_inquiry_script()
    {
        if (is_admin() || !function_exists('is_vcpg_generated_page') || !is_vcpg_generated_page()) {
            return; // ZERO JS execution or interference on built-in website pages!
        }
        $ajax_url = admin_url('admin-ajax.php');
        ?>
        <script id="vcpg-global-inquiry-script">
        (function() {
            var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
            document.addEventListener("submit", function(e) {
                var form = e.target;
                if (!form || form.tagName !== "FORM") return;

                // STRICT ISOLATION: Only target forms explicitly belonging to VCPG generated pages
                var formId = form.id || "";
                var formClass = form.className || "";
                var formAction = form.getAttribute("action") || "";
                var isVcpgForm = formId.indexOf("proposal") !== -1 || 
                                 formId.indexOf("vcpg") !== -1 || 
                                 formClass.indexOf("vcpg") !== -1 || 
                                 formAction.indexOf("vcpg_submit_inquiry") !== -1 ||
                                 form.querySelector("input[name='action'][value='vcpg_submit_inquiry']");

                if (!isVcpgForm) return; // Leave all theme & built-in native forms 100% untouched!

                e.preventDefault();
                e.stopPropagation();

                var emailInput = form.querySelector("input[name='email'], input[type='email']");
                var nameInput  = form.querySelector("input[name='fullname'], input[name='name'], input[name='your-name']");

                var name = nameInput ? nameInput.value.trim() : "";
                var email = emailInput ? emailInput.value.trim() : "";
                
                var phoneInput = form.querySelector("input[name='phone'], input[type='tel']");
                var phone = phoneInput ? phoneInput.value.trim() : "";

                var serviceInput = form.querySelector("input[name='service'], select[name='service']");
                var service = serviceInput ? serviceInput.value.trim() : "General Inquiry";

                var companyInput = form.querySelector("input[name='company']");
                var company = companyInput ? companyInput.value.trim() : "N/A";

                var websiteInput = form.querySelector("input[name='website']");
                var website = websiteInput ? websiteInput.value.trim() : "";

                var budgetInput = form.querySelector("input[name='budget'], select[name='budget']");
                var budget = budgetInput ? budgetInput.value.trim() : "N/A";

                var detailsInput = form.querySelector("textarea[name='details'], textarea[name='message'], input[name='details']");
                var details = detailsInput ? detailsInput.value.trim() : "";

                // Anti-Spam Honeypot Field Check
                var hpInput = form.querySelector("input[name='vcpg_hp_trap']");
                var hpVal = hpInput ? hpInput.value : "";

                var errorDiv   = form.querySelector(".vcpg-error-msg, [id^='error_']") || document.getElementById("error_" + form.id);
                var successDiv = form.querySelector(".vcpg-success-msg, [id^='success_']") || document.getElementById("success_" + form.id);

                if (errorDiv) errorDiv.style.display = "none";
                if (successDiv) successDiv.style.display = "none";

                if (!name || !email) {
                    if (errorDiv) {
                        errorDiv.textContent = "Please enter your name and a valid email address.";
                        errorDiv.style.display = "block";
                    } else {
                        alert("Please enter your name and a valid email address.");
                    }
                    return false;
                }

                var submitBtn = form.querySelector("button[type='submit'], input[type='submit']");
                var origText  = submitBtn ? (submitBtn.textContent || submitBtn.value) : "Submit";
                if (submitBtn) {
                    submitBtn.disabled = true;
                    if (submitBtn.tagName === "INPUT") submitBtn.value = "Submitting...";
                    else submitBtn.textContent = "Submitting...";
                }

                var formData = new FormData();
                formData.append("action", "vcpg_submit_inquiry");
                formData.append("fullname", name);
                formData.append("email", email);
                formData.append("phone", phone);
                formData.append("service", service);
                formData.append("company", company);
                formData.append("website", website);
                formData.append("budget", budget);
                formData.append("details", details);
                formData.append("vcpg_hp_trap", hpVal);
                formData.append("page_url", window.location.href);

                fetch(ajaxUrl, {
                    method: "POST",
                    body: formData
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        if (submitBtn.tagName === "INPUT") submitBtn.value = origText;
                        else submitBtn.textContent = origText;
                    }
                    if (res.success) {
                        var msg = (res.data && res.data.message) ? res.data.message : "Thank you! Your proposal request has been submitted successfully.";
                        if (successDiv) {
                            successDiv.textContent = msg;
                            successDiv.style.display = "block";
                        } else {
                            alert(msg);
                        }
                        form.reset();
                    } else {
                        var errMsg = (res.data && res.data.message) ? res.data.message : "Submission failed. Please try again.";
                        if (errorDiv) {
                            errorDiv.textContent = errMsg;
                            errorDiv.style.display = "block";
                        } else {
                            alert(errMsg);
                        }
                    }
                })
                .catch(function() {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        if (submitBtn.tagName === "INPUT") submitBtn.value = origText;
                        else submitBtn.textContent = origText;
                    }
                    if (errorDiv) {
                        errorDiv.textContent = "An error occurred while submitting. Please try again.";
                        errorDiv.style.display = "block";
                    } else {
                        alert("An error occurred while submitting. Please try again.");
                    }
                });

                return false;
            }, true);
        })();
        </script>
        <?php
    }


    /**
     * Get configured notification emails.
     */
    public function get_notification_emails()
    {
        $custom = get_option('vcpg_notification_emails', '');
        if (!empty($custom)) {
            $list = array_map('trim', explode(',', $custom));
            return array_filter($list, 'is_email');
        }
        return $this->default_emails;
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
     * Automatically configure PHPMailer to use custom SMTP if enabled in settings.
     */
    public function configure_phpmailer($phpmailer)
    {
        $smtp_user = get_option('vcpg_smtp_user', '');
        $smtp_pass = get_option('vcpg_smtp_pass', '');
        $smtp_enabled = get_option('vcpg_smtp_enabled', '0') === '1' || (!empty($smtp_user) && !empty($smtp_pass));

        if ($smtp_enabled) {
            $phpmailer->isSMTP();
            $phpmailer->Host       = get_option('vcpg_smtp_host', 'smtp.gmail.com');
            $phpmailer->Port       = (int) get_option('vcpg_smtp_port', 587);
            $phpmailer->SMTPAuth   = true;
            $phpmailer->Username   = $smtp_user;
            $phpmailer->Password   = $smtp_pass;
            
            $encryption = get_option('vcpg_smtp_encryption', 'tls');
            if ($encryption !== 'none') {
                $phpmailer->SMTPSecure = $encryption;
            } else {
                $phpmailer->SMTPSecure = '';
            }

            $from_email = get_option('vcpg_smtp_from_email', !empty($smtp_user) ? $smtp_user : 'dip.vispan@gmail.com');
            $from_name  = get_option('vcpg_smtp_from_name', get_bloginfo('name'));

            if (!empty($from_email)) {
                $phpmailer->From     = $from_email;
                $phpmailer->FromName = $from_name;
            }
        }
    }


    /**
     * Handle the AJAX form submission from frontend.
     */
    public function handle_submission()
    {
        // Anti-Spam / Anti-Malware Honeypot Trap Verification
        if (!empty($_POST['vcpg_hp_trap'])) {
            // Automated bot detected filling hidden trap field. Return success to trick bot and abort.
            wp_send_json_success(array('message' => 'Thank you! Your proposal request has been submitted successfully.'));
            exit;
        }

        // Rate limiting: 1 submission per IP per 30 seconds
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'unknown';
        $rate_key = 'vcpg_inquiry_' . md5($ip);
        if (get_transient($rate_key)) {
            wp_send_json_error(array('message' => 'Please wait a few seconds before submitting another request.'));
        }

        // Flexible field extraction for retro-compatibility with all existing generated pages
        $fullname     = !empty($_POST['fullname']) ? sanitize_text_field($_POST['fullname']) : (!empty($_POST['name']) ? sanitize_text_field($_POST['name']) : (!empty($_POST['your-name']) ? sanitize_text_field($_POST['your-name']) : 'Inquirer'));
        $email        = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $country_code = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : '';
        $phone        = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $service      = !empty($_POST['service']) ? sanitize_text_field($_POST['service']) : 'General Inquiry';
        $company      = !empty($_POST['company']) ? sanitize_text_field($_POST['company']) : 'N/A';
        $website      = isset($_POST['website']) ? esc_url_raw($_POST['website']) : '';
        $budget       = !empty($_POST['budget']) ? sanitize_text_field($_POST['budget']) : 'N/A';
        $details      = isset($_POST['details']) ? sanitize_textarea_field($_POST['details']) : (isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '');
        $page_url     = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';

        if (empty($fullname) || empty($email)) {
            wp_send_json_error(array('message' => 'Please enter your name and email address.'));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Please provide a valid email address.'));
        }

        // Set rate limit transient (30s)
        set_transient($rate_key, true, 30);

        // 1. SAVE TO DATABASE
        global $wpdb;
        $table_name = $wpdb->prefix . 'vcpg_inquiries';

        $wpdb->insert(
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
        $this->send_notification_email($fullname, $email, $country_code, $phone, $service, $company, $website, $budget, $details, $page_url, $ip);

        wp_send_json_success(array('message' => 'Thank you! Your proposal request has been submitted successfully. We will get back to you shortly.'));
    }


    /**
     * Send email notifications to all configured recipients.
     */
    public function send_notification_email($fullname, $email, $country_code, $phone, $service, $company, $website, $budget, $details, $page_url, $ip)
    {
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

        $from_email = get_option('vcpg_smtp_from_email', 'noreply@vispansolutions.com');
        $from_name  = get_option('vcpg_smtp_from_name', 'Vispan Solutions');

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $fullname . ' <' . $email . '>',
        );

        $recipients = $this->get_notification_emails();
        $mail_sent  = false;
        $errors     = array();

        foreach ($recipients as $to) {
            $result = wp_mail($to, $subject, $body, $headers);
            if ($result) {
                $mail_sent = true;
            } else {
                $errors[] = $to;
            }
        }

        error_log("VCPG INQUIRY: Email dispatch from {$fullname} ({$email}) -> Sent: " . ($mail_sent ? 'YES' : 'NO'));
        return $mail_sent;
    }


    /**
     * Render the Admin "Lead Inquiries" & SMTP Settings Page inside WP Dashboard.
     */
    public function render_admin_page()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vcpg_inquiries';

        $notice = '';
        $error_notice = '';

        // Save SMTP / Notification Settings
        if (isset($_POST['vcpg_save_inquiry_settings']) && check_admin_referer('vcpg_inquiry_settings_nonce')) {
            update_option('vcpg_notification_emails', sanitize_text_field($_POST['vcpg_notification_emails']));
            update_option('vcpg_smtp_enabled', isset($_POST['vcpg_smtp_enabled']) ? '1' : '0');
            update_option('vcpg_smtp_host', sanitize_text_field($_POST['vcpg_smtp_host']));
            update_option('vcpg_smtp_port', (int) $_POST['vcpg_smtp_port']);
            update_option('vcpg_smtp_auth', isset($_POST['vcpg_smtp_auth']) ? '1' : '0');
            update_option('vcpg_smtp_user', sanitize_text_field($_POST['vcpg_smtp_user']));
            update_option('vcpg_smtp_pass', sanitize_text_field($_POST['vcpg_smtp_pass']));
            update_option('vcpg_smtp_encryption', sanitize_text_field($_POST['vcpg_smtp_encryption']));
            update_option('vcpg_smtp_from_email', sanitize_email($_POST['vcpg_smtp_from_email']));
            update_option('vcpg_smtp_from_name', sanitize_text_field($_POST['vcpg_smtp_from_name']));

            $notice = 'Settings saved successfully!';
        }

        // Send Test Email Action
        if (isset($_POST['vcpg_send_test_email']) && check_admin_referer('vcpg_test_email_nonce')) {
            $test_to = sanitize_email($_POST['test_email_target']);
            if (is_email($test_to)) {
                $test_subject = '🧪 Test Email — Vispan City Page Generator';
                $test_body    = '<p>Hello,</p><p>This is a test notification email from <strong>Vispan City Page Generator</strong> plugin.</p><p>Sent at: ' . current_time('F j, Y g:i A') . '</p>';
                $headers      = array('Content-Type: text/html; charset=UTF-8');

                // Capture PHPMailer errors
                global $ts_phpmailer_error;
                $ts_phpmailer_error = '';
                add_action('wp_mail_failed', function($wp_error) {
                    global $ts_phpmailer_error;
                    $ts_phpmailer_error = $wp_error->get_error_message();
                });

                $sent = wp_mail($test_to, $test_subject, $test_body, $headers);

                if ($sent) {
                    $notice = "Test email successfully sent to: <strong>" . esc_html($test_to) . "</strong>! Please check your inbox / spam folder.";
                } else {
                    global $ts_phpmailer_error;
                    $err_msg = !empty($ts_phpmailer_error) ? $ts_phpmailer_error : 'wp_mail() returned false. In local development environments, PHP mail() requires SMTP credentials to deliver to real inboxes like Gmail.';
                    $error_notice = "Failed to send test email to <strong>" . esc_html($test_to) . "</strong>.<br><strong>Error:</strong> " . esc_html($err_msg);
                }
            } else {
                $error_notice = "Please enter a valid email address for the test.";
            }
        }

        // Delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['inquiry_id'])) {
            $id = intval($_GET['inquiry_id']);
            check_admin_referer('delete_inquiry_' . $id);
            $wpdb->delete($table_name, array('id' => $id));
            $notice = 'Inquiry deleted successfully.';
        }

        // Fetch all inquiries
        $inquiries = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
        $recipients = implode(', ', $this->get_notification_emails());
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Lead Inquiries & Email Dispatch</h1>
            <p class="description">Manage captured leads and configure email notification routing.</p>

            <?php if (!empty($notice)) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo $notice; ?></p></div>
            <?php endif; ?>

            <?php if (!empty($error_notice)) : ?>
                <div class="notice notice-error is-dismissible"><p><?php echo $error_notice; ?></p></div>
            <?php endif; ?>

            <!-- Notification & SMTP Settings Box -->
            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:20px;margin:20px 0;">
                <h2 style="margin-top:0;">📩 Notification & Mail Server Settings</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('vcpg_inquiry_settings_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="vcpg_notification_emails">Notification Recipients</label></th>
                            <td>
                                <input type="text" name="vcpg_notification_emails" id="vcpg_notification_emails" class="large-text" value="<?php echo esc_attr(get_option('vcpg_notification_emails', 'ga@vispansolutions.com, contact@vispansolutions.com, dip.vispan@gmail.com')); ?>" />
                                <p class="description">Comma-separated email addresses that receive lead alerts when a customer submits an inquiry form.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Enable Custom SMTP?</th>
                            <td>
                                <label><input type="checkbox" name="vcpg_smtp_enabled" value="1" <?php checked(get_option('vcpg_smtp_enabled', '0'), '1'); ?> /> Use Custom SMTP Mailer (Recommended for Local Dev & Reliable Delivery)</label>
                                <p class="description">If standard <code>wp_mail()</code> is blocked or failing to reach Gmail inboxes, enable SMTP below using Gmail or your host SMTP account.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vcpg_smtp_host">SMTP Host</label></th>
                            <td>
                                <input type="text" name="vcpg_smtp_host" id="vcpg_smtp_host" class="regular-text" value="<?php echo esc_attr(get_option('vcpg_smtp_host', 'smtp.gmail.com')); ?>" placeholder="e.g. smtp.gmail.com or mail.vispansolutions.com" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vcpg_smtp_port">SMTP Port</label></th>
                            <td>
                                <input type="number" name="vcpg_smtp_port" id="vcpg_smtp_port" class="small-text" value="<?php echo esc_attr(get_option('vcpg_smtp_port', '587')); ?>" />
                                <span>(587 for TLS, 465 for SSL)</span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vcpg_smtp_encryption">Encryption</label></th>
                            <td>
                                <select name="vcpg_smtp_encryption" id="vcpg_smtp_encryption">
                                    <option value="tls" <?php selected(get_option('vcpg_smtp_encryption', 'tls'), 'tls'); ?>>TLS (Recommended)</option>
                                    <option value="ssl" <?php selected(get_option('vcpg_smtp_encryption'), 'ssl'); ?>>SSL</option>
                                    <option value="none" <?php selected(get_option('vcpg_smtp_encryption'), 'none'); ?>>None</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vcpg_smtp_user">SMTP Username / Email</label></th>
                            <td>
                                <input type="text" name="vcpg_smtp_user" id="vcpg_smtp_user" class="regular-text" value="<?php echo esc_attr(get_option('vcpg_smtp_user', '')); ?>" placeholder="e.g. dip.vispan@gmail.com" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vcpg_smtp_pass">SMTP Password / App Password</label></th>
                            <td>
                                <input type="password" name="vcpg_smtp_pass" id="vcpg_smtp_pass" class="regular-text" value="<?php echo esc_attr(get_option('vcpg_smtp_pass', '')); ?>" placeholder="App Password" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vcpg_smtp_from_email">Sender "From" Email</label></th>
                            <td>
                                <input type="email" name="vcpg_smtp_from_email" id="vcpg_smtp_from_email" class="regular-text" value="<?php echo esc_attr(get_option('vcpg_smtp_from_email', 'noreply@vispansolutions.com')); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vcpg_smtp_from_name">Sender "From" Name</label></th>
                            <td>
                                <input type="text" name="vcpg_smtp_from_name" id="vcpg_smtp_from_name" class="regular-text" value="<?php echo esc_attr(get_option('vcpg_smtp_from_name', 'Vispan Solutions')); ?>" />
                            </td>
                        </tr>
                    </table>
                    <p><input type="submit" name="vcpg_save_inquiry_settings" class="button button-primary" value="Save Settings" /></p>
                </form>

                <hr style="margin:20px 0;border:0;border-top:1px solid #eee;">

                <!-- Test Email Section -->
                <h3>🧪 Test Mail Delivery</h3>
                <form method="post" action="" style="display:flex;gap:10px;align-items:center;">
                    <?php wp_nonce_field('vcpg_test_email_nonce'); ?>
                    <input type="email" name="test_email_target" value="dip.vispan@gmail.com" class="regular-text" placeholder="Enter target email address" required />
                    <input type="submit" name="vcpg_send_test_email" class="button button-secondary" value="Send Test Email Now" />
                </form>
            </div>

            <!-- Inquiries Table -->
            <h2>📋 Captured Leads (<?php echo count($inquiries); ?>)</h2>
            <table class="widefat striped" style="margin-top:10px;">
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
