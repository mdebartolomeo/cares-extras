<?php
/**
 * Admin functions for the CARES theme.
 *
 * @package    CARES
 * @subpackage Admin
 */

 
/*  
 * Enqueue and load jquery autocomplete for finding Related Projects for Project pages and for Staff pages
 * 
*/

//Only needed in admin section, so only load it there
add_action( 'admin_enqueue_scripts', 'cares_load_admin_scripts' );

function cares_load_admin_scripts() { 	

	wp_enqueue_script( 'jquery-ui-autocomplete', '','jquery' );
	
	wp_register_script(
		'cares-admin-scripts',
		get_template_directory_uri().'/js/cares-admin-scripts.js',
		array( 'jquery' ),
		false,
		false
	);
	
	//let js find the admin-ajax file
	wp_localize_script(
		'cares-admin-scripts',
		'cares_ajax',
		array(
			'adminAjax' => admin_url( 'admin-ajax.php' )//,
			//'dashboardURL' => get_bloginfo( 'url' ) . NM_USER_DASH
		)
	);
	wp_enqueue_script('cares-admin-scripts');
	
}

/* Set up the admin column functionality and meta boxes. */
add_action( 'admin_init', 'cares_admin_setup' );  //why admin_menu here and not admin_init?  Mel changed..

/**
 * Adds actions where needed for setting up the theme's admin functionality.
 *
 * @since  0.1.0
 * @access public
 * @return void
 */
function cares_admin_setup() {

	/* Custom columns on the edit posts screen. */
	add_filter( 'manage_edit-post_columns', 'cares_edit_posts_columns', 9 );
	add_action( 'manage_posts_custom_column', 'cares_manage_posts_columns', 10, 2 );

	/* Add meta boxes to post and save metadata. */
	add_action( 'add_meta_boxes', 'cares_post_meta_box', 4, 2 );
	add_action( 'save_post', 'cares_post_meta_box_save', 12, 2 );
	
	
	//TODO: Mel: break up, move this and corresponding functions to respective plugin files
	/* Add meta boxes to portfolio_item and profile and save metadata. */
	add_action( 'add_meta_boxes', 'cares_profile_project_meta_box', 4, 2 );
	add_action( 'save_post', 'cares_related_project_meta_box_save', 12, 2 );
	
}

/* 
 * AJAX FUNCTIONS FOR RELATED PROJECTS 
 *
 * Profiles and Projects(Portfolio Items) have optional metadata ('related_projects') to tie them to Projects (Portfolio Items)
 * The individual posts in wp-admin have meta boxes that use the following ajax functions/actions to allow admins to make associations
 *
 */
 
// Use autocomplete on Portfolio Item or Profile to find projects (req. due to projected future large volume of Projects)
add_action( 'wp_ajax_find_projects', 'cares_ajax_find_projects' );

function cares_ajax_find_projects() {

	global $wpdb;
	
	//check_ajax_referer('cares_ajax_data', 'cares_ajax_data_nonce');
	
	if ( !isset ( $_GET['get_projects'] ) || !isset ( $_GET['term'] ) ) return;
	if ( $_GET['get_projects'] != true ) return;
	
	//pull in search term from autocomplete ajax, if it exists
	$term = '%' . $_GET['term'] . '%';
	$post_type = 'portfolio_item';

	//for now, query projects by title only
	$postids = $wpdb->get_col( $wpdb->prepare( 
		"
		SELECT 		* 
		FROM 		$wpdb->posts
		WHERE 		$wpdb->posts.post_title LIKE %s
		AND 		$wpdb->posts.post_type = %s
		", 
		$term,
		$post_type
	) );
	
	//setup wp_query args
	$args = array(
		'post__in'=> $postids,
		'post_type' => array ( 'portfolio_item', 'profile' ),
		//'post_status' => 'publish',
		'posts_per_page' => 12
	);
	
	//run the query
	$project_query = new WP_Query( $args );
	$results = array();
	
	if ( $project_query->have_posts() ) {

		$count = 0;
		while ( $project_query->have_posts() ) {
			$project_query->the_post();
			$now_id = get_the_ID();
			
			if ( ! ( in_array( $now_id, $postids ) ) ) { continue; }
			//put necessary info in multi-array to json back to ajax function
			$results[$count]['id'] = get_the_ID();
			$results[$count]['title'] = get_the_title();
			$count++;
		}
		
		//array_filter() function's default behavior will remove all values from array which are equal to null, 0, '' or false
		$results = array_filter($results);

		//send back null if array is empty
		if ( empty($results) ) $results = null;
		
		echo json_encode ($results); //send the results back to the mother ship.
	}
	
	/* Restore original Post Data */
	wp_reset_postdata();

	die();
	
}

