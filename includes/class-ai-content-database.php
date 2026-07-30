<?php

defined('ABSPATH') || exit;


class VCPG_AI_Content_Database
{


    public function __construct()
    {

        add_action(
            'admin_init',
            array(
                $this,
                'create_table'
            )
        );

    }





    public function create_table()
    {

        global $wpdb;


        $table_name = $wpdb->prefix . 'vcpg_ai_content';


        $charset_collate = $wpdb->get_charset_collate();





        $sql = "CREATE TABLE IF NOT EXISTS $table_name (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            service VARCHAR(255) NOT NULL,

            city VARCHAR(255) NOT NULL,

            state VARCHAR(255),

            country VARCHAR(255),

            content LONGTEXT NOT NULL,

            content_hash VARCHAR(255) DEFAULT '',

            used_phrases LONGTEXT,


            quality_score INT DEFAULT 0,

            status VARCHAR(50) DEFAULT 'generated',

            ai_model VARCHAR(100) DEFAULT '',

            prompt_version VARCHAR(50) DEFAULT 'v1',


            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP 
            ON UPDATE CURRENT_TIMESTAMP,


            PRIMARY KEY(id),

            INDEX(content_hash)


        ) $charset_collate;";





        require_once(
            ABSPATH . 'wp-admin/includes/upgrade.php'
        );


        dbDelta($sql);





        /*
        Upgrade existing table
        */


