<?php
/*
Plugin Name: LONG URL MAKER
Plugin URI: http://www.kpcode.com
Description: Change slug maximum length from 200 to be 2000. Support all language. ปลั๊กอินนี้สามารถทำให้ตั้งชื่อ url ได้ยาวขึ้นกว่าเดิม
Author: kpcode
Version: 2.0.1
Author URI: http://www.kpcode.com
*/

register_activation_hook(__FILE__, 'kpcode_long_url_activation');

global $kpcode_longurlmaker_version;
$kpcode_longurlmaker_version = '2.0';

// check wordpress version for auto activate plugin again.
	$now_version = get_bloginfo( 'version' );
	$last_version = get_option( 'kpcode_version_wp');
	$kpcode_plugin_version = get_option('kpcode_longurlmaker_version');

if(($now_version != $last_version)&&($kpcode_plugin_version != '')){
	
	kpcode_setup_new_database();
	kpcode_restore_db_posts();
	
	//update last_version
	update_option( 'kpcode_version_wp', $now_version );

}

/// for update from version 1.0 be version 2.0.1
$ck_old_version = get_option( 'c_version_wp');
if($ck_old_version!=''){
	kpcode_cre_db_posts('new');
	kpcode_setup_new_database();
	kpcode_restore_db_posts();
	
	$wp_version = get_bloginfo( 'version' );
	global $kpcode_longurlmaker_version;
	$kpcode_plugin_version = get_option('kpcode_longurlmaker_version');
	
	add_option( 'kpcode_version_wp', $wp_version, '', 'yes' );
	add_option( 'kpcode_longurlmaker_version', $kpcode_longurlmaker_version, '', 'yes' );
	delete_option( 'c_version_wp' );
}


/// activate plugin
function kpcode_long_url_activation() {
	global $kpcode_longurlmaker_version;
	
	$kpcode_plugin_version = get_option('kpcode_longurlmaker_version');
	
	if($kpcode_plugin_version==''){
	kpcode_cre_db_posts('new');
	kpcode_setup_new_database();
	kpcode_restore_db_posts();
	}else{
	kpcode_setup_new_database();
	kpcode_restore_db_posts();		
	}
	
	
	// set version wordpress for update checking 
	$wp_version = get_bloginfo( 'version' );

	add_option( 'kpcode_version_wp', $wp_version, '', 'yes' );
	add_option( 'kpcode_longurlmaker_version', $kpcode_longurlmaker_version, '', 'yes' );
}


// setup type of postname (varchar 20 -> varchar 2000)
function kpcode_setup_new_database(){ 

	global $wpdb;
	
		// check mysql version
      $t=$wpdb->get_results( "select version() as ve");
 
      define('MYSQL_VERSION', $t[0]->ve); 

      $min_msql = '5.0.15';

	  if(version_compare(MYSQL_VERSION, $min_msql) >= 0){

			$max_char=2000;

	  }else{

			$max_char=255;  

	  }

	 global $blog_id; 

	 if (!empty ($wpdb->charset))

		$charset_charset = " CHARACTER SET {$wpdb->charset} ";

	 if (!empty ($wpdb->collate))

		$charset_collate = " COLLATE {$wpdb->collate} ";


		 $sql= "ALTER TABLE ".$wpdb->posts." CHANGE post_name post_name VARCHAR(".$max_char.") $charset_charset $charset_collate NOT NULL DEFAULT ''";

   		 $results = $wpdb->query($sql);
		 
		 $sql_terms= "ALTER TABLE ".$wpdb->terms." CHANGE slug slug VARCHAR(".$max_char.") $charset_charset $charset_collate NOT NULL DEFAULT ''";

   		 $results_terms = $wpdb->query($sql_terms);
	 
}

