<?php

defined('ABSPATH') || exit;


class VCPG_Keyword_Manager
{


    private $table;


    public function __construct()
    {

        global $wpdb;

        $this->table = $wpdb->prefix . 'vcpg_keywords';


        add_action(
            'admin_init',
            array(
                $this,
                'create_table'
            )
        );

        add_action(
            'admin_menu',
            array(
                $this,
                'add_menu'
            ),
            100
        );

        add_action(
            'admin_init',
            array(
                $this,
                'purge_stale_keywords'
            )
        );

    }




    public function create_table()
    {

        global $wpdb;


        $charset_collate = $wpdb->get_charset_collate();


        $sql = "CREATE TABLE IF NOT EXISTS $this->table (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            keyword VARCHAR(255) NOT NULL,

            avg_volume INT DEFAULT 0,

            service VARCHAR(255) NOT NULL DEFAULT '',

            used_count INT NOT NULL DEFAULT 0,

            last_page_id BIGINT(20) DEFAULT NULL,

            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY(id),

            UNIQUE KEY keyword (keyword)

        ) $charset_collate;";


        require_once(
            ABSPATH . 'wp-admin/includes/upgrade.php'
        );


        dbDelta($sql);

    }




    /*
    One-time cleanup: remove keyword rows that were auto-seeded from the bundled
    example CSV (they are untagged and are never matched anymore). Only runs once,
    so keywords the user imports afterwards are kept.
    */

    public function purge_stale_keywords()
    {

        global $wpdb;


        $option = 'vcpg_keywords_purged';

        if(get_option($option, ''))
        {
            return;
        }


        $wpdb->query(
            "DELETE FROM $this->table WHERE service = ''"
        );


        update_option($option, 1);

    }




    /*
    Pick the next batch of keywords for a page.
    Keywords are generated from the page's service and location, so they are
    always relevant to what the page is about. Optionally, keywords that were
    imported per-service are used first (unused before reused).
    */

    public function get_keywords_for_page($service = '', $city = '', $state = '', $limit = 50)
    {

        $limit = max(1, (int)$limit);


        $pool = array_merge(
            $this->get_stored_keywords_for_service($service),
            $this->generate_service_keywords($service, $city, $state)
        );


        $pool = array_values(array_unique($pool));


        return array_slice($pool, 0, $limit);

    }



    /*
    Pull any keywords that were explicitly imported/associated with this exact
    service. Only keywords tagged with a matching service name are used, so an
    imported list for one service can never leak into another service's pages.
    Unused ones come first so imported lists are fully covered before reuse.
    */

    private function get_stored_keywords_for_service($service)
    {

        global $wpdb;


        $service = trim($service);

        if(empty($service))
        {
            return array();
        }


        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT keyword
                 FROM $this->table
                 WHERE service = %s
                 ORDER BY used_count ASC, avg_volume DESC, id ASC
                 LIMIT 200",
                $service
            )
        );


        $keywords = array();

        if($rows)
        {
            foreach($rows as $row)
            {
                $keywords[] = $row->keyword;
            }
        }


        return $keywords;

    }



    /*
    Build a large pool of search-style keywords from the page's service and
    location. Keywords are always derived from the service name so they are
    relevant no matter what service the page is about.
    */

    private function generate_service_keywords($service, $city = '', $state = '')
    {

        $svc = strtolower(trim($service));

        if(empty($svc))
        {
            return array();
        }

        $city_lc = strtolower(trim($city));
        $state_lc = strtolower(trim($state));

        $base = $this->strip_business_words($svc);

        $use_base = !empty($base) && $base !== $svc && str_word_count($base) >= 2;


        $fill = function($template) use ($svc, $base, $city_lc, $state_lc) {
            return trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    str_replace(
                        array('{{svc}}', '{{base}}', '{{city}}', '{{state}}'),
                        array($svc, $base, $city_lc, $state_lc),
                        $template
                    )
                )
            );
        };


        $patterns = array(
            '{{svc}} near me',
            'best {{svc}} near me',
            '{{svc}} near me today',
            'top rated {{svc}} near me',
            'affordable {{svc}} near me',
            'cheap {{svc}} near me',
            '{{svc}} near me now',
            '{{svc}}',
            'best {{svc}}',
            'top {{svc}}',
            '{{svc}} reviews',
            'local {{svc}}',
            'affordable {{svc}}',
            'professional {{svc}}',
            '{{svc}} cost',
            '{{svc}} pricing',
            '{{svc}} services',
            '{{svc}} company',
            '{{svc}} for small business',
            '{{svc}} for startups',
            '{{svc}} for enterprises',
            'trusted {{svc}}',
            'experienced {{svc}}',
            'hire a {{svc}}',
            'find a {{svc}}',
            '{{svc}} expert',
            '{{svc}} specialist',
            '{{svc}} online',
            '{{svc}} packages',
            '{{svc}} quotes',
        );


        if(!empty($city_lc))
        {
            $patterns = array_merge($patterns, array(
                '{{svc}} in {{city}}',
                '{{svc}} {{city}}',
                'best {{svc}} in {{city}}',
                '{{city}} {{svc}}',
                'top {{svc}} in {{city}}',
                '{{svc}} near {{city}}',
                'local {{svc}} in {{city}}',
                'leading {{svc}} in {{city}}',
                '{{svc}} companies in {{city}}',
                'affordable {{svc}} in {{city}}',
                '{{svc}} in {{city}} today',
            ));
        }

        if(!empty($state_lc))
        {
            $patterns = array_merge($patterns, array(
                '{{svc}} in {{state}}',
                'best {{svc}} in {{state}}',
                '{{svc}} {{state}}',
                'top {{svc}} in {{state}}',
                '{{svc}} companies in {{state}}',
                '{{svc}} near {{state}}',
            ));
        }


        if($use_base)
        {
            $patterns = array_merge($patterns, array(
                '{{base}} near me',
                '{{base}} near me today',
                'best {{base}} near me',
                '{{base}}',
                '{{base}} services',
                '{{base}} company',
                'best {{base}}',
                'top {{base}}',
                '{{base}} reviews',
                'local {{base}}',
                'affordable {{base}}',
                '{{base}} cost',
                '{{base}} pricing',
                'seo for {{base}}',
                'social media for {{base}}',
                '{{base}} for small business',
                '{{base}} expert',
                '{{base}} specialist',
                'online {{base}}',
                '{{base}} near me now',
                '{{base}} tips',
                '{{base}} strategies',
                '{{base}} packages',
            ));

            if(!empty($city_lc))
            {
                $patterns = array_merge($patterns, array(
                    '{{base}} in {{city}}',
                    '{{base}} {{city}}',
                    'best {{base}} in {{city}}',
                    '{{city}} {{base}}',
                    'top {{base}} in {{city}}',
                    'local {{base}} in {{city}}',
                    '{{base}} near {{city}}',
                    '{{base}} companies in {{city}}',
                ));
            }

            if(!empty($state_lc))
            {
                $patterns = array_merge($patterns, array(
                    '{{base}} in {{state}}',
                    '{{base}} {{state}}',
                    'best {{base}} in {{state}}',
                ));
            }
        }


        $keywords = array($svc);

        foreach($patterns as $template)
        {
            $kw = $fill($template);

            if(!empty($kw))
            {
                $keywords[] = $kw;
            }
        }


        return array_values(array_unique($keywords));

    }



    /*
    Reduce a service name to its core term by removing leading modifiers and
    trailing business-type words (e.g. "Law Firm Marketing Agency" -> "law
    firm marketing").
    */

    private function strip_business_words($text)
    {

        $text = strtolower(trim($text));

        if(empty($text))
        {
            return '';
        }


        $text = preg_replace(
            '/^(best|top rated|top|affordable|cheap|professional|local|experienced|trusted|leading|online|premier|award winning|award-winning|reliable|expert|number one|#1|licensed|certified)\s+/i',
            '',
            $text
        );


        $text = preg_replace(
            '/\s+(agencies|agency|companies|company|services?|firms?|consultants?|consultancy|studios?|groups?|partners?|solutions?|experts?|specialists?|marketing|providers?|suppliers?|vendors?|brands?|businesses?|enterprises?|offices?|departments?|sections?)\s*$/i',
            '',
            $text
        );


        return trim($text);

    }




    public function mark_keywords_used($keywords, $page_id = null)
    {

        if(empty($keywords) || !is_array($keywords))
        {
            return;
        }


        global $wpdb;


        foreach($keywords as $keyword)
        {

            if(empty($keyword))
            {
                continue;
            }


            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $this->table
                     SET used_count = used_count + 1,
                         last_page_id = %d
                     WHERE keyword = %s",
                    $page_id,
                    $keyword
                )
            );

        }

    }




    public function get_stats()
    {

        global $wpdb;


        return array(
            'total' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $this->table"),
            'used' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $this->table WHERE used_count > 0"),
        );

    }




    /*
    |--------------------------------------------------------------------------
    | Admin — Keywords submenu page
    |--------------------------------------------------------------------------
    */

    public function add_menu()
    {

        add_submenu_page(
            'vispan-city-generator',
            'Keywords',
            'Keywords',
            'manage_options',
            'vcpg-keywords',
            array(
                $this,
                'render_page'
            )
        );

    }




    public function render_page()
    {

        if(isset($_POST['vcpg_import_keywords']) && check_admin_referer('vcpg_import_keywords'))
        {
            $this->import_csv();
        }

        if(isset($_POST['vcpg_reset_keywords']) && check_admin_referer('vcpg_reset_keywords'))
        {
            $this->reset_usage();
        }


        global $wpdb;

        $stats = $this->get_stats();

        $search = isset($_GET['vk']) ? sanitize_text_field(wp_unslash($_GET['vk'])) : '';

        if(!empty($search))
        {
            $list = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT keyword, avg_volume, used_count
                     FROM $this->table
                     WHERE keyword LIKE %s
                     ORDER BY avg_volume DESC, id ASC
                     LIMIT 300",
                    '%' . $wpdb->esc_like($search) . '%'
                )
            );
        }
        else
        {
            $list = $wpdb->get_results(
                "SELECT keyword, avg_volume, used_count
                 FROM $this->table
                 ORDER BY avg_volume DESC, id ASC
                 LIMIT 300"
            );
        }

