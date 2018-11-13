<?php
/*
  Plugin Name: IGDB to WooCommerce
  Plugin URI: 
  Description: Search IGDB Databases with your WooCommerce Game Products and get the game details and update WooCommerce Product.
  Author: Shiva Srikanth T
  Version: 1.0
  Author URI: 
 */

class IgdbToWc {

  public function __construct() {
    add_action('wp_loaded', array($this,'igdbAddStyles'));
    add_action('admin_menu', array($this,'itwCreateAdminPages'));
    register_activation_hook(__FILE__, array($this, 'itwCreateDatabaseTable'));
  }

  public function igdbAddStyles() {
    wp_register_style( 'igdbapirbbstyle', plugins_url('css/style.css',__FILE__ ));
  }

  public function itwCreateDatabaseTable() {

    // WooCommerce Posts Table IGDB Scripts take posts from here to process.
    $fields = array(
      '`id` INTEGER(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
      '`post_id` bigint(20)',
      '`post_title` text',
      '`is_processed` tinyint(1)',
      '`object` text',
      '`created` datetime',
      '`updated` datetime'
    );
    $this->createTable('itw_wc_posts',$fields);
  }
  
  public function createTable($table,$fields){
    global $wpdb;
    $table = $wpdb->prefix.$table;
    if ($wpdb->get_var("show tables like '$table'") != $table) :
      $implode = implode(" ,", $fields);
      $sql = 'CREATE TABLE ';
      $sql .= $table;
      $sql .= ' (';
      $sql .= $implode;
      $sql .= ')';
      require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
      dbDelta($sql);
    endif;
  }

  public function itwCreateAdminPages() {
    
    $page_title = 'IGDB To WooCommerce';
    $menu_title = 'IGDB To WooCommerce';
    $capability = 'manage_options';
    $menu_slug = 'itw_api_rbb';
    $parent_slug = $menu_slug;
    $function = array($this,'itwMainPage');
    add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function);

    $st_page_title = 'IGDB Settings';
    $st_menu_title = 'IGDB Settings';
    $st_menu_slug = 'igdb_settings_rbb';
    $st_function = array($this,'igdb_settings_page_display');
    add_submenu_page( $parent_slug, $st_page_title, $st_menu_title, $capability, $st_menu_slug, $st_function);

  }

  public function igdb_settings_page_display() {

    wp_enqueue_style('igdbapirbbstyle');    
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized user');
    }
  
    if (!empty($_POST))
    {
        if (! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'IgdbNonce' ) )
        {
            print 'Sorry, your nonce did not verify.';
            exit;
        }
    }
  
    if (isset($_POST['igdbapikey']) && strlen($_POST['igdbapikey']) ) {
        $value = sanitize_text_field($_POST['igdbapikey']);
        update_option('igdbapikey', $value);
    }
  
    $value = get_option('igdbapikey');
    include 'settings-file.php';
  }
  public function processForTesting(){
    echo "<pre>";
    echo "Testing Enabled";
    $this->itwGetProductsFromWooCommerce();
		die;
  }

  public function itwGetProductsFromWooCommerce(){
    
    $table = 'itw_wc_posts';

    $post_ids = $this->getAllPostIds($table);

    $args = array(
      'post_type'      => 'product',
      'post__not_in' => $post_ids,
      'posts_per_page' => -1,
      'post_status' => 'publish',
    );

    $loop = new WP_Query( $args );

    while ( $loop->have_posts() ) : $loop->the_post();
      global $product;
      $fields['id'] = "";
      $fields['post_id'] = get_the_ID();
      $fields['post_title'] = get_the_title();
      $fields['is_processed'] = 0;
      $fields['created'] = date('Y-m-d H:i:s', time());
      $fields['updated'] = date('Y-m-d H:i:s', time());
      $this->sendToTable($table,$fields);
    endwhile;

    wp_reset_query();

  }

  public function sendToTable($table,$values){
    global $wpdb;
    $table = $wpdb->prefix.$table;
    $wpdb->insert($table,$values);
  }

  public function getAllPostIds($table){
    global $wpdb;
    $table = $wpdb->prefix.$table;
    $sqlQuery = "SELECT post_id FROM $table WHERE id > 0 ORDER BY id DESC";
    $results = $wpdb->get_results( $sqlQuery, OBJECT_K );
    $results = array_keys($results);
    return $results;
  }

  public function itwMainPage(){
    //$this->processForTesting(); die;
    $getTitles = $this->itwGetProductsFromTable();
    foreach($getTitles as $key => $title){
      $this->call_IGDB_API($key, $title);
    }
  }

  public function itwGetProductsFromTable(){
    $table = 'itw_wc_posts';
    global $wpdb;
    $table = $wpdb->prefix.$table;
    $sqlQuery = "SELECT post_id,post_title FROM $table WHERE id > 0 and is_processed = 0 ORDER BY id DESC limit 30";
    $results = $wpdb->get_results( $sqlQuery, OBJECT_K );
    $results = array_column($results, 'post_title','post_id');
    return $results;
  }

  public function updateTable($table,$field,$value,$where,$where_value){
    global $wpdb;
    $table = $wpdb->prefix.$table;
    $fieldA[$field] = $value;
    $whereA[$where] = $where_value;
    $wpdb->update($table, $fieldA, $whereA);
  }

  public function call_IGDB_API($post_id, $title){

    $options = array(
      'fields'=> '*',
      'search' => urlencode($title),
      'limit' => 1,
    );
    
    $url = 'https://api-endpoint.igdb.com/games/?';

    $fields = array();
    foreach($options as $key => $value){
      $fields[] = $key.'='.$value;
    }

    $field_url = implode('&',$fields);
    $curl = curl_init();

    $url = $url . $field_url;

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'user-key: ' . get_option('igdbapikey'),
        'Accept: application/json',
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    // EXECUTE:
    $result = curl_exec($curl);
    if(!$result){
      $results  = "<h3>Oops! The request was not successful. Make sure you are using a valid ";
      $results .= "AppID for the Production environment.</h3>";
    }else{
      $table = 'itw_wc_posts';
      //$result = json_decode($result);
      $this->updateTable($table,'object',$result,"post_id",$post_id);
      $field = 'is_processed';
      $this->updateTable($table,$field,1,'post_id',$post_id);
      $results =  "Search Key is Successfully saved";
    }
    curl_close($curl);
    return $results;
  }

}

global $IgdbToWc;
$IgdbToWc = new IgdbToWc();