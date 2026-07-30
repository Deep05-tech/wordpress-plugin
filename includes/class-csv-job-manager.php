<?php

defined('ABSPATH') || exit;


class VCPG_CSV_Job_Manager
{


    private $page_generator;


    public function __construct($page_generator)
    {

        $this->page_generator = $page_generator;


        add_action(
            'admin_init',
            array(
                $this,
                'create_table'
            )
        );


        add_action(
            'wp_ajax_vcpg_process_csv_job',
            array(
                $this,
                'process_job'
            )
        );


        add_action(
            'wp_ajax_vcpg_stop_csv_job',
            array(
                $this,
                'stop_job'
            )
        );


    }





    public function create_table()
    {

        global $wpdb;


        $table = $wpdb->prefix . 'vcpg_csv_jobs';


        $charset = $wpdb->get_charset_collate();



        $sql = "CREATE TABLE IF NOT EXISTS $table (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            batch_id VARCHAR(100) NOT NULL,

            city VARCHAR(255) NOT NULL,

            service VARCHAR(255) NOT NULL,

            country_code VARCHAR(10) NOT NULL DEFAULT '',

            country VARCHAR(255) NOT NULL DEFAULT '',

            state VARCHAR(255) DEFAULT '',

            status VARCHAR(50) DEFAULT 'pending',

            page_id BIGINT(20) DEFAULT NULL,

            message TEXT,

            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP 
            ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY  (id)

        ) $charset;";



        require_once(
            ABSPATH . 'wp-admin/includes/upgrade.php'
        );


        dbDelta($sql);


        /*
        CODE FIX for issue 2 (stale table missing batch_id,
        country_code, country, state): dbDelta does not reliably
        ALTER an existing table on every environment/formatting
        combination. Rather than depend on it, explicitly check for
        each column this class needs and add whatever is missing.
        This runs on every admin_init, is non-destructive (existing
        rows are kept), and self-heals any install stuck on an old
        schema without a manual DROP TABLE.
        */

        $this->upgrade_existing_columns($table);


    }




    private function upgrade_existing_columns($table)
    {

        global $wpdb;


        $columns = $wpdb->get_results(

            "SHOW COLUMNS FROM $table"

        );


        $existing = array();

        foreach($columns as $column)
        {

            $existing[] = $column->Field;

        }


        $required = array(

            'batch_id'     => "ALTER TABLE $table ADD batch_id VARCHAR(100) NOT NULL DEFAULT ''",

            'country_code' => "ALTER TABLE $table ADD country_code VARCHAR(10) NOT NULL DEFAULT ''",

            'country'      => "ALTER TABLE $table ADD country VARCHAR(255) NOT NULL DEFAULT ''",

            'state'        => "ALTER TABLE $table ADD state VARCHAR(255) DEFAULT ''",

        );


        foreach($required as $column_name => $alter_sql)
        {

            if(!in_array($column_name, $existing))
            {

                $wpdb->query($alter_sql);

            }

        }


    }







    public function clear_previous_jobs()
    {

        global $wpdb;


        $table=$wpdb->prefix.'vcpg_csv_jobs';


        $wpdb->query(
            "TRUNCATE TABLE $table"
        );


    }







    public function add_job($batch_id,$city,$service,$country_code='',$country='',$state='')
    {

        global $wpdb;


        if(
            empty($city)
            ||
            empty($service)
        )
        {

            return false;

        }



        $table=$wpdb->prefix.'vcpg_csv_jobs';



        return $wpdb->insert(

            $table,

            array(

                'batch_id'=>$batch_id,

                'city'=>$city,

                'service'=>$service,

                'country_code'=>$country_code,

                'country'=>$country,

                'state'=>$state,

                'status'=>'pending'

            )

        );


    }






public function process_job()
{

    global $wpdb;


    $table = $wpdb->prefix . 'vcpg_csv_jobs';



    /*
    Check Stop Signal
    */

    if(
        get_option('vcpg_csv_stop', false)
    )
    {

        wp_send_json_success(

            array(

                'stopped'=>true,

                'message'=>'Process stopped.'

            )

        );

    }




    /*
    Get Current Batch
    */

    $batch_id = get_option(
        'vcpg_current_batch'
    );



    if(
        empty($batch_id)
    )
    {

        wp_send_json_success(

            array(

                'completed'=>0,

                'total'=>0,

                'processing'=>0,

                'failed'=>0,

                'current'=>'',

                'message'=>'No active batch found.'

            )

        );

    }





    /*
    Find Next Pending Job
    */

    $job = $wpdb->get_row(

        $wpdb->prepare(

            "
            SELECT *
            FROM $table
            WHERE batch_id=%s
            AND status='pending'
            ORDER BY id ASC
            LIMIT 1
            ",

            $batch_id

        )

    );





    /*
    No Pending Jobs
    */

    if(!$job)
    {


        $total = $this->count_jobs(
            $batch_id
        );


        $completed = $this->count_status(
            $batch_id,
            'completed'
        );


        $processing = $this->count_status(
            $batch_id,
            'processing'
        );


        $failed = $this->count_status(
            $batch_id,
            'failed'
        );



        wp_send_json_success(

            array(

                'completed'=>$completed,

                'total'=>$total,

                'processing'=>$processing,

                'failed'=>$failed,

                'finished'=>true,

                'current'=>'',

                'message'=>'All jobs completed.'

            )

        );

    }






    /*
    Lock Job
    */

    $updated = $wpdb->update(

        $table,

        array(

            'status'=>'processing'

        ),

        array(

            'id'=>$job->id,

            'status'=>'pending'

        )

    );



    /*
    If another request locked it already
    */

    if(!$updated)
    {

        wp_send_json_success(

            array(

                'completed'=>$this->count_status(
                    $batch_id,
                    'completed'
                ),

                'total'=>$this->count_jobs(
                    $batch_id
                ),

                'processing'=>$this->count_status(
                    $batch_id,
                    'processing'
                ),

                'failed'=>$this->count_status(
                    $batch_id,
                    'failed'
                ),

                'current'=>'',

            )

        );

    }







    /*
    Generate Page
    */

    $result = $this->page_generator->create_page(

        array(

            'city'=>$job->city,

            'service'=>$job->service,

            'country'=>$job->country,

            'country_code'=>$job->country_code,

            'state'=>$job->state,

            'service_keyword'=>sanitize_title(
                $job->service
            )

        )

    );








    /*
    Update Job Result
    */

    if(
        isset($result['status'])
        &&
        $result['status']
    )
    {


        $wpdb->update(

            $table,

            array(

                'status'=>'completed',

                'page_id'=>$result['page_id'],

                'message'=>$result['message']

            ),

            array(

                'id'=>$job->id

            )

        );


    }
    else
    {


        $wpdb->update(

            $table,

            array(

                'status'=>'failed',

                'message'=>isset($result['message'])

                    ? $result['message']

                    : wp_json_encode($result)

            ),

            array(

                'id'=>$job->id

            )

        );


    }








    /*
    Return Fresh Database Status
    */

    wp_send_json_success(

        array(

            'completed'=>$this->count_status(

                $batch_id,

                'completed'

            ),

            'total'=>$this->count_jobs(

                $batch_id

            ),

            'processing'=>$this->count_status(

                $batch_id,

                'processing'

            ),

            'failed'=>$this->count_status(

                $batch_id,

                'failed'

            ),

            'current'=>$job->city.' - '.$job->service

        )

    );


}