function kpcode_cre_db_posts($command){
	
	global $wpdb;
	 
	if($command=='new'){
		
		/// Create db for backup postname
		
	$table_name = $wpdb->prefix . 'kpcode_url_posts';
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		kpcode_post_id bigint(20) DEFAULT '0' NOT NULL ,
		kpcode_post_name varchar(2000) DEFAULT '' NOT NULL,
		kpcode_type varchar(200) DEFAULT '' NOT NULL,
		UNIQUE KEY id (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	
	
	// backup post_name to my db
	
	$posts = $wpdb->get_results("SELECT ID,post_name FROM ".$wpdb->posts." WHERE post_status = 'publish' OR post_status = 'draft' OR post_status = 'pending' OR post_status = 'private' OR post_status = 'future' OR post_status = 'trash'"	);

			 	foreach ( $posts as $post ) {

					$backup_postname=$post->post_name;

					$sql_backup= "INSERT INTO ".$wpdb->prefix . 'kpcode_url_posts'." (`id` ,`kpcode_post_id` ,`kpcode_post_name` ,`kpcode_type`
)VALUES(NULL , '$post->ID', '$backup_postname', 'posts');";
					
					$results_backup = $wpdb->query($sql_backup);

				}
	
	// backup terms_name to my db			
				
			$posts_terms = $wpdb->get_results("SELECT $wpdb->terms.term_id,$wpdb->terms.slug FROM $wpdb->terms
INNER JOIN $wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_id = $wpdb->terms.term_id
WHERE $wpdb->term_taxonomy.taxonomy = 'category' OR $wpdb->term_taxonomy.taxonomy = 'post_tag' OR $wpdb->term_taxonomy.taxonomy = 'product_tag' OR $wpdb->term_taxonomy.taxonomy = 'product_cat'
ORDER BY name ASC"	);

			 	foreach ( $posts_terms as $post_terms ) {

					$backup_slug=$post_terms->slug;

					$sql_bp_terms= "INSERT INTO ".$wpdb->prefix . 'kpcode_url_posts'." (`id` ,`kpcode_post_id` ,`kpcode_post_name` ,`kpcode_type`
)VALUES(NULL , '$post_terms->term_id', '$backup_slug', 'terms');";

					$results_bp_terms = $wpdb->query($sql_bp_terms);

				}			
	
	}
	
}