?>

<div class="wrap">
<h1>Keywords</h1>

<p>Keywords for each page are <strong>generated automatically from that page's service and location</strong> (e.g. for "Digital Marketing Agency" in Los Angeles you get "digital marketing agency in los angeles", "seo for digital marketing", "best digital marketing agency near me", and similar search terms).</p>
<p class="description">You can optionally import a keyword CSV below. Imported keywords are matched against each page's service and used first, so a per-service keyword list will take priority over the auto-generated ones.</p>

<table class="widefat striped" style="max-width:520px; margin-bottom:24px;">
<tbody>
<tr><th>Total Keywords</th><td><?php echo esc_html($stats['total']); ?></td></tr>
<tr><th>Already Covered (used on at least one page)</th><td><?php echo esc_html($stats['used']); ?></td></tr>
<tr><th>Remaining Uncovered</th><td><?php echo esc_html($stats['total'] - $stats['used']); ?></td></tr>
</tbody>
</table>

<h2>Re-Import Keywords From CSV</h2>
<p class="description">Upload a Google Keyword Planner export (or a plain CSV/TSV with a "Keyword" column). This replaces the current keyword list. Imported keywords are only used for pages whose service matches the <strong>Service</strong> field below — leave it blank if you only want auto-generated keywords.</p>
<form method="post" enctype="multipart/form-data">
<?php wp_nonce_field('vcpg_import_keywords'); ?>
<p>
<label for="keyword_service">Service (optional):</label>
<input type="text" id="keyword_service" name="keyword_service" style="min-width:280px;" placeholder="e.g. Digital Marketing Agency">
</p>
<p>
<input type="file" name="keywords_csv" accept=".csv,.tsv,.txt" required>
</p>
<?php submit_button('Import Keywords', 'secondary', 'vcpg_import_keywords'); ?>
</form>