// Once Project is found/selected, add meta to post ( portfolio_item or profile ) to associate projects
add_action( 'wp_ajax_select_projects', 'cares_ajax_select_projects' );

function cares_ajax_select_projects() {
	
	//get data sent through ajax
	$project_ids_to_add = $_POST['project_ids'];  //casted as ints in javascript, does it translate
	$post_id = (int)$_POST['post_id'];

	//get postmeta for related_projects, compare to add to or throw error on duplicate
	$already_related_projects = get_post_meta( $post_id, 'related_projects', false );
	
	$project_results;
	$project_id_ints;
	$count = 0;
	
	//loop through ids array from ajax and make sure final array 
	foreach ( $project_ids_to_add as $id ){
	
		$project_id_ints[] = (int)$id;
		$project_title = get_the_title( $id );
		
		$project_results[$count]['id'] = $id;
		$project_results[$count]['title'] = $project_title;
		
		//if we have postmeta
		if ( is_array( $already_related_projects[0] ) ){
			if ( in_array ( $id, $already_related_projects[0] ) ) {
				
				$project_results[$count]['error'] = 1;  //we already have this associated with post, move along plz
				$project_results[$count]['posts_exists'] = true;
				
			}
		}
		
		//increment
		$count++;
	}
	
	//remove duplicate ids - TODO: reindex
	if ( is_array( $already_related_projects[0] ) ){
		$final_postmeta = array_merge( $already_related_projects[0], $project_id_ints );
		$final_postmeta = array_values( array_unique( $final_postmeta ) );
		//echo 'already: ' . $already_related_projects[0] . '/n';
		//echo 'news: ' . $project_id_ints;
	} else {
		$final_postmeta = $project_id_ints;
	}
	
	//update this post's ( portfolio_item or profile ) meta
	update_post_meta( $post_id, 'related_projects', $final_postmeta );
	
	//send back json data about the success/failure
	echo json_encode( $project_results );
	
	die();  //because 1s show up
}

/*
 *	Ajax-called function (action) to remove Project associations from posts ( portfolio_item or profile )
 */
add_action( 'wp_ajax_remove_projects', 'cares_ajax_remove_projects' );

function cares_ajax_remove_projects() {

	//get data sent through ajax - project ids to remove; current post;
	$project_ids_to_remove = $_POST['project_ids'];  //casted as ints in javascript, does it translate
	$post_id = (int)$_POST['post_id'];
	
	//get postmeta for related_projects
	$already_related_projects = maybe_unserialize( get_post_meta( $post_id, 'related_projects', false ) );
	
	// remove $project_ids_to_remove from $already_related_projects
	if ( is_array ( $already_related_projects[0] ) ){
		$new_related_projects = array_diff( $already_related_projects[0], $project_ids_to_remove );
			
		//Returns meta_id if the meta doesn't exist, otherwise returns true on success and false on failure. 
		//NOTE: If the meta_value passed to this function is the same as the value that is already in the database, this function returns false. 
		$success = update_post_meta( $post_id, 'related_projects', $new_related_projects );
		
		echo json_encode( $success );
	} else {
		echo json_encode( false );
	}
	
	die();
}

/* END AJAX FUNCTIONS FOR RELATED PROJECTS */



/**
 * Add meta box to portfolio_item, profile for finding projects using autocomplete
 *
 * @since  0.1.0
 * @access public
 * @return void
 */
function cares_profile_project_meta_box( ) {

	$screens = array( 'portfolio_item', 'profile' ); //maybe add page later, or other CPT

	foreach ( $screens as $screen ) {

		add_meta_box(
			'cares-select-projects',
			__( 'Select Related Projects to display with this Post', 'cares' ),
			'cares_related_project_meta_box_callback',
			$screen,
			'normal'
		);
		
		add_meta_box(
			'cares-view-delete-projects',
			__( 'Related Projects currently displayed with this Post', 'cares' ),
			'cares_related_view_delete_meta_box_callback',
			$screen,
			'normal'
		);
	
	}
}

/**
 * Renders the Select Related Projects box content.
 */