function kpcode_restore_db_posts(){
	
	global $wpdb;
	
	/// restore posts
	$posts_backup = $wpdb->get_results("
	SELECT 
a.kpcode_post_id,
b.kpcode_post_name
FROM   (SELECT kpcode_post_id, 
               Max(id) AS id 
        FROM   ".$wpdb->prefix . 'kpcode_url_posts'."
		WHERE  kpcode_type='posts'
        GROUP  BY kpcode_post_id) a 
       INNER JOIN ".$wpdb->prefix . 'kpcode_url_posts'." b 
               ON b.id = a.id"	);

			 	foreach ( $posts_backup as $posts ) {
					
					$restore_name = $posts->kpcode_post_name;

					$sql_restore= "UPDATE ".$wpdb->posts." SET post_name ='$restore_name' WHERE ID ='$posts->kpcode_post_id'";

					$results_restore = $wpdb->query($sql_restore);

				}
		/// restore terms
	$terms_backup = $wpdb->get_results("
	SELECT 
a.kpcode_post_id,
b.kpcode_post_name
FROM   (SELECT kpcode_post_id, 
               Max(id) AS id 
        FROM   ".$wpdb->prefix . 'kpcode_url_posts'." 
		WHERE  kpcode_type='terms'
        GROUP  BY kpcode_post_id) a 
       INNER JOIN ".$wpdb->prefix . 'kpcode_url_posts'." b 
               ON b.id = a.id"	);

			 	foreach ( $terms_backup as $terms ) {
					
					$restore_slug = $terms->kpcode_post_name;

					$sql_restore_terms = "UPDATE ".$wpdb->terms." SET slug ='$restore_slug' WHERE term_id ='$terms->kpcode_post_id'";

					$results_restore_terms = $wpdb->query($sql_restore_terms);

				}
	
	}
	
/// check unique post slug

if (is_admin()) {
			add_filter('wp_unique_post_slug', 'kpcode_unique_post_slug', 100, 6);
		}

    function kpcode_unique_post_slug($slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug='' /* @since WP3.5 */) {
		if ($slug === '') return '';
		
		global $wpdb, $wp_rewrite;
		
		$slug = $original_slug;

$feeds = $wp_rewrite->feeds;
	if ( ! is_array( $feeds ) )
		$feeds = array();

	if ( 'attachment' == $post_type ) {
		$check_sql = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND ID != %d LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_ID ) );
		
		if ( $post_name_check || in_array( $slug, $feeds ) || apply_filters( 'wp_unique_post_slug_is_bad_attachment_slug', false, $slug ) ) {
			$suffix = 2;
			do {
				$alt_post_name = _truncate_post_slug( $slug, 1900 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
				$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_ID ) );
				$suffix++;
			} while ( $post_name_check );
			$slug = $alt_post_name;
		}
	} elseif ( is_post_type_hierarchical( $post_type ) ) {
		if ( 'nav_menu_item' == $post_type )
			return $slug;

		$check_sql = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type IN ( %s, 'attachment' ) AND ID != %d AND post_parent = %d LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_type, $post_ID, $post_parent ) );

		if ( $post_name_check || in_array( $slug, $feeds ) || preg_match( "@^($wp_rewrite->pagination_base)?\d+$@", $slug )  || apply_filters( 'wp_unique_post_slug_is_bad_hierarchical_slug', false, $slug, $post_type, $post_parent ) ) {
			$suffix = 2;
			do {
				$alt_post_name = _truncate_post_slug( $slug, 1900 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
				$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_type, $post_ID, $post_parent ) );
				$suffix++;
			} while ( $post_name_check );
			$slug = $alt_post_name;
		}
	} else {
		$check_sql = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND ID != %d LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_type, $post_ID ) );
		if ( $post_name_check || in_array( $slug, $feeds ) || apply_filters( 'wp_unique_post_slug_is_bad_flat_slug', false, $slug, $post_type ) ) {
			$suffix = 2;
			do {
				$alt_post_name = _truncate_post_slug( $slug, 1900 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
				$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_type, $post_ID ) );
				$suffix++;
			} while ( $post_name_check );
			$slug = $alt_post_name;
		}
	}
		
						
		return $slug;
		
	}

remove_filter( 'sanitize_title', 'sanitize_title_with_dashes');

add_filter( 'sanitize_title', 'kpcode_set_url_format' );

function kpcode_set_url_format($title) {

	global $wpdb;
	
	$title = strip_tags($title);



	$title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);



	$title = str_replace('%', '', $title);


	$title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);



	if (seems_utf8($title)) {

		if (function_exists('mb_strtolower')) {

			$title = mb_strtolower($title, 'UTF-8');

		}

		$title = utf8_uri_encode($title, 1900);

	}



	$title = strtolower($title);

	$title = preg_replace('/&.+?;/', '', $title); 

	$title = str_replace('.', '-', $title);
	
	if ( 'save' == $context ) {
		// Convert nbsp, ndash and mdash to hyphens
		$title = str_replace( array( '%c2%a0', '%e2%80%93', '%e2%80%94' ), '-', $title );

		// Strip these characters entirely
		$title = str_replace( array(
			// iexcl and iquest
			'%c2%a1', '%c2%bf',
			// angle quotes
			'%c2%ab', '%c2%bb', '%e2%80%b9', '%e2%80%ba',
			// curly quotes
			'%e2%80%98', '%e2%80%99', '%e2%80%9c', '%e2%80%9d',
			'%e2%80%9a', '%e2%80%9b', '%e2%80%9e', '%e2%80%9f',
			// copy, reg, deg, hellip and trade
			'%c2%a9', '%c2%ae', '%c2%b0', '%e2%80%a6', '%e2%84%a2',
			// acute accents
			'%c2%b4', '%cb%8a', '%cc%81', '%cd%81',
			// grave accent, macron, caron
			'%cc%80', '%cc%84', '%cc%8c',
		), '', $title );

		// Convert times to x
		$title = str_replace( '%c3%97', 'x', $title );
	}

	$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);

	$title = preg_replace('/\s+/', '-', $title);

	$title = preg_replace('|-+|', '-', $title);

	$title = trim($title, '-');

	return $title;

}