// public function process_job()
// {
//     global $wpdb;

//     $table = $wpdb->prefix . 'vcpg_csv_jobs';

//     // Get current batch ID
//     $batch_id = get_option('vcpg_current_batch');
//     if (empty($batch_id)) {
//         wp_send_json_success([
//             'completed' => true,
//             'total' => 0,
//             'completed' => 0,
//             'processing' => 0,
//             'failed' => 0,
//             'current' => '',
//             'message' => 'No batch found.'
//         ]);
//     }

//     // Check if stop is requested
//     $stop = get_option('vcpg_csv_stop', false);
//     if ($stop) {
//         wp_send_json_success([
//             'stopped' => true,
//             'message' => 'Process stopped.'
//         ]);
//     }

//     // Pick the next pending job
//     $job = $wpdb->get_row(
//         $wpdb->prepare(
//             "SELECT * FROM $table WHERE batch_id=%s AND status='pending' ORDER BY id ASC LIMIT 1",
//             $batch_id
//         )
//     );

//     // If no pending jobs, return full progress
//     if (!$job) {
//         $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE batch_id=%s", $batch_id));
//         $completed = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE batch_id=%s AND status='completed'", $batch_id));
//         $processing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE batch_id=%s AND status='processing'", $batch_id));
//         $failed = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE batch_id=%s AND status='failed'", $batch_id));

//         wp_send_json_success([
//             'completed' => $completed >= $total,
//             'total' => $total,
//             'completed' => $completed,
//             'processing' => $processing,
//             'failed' => $failed,
//             'current' => '',
//             'message' => 'All jobs completed.'
//         ]);
//     }

//     // Lock job for processing
//     $wpdb->update($table, ['status' => 'processing'], ['id' => $job->id]);

//     // Generate the page
//     $result = $this->page_generator->create_page([
//         'city' => $job->city,
//         'service' => $job->service,
//         'country' => 'United States',
//         'country_code' => 'us',
//         'state' => '',
//         'service_keyword' => sanitize_title($job->service)
//     ]);

//     // Update job result
//     if (isset($result['status']) && $result['status']) {
//         $wpdb->update($table, [
//             'status' => 'completed',
//             'page_id' => $result['page_id'],
//             'message' => $result['message']
//         ], ['id' => $job->id]);
//     } else {
//         $wpdb->update($table, [
//             'status' => 'failed',
//             'message' => isset($result['message']) ? $result['message'] : 'Unknown error'
//         ], ['id' => $job->id]);
//     }

//     // Fetch fresh progress for **live update**
//     $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE batch_id=%s", $batch_id));
//     $completed = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE batch_id=%s AND status='completed'", $batch_id));
//     $processing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE batch_id=%s AND status='processing'", $batch_id));
//     $failed = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE batch_id=%s AND status='failed'", $batch_id));

//     wp_send_json_success([
//         'total' => $total,
//         'completed' => $completed,
//         'processing' => $processing,
//         'failed' => $failed,
//         'current' => $job->city . ' - ' . $job->service
//     ]);
// }









    private function count_jobs($batch_id)
    {

        global $wpdb;


        $table=$wpdb->prefix.'vcpg_csv_jobs';



        return $wpdb->get_var(

            $wpdb->prepare(

                "
                SELECT COUNT(*)
                FROM $table
                WHERE batch_id=%s
                ",

                $batch_id

            )

        );


    }







    private function count_status($batch_id,$status)
    {

        global $wpdb;


        $table=$wpdb->prefix.'vcpg_csv_jobs';



        return $wpdb->get_var(

            $wpdb->prepare(

                "
                SELECT COUNT(*)
                FROM $table
                WHERE batch_id=%s
                AND status=%s
                ",

                $batch_id,
                $status

            )

        );


    }








    public function stop_job()
    {


        update_option(
            'vcpg_csv_stop',
            true
        );


        wp_send_json_success();


    }






    public function reset_stop()
    {


        delete_option(
            'vcpg_csv_stop'
        );


    }


}