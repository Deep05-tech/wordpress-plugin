<?php

defined('ABSPATH') || exit;


class VCPG_CSV_Importer
{


    private $job_manager;

    /*
    ISO country codes we know how to render a full country name for.
    Add to this as you add markets. Anything not listed falls back to
    strtoupper(code), so a page is never blocked on this list, but it's
    kept accurate for anything you actually use.
    */
    private $country_names = array(
    
        'in' => 'India',
    
        'us' => 'United States',
    
        'uk' => 'United Kingdom',
    
        'ca' => 'Canada',
    
        'au' => 'Australia',
    
    );
    
    
    
    /*
    Country name to ISO code mapping
    */
    
    private $country_codes = array(
    
        'india' => 'in',
    
        'united states' => 'us',
    
        'usa' => 'us',
    
        'united kingdom' => 'uk',
    
        'uk' => 'uk',
    
        'canada' => 'ca',
    
        'australia' => 'au',
    
    );

    public function __construct($job_manager)
    {

        $this->job_manager = $job_manager;


        add_action(
            'admin_menu',
            array(
                $this,
                'add_menu'
            ),
            99
        );

    }






    public function add_menu()
    {

        add_submenu_page(

            'vispan-city-generator',

            'CSV Bulk Import',

            'CSV Bulk Import',

            'manage_options',

            'vcpg-csv-import',

            array(
                $this,
                'render_page'
            )

        );

    }