function cares_related_project_meta_box_callback( $post ) {

	// Add an nonce field so we can check for it later.
	wp_nonce_field( 'cares_profile_project_meta_box', 'cares_profile_project_meta_nonce' );

	echo '<table class="form-table widget"><tr>';
	echo ' <td style="width:300px">';
	echo '<b>Get Project by Title</b> <p>(type in at least 4 characters):</p>';
	echo '<input type="text" id="auto-projects" value=""></div>';
	echo '<div id="empty-message" style="color:red;"></div>';
	echo '</td>';
	echo '<label for="auto-projects">';
	_e( 'Find Projects by Title then click on "RELATE SELECTED PROJECTS"', 'cares' );
	echo '</label> ';
	echo '<td><div id="pending-projects" style="margin-bottom:20px"></div>';
	echo '<button type="button" name="associate-projects" id="associate-projects" class="button-primary" value="RELATE SELECTED PROJECTS" style="display:none;">RELATE SELECTED PROJECTS!</button>';
	echo '</td><td style="vertical-align:top;"><div class="project-result"></div></td>';
	
	echo '</table> ';
}

/**
 * Renders the View or Delete Related Projects content.
 */
function cares_related_view_delete_meta_box_callback( $post ) {

	// Add an nonce field so we can check for it later.
	wp_nonce_field( 'cares_profile_project_meta_box', 'cares_profile_project_meta_nonce' );
	
	//$this_post_id = $post->ID;
	$post_title = get_the_title( $this_post_id );
	
	//query for array of project ids associated with this project
	$postids = get_post_meta( $post->ID, 'related_projects', false );
	
	//echo sizeof($postids);
	//echo print_r( $postids );
	// error handling: if meta field exists and is null
	if ( !( $postids[0] == null ) )
		$num_projects = sizeof( $postids[0] );
	else $num_projects = 0;
	
	//echo $postids[0][0];
	//$postids = $postids[0];
	//setup wp_query args
	
	//9July2014 - not cooperating, whhhyyyy
	/*$args = array(
		'post__in'=> $postids
	);
	
	//run the query
	$project_query = new WP_Query( $args );
	$results = array();
	*/
	
	
	//Now, display the Projects and checkboxes for removal
	echo '<table class="form-table widget"><tr>';
	echo ' <td style="width:100%">';
	echo '<label for="related-projects">';
	_e( '<p>Remove project-post association by selecting the Project below, then clicking "REMOVE ASSOCIATION". (Note: will not delete Project)</p>', 'cares' );
	echo '</label> ';	
	
	//if ( $project_query->have_posts() ) {
	if ( $num_projects > 0 ) {
		
		//echo 'Total Number of Projects Related to <b>' . $post_title . ': ' . $project_query->found_posts . '</b>';
		echo '<p>Total Number of Projects Related to <b>' . $post_title . ' Project: ' . $num_projects . '</b></p><br />';
		
		//cycle through and add checkboxes with related id for removal if necessary
		/*while ( $project_query->have_posts() ) {
			$project_query->the_post();
			$project_id = $project_query->post->ID;
		*/
		
		//for ($i = 0; $i < $num_projects; $i++ ) { 
		foreach ( $postids[0] as $postid ) { ?> 
			<p><input type="checkbox" name="remove-projects-checkboxes[]" class="remove-projects-checkboxes" value="<?php echo $postid; ?>" > &nbsp; <a href='<?php echo get_edit_post_link( $postid );?>'>
			<?php echo get_the_title( $postid ); ?></a></p>
			
		<?php } //end while/for ?>
		<br />
		<button type="button" name="remove-projects" id="remove-projects" class="button-primary" value="REMOVE SELECTED PROJECTS" style="display:none;">REMOVE ASSOCIATION</button>
		<br />
	<?php } else {
		// no posts found
		$noposts = '<div id="empty-message" style="color:red;margin-top:12px;font-weight:bold;">No Related Projects associated with this Post</div>';
		echo $noposts;
	}
	
	echo '</td></table>';
}





/**
 * Mel doesn't know if we need this here..
 *
 * @since  0.1.0
 * @access public
 * @param  int     $post_id
 * @param  object  $post
 * @return void
 */
function cares_related_project_meta_box_save( $post_id, $post ) {

	if ( !isset( $_POST['cares_profile_project_meta_nonce'] ) || !wp_verify_nonce( $_POST['cares_profile_project_meta_nonce'], 'cares_profile_project_meta_box' ) )
		return;


}

 
/**
 * Sets up custom columns on the post admin list screen.
 *
 * @since  0.1.0
 * @access public
 * @param  array  $columns
 * @return array
 */
function cares_edit_posts_columns( $columns ) {

	$new_columns = array(
		'featured' => __( 'Featured', 'cares' ),
		'sticky' => __( 'Sticky', 'custom-content-portfolio-extras' )
	);

	return array_merge( $columns, $new_columns );
	
}