        $this->upgrade_existing_columns(
            $table_name
        );


    }








    private function upgrade_existing_columns($table_name)
    {

        global $wpdb;





        $columns = $wpdb->get_results(

            "SHOW COLUMNS FROM $table_name"

        );



        $existing = array();



        foreach($columns as $column)
        {

            $existing[] = $column->Field;

        }







        if(
            !in_array(
                'quality_score',
                $existing
            )
        )
        {

            $wpdb->query(

                "ALTER TABLE $table_name 
                ADD quality_score INT DEFAULT 0"

            );

        }








        if(
            !in_array(
                'status',
                $existing
            )
        )
        {

            $wpdb->query(

                "ALTER TABLE $table_name 
                ADD status VARCHAR(50) DEFAULT 'generated'"

            );

        }








        if(
            !in_array(
                'ai_model',
                $existing
            )
        )
        {

            $wpdb->query(

                "ALTER TABLE $table_name 
                ADD ai_model VARCHAR(100) DEFAULT ''"

            );

        }








        if(
            !in_array(
                'prompt_version',
                $existing
            )
        )
        {

            $wpdb->query(

                "ALTER TABLE $table_name 
                ADD prompt_version VARCHAR(50) DEFAULT 'v1'"

            );

        }








        if(
            !in_array(
                'updated_at',
                $existing
            )
        )
        {

            $wpdb->query(

                "ALTER TABLE $table_name 
                ADD updated_at DATETIME DEFAULT CURRENT_TIMESTAMP 
                ON UPDATE CURRENT_TIMESTAMP"

            );

        }

        /*
        Add uniqueness columns
        */


        if(
            !in_array(
                'content_hash',
                $existing
            )
        )
        {

            $wpdb->query(

                "ALTER TABLE $table_name
                ADD content_hash VARCHAR(255) DEFAULT ''"

            );

        }



        if(
            !in_array(
                'used_phrases',
                $existing
            )
        )
        {

            $wpdb->query(

                "ALTER TABLE $table_name
                ADD used_phrases LONGTEXT"

            );

        }

        /*
        Add content hash index
        */

        $indexes = $wpdb->get_results(

            "SHOW INDEX FROM $table_name"

        );


        $hash_index_exists = false;


        foreach($indexes as $index)
        {

            if($index->Key_name === 'content_hash')
            {
        
                $hash_index_exists = true;

                break;

            }

        }



        if(!$hash_index_exists)
        {

            $wpdb->query(

                "ALTER TABLE $table_name
                ADD INDEX(content_hash)"

            );

        }

    }








    public function get_content($data)
    {

        global $wpdb;


        $table_name = $wpdb->prefix . 'vcpg_ai_content';




        return $wpdb->get_row(

            $wpdb->prepare(

                "
                SELECT *
                FROM $table_name
                WHERE service=%s
                AND city=%s
                AND country=%s
                LIMIT 1
                ",

                $data['service'],

                $data['city'],

                $data['country']

            )

        );


    }








    public function save_content($data,$content,$quality_score=0,$status='generated')
    {

        global $wpdb;


        $table_name = $wpdb->prefix . 'vcpg_ai_content';





        $result = $wpdb->insert(

            $table_name,

            array(

                'service'=>$data['service'],

                'city'=>$data['city'],

                'state'=>isset($data['state'])
                    ? $data['state']
                    : '',


                'country'=>$data['country'],


                'content'=>json_encode($content),


                'content_hash'=>md5(
                    json_encode($content)
                ),


                'used_phrases'=>$this->extract_phrases($content),


                'quality_score'=>$quality_score,


                'status'=>$status,


                'ai_model'=>'gpt-4.1-mini',


                'prompt_version'=>'v2'


            )

        );







        if(!$result)
        {

            error_log(
                "AI DATABASE ERROR: ".$wpdb->last_error
            );

        }
        else
        {

            error_log(
                "AI CONTENT SAVED SUCCESSFULLY"
            );

        }





        return $result;


    }

    public function content_hash_exists($hash)
    {

        global $wpdb;


        $table_name = $wpdb->prefix . 'vcpg_ai_content';



        $count = $wpdb->get_var(

            $wpdb->prepare(

                "
                SELECT COUNT(*)
                FROM $table_name
                WHERE content_hash=%s
                ",

                $hash

            )

        );


        return ((int)$count > 0);


    }

    public function get_recent_content_patterns($limit = 40)
    {

        global $wpdb;


        $table_name = $wpdb->prefix . 'vcpg_ai_content';


        return $wpdb->get_results(

            $wpdb->prepare(

                "
                SELECT 

                city,

                state,

                service,

                used_phrases,

                content_hash

                FROM $table_name

                ORDER BY id DESC

                LIMIT %d
                ",

                $limit

            )

        );


    }

    private function extract_phrases($content)
    {
    
    
        $text = json_encode($content);
    
    
        $text = wp_strip_all_tags($text);
    
    
        $text = strtolower($text);
    
    
    
        /*
        Remove unnecessary characters
        */
    
        $text = preg_replace(
            '/[^a-z0-9\s]/',
            '',
            $text
        );
    
    
    
        $words = preg_split(
            '/\s+/',
            $text
        );
    
    
    
        $words = array_filter(
    
            $words,
    
            function($word)
            {
    
                return strlen($word) >= 4;
    
            }
    
        );
    
    
    
        $words = array_values($words);
    
    
    
        $phrases = array();
    
    
    
        /*
        Create 2-word phrases (section starters)
        */
    
        for(
            $i = 0;
            $i < count($words)-1;
            $i++
        )
        {
    
            $phrase =
    
                $words[$i]
                .' '.
                $words[$i+1];
    
    
            $phrases[] = $phrase;
    
    
        }
    
    
    
        /*
        Create 3-word phrases
        */
    
        for(
            $i = 0;
            $i < count($words)-2;
            $i++
        )
        {
    
            $phrase =
    
                $words[$i]
                .' '.
                $words[$i+1]
                .' '.
                $words[$i+2];
    
    
            $phrases[] = $phrase;
    
    
        }
    
    
    
        /*
        Create 4-word phrases
        */
    
        for(
            $i = 0;
            $i < count($words)-3;
            $i++
        )
        {
    
            $phrase =
    
                $words[$i]
                .' '.
                $words[$i+1]
                .' '.
                $words[$i+2]
                .' '.
                $words[$i+3];
    
    
            $phrases[] = $phrase;
    
        }
    
    
    
    
        return json_encode(
    
            array_slice(
    
                array_unique($phrases),
    
                0,
    
                150
    
            )
    
        );
    
    
    }

    public function compare_content_similarity($new_content)
    {
    
        global $wpdb;
    
    
        $table_name = $wpdb->prefix . 'vcpg_ai_content';
    
    
    
        $existing = $wpdb->get_results(
    
            "
            SELECT content
            FROM $table_name
            ORDER BY id DESC
            LIMIT 20
            "
    
        );
    
    
    
        $new_text = strtolower(
    
            json_encode($new_content)
    
        );
    
    
    
        $highest = 0;
    
    
    
        foreach($existing as $item)
        {
    
    
            $old_text = strtolower(
    
                $item->content
    
            );
    
    
    
            similar_text(
    
                $new_text,
    
                $old_text,
    
                $percent
    
            );
    
    
    
            if($percent > $highest)
            {
        
                $highest = $percent;
    
            }
    
    
        }
    
    
    
        return round($highest,2);
    

}


}