    public function render_page()
    {


        if(isset($_POST['upload_csv']) && check_admin_referer('vcpg_upload_csv'))
        {

            $this->upload_csv();

        }


        /*
        CODE FIX: detect an incomplete batch left over from a previous
        page load (e.g. the browser tab was navigated away from or
        closed mid-run). If pending/processing jobs exist, we auto-
        resume polling below instead of requiring a fresh CSV upload.
        */

        global $wpdb;

        $jobs_table = $wpdb->prefix . 'vcpg_csv_jobs';

        $current_batch = get_option('vcpg_current_batch');

        $has_incomplete_batch = false;

        if($current_batch)
        {

            $pending_count = $wpdb->get_var(

                $wpdb->prepare(

                    "
                    SELECT COUNT(*)
                    FROM $jobs_table
                    WHERE batch_id=%s
                    AND status IN ('pending','processing')
                    ",

                    $current_batch

                )

            );

            $has_incomplete_batch = ((int) $pending_count) > 0;

        }


?>

<div class="wrap">


<h1>
CSV Bulk Page Generator
</h1>



<form method="post" enctype="multipart/form-data">

<?php wp_nonce_field('vcpg_upload_csv'); ?>


<input 
type="file"
name="csv_file"
accept=".csv"
required
>

<p class="description">
CSV columns, in order: <strong>country, state, city, service</strong> 
(e.g. <code>United States,California,Los Angeles,Digital Marketing Agency</code>).
</p>

<table class="form-table" role="presentation" style="margin-top: 20px;">
    <tbody>
        <tr>
            <th scope="row"><label for="vcpg-concurrency"><strong>Parallel Threads (Speed)</strong></label></th>
            <td>
                <select name="vcpg_concurrency" id="vcpg-concurrency">
                    <option value="1" <?php selected(get_option('vcpg_concurrency', 1), 1); ?>>1 Thread (Safe / Default)</option>
                    <option value="2" <?php selected(get_option('vcpg_concurrency', 1), 2); ?>>2 Threads (Fast)</option>
                    <option value="3" <?php selected(get_option('vcpg_concurrency', 1), 3); ?>>3 Threads (Faster)</option>
                    <option value="4" <?php selected(get_option('vcpg_concurrency', 1), 4); ?>>4 Threads (Turbo)</option>
                </select>
                <p class="description">Select how many pages to generate concurrently. Higher values speed up generation but require more server resources and OpenAI API limits.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="vcpg-max-attempts"><strong>AI Quality Checker Retries</strong></label></th>
            <td>
                <select name="vcpg_max_attempts" id="vcpg-max-attempts">
                    <option value="0" <?php selected(get_option('vcpg_max_attempts', 2), 0); ?>>No Retries (Fastest: ~25s per page, uses auto-sanitizer for SEO)</option>
                    <option value="1" <?php selected(get_option('vcpg_max_attempts', 2), 1); ?>>1 Retry (Balanced: ~25s - 50s per page)</option>
                    <option value="2" <?php selected(get_option('vcpg_max_attempts', 2), 2); ?>>2 Retries (Thorough / Default: ~25s - 80s per page)</option>
                </select>
                <p class="description">Choose how many times the plugin asks OpenAI to rewrite a page if it fails the strict programmatic SEO checks on the first try. Setting to 0/1 decreases page generation time significantly.</p>
            </td>
        </tr>
    </tbody>
</table>

<br>

<?php

submit_button(
    'Start Generation',
    'primary',
    'upload_csv'
);

?>


</form>



<hr>



<h2>
Generation Progress
</h2>



<div id="vcpg-progress">

Waiting...

</div>



<br>


<button 
id="vcpg-stop"
class="button button-secondary"
style="display:none;"
>

Stop Generation

</button>





<script>

jQuery(document).ready(function(){

    let running = false;
    let pollInterval = null;

    function start_polling() {
        if (pollInterval) {
            clearInterval(pollInterval);
        }
        pollInterval = setInterval(fetch_progress, 1000);
    }

    function stop_polling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    function fetch_progress() {
        jQuery.post(
            ajaxurl,
            {
                action: 'vcpg_get_csv_progress'
            },
            function(response) {
                if (!response.success) {
                    return;
                }
                let data = response.data;

                // Build HTML output
                let html = '<strong>Total:</strong> ' + data.total +
                    '<br><strong>Completed:</strong> ' + data.completed +
                    '<br><strong>Processing:</strong> ' + data.processing +
                    '<br><strong>Failed:</strong> ' + data.failed +
                    '<br><br><strong>Current:</strong> ' + (data.current || '') +
                    '<br><strong>Live Status:</strong> <span style="color: #007cba;">' + (data.activity || 'Waiting...') + '</span>';

                // Display failures if any
                if (data.failures && data.failures.length > 0) {
                    html += '<br><br><strong style="color: #d63638;">Recent Failures:</strong><ul style="margin: 5px 0 0 20px; list-style-type: disc; color: #d63638;">';
                    data.failures.forEach(function(fail) {
                        html += '<li><strong>' + fail.city + ' - ' + fail.service + ':</strong> ' + fail.message + '</li>';
                    });
                    html += '</ul>';
                }

                jQuery('#vcpg-progress').html(html);

                // If stopped or complete, hide stop button and stop polling
                if (parseInt(data.completed) + parseInt(data.failed) >= parseInt(data.total) && parseInt(data.total) > 0) {
                    stop_polling();
                    jQuery('#vcpg-stop').hide();
                    if (!jQuery('#vcpg-progress').text().includes('Generation Completed')) {
                        jQuery('#vcpg-progress').append('<br><br><strong style="color: #46b450;">Generation Completed.</strong>');
                    }
                }
            },
            'json'
        );
    }

    function process_queue() {
        if (!running) {
            return;
        }

        jQuery.post(
            ajaxurl,
            {
                action: 'vcpg_process_csv_job'
            },
            function(response) {
                if (!response.success) {
                    // Try again in 3 seconds
                    setTimeout(process_queue, 3000);
                    return;
                }

                let data = response.data;

                if (data.stopped) {
                    running = false;
                    stop_polling();
                    jQuery('#vcpg-progress').html('Process stopped.');
                    jQuery('#vcpg-stop').hide();
                    return;
                }

                if (parseInt(data.completed) + parseInt(data.failed) >= parseInt(data.total)) {
                    running = false;
                    stop_polling();
                    fetch_progress(); // final progress fetch to ensure sync
                    jQuery('#vcpg-stop').hide();
                } else {
                    // Trigger next job immediately
                    setTimeout(process_queue, 500);
                }
            },
            'json'
        );
    }

    let concurrency = <?php echo intval(get_option('vcpg_concurrency', 1)); ?>;

    // Start execution
    running = true;
    jQuery('#vcpg-stop').show();
    start_polling();
    
    for (let i = 0; i < concurrency; i++) {
        setTimeout(process_queue, i * 400);
    }

    // Click handler for Stop Button
    jQuery('#vcpg-stop').on('click', function(e) {
        e.preventDefault();
        jQuery.post(
            ajaxurl,
            {
                action: 'vcpg_stop_csv_job'
            },
            function(response) {
                running = false;
                stop_polling();
                jQuery('#vcpg-progress').html('Process stopped.');
                jQuery('#vcpg-stop').hide();
            }
        );
    });

});

</script>



</div>


<?php


}









private function upload_csv()
{

    global $wpdb;


    $concurrency = isset($_POST['vcpg_concurrency']) ? max(1, min(4, intval($_POST['vcpg_concurrency']))) : 1;
    $max_attempts = isset($_POST['vcpg_max_attempts']) ? max(0, min(2, intval($_POST['vcpg_max_attempts']))) : 2;
    update_option('vcpg_concurrency', $concurrency);
    update_option('vcpg_max_attempts', $max_attempts);

    $table = $wpdb->prefix . 'vcpg_csv_jobs';



    /*
    Clear previous queue
    */

    $wpdb->query(
        "TRUNCATE TABLE $table"
    );


    /*
    Reset stop flag and activity
    */

    delete_option(
        'vcpg_csv_stop'
    );

    delete_option(
        'vcpg_job_activity'
    );




    if(
        !isset($_FILES['csv_file'])
        ||
        empty($_FILES['csv_file']['tmp_name'])
    )
    {

        echo '<div class="notice notice-error">';
        echo '<p>No CSV file selected.</p>';
        echo '</div>';

        return;

    }




    $file = $_FILES['csv_file']['tmp_name'];




    $handle = fopen(
        $file,
        'r'
    );




    if(!$handle)
    {

        echo '<div class="notice notice-error">';
        echo '<p>Unable to read CSV file.</p>';
        echo '</div>';

        return;

    }





    /*
    Detect delimiter
    */


    $first_line = fgets($handle);



    rewind($handle);



    $delimiter = ",";



    if(
        substr_count($first_line,';')
        >
        substr_count($first_line,',')
    )
    {

        $delimiter = ";";

    }






    /*
    Read header
    */


    $header = fgetcsv(
        $handle,
        0,
        $delimiter,
        '"',
        '\\'
    );





    /*
    Remove BOM
    */

    if(isset($header[0]))
    {

        $header[0] = preg_replace(

            '/^\xEF\xBB\xBF/',

            '',

            $header[0]

        );

    }





    $added = 0;

    $skipped = 0;
    
    
    $batch_id = 'batch_' . time();





    while(

        ($row = fgetcsv(

            $handle,
            0,
            $delimiter,
            '"',
            '\\'

        )) !== false

    )
    {



        /*
        Remove empty lines
        */


        if(
            empty($row)
            ||
            (count($row) === 1 && trim($row[0]) === '')
        )
        {

            continue;

        }






        $row = array_map(

            'trim',

            $row

        );







        $country = isset($row[0])

            ? sanitize_text_field($row[0])

            : '';



        $state = isset($row[1])

            ? sanitize_text_field($row[1])

            : '';



        $city = isset($row[2])

            ? sanitize_text_field($row[2])

            : '';



        $service = isset($row[3])

            ? sanitize_text_field($row[3])

            : '';







        if(
            empty($country)
            ||
            empty($city)
            ||
            empty($service)
        )
        {

            $skipped++;

            continue;

        }




        /*
        Generate country code automatically
        */
        
        $country_key = strtolower(
            trim($country)
        );
        
        
        $country_code = isset($this->country_codes[$country_key])
        
            ? $this->country_codes[$country_key]
        
            : sanitize_key($country);
        
        
        
        $result = $this->job_manager->add_job(
                
            $batch_id,
                
            $city,
                
            $service,
                
            $country_code,
                
            $country,
                
            $state
                
        );







        if($result)
        {

            $added++;

        }
        else
        {

            $skipped++;

        }




    }




    fclose($handle);


    /*
    CRITICAL FIX: register this batch so the AJAX job runner
    (VCPG_CSV_Job_Manager::process_job) can find it. Previously this
    was never called, so process_job() always read an empty
    'vcpg_current_batch' option and exited before processing a
    single row. This is the actual reason no pages were generated.
    */

    if($added > 0)
    {

        update_option(
            'vcpg_current_batch',
            $batch_id
        );

    }





    echo '<div class="notice notice-success">';


    echo '<p>';

    echo $added.' CSV jobs added successfully.<br>';

    echo $skipped.' rows skipped.';

    echo '</p>';



    echo '</div>';



}



}