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
    add_action('admin_menu', array($this,'itwCreateAdminPages'));
    register_activation_hook(__FILE__, array($this, 'itwCreateDatabaseTable'));
  }

  public function itwCreateDatabaseTable() {

    // WooCommerce Posts Table IGDB Scripts take posts from here to process.
    $fields = array(
      '`id` INTEGER(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
      '`post_id` bigint(20)',
      '`post_title` text',
      '`is_processed` tinyint(1)',
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
    $this->processForTesting();
  }

}

global $IgdbToWc;
$IgdbToWc = new IgdbToWc();