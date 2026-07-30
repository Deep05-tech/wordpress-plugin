<?php

defined('ABSPATH') || exit;


class VCPG_City_Manager
{


    public function __construct()
    {

        add_action(
            'admin_init',
            array($this,'create_city_table')
        );

    }



    public function create_city_table()
    {

        global $wpdb;


        $table_name = $wpdb->prefix . 'vcpg_cities';


        $charset_collate = $wpdb->get_charset_collate();



        $sql = "CREATE TABLE IF NOT EXISTS $table_name (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            country VARCHAR(255) NOT NULL,

            country_code VARCHAR(50) NOT NULL,

            state VARCHAR(255),

            city VARCHAR(255) NOT NULL,

            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY(id)

        ) $charset_collate;";



        require_once(
            ABSPATH . 'wp-admin/includes/upgrade.php'
        );


        dbDelta($sql);

    }

    public function save_city($data)
        {
        
            global $wpdb;
        
        
            $table_name = $wpdb->prefix . 'vcpg_cities';
        
        
        
            $exists = $wpdb->get_var(
        
                $wpdb->prepare(
        
                    "
                    SELECT id 
                    FROM $table_name
                    WHERE city=%s
                    AND country=%s
                    ",
        
                    $data['city'],
        
                    $data['country']
        
                )
        
            );
        
        
        
            if($exists)
            {
                return;
            }
        
        
        
            $wpdb->insert(
        
                $table_name,
        
                array(
        
                    'country'=>$data['country'],
        
                    'country_code'=>$data['country_code'],
        
                    'state'=>$data['state'],
        
                    'city'=>$data['city']
        
                ),
        
                array(
        
                    '%s',
        
                    '%s',
        
                    '%s',
        
                    '%s'
        
                )
        
            );
        
}


}