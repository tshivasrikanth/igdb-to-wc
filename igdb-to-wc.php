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
	
	add_filter('cron_schedules', array($this,'wcCronInterval'));
	register_activation_hook(__FILE__, array($this,'wcCronActivation'));
	add_action('wcCronRun', array($this,'wcCron'));
	register_deactivation_hook(__FILE__, array($this,'wcCronDeactivate'));
  }
  
    public function wcCronDeactivate() {
        wp_clear_scheduled_hook('wcCronRun');
    }
	
	public function wcCronActivation(){
		if (! wp_next_scheduled ( 'wcCronRun' )) {
			wp_schedule_event(time(), 'FiteenMinutes', 'wcCronRun');
		}
	}

	public function wcCronInterval( $schedules ) {
		$schedules['FiteenMinutes'] = array(
			'interval' => 900,
			'display' => __( 'Every 15 Minutes' ),
		);
		return $schedules;
	}
	
	public function wcCron(){
		$this->itwGetProductsFromWooCommerce();
		$this->itwGetProductsFromIGDB();
		$this->itwUpdateWcProducts();		
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
    //$this->itwGetProductsFromWooCommerce();
	//$this->itwGetProductsFromIGDB();
	//$this->itwUpdateWcProducts();
  }
  
  public function itwShowUpdatedWcProducts(){
	  
	wp_enqueue_style('igdbapirbbstyle');

    $perpage = 30;
    
    if(isset($_GET['paged']) & !empty($_GET['paged'])){
      $curpage = $_GET['paged'];
    }else{
      $curpage = 1;
    }

    $start = ($curpage * $perpage) - $perpage;
	$table = 'itw_wc_posts';
    $totalRows = $this->getRowCount($table,0);
	$processedRows = $this->getRowCount($table,2);
    $totalItems = $totalRows->count;
	$processedItems = $processedRows->count;
    $startpage = 1;
    $endpage = ceil($processedItems/$perpage);
    $nextpage = $curpage + 1;
    $previouspage = $curpage - 1;
    
    $productResults = $this->getUpdatedResults($start,$perpage,$table);
    include 'products-file.php';
	  
  }
  
  public function getRowCount($table,$processed){
    global $wpdb;
    $table = $wpdb->prefix.$table;
	if($processed){
		$query = "SELECT count(*) as count FROM $table where is_processed = $processed";
	}else{
		$query = "SELECT count(*) as count FROM $table";
	}
    
    $results = $wpdb->get_row( $query, OBJECT );
    return $results;
  }
  
  public function getUpdatedResults($start,$limit,$table){
    global $wpdb;
    $table = $wpdb->prefix.$table;
    $query = "SELECT * FROM $table WHERE id > 0 and is_processed = 2 ORDER BY updated DESC LIMIT $start,$limit";
    $results = $wpdb->get_results( $query, OBJECT_K );
	$results = array_column($results, 'object','post_id');
    return $results;
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
  
  public function itwGetProductsFromIGDB(){
	$getTitles = $this->itwGetProductsFromWcTable(0);
	foreach($getTitles as $key => $title){
	  $this->call_IGDB_API($key, $title);
	}	  
  }
  
  public function itwUpdateWcProducts(){
	$getTitles = $this->itwGetProductsFromWcTable(1);
	foreach($getTitles as $key => $gameObj){
	  $this->itwUpdateProductToWc($key,json_decode($gameObj));
	}
  }
  
  public function itwUpdateProductToWc($id,$gameObj){

	$my_post = array(
		'ID'           => $id,
		'post_content' => $gameObj[0]->summary,
	);
	
	// Update the post into the database
	wp_update_post( $my_post );
	
	$table = 'itw_wc_posts';
	$fieldA = array('is_processed' => 2, 'updated'=> date('Y-m-d H:i:s', time()));
	$whereA = array('post_id'=> $id );
	$this->updateTable($table,$fieldA,$whereA);

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
    //$this->processForTesting();
	$this->itwShowUpdatedWcProducts();
  }

  public function itwGetProductsFromWcTable($is_processed){
    $table = 'itw_wc_posts';
    global $wpdb;
    $table = $wpdb->prefix.$table;
	if($is_processed == 0){
		$sqlQuery = "SELECT post_id,post_title FROM $table WHERE id > 0 and is_processed = 0 ORDER BY id DESC limit 15";
		$results = $wpdb->get_results( $sqlQuery, OBJECT_K );
		$results = array_column($results, 'post_title','post_id');
	}else if($is_processed == 1){
		$sqlQuery = "SELECT post_id,object FROM $table WHERE id > 0 and is_processed = 1 ORDER BY id DESC limit 15";
		$results = $wpdb->get_results( $sqlQuery, OBJECT_K );
		$results = array_column($results, 'object','post_id');		
	}
    return $results;
  }

  public function updateTable($table,$fieldA,$whereA){
    global $wpdb;
    $table = $wpdb->prefix.$table;
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
	  $fieldA = array('object' => $result,'is_processed' => 1, 'updated'=> date('Y-m-d H:i:s', time()));
	  $whereA = array('post_id'=> $post_id );
      $this->updateTable($table,$fieldA,$whereA);
      $results =  "Search Key is Successfully saved";
    }
    curl_close($curl);
    return $results;
  }

}

global $IgdbToWc;
$IgdbToWc = new IgdbToWc();