function kpcode_duplicate_postname ($post_id) {
	global $wpdb;
	
    if ( $post_id == null || empty($_POST) )
        return;

    if ( !isset( $_POST['post_type'] ) )  
        return; 

        $post_kpcode = get_post($post_id);
	
	if(($_POST['post_type']=='post')||($_POST['post_type']=='page')||($_POST['post_type']=='product')||($_POST['post_type']=='attachment')){
		$sql_backup_new= "INSERT INTO ".$wpdb->prefix . 'kpcode_url_posts'." (`id` ,`kpcode_post_id` ,`kpcode_post_name` ,`kpcode_type` )VALUES(NULL , '".$post_id."', '".$post_kpcode->post_name."', 'posts');";
					
		$results_backup_new = $wpdb->query($sql_backup_new);
	}
	
}
add_action('save_post', 'kpcode_duplicate_postname', 12 );

function kpcode_get_taxonomy($term_id){
	global $wpdb;
	$results = $wpdb->get_var( "SELECT taxonomy FROM ".$wpdb->term_taxonomy." WHERE term_id = '$term_id'" );
	return $results;
}

function kpcode_duplicate_term ($term_id) {
	
	global $wpdb;

		$taxonomy_name = kpcode_get_taxonomy($term_id);
        $term = get_term( $term_id, $taxonomy_name );
		$slug = $term->slug;
	
		$sql_backup_terms= "INSERT INTO ".$wpdb->prefix . 'kpcode_url_posts'." (`id` ,`kpcode_post_id` ,`kpcode_post_name` ,`kpcode_type` )VALUES(NULL , '".$term_id."', '".$slug."', 'terms');";
					
		$results_backup_terms = $wpdb->query($sql_backup_terms);

	
}
add_action('create_term', 'kpcode_duplicate_term', 12 );

function kpcode_update_term ($term_id) {
	
	global $wpdb;

		$taxonomy_name = kpcode_get_taxonomy($term_id);
        $term = get_term( $term_id, $taxonomy_name );
		$slug = $term->slug;
	
		$sql_backup_terms= "INSERT INTO ".$wpdb->prefix . 'kpcode_url_posts'." (`id` ,`kpcode_post_id` ,`kpcode_post_name` ,`kpcode_type` )VALUES(NULL , '".$term_id."', '".$slug."', 'terms');";
					
		$results_backup_terms = $wpdb->query($sql_backup_terms);

	
}
add_action('edited_term', 'kpcode_update_term' );


