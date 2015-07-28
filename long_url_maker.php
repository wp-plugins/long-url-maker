<?php
/*
Plugin Name: LONG URL MAKER
Plugin URI: http://www.kpcode.com
Description: Making long url. Change maximum slug length from 200 to be 2000. support post page product(woocommerce) and category name. support all language.
Author: kpcode
Version: 1.0
Author URI: http://www.kpcode.com
*/

register_activation_hook(__FILE__, 'kpcode_long_url_activation');

// check wordpress version for auto activate plugin again.
	$now_version = get_bloginfo( 'version' );
	$last_version = get_option( 'c_version_wp');

if($now_version<>$last_version){
	
	kpcode_setup_new_database();
	
//update last_version
update_option( 'c_version_wp', $now_version );

}


/// activate plugin
function kpcode_long_url_activation() {
	
	kpcode_setup_new_database();
	
	
	// set version wordpress for update checking 
	$wp_version = get_bloginfo( 'version' );

	add_option( 'c_version_wp', $wp_version, '', 'yes' );
}

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

		

	 if( function_exists('is_multisite') && is_multisite()==1 ){


			$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));

			$i=1;

			foreach ($blogids as $blog_id) {

				if($blog_id==1){

					$table_post[$i]=$wpdb->posts;

				}else{

					$table_post[$i]=$wpdb->prefix.$blog_id.'_posts';

				}

				$i++;

			}


		 	foreach($table_post AS $table){

				 $sql= "ALTER TABLE ".$table." CHANGE post_name post_name VARCHAR(".$max_char.") $charset_charset $charset_collate NOT NULL DEFAULT ''";

   				 $results = $wpdb->query($sql);

			}

			

	 }else{ 


		 $sql= "ALTER TABLE ".$wpdb->posts." CHANGE post_name post_name VARCHAR(".$max_char.") $charset_charset $charset_collate NOT NULL DEFAULT ''";

   		 $results = $wpdb->query($sql);
		 

		 $sql_terms= "ALTER TABLE ".$wpdb->terms." CHANGE slug slug VARCHAR(".$max_char.") $charset_charset $charset_collate NOT NULL DEFAULT ''";

   		 $results_terms = $wpdb->query($sql_terms);
	 } 

	 kpcode_auto_update_url('new');

}



remove_filter( 'sanitize_title', 'sanitize_title_with_dashes');

add_filter( 'sanitize_title', 'kpcode_set_url_format' );

function kpcode_set_url_format($title) {

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

	$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);

	$title = preg_replace('/\s+/', '-', $title);

	$title = preg_replace('|-+|', '-', $title);

	$title = trim($title, '-');



	return $title;

}

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

	$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);

	$title = preg_replace('/\s+/', '-', $title);

	$title = preg_replace('|-+|', '-', $title);

	$title = trim($title, '-');



	return $title;

}


 function kpcode_auto_update_url($type){

		global $wpdb;

	if( function_exists('is_multisite') && is_multisite()==1 ){

		$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));

		$i=1;

		foreach ($blogids as $blog_id) {

			if($blog_id==1||$blog_id==""||$blog_id==0){

				$table_post[$i]=$wpdb->posts;

			}else{

				$table_post[$i]=$wpdb->prefix.$blog_id.'_posts';

			}

			$i++;

		}

		foreach($table_post AS $table){

			$posts = $wpdb->get_results("SELECT ID, post_title,post_name FROM ".$table." WHERE post_status = 'publish' OR post_status = 'draft' OR post_status = 'pending' OR post_status = 'private' OR post_status = 'future' OR post_status = 'trash'"	);

			 	foreach ( $posts as $post ) {

					if($type=='new'){

						$new_postname=kpcode_set_url_format($post->post_title);

					}else{

						$new_postname=kpcode_set_url_format_old($post->post_title);

					}

					$sql2= "UPDATE ".$table." SET post_name ='$new_postname' WHERE ID ='$post->ID'";

					$results2 = $wpdb->query($sql2);

				}

		}

	}else{
		

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

		$posts_terms = $wpdb->get_results("SELECT $wpdb->terms.term_id,$wpdb->terms.name,$wpdb->terms.slug FROM $wpdb->terms
INNER JOIN $wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_id = $wpdb->terms.term_id
WHERE $wpdb->term_taxonomy.taxonomy = 'category'
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

		

	 if( function_exists('is_multisite') && is_multisite()==1 ){


			$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));

			$i=1;

			foreach ($blogids as $blog_id) {

				if($blog_id==1){

					$table_post[$i]=$wpdb->posts;

				}else{

					$table_post[$i]=$wpdb->prefix.$blog_id.'_posts';

				}

				$i++;

			}

		 	foreach($table_post AS $table){

				 $sql= "ALTER TABLE ".$table." CHANGE post_name post_name VARCHAR(200) $charset_charset $charset_collate NOT NULL DEFAULT ''";

   				 $results = $wpdb->query($sql);

			}


	 }else{ 

		 $sql= "ALTER TABLE ".$wpdb->posts." CHANGE post_name post_name VARCHAR(200) $charset_charset $charset_collate NOT NULL DEFAULT ''";

   		 $results = $wpdb->query($sql);

		 $sql_terms= "ALTER TABLE ".$wpdb->terms." CHANGE slug slug VARCHAR(200) $charset_charset $charset_collate NOT NULL DEFAULT ''";

   		 $results_terms = $wpdb->query($sql_terms);

	 }
}

?>