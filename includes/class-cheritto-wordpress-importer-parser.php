<?php

/**
 * The parser.
 *
 * @since      1.0.0
 * @package    Cheritto_Wordpress_Importer
 * @subpackage Cheritto_Wordpress_Importer/includes
 * @author     Flavio Iulita <fiulita@gmail.com>
 */
class Cheritto_Wordpress_Importer_Parser
{
    protected $_stack = array();
    protected $_file = "";
    protected $plugin_prefix;
    protected $_parser = null;

    protected $_currentId = "";
    protected $_current = "";
    protected $_mode = "run";

    public $error_code = 0;
    public $error_string = '';

    public $wxr_version = '';

    public $xml_tags = [];
    public $post_types = [];
    public $post_ids = [];
    public $post_parents = [];

    protected $_isAuthor = false;
    protected $_isCategory = false;
    protected $_isTag = false;
    protected $_isTerm = false;
    protected $_isItem = false;
    protected $_isComment = false;
    protected $_isPostmeta = false;
    protected $_isTermmeta = false;
    protected $_isPostterm = false;

    protected $_currentNodeValues = [];
    protected $_currentSubNodeValues = [];

    public $total_authors = 0;
    public $total_tags = 0;
    public $total_terms = 0;
    public $total_categories = 0;
    public $total_comments = 0; 

    public function __construct($file,$plugin_prefix)
    {
        $this->_file = $file;
        $this->plugin_prefix = $plugin_prefix;

        $this->_parser = xml_parser_create("UTF-8");
        xml_set_object($this->_parser, $this);
        xml_set_element_handler($this->_parser, "startTag", "endTag");
        xml_set_character_data_handler($this->_parser, "parseTag");
    }

    public function startTag($parser, $name, $attribs)
    {
        global $wpdb;

        // Keepalive
        $wpdb->query("UPDATE " . $wpdb->prefix . $this->plugin_prefix . "keepalives SET last_seen_at = NOW() WHERE name='job'");

        array_push($this->_stack, $this->_current);

        if (!in_array($name,$this->xml_tags)) $this->xml_tags[] = $name;

        if ($name == "WP:AUTHOR") {
            $this->_isAuthor=true;
            $this->total_authors = $this->total_authors + 1;
        }

        else 

        if ($name == "WP:CATEGORY") {
            $this->_isCategory=true;
            $this->total_categories = $this->total_categories + 1;
        }

        else 

        if ($name == "WP:TAG") {
            $this->_isTag=true;
            $this->total_tags = $this->total_tags + 1;
        }

        else 

        if ($name == "WP:TERM") {
            $this->_isTerm=true;
            $this->total_terms = $this->total_terms + 1;
        }

        else

        if ($name == "ITEM") {
            $this->_isItem=true;
        }

        else

        if ($name == "WP:COMMENT") {
            $this->_isComment=true;
            $this->total_comments = $this->total_comments + 1;
        }

        else

        if ($name == "WP:POSTMETA") {
            $this->_isPostmeta=true;
        }

        else

        if ($name == "WP:TERMMETA") {
            $this->_isTermmeta=true;
        }

        else

        if ($name == "CATEGORY") {
            $this->_isPostterm=true;
            $this->_currentSubNodeValues['DOMAIN'] = $attribs['DOMAIN'];
            $this->_currentSubNodeValues['NICENAME'] = $attribs['NICENAME'];
        }

        $this->_current = $name;
    }