/**
 * Displays the content of posts columns on the admin list screen.
 *
 * @since  0.1.0
 * @access public
 * @param  string  $column
 * @param  int     $post_id
 * @return void
 */
function cares_manage_posts_columns( $column, $post_id ) {
	global $post;

	switch( $column ) {

		case 'featured' :
			echo get_post_meta( $post_id, 'post_feature', true );
			break;
		case 'sticky' :
			echo get_post_meta( $post_id, 'post_sticky', true );
			break;
		/* Just break out of the switch statement for everything else. */
		default :
			break;
	}
}

/**
 * Add custom fields to the Meta box provided by the portfolio plugin
 *
 * @since  0.1.0
 * @access public
 * @return void
 */
function cares_post_meta_box( ) {

	$screens = array( 'post' ); //maybe add page later, or other CPT

	foreach ( $screens as $screen ) {

		add_meta_box(
			'cares-post-featured',
			__( 'Featured on Home Page?', 'cares' ),
			'cares_post_meta_box_callback',
			$screen,
			'side'
		);
	}
}

/**
 * Prints the box content.
 * 
 * @param WP_Post $post The object for the current post/page.
 */
function cares_post_meta_box_callback( $post ) {

	// Add an nonce field so we can check for it later.
	wp_nonce_field( 'cares_post_meta_box', 'cares_post_meta_nonce' );
	?>
		<br />
		<div style="margin-top:1em;">
			<input type="checkbox" name="cares-post-featured" id="cares-post-featured" value="1" <?php checked( get_post_meta( $post->ID, 'post_feature', true ), 'yes' ); ?> />
			<label for="cares-post-featured" ><?php _e( 'Featured - top of home page', 'cares' ); ?></label>
		</div>
		
		<div style="margin-top:1em;">
			<input type="checkbox" name="cares-post-sticky" id="cares-post-sticky" value="1" <?php checked( get_post_meta( $post->ID, 'post_sticky', true ), 'yes' ); ?> />
			<label for="cares-post-sticky" ><?php _e( 'Sticky - stays at top of Recent Projects on Homepage', 'cares' ); ?></label>
		</div>
		
		
	<?php
	
	/*
	 * Use get_post_meta() to retrieve an existing value
	 * from the database and use the value for the form.
	 */
	/*$value = get_post_meta( $post->ID, 'post_feature', true );
	if ( $value == "yes" ) { $checked = 'checked=checked'; } else { $checked = ''; }
	echo '<input type="checkbox" id="cares-post-featured" name="cares-post-featured" value="1"' . $checked . '" />';
	echo '<label for="cares-post-featured">';
	_e( 'Featured', 'cares' );
	echo '</label> ';*/
}


/**
 * Saves the metadata for the portfolio item info meta box.
 *
 * @since  0.1.0
 * @access public
 * @param  int     $post_id
 * @param  object  $post
 * @return void
 */
function cares_post_meta_box_save( $post_id, $post ) {

	if ( !isset( $_POST['cares_post_meta_nonce'] ) || !wp_verify_nonce( $_POST['cares_post_meta_nonce'], 'cares_post_meta_box' ) )
		return;

	$meta = array(
		'post_feature' => isset( $_POST['cares-post-featured'] ) ? 'yes' : 'no', // We can't use true/false for this value because we want to be able to sort by it, and WP won't include value=false in an orderby
		'portfolio_item_sticky' => isset( $_POST['ccp-portfolio-sticky'] ) ? 'yes' : 'no' 
	);

	foreach ( $meta as $meta_key => $new_meta_value ) {

		/* Get the meta value of the custom field key. */
		$meta_value = get_post_meta( $post_id, $meta_key, true );

		/* If there is no new meta value but an old value exists, delete it. */
		if ( current_user_can( 'delete_post_meta', $post_id, $meta_key ) && '' == $new_meta_value && $meta_value )
			delete_post_meta( $post_id, $meta_key, $meta_value );

		/* If a new meta value was added and there was no previous value, add it. */
		elseif ( current_user_can( 'add_post_meta', $post_id, $meta_key ) && $new_meta_value && '' == $meta_value )
			add_post_meta( $post_id, $meta_key, $new_meta_value, true );

		/* If the new meta value does not match the old value, update it. */
		elseif ( current_user_can( 'edit_post_meta', $post_id, $meta_key ) && $new_meta_value && $new_meta_value != $meta_value )
			update_post_meta( $post_id, $meta_key, $new_meta_value );
	}

}

