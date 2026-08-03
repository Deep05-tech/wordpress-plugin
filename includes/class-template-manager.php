<?php

defined('ABSPATH') || exit;


class VCPG_Template_Manager
{


    public function __construct()
    {

        add_action(
            'admin_init',
            array($this,'create_template_table')
        );


    }





    public function create_template_table()
    {


        global $wpdb;


        $table_name = $wpdb->prefix . 'vcpg_templates';



        $charset_collate = $wpdb->get_charset_collate();



        $sql = "CREATE TABLE IF NOT EXISTS $table_name (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            name VARCHAR(255) NOT NULL,

            content LONGTEXT NOT NULL,

            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY(id)

        ) $charset_collate;";



        require_once(
            ABSPATH . 'wp-admin/includes/upgrade.php'
        );


        dbDelta($sql);


        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        $template_file = dirname(dirname(__FILE__)) . '/templates/premium-template.html';
        $file_content = file_exists($template_file) ? file_get_contents($template_file) : '';

        if((int)$count === 0 && $file_content)
        {
            $wpdb->insert(
                $table_name,
                array(
                    'name' => 'Premium Landing Page Template',
                    'content' => $file_content
                ),
                array('%s','%s')
            );
        }
        elseif((int)$count > 0 && $file_content)
        {
            $stored = $wpdb->get_var("SELECT content FROM $table_name ORDER BY id DESC LIMIT 1");
            if(
                $stored &&
                (strpos($stored, 'vp-hero-city-label') === false ||
                 strpos($stored, 'vp-topbar') === false ||
                 strpos($stored, '<style>') === false ||
                 strpos($stored, '{{about_title}}') === false ||
                 strpos($stored, '{{local_insight}}') === false ||
                 strpos($stored, '{{process_steps}}') === false ||
                 strpos($stored, '{{services_title}}') === false ||
                 strpos($stored, '{{case_studies}}') === false ||
                 strpos($stored, '{{case_studies_description}}') === false ||
                 strpos($stored, 'VCPG-TEMPLATE-V4') === false)
            )
            {
                $wpdb->update(
                    $table_name,
                    array('content' => $file_content),
                    array('id' => $wpdb->get_var("SELECT id FROM $table_name ORDER BY id DESC LIMIT 1")),
                    array('%s'),
                    array('%d')
                );
            }
        }


    }



}