    public function endTag($parser, $name)
    {
        global $wpdb;
        
        if ($name == "WP:AUTHOR") {
            $this->_isAuthor=false;

            $data = [
                'author_id' => $this->_currentNodeValues['WP:AUTHOR_ID'],
                'author_login' => $this->_currentNodeValues['WP:AUTHOR_LOGIN'],
                'author_email' => $this->_currentNodeValues['WP:AUTHOR_EMAIL'],
                'author_display_name' => $this->_currentNodeValues['WP:AUTHOR_DISPLAY_NAME'],
                'author_first_name' => $this->_currentNodeValues['WP:AUTHOR_FIRST_NAME'],
                'author_last_name' => $this->_currentNodeValues['WP:AUTHOR_LAST_NAME']
            ];

            require_once( ABSPATH . 'wp-includes/class-phpass.php' );

            $wp_hasher = new PasswordHash(8, TRUE);

            $pass = wp_generate_password();

            $hashed_pass = $wp_hasher->HashPassword($pass);

            $user_data = [
                'ID' => $data['author_id'],
                'user_login' => $data['author_login'],
                'user_pass' => $hashed_pass,
                'user_nicename' => sanitize_title($data['author_login']),
                'user_email' => $data['author_email'],
                'display_name' => $data['author_display_name']
            ];

            $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "users", $user_data );

            $usermeta_data_first_name = [
                'user_id' => $data['author_id'],
                'meta_key' => 'first_name',
                'meta_value' => $data['author_first_name']
            ];

            $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "usermeta", $usermeta_data_first_name );

            $usermeta_data_last_name = [
                'user_id' => $data['author_id'],
                'meta_key' => 'last_name',
                'meta_value' => $data['author_last_name']
            ];

