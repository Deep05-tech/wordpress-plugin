<?php

defined('ABSPATH') || exit;


class VCPG_AI_Content_Database
{

    /*
    Content pipeline version. Bump this whenever the way content is generated
    changes (e.g. new keyword logic, new sections) so stale cached content is
    bypassed and regenerated with the new rules.
    */
    const CONTENT_VERSION = 'v4';

    public function __construct()
    {

        add_action(
            'admin_init',
            array(
                $this,
                'create_table'
            )
        );

        add_action(
            'admin_init',
            array(
                $this,
                'purge_stale_content'
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

            content_source VARCHAR(20) DEFAULT 'api',

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




    /*
    Delete cached content generated with an older pipeline version once per
    version bump, so regenerating a page uses the current generation rules.
    */

    public function purge_stale_content()
    {

        global $wpdb;


        $option = 'vcpg_content_version_purged';

        $purged = get_option($option, '');

        if($purged === self::CONTENT_VERSION)
        {
            return;
        }


        $table_name = $wpdb->prefix . 'vcpg_ai_content';

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE prompt_version <> %s",
                self::CONTENT_VERSION
            )
        );


        update_option($option, self::CONTENT_VERSION);

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
                'content_source',
                $existing
            )
        )
        {

            $wpdb->query(

                "ALTER TABLE $table_name 
                ADD content_source VARCHAR(20) DEFAULT 'api'"

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

        if(
            !in_array(
                'used_title_words',
                $existing
            )
        )
        {

            $wpdb->query(

                "ALTER TABLE $table_name
                ADD used_title_words LONGTEXT"

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
                AND prompt_version=%s
                LIMIT 1
                ",

                $data['service'],

                $data['city'],

                $data['country'],

                self::CONTENT_VERSION

            )

        );


    }








    public function save_content($data,$content,$quality_score=0,$status='generated',$content_source='api')
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

                'used_title_words'=>json_encode($this->extract_title_words($content)),


                'quality_score'=>$quality_score,


                'status'=>$status,


                'ai_model'=>'gpt-4.1-mini',


                'content_source'=>($content_source === 'fallback') ? 'fallback' : 'api',


                'prompt_version'=>self::CONTENT_VERSION


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

    public function get_service_content_count($service)
    {

        global $wpdb;


        $table_name = $wpdb->prefix . 'vcpg_ai_content';


        return (int)$wpdb->get_var(

            $wpdb->prepare(

                "
                SELECT COUNT(*)
                FROM $table_name
                WHERE service=%s
                ",

                $service

            )

        );

    }

    public function get_recent_content_patterns($limit = 200)
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

                used_title_words,

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
        $texts = array();
        if(is_array($content))
        {
            array_walk_recursive($content, function($v) use (&$texts) {
                if(is_string($v))
                {
                    $texts[] = $v;
                }
            });
        }
        $text = implode(' ', $texts);
        $text = wp_strip_all_tags($text);
        $text = strtolower($text);

        $text = preg_replace('/[^a-z0-9\s]/', '', $text);

        $words = preg_split('/\s+/', $text);
        $words = array_filter($words, function($word) {
            return strlen($word) >= 4;
        });
        $words = array_values($words);

        $phrases = array();

        for($i = 0; $i < count($words)-1; $i++)
        {
            $phrases[] = $words[$i] . ' ' . $words[$i+1];
        }

        for($i = 0; $i < count($words)-2; $i++)
        {
            $phrases[] = $words[$i] . ' ' . $words[$i+1] . ' ' . $words[$i+2];
        }

        for($i = 0; $i < count($words)-3; $i++)
        {
            $phrases[] = $words[$i] . ' ' . $words[$i+1] . ' ' . $words[$i+2] . ' ' . $words[$i+3];
        }

        return json_encode(array_slice(array_unique($phrases), 0, 300));
    }

    public function extract_title_words($content)
    {
        if(!is_array($content))
        {
            return array();
        }
        $title_fields = array('hero_title', 'hero_subtitle', 'about_title', 'cta_title', 'difference_content');
        $words = array();
        foreach($title_fields as $field)
        {
            if(isset($content[$field]) && is_string($content[$field]))
            {
                $text = strtolower(wp_strip_all_tags($content[$field]));
                $text = preg_replace('/[^a-z0-9\s]/', '', $text);
                $parts = preg_split('/\s+/', $text);
                foreach($parts as $w)
                {
                    $w = trim($w);
                    if(strlen($w) >= 4)
                    {
                        $words[] = $w;
                    }
                }
            }
        }
        return array_unique($words);
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