<h2>Reset Coverage Status</h2>
<p class="description">Resets the "used" counter so all keywords become available again for future pages.</p>
<form method="post">
<?php wp_nonce_field('vcpg_reset_keywords'); ?>
<?php submit_button('Reset Keyword Usage', 'secondary', 'vcpg_reset_keywords'); ?>
</form>

<h2>Keyword List</h2>
<form method="get">
<input type="hidden" name="page" value="vcpg-keywords">
<input type="text" name="vk" value="<?php echo esc_attr($search); ?>" placeholder="Search keywords...">
<?php submit_button('Search', 'secondary', '', false); ?>
</form>

<table class="widefat striped">
<thead>
<tr><th>Keyword</th><th>Avg. Monthly Searches</th><th>Used Count</th></tr>
</thead>
<tbody>
<?php if(!empty($list)) : foreach($list as $row) : ?>
<tr>
<td><?php echo esc_html($row->keyword); ?></td>
<td><?php echo esc_html(number_format((int)$row->avg_volume)); ?></td>
<td><?php echo esc_html((int)$row->used_count); ?></td>
</tr>
<?php endforeach; else : ?>
<tr><td colspan="3">No keywords found.</td></tr>
<?php endif; ?>
</tbody>
</table>

</div>

<?php
    }




    private function reset_usage()
    {

        global $wpdb;


        $wpdb->query(
            "UPDATE $this->table SET used_count = 0, last_page_id = NULL"
        );


        echo '<div class="notice notice-success"><p>Keyword usage reset successfully.</p></div>';

    }




    private function import_csv()
    {

        global $wpdb;


        if(!isset($_FILES['keywords_csv']) || empty($_FILES['keywords_csv']['tmp_name']))
        {
            echo '<div class="notice notice-error"><p>No CSV file selected.</p></div>';
            return;
        }


        $file = $_FILES['keywords_csv']['tmp_name'];

        $handle = fopen($file, 'r');

        if(!$handle)
        {
            echo '<div class="notice notice-error"><p>Unable to read the file.</p></div>';
            return;
        }


        /*
        Detect delimiter (tab / comma / semicolon)
        */

        $first_line = fgets($handle);

        rewind($handle);


        $tabs = substr_count($first_line, "\t");
        $commas = substr_count($first_line, ',');
        $semicolons = substr_count($first_line, ';');

        $delimiter = "\t";

        if($semicolons > $tabs && $semicolons > $commas)
        {
            $delimiter = ';';
        }
        elseif($commas > $tabs)
        {
            $delimiter = ',';
        }


        /*
        Find the header row (a row whose first cell is exactly "Keyword"),
        then read every data row after it.
        */

        $header = null;
        $data_rows = array();

        while(($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false)
        {

            if(isset($row[0]))
            {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            }

            $row = array_map('trim', $row);


            if($header === null)
            {

                $first_cell = isset($row[0]) ? $row[0] : '';

                if(strtolower($first_cell) === 'keyword')
                {
                    $header = $row;
                }

                continue;

            }


            $data_rows[] = $row;

        }


        fclose($handle);


        if(!$header)
        {
            echo '<div class="notice notice-error"><p>Could not find a "Keyword" header column in the file.</p></div>';
            return;
        }


        /*
        Locate the volume column index
        */

        $volume_index = false;

        foreach($header as $index => $column)
        {
            if(stripos($column, 'Avg. monthly searches') !== false)
            {
                $volume_index = $index;
                break;
            }
        }


        /*
        Optional service tag — imported keywords are only used for pages whose
        service matches this exactly.
        */

        $service_tag = isset($_POST['keyword_service'])
            ? sanitize_text_field(wp_unslash($_POST['keyword_service']))
            : '';


        /*
        Replace existing keyword list
        */

        $wpdb->query("TRUNCATE TABLE $this->table");


        $added = 0;

        foreach($data_rows as $row)
        {

            $keyword = isset($row[0]) ? trim($row[0]) : '';

            if(empty($keyword))
            {
                continue;
            }

            $volume = 0;

            if($volume_index !== false && isset($row[$volume_index]))
            {
                $volume = (int)preg_replace('/\D/', '', $row[$volume_index]);
            }


            $wpdb->insert(
                $this->table,
                array(
                    'keyword' => $keyword,
                    'avg_volume' => $volume,
                    'service' => $service_tag
                ),
                array('%s', '%d', '%s')
            );

            $added++;

        }


        echo '<div class="notice notice-success"><p>' . esc_html($added) . ' keywords imported successfully' . (!empty($service_tag) ? ' for "' . esc_html($service_tag) . '"' : '') . '.</p></div>';

    }


}