function kpcode_set_url_format_old($title) {

	$title = strip_tags($title);

	$title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);

	$title = str_replace('%', '', $title);

	$title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);



	if (seems_utf8($title)) {

		if (function_exists('mb_strtolower')) {

			$title = mb_strtolower($title, 'UTF-8');

		}

		$title = utf8_uri_encode($title, 200);

	}



	$title = strtolower($title);

	$title = preg_replace('/&.+?;/', '', $title);

	$title = str_replace('.', '-', $title);
	
	if ( 'save' == $context ) {
		// Convert nbsp, ndash and mdash to hyphens
		$title = str_replace( array( '%c2%a0', '%e2%80%93', '%e2%80%94' ), '-', $title );

		// Strip these characters entirely
		$title = str_replace( array(
			// iexcl and iquest
			'%c2%a1', '%c2%bf',
			// angle quotes
			'%c2%ab', '%c2%bb', '%e2%80%b9', '%e2%80%ba',
			// curly quotes
			'%e2%80%98', '%e2%80%99', '%e2%80%9c', '%e2%80%9d',
			'%e2%80%9a', '%e2%80%9b', '%e2%80%9e', '%e2%80%9f',
			// copy, reg, deg, hellip and trade
			'%c2%a9', '%c2%ae', '%c2%b0', '%e2%80%a6', '%e2%84%a2',
			// acute accents
			'%c2%b4', '%cb%8a', '%cc%81', '%cd%81',
			// grave accent, macron, caron
			'%cc%80', '%cc%84', '%cc%8c',
		), '', $title );

		// Convert times to x
		$title = str_replace( '%c3%97', 'x', $title );
	}

	$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);

	$title = preg_replace('/\s+/', '-', $title);

	$title = preg_replace('|-+|', '-', $title);

	$title = trim($title, '-');



	return $title;

}


 function kpcode_auto_update_url($type){

		global $wpdb;		

		$posts = $wpdb->get_results("SELECT ID, post_title,post_name FROM ".$wpdb->posts." WHERE post_status = 'publish' OR post_status = 'draft' OR post_status = 'pending' OR post_status = 'private' OR post_status = 'future' OR post_status = 'trash'"	);

			 	foreach ( $posts as $post ) {

					if($type=='new'){

						$new_postname=kpcode_set_url_format($post->post_title);

					}else{

						$new_postname=kpcode_set_url_format_old($post->post_title);

					}

					$sql2= "UPDATE ".$wpdb->posts." SET post_name ='$new_postname' WHERE ID ='$post->ID'";

					$results2 = $wpdb->query($sql2);

				}

		$posts_terms = $wpdb->get_results("SELECT $wpdb->terms.term_id,$wpdb->terms.name,$wpdb->terms.slug FROM $wpdb->terms INNER JOIN $wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_id = $wpdb->terms.term_id
WHERE $wpdb->term_taxonomy.taxonomy = 'category' OR $wpdb->term_taxonomy.taxonomy = 'post_tag' OR $wpdb->term_taxonomy.taxonomy = 'product_tag' OR $wpdb->term_taxonomy.taxonomy = 'product_cat'
ORDER BY name ASC"	);

			 	foreach ( $posts_terms as $post_terms ) {

					if($type=='new'){

						$new_postname_terms=kpcode_set_url_format($post_terms->name);

					}else{

						$new_postname_terms=kpcode_set_url_format_old($post_terms->name);

					}

					$sql2_terms= "UPDATE ".$wpdb->terms." SET slug ='$new_postname_terms' WHERE term_id ='$post_terms->term_id'";

					$results2_terms = $wpdb->query($sql2_terms);

				}
 }




register_deactivation_hook(__FILE__, 'kpcode_long_url_deactivation');

function kpcode_long_url_deactivation() {

	global $wpdb;

	remove_filter( 'sanitize_title', 'kpcode_set_url_format');

	add_filter( 'sanitize_title','kpcode_set_url_format_old');

	kpcode_auto_update_url('old');


	if (!empty ($wpdb->charset))

		$charset_charset = " CHARACTER SET {$wpdb->charset} ";

	if (!empty ($wpdb->collate))

		$charset_collate = " COLLATE {$wpdb->collate} ";


		 $sql= "ALTER TABLE ".$wpdb->posts." CHANGE post_name post_name VARCHAR(200) $charset_charset $charset_collate NOT NULL DEFAULT ''";

   		 $results = $wpdb->query($sql);

		 $sql_terms= "ALTER TABLE ".$wpdb->terms." CHANGE slug slug VARCHAR(200) $charset_charset $charset_collate NOT NULL DEFAULT ''";

   		 $results_terms = $wpdb->query($sql_terms);

	 
}

?>