            $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "usermeta", $usermeta_data_last_name );

            $usermeta_data_capabilities = [
                'user_id' => $data['author_id'],
                'meta_key' => 'wp_capabilities',
                'meta_value' => 'a:1:{s:6:"author";b:1;}'
            ];

            $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "usermeta", $usermeta_data_capabilities );

            $usermeta_data_level = [
                'user_id' => $data['author_id'],
                'meta_key' => 'wp_user_level',
                'meta_value' => '2'
            ];

            $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "usermeta", $usermeta_data_level );
   

            $this->_currentNodeValues = [];

        }

        else

        if ($name == "WP:CATEGORY") {
            $this->_isCategory=false;

            $data = [
                'term_id' => $this->_currentNodeValues['WP:TERM_ID'],
                'name' => $this->_currentNodeValues['WP:CAT_NAME'],
                'slug' => $this->_currentNodeValues['WP:CATEGORY_NICENAME'],
                'term_group' => 0
            ];
            
            $exists = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "terms" . " WHERE term_id = " . (int) $data['term_id']);

            if (!$exists) $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "terms", $data );

            $taxonomy_exists = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy" . " WHERE term_id = " . (int) $data['term_id']);

            $taxonomy_data = [
                'term_id' => $this->_currentNodeValues['WP:TERM_ID'],
                'taxonomy' => 'category',
                'description' => '',
                'parent' => array_key_exists('WP:CATEGORY_PARENT',$this->_currentNodeValues) ? $this->_currentNodeValues['WP:CATEGORY_PARENT'] : 0,
                'count' => 0
            ];

            if (!$taxonomy_exists) $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "term_taxonomy", $taxonomy_data );
           
            $this->_currentNodeValues = [];
        }

        else

        if ($name == "WP:TAG") {
            $this->_isTag=false;

            $data = [
                'term_id' => $this->_currentNodeValues['WP:TERM_ID'],
                'name' => $this->_currentNodeValues['WP:TAG_NAME'],
                'slug' => $this->_currentNodeValues['WP:TAG_SLUG'],
                'term_group' => 0
            ];

            $exists = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "terms" . " WHERE term_id = " . (int) $data['term_id']);
            if (!$exists) $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "terms", $data );

            $taxonomy_exists = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy" . " WHERE term_id = " . (int) $data['term_id'] . " AND taxonomy = 'post_tag'");

            $taxonomy_data = [
                'term_id' => $this->_currentNodeValues['WP:TERM_ID'],
                'taxonomy' => 'post_tag',
                'description' => '',
                'parent' => 0,
                'count' => 0
            ];

            if (!$taxonomy_exists) $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "term_taxonomy", $taxonomy_data );

            $this->_currentNodeValues = [];
        }

        else

        if ($name == "WP:TERM") {
            $this->_isTerm=false;

            $data = [
                'term_id' => $this->_currentNodeValues['WP:TERM_ID'],
                'taxonomy' => $this->_currentNodeValues['WP:TERM_TAXONOMY'],
                'parent' => array_key_exists("WP:TERM_PARENT", $this->_currentNodeValues) ? $this->_currentNodeValues['WP:TERM_PARENT'] : 0
            ];

            $exists = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy" . " WHERE term_id = " . (int) $data['term_id']);
            if (!$exists) $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "term_taxonomy", $data );

            $this->_currentNodeValues = [];
        }

        else

        if ($name == "ITEM") {
            $this->_isItem=false;

            $user_id = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT ID FROM " . $wpdb->prefix . $this->plugin_prefix . "users WHERE user_login = %s",$this->_currentNodeValues['DC:CREATOR'])
            );

            $defaults = array(
                'post_author'           => (int) $user_id ? $user_id : get_current_user_id(),
                'post_content'          => '',
                'post_title'            => '',
                'post_excerpt'          => '',
                'post_status'           => 'draft',
                'post_type'             => 'post',
                'comment_status'        => '',
                'ping_status'           => '',
                'post_password'         => '',
                'to_ping'               => '',
                'pinged'                => '',
                'post_parent'           => 0,
                'menu_order'            => 0,
                'guid'                  => '',
                'post_date'             => '',
                'post_date_gmt'         => '',
                'post_name'             => ''
            );

            $data = array(
                'ID' => $this->_currentNodeValues['WP:POST_ID'], 
                'post_author' => (int) $user_id ? $user_id : get_current_user_id(), 
                'post_date' => $this->_currentNodeValues['WP:POST_DATE'] ? $this->_currentNodeValues['WP:POST_DATE'] : '',
                'post_date_gmt' => $this->_currentNodeValues['WP:POST_DATE_GMT'] ? $this->_currentNodeValues['WP:POST_DATE_GMT'] : '', 
                'post_content' => $this->_currentNodeValues['CONTENT:ENCODED'] ? $this->_currentNodeValues['CONTENT:ENCODED'] : '',
                'post_excerpt' => $this->_currentNodeValues['EXCERPT:ENCODED'] ? $this->_currentNodeValues['EXCERPT:ENCODED'] : '', 
                'post_title' => array_key_exists("TITLE", $this->_currentNodeValues) ? $this->_currentNodeValues['TITLE'] : '',
                'post_status' => $this->_currentNodeValues['WP:STATUS'] ? $this->_currentNodeValues['WP:STATUS'] : '', 
                'post_name' => array_key_exists("WP:POST_NAME", $this->_currentNodeValues) ? $this->_currentNodeValues['WP:POST_NAME'] : '',
                'comment_status' => $this->_currentNodeValues['WP:COMMENT_STATUS'] ? $this->_currentNodeValues['WP:COMMENT_STATUS'] : '', 
                'ping_status' => $this->_currentNodeValues['WP:PING_STATUS'] ? $this->_currentNodeValues['WP:PING_STATUS'] : '',
                'guid' =>  array_key_exists("GUID",$this->_currentNodeValues) ? $this->_currentNodeValues['GUID'] : '', 
                'post_parent' => $this->_currentNodeValues['WP:POST_PARENT'] ? $this->_currentNodeValues['WP:POST_PARENT'] : 0, 
                'menu_order' => $this->_currentNodeValues['WP:MENU_ORDER'] ? $this->_currentNodeValues['WP:MENU_ORDER'] : 0,
                'post_type' => $this->_currentNodeValues['WP:POST_TYPE'] ? $this->_currentNodeValues['WP:POST_TYPE'] : '', 
                'post_password' => array_key_exists("WP:POST_PASSWORD", $this->_currentNodeValues) ? $this->_currentNodeValues['WP:POST_PASSWORD'] : ''
            );

            $data = wp_parse_args( $data, $defaults );

            $exists = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "posts" . " WHERE ID = " . (int) $data['ID']);
            if (!$exists) $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "posts", $data );

            if ($this->_currentNodeValues['WP:POST_TYPE']=='attachment') {
                $url = array_key_exists('WP:ATTACHMENT_URL',$this->_currentNodeValues) ? $this->_currentNodeValues['WP:ATTACHMENT_URL'] : $this->_currentNodeValues['GUID'];

                // if the URL is absolute, but does not contain address, then upload it assuming base_site_url
                if ( preg_match( '|^/[\w\W]+$|', $url ) ) $url = rtrim( $this->base_url, '/' ) . $url;

                if ( preg_match( '%/[0-9]{4}/[0-9]{2}/%', $url, $matches ) ) {
                    $relative_path_from_uploads_dir = $matches[0];
                } else {
                    /*
                     * Can't find storage path - Will use current YYYY/MM folder?
                     */
                    $relative_path_from_uploads_dir = '';
                }

                $data = [
                    'ID' => $this->_currentNodeValues['WP:POST_ID'],
                    'attachment_url' => $url,
                    'upload_path' => $relative_path_from_uploads_dir
                ];

                $exists = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "attachments" . " WHERE ID = " . (int) $data['ID']);
                
                if (!$exists && $url!='') $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "attachments", $data );


            }

            $this->_currentNodeValues = [];
        }

        else

        if ($name == "WP:COMMENT") {
            $this->_isComment=false;

            $data = array(
                'comment_ID' => $this->_currentSubNodeValues['WP:COMMENT_ID'],
                'comment_post_ID' => $this->_currentNodeValues['WP:POST_ID'],
                'comment_author' => array_key_exists("WP:COMMENT_AUTHOR",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:COMMENT_AUTHOR'] : 0,
                'comment_author_email' => array_key_exists("WP:COMMENT_AUTHOR_EMAIL",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:COMMENT_AUTHOR_EMAIL'] : '',
                'comment_author_url' => array_key_exists("WP:COMMENT_AUTHOR_URL",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:COMMENT_AUTHOR_URL'] : '',
                'comment_author_IP' => array_key_exists("WP:COMMENT_AUTHOR_IP",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:COMMENT_AUTHOR_IP'] : '',
                'comment_date' => $this->_currentSubNodeValues['WP:COMMENT_DATE'],
                'comment_date_gmt' => $this->_currentSubNodeValues['WP:COMMENT_DATE_GMT'],
                'comment_content' => array_key_exists("WP:COMMENT_CONTENT",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:COMMENT_CONTENT'] : '',
                'comment_karma' => array_key_exists("WP:COMMENT_KARMA",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:COMMENT_KARMA'] : '',
                'comment_approved' => array_key_exists("WP:COMMENT_APPROVED",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:COMMENT_APPROVED'] : '',
                'comment_type' => array_key_exists("WP:COMMENT_TYPE",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:COMMENT_TYPE'] : '',
                'comment_parent' => array_key_exists("WP:COMMENT_PARENT",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:COMMENT_PARENT'] : 0,
                'user_id' => array_key_exists("WP:COMMENT_USER_ID",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:COMMENT_USER_ID'] : 0
            );

            $exists = $wpdb->get_var("SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "comments" . " WHERE comment_ID = " . (int) $data['comment_ID']);
            if (!$exists) $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "comments", $data );

            $this->_currentSubNodeValues = [];
        }

        else

        if ($name == "WP:POSTMETA") {
            $this->_isPostmeta=false;

            if (array_key_exists("WP:META_KEY",$this->_currentSubNodeValues))
                if ($this->_currentSubNodeValues['WP:META_KEY']) 
                {

                    $data = array(
                        'post_id' => $this->_currentNodeValues['WP:POST_ID'],
                        'meta_key' => array_key_exists("WP:META_KEY",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:META_KEY'] : '',
                        'meta_value' => array_key_exists("WP:META_VALUE",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:META_VALUE'] : ''
                    );

                    $exists = $wpdb->get_var(
                        $wpdb->prepare("SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "postmeta" . " WHERE post_id = " . (int) $data['post_id'] . " AND meta_key = %s",$data['meta_key'])
                    );
                    if (!$exists) $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "postmeta", $data );
                }

            $this->_currentSubNodeValues = [];
        }

        else

        if ($name == "WP:TERMMETA") {
            $this->_isPostmeta=false;

            if (array_key_exists("WP:META_KEY",$this->_currentSubNodeValues))
                if ($this->_currentSubNodeValues['WP:META_KEY']) 
                {

                    $data = array(
                        'term_id' => $this->_currentNodeValues['WP:TERM_ID'],
                        'meta_key' => array_key_exists("WP:META_KEY",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:META_KEY'] : '',
                        'meta_value' => array_key_exists("WP:META_VALUE",$this->_currentSubNodeValues) ? $this->_currentSubNodeValues['WP:META_VALUE'] : ''
                    );

                    $exists = $wpdb->get_var(
                        $wpdb->prepare("SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "termmeta" . " WHERE term_id = " . (int) $data['term_id'] . " AND meta_key = %s",$data['meta_key'])
                    );
                    if (!$exists) $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "termmeta", $data );
                }

            $this->_currentSubNodeValues = [];
        }

        else

        if ($name == "CATEGORY") {
            $this->_isPostterm=false;

            $term_id = $wpdb->get_var(
                $wpdb->prepare("SELECT term_id FROM " . $wpdb->prefix . $this->plugin_prefix . "terms" . " WHERE slug = %s",$this->_currentSubNodeValues['NICENAME'])
            );

            $term_taxonomy_id = $wpdb->get_var(
                $wpdb->prepare("SELECT term_taxonomy_id FROM " . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy" . " WHERE taxonomy = %s AND term_id = ".(int) $term_id,$this->_currentSubNodeValues['DOMAIN'])
            );

            $exists = $wpdb->get_var(
                "SELECT COUNT(1) FROM " . $wpdb->prefix . $this->plugin_prefix . "term_relationships" . " WHERE object_id = " . (int) $this->_currentNodeValues['WP:POST_ID'] . " AND term_taxonomy_id = " . (int) $term_taxonomy_id
            );

            $data = array(
                'term_taxonomy_id' => (int) $term_taxonomy_id,
                'object_id' => (int) $this->_currentNodeValues['WP:POST_ID'],
                'term_order' => 0
            );

            if (!$exists && $term_taxonomy_id) $wpdb->insert( $wpdb->prefix . $this->plugin_prefix . "term_relationships", $data );
            
            $wpdb->query(
                $wpdb->prepare('UPDATE ' . $wpdb->prefix . $this->plugin_prefix . "term_taxonomy SET count=count+1 WHERE term_id = " . (int) $term_id . " AND taxonomy = %s", $this->_currentSubNodeValues['DOMAIN'])
            );

            $this->_currentSubNodeValues = [];
        }

        $this->_current = array_pop($this->_stack);
    }

    public function parseTag($parser, $data) 
    {
        if ($this->_isCategory || $this->_isTag || $this->_isTerm || $this->_isItem || $this->_isAuthor) 
        {
           $this->_currentNodeValues[$this->_current] = $data;
        }

        if ($this->_isComment || $this->_isPostmeta || $this->_isTermmeta || $this->_isPostterm)
        {
            $this->_currentSubNodeValues[$this->_current] = $data;
        }

        if ($this->_current=="WP:POST_TYPE") {
            if (!array_key_exists($data,$this->post_types))
                $this->post_types[$data] = 1;
            else
                $this->post_types[$data] = $this->post_types[$data] + 1;
        }

        if ($this->_current=="WP:WXR_VERSION") {
            $this->wxr_version = $data;
        }
        
    }

    public function parse($dry_run=false)
    {
        if ($dry_run) $this->_mode="dry-run";

        $fh = fopen($this->_file, "r");
        if (!$fh) {
            wp_die(__("Error opening file!"));
        }

        while (!feof($fh)) {
            $data = fread($fh, 4096);
            $parsing = xml_parse($this->_parser, $data, feof($fh));
            if (!$parsing) {
                $this->error_code = xml_get_error_code($this->_parser);
                $this->error_string = xml_error_string($this->error_code);
                xml_parser_free($this->_parser);
                break;
            }
        }
        xml_parse($this->_parser, '', true); // finalize parsing
    }

    public function getParser()
    {
        return $this->_parser;
    }

}