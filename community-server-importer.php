<?php
/*
  Plugin Name: Community Server Importer
  Plugin URI: https://github.com/blobaugh/community-server-importer
  Description: Creates an importer under Tools > Import for posts and comments via the Telligent REST API
  Author: Ben Lobaugh
  Version: 0.6
  Author URI: http://ben.lobaugh.net
 */

// 11567
/*
  list activitymessagesapi.ashx/v2/users/{userid}/activities.{format}

  LIST activity messages for a user by user id.listactivitymessagesapi.ashx/v2/users/{username}/activities.{format}
  LIST activity messages for a user by username.createactivitymessagesapi.ashx/v2/users/{userid}/activities.{format}

  CREATE an activity message for a user by user id.createactivitymessagesapi.ashx/v2/users/{username}/activities.{format}
  CREATE an activity message for a user by username.listactivitymessagesapi.ashx/v2/groups/{groupid}/activities.{format}

  LIST activity messages for a group.listactivitymessagesapi.ashx/v2/activities.{format}
  LIST sitewide activity messages.listblogcommentsapi.ashx/v2/blogs/{blogid}/posts/{blogpostid}/comments.{format}
  LIST blog comments by post.createblogcommentsapi.ashx/v2/blogs/{blogid}/posts/{blogpostid}/comments.{format}

  CREATE a new comment for a blog post.updateblogcommentsapi.ashx/v2/blogs/{blogid}/posts/{blogpostid}/comments/{id}.{format}

  UPDATE a comment for a blog post.deleteblogcommentsapi.ashx/v2/blogs/{blogid}/posts/{blogpostid}/comments/{id}.{format}

  DELETE a comment for a blog post.showblogpostsapi.ashx/v2/blogs/{blogid}/posts/{id}.{format}

  SHOW a blog post.showblogpostsapi.ashx/v2/blogs/{blogid}/posts/{name}.{format}
  SHOW a blog post.listblogpostsapi.ashx/v2/blogs/{blogid}/posts.{format}

  LIST blog posts.listblogpostsapi.ashx/v2/groups/{groupid}/blogs/posts.{format}
  LIST blog posts.showblogpostsapi.ashx/v2/blogs/{blogid}/posts/{id}/{filename}

  SHOW a blog post file attachment.showblogpostsapi.ashx/v2/blogs/{blogid}/posts/{name}/{filename}
  SHOW a blog post file attachment.createblogpostsapi.ashx/v2/blogs/{blogid}/posts.{format}

  CREATE a blog post.updateblogpostsapi.ashx/v2/blogs/{blogid}/posts/{id}.{format}

  UPDATE a blog post.deleteblogpostsapi.ashx/v2/blogs/{blogid}/posts/{id}.{format}

  DELETE a blog post.listblogsapi.ashx/v2/blogs.{format}

  LIST blogs.
 */

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';


class CommunityServerImporter /* extends WP_Importer */ {

    private $mUser;
    private $mApikey;
    private $mAdminEndpoint = 'admin.php?import=community-server-importer';
    private $mOrigUrl;
    private $mNewUrl;
    private $mApiEntry;
    private $mBlogId;
    private $mAddCats;

    public function __construct() {
	$this->mUser = get_option('community-server-importer-user');
	$this->mApikey = get_option('community-server-importer-apikey');
	$this->mOrigUrl = get_option('community-server-importer-orig-url');
	$this->mNewUrl = get_option('community-server-importer-new-url');
	$this->mApiEntry = get_option('community-server-importer-api-entry');
	$this->mBlogId = get_option('community-server-importer-blog-id');
	$this->mAddCats = get_option('community-server-importer-add-cats');
    }

    public function greet() {
	?>
	<form method="POST" action="<?php echo $_SERVER['REQUEST_URI'] . '&step=2'; ?>">
	    <ul>
		<li><label for="csi_user">User: </label>
		    <input type="text" id="csi_user" name="csi_user" value="<?php echo $this->mUser; ?>" />
		</li>

		<li><label for="csi_apikey">API Key: </label>
		    <input type="text" id="csi_apikey" name="csi_apikey" value="<?php echo $this->mApikey; ?>" />
		</li>
		<li><label for="csi_origurl">Origin URL: </label>
		    <input type="text" id="csi_origurl" name="csi_origurl" value="<?php echo $this->mOrigUrl; ?>" />
		    <br/><i>URL to original blog site. Used to rewrite inter-post links</i>
		</li>
		<li><label for="csi_newurl">New URL: </label>
		    <input type="text" id="csi_newurl" name="csi_newurl" value="<?php echo $this->mNewUrl; ?>" />
		    <br/><i>URL to new blog site. Used to rewrite inter-post links</i>
		</li>
		<li><label for="csi_api_entry">API Entry: </label>
		    <input type="text" id="csi_api_entry" name="csi_api_entry" value="<?php echo $this->mApiEntry; ?>" />
		    <br/><i>URL to the blog's API. Your site admin should know this.</i>
		</li>
		<li><label for="csi_blog_id">Blog ID: </label>
		    <input type="text" id="csi_blog_id" name="csi_blog_id" value="<?php echo $this->mBlogId; ?>" />
		</li>
		<li><label for="csi_add_cats">Add Categories: </label>
		    <input type="text" id="csi_add_cats" name="csi_add_cats" value="<?php echo $this->mAddCats; ?>" />
		    <br/><i>Comma separated list of categories to add to each post.</i>
		</li>
		<li><input type="submit" name="csi_submit" value="Save API Connection Information" class="button-secondary" /></li>
	    </ul>

	</form>

	<hr/>
	<h3>Ready to import?</h3>
	<p>Click below to begin your import. Importing multiple times from the same server should not produce duplicate content.</p>
	<p><form method="POST" action="<?php echo admin_url($this->mAdminEndpoint); ?>&step=2"><input type="submit" name="csi_start" value="Begin Import" class="button-primary" /></form></p>
	    <?php
	}

	function header() {
	    echo '<div class="wrap">';
	    screen_icon();
	    echo '<h2>' . __('Community Server Importer', 'csi') . '</h2>';
	    echo '<div class="narrow">';
	}

	function footer() {
	    echo '</div>';
	    echo '</div>';
	}

	/**
	 * Pulls in all blog posts and comments
	 *
	 * - If PageIndex query param is set use it in API call
	 * - Retrieve blog posts
	 * - If orig-url is set rewrite any URLs
	 */
	function import() {

	    $posts = $this->getPosts();

	    if ($posts) {
		echo '<h3>Imported posts:</h3>';
		echo '<ul>';
		foreach ($posts AS $p) {
		    /*
		     * Use the orig post id to verify that this post has never
		     * before been imported. If it has do not reimport it
		     */
		    $orig_id = $p['Id'];
		    $args = array(
			'meta_key' => 'csi-Id',
			'meta_value' => $p['Id'],
			'post_status' => 'any'
		    );
		    $orig_posts = get_posts($args);

		    if (empty($orig_posts)) {
			// Post has not yet been created. Lets create it now
			$args = array(
			    'post_title' => $p['Title'],
			    'post_name' => sanitize_title_with_dashes($p['Slug']),
			    'post_date' => $this->getTimestamp($p['PublishedDate']),
			    'post_excerpt' => $p['Excerpt'],
			    'post_content' => $this->parseContent($p['Body']),
			    'post_status' => 'publish',
			);

			/*
			 * Add post tags
			 */
			if (!empty($p['Tags'])) {
			    $args['tags_input'] = $this->getTags($p['Tags']);
			}

			/*
			 * Setup the categories we need
			 */
			//get_cat_ID
			$cat_args = array();
			if (!empty($this->mAddCats)) {
			    $cats = explode(',', $this->mAddCats);
			    foreach ($cats AS $c) {
				$cat_id = get_cat_ID($c);
				if (!$cat_id) {
				    // Category did not exist. Create it
				    $cat_id = wp_create_category($c);
				}
				$cat_args[] = $cat_id;
			    }
			}
			$args['post_category'] = $cat_args;


			/*
			 * Setup post author. A new user will be created if the
			 * author does not match an existing user by email
			 */
			$author = $p['Author'];
			$user = get_user_by('email', $author['Username']);
			$user_id = 1;
			if (!$user) {
			    // Create a new user based on the author
			    $name = explode(' ', $author['DisplayName']);

			    $user_args = array(
				'user_login' => $author['Username'],
				'user_email' => $author['Username'],
				'display_name' => $author['DisplayName'],
				'first_name' => $name[0],
				'last_name' => $name[1],
				'user_pass' => uniqid(time(), true),
			    );
			    $user_id = wp_insert_user($user_args);

			    update_user_meta($user_id, 'csi-import-data', $author);
			    update_user_meta($user_id, 'csi-Id', $author['Id']);
			} else {
			    // User already exists, what is their id?
			    $user_id = $user->ID;
			}
			$args['post_author'] = $user_id;


			/*
			 * And FINALLY Insert the POST!!!
			 */
			$post_id = wp_insert_post($args);
			update_post_meta($post_id, 'csi-import-data', $p);
			update_post_meta($post_id, 'csi-BlogId', $p['BlogId']);
			update_post_meta($post_id, 'csi-Id', $p['Id']);

			/*
			 * Things to do after insert
			 */

			// Tell the user what has been inserted
			echo '<li><a href="' . get_permalink($post_id) . '">' . $p['Title'] . '</a></li>';

			if ($p['CommentCount'] > 0) {
			    $this->importComments($post_id, $p['Id']);
			}
		    } // end if empty( $orig_posts )

		} // end foreach( imported posts )
		echo '</ul>';

		$page_index = ( isset($_GET['PageIndex']) ) ? $_GET['PageIndex'] : 1;
		$page_index++;

		$hack = "PageIndex=$page_index";
		?>

	    <script type="text/javascript">
	        <!--
	        window.location = "<?php echo admin_url($this->mAdminEndpoint . '&step=2&' . $hack); ?>"
	        //-->
	    </script>
	    <?php
	} // end if( $posts )
	else {
	    echo '<h3>Import Complete!</p>';
	}
    }

// end function

    /**
     * Handles importing the origin comments into the WordPress site
     *
     * @param Integer $post_id - WP post id
     * @param Integer $orig_post_id Origin post id
     */
    private function importComments($post_id, $orig_post_id) {
	$comments = $this->getComments($orig_post_id);
	if (!empty($comments)) {
	    foreach ($comments AS $c) {
		$args = array(
		    'comment_post_ID' => $post_id,
		    'comment_author' => $c['Author']['DisplayName'],
		    'comment_author_email' => $c['Author']['Username'],
		    'comment_author_url' => $c['Author']['ProfileUrl'],
		    'comment_content' => $c['Body'],
		    'comment_date' => $this->getTimestamp($c['PublishedDate']),
		    'comment_approved' => $c['IsApproved'],
		);


		// Connect comment with user if they exist
		$user = get_user_by('login', $c['Author']['Username']);

		if ($user) {
		   $args['user_id'] = $user->ID;
		}




		//$comment_id = wp_insert_comment($args);
		$comment_id = wp_new_comment($args);

		update_comment_meta($comment_id, 'csi-import-data', $c);
	    } // end foreach
	} // end if !empty
    }

// end function

    /**
     * Creates a clean and usable tag list from the wonky CS output
     *
     * @param Array $nastyTags
     * @return String
     */
    private function getTags($nastyTags) {
	$tags = array();
	foreach ($nastyTags AS $k => $v) {
	    $tags[] = $v['Value'];
	}
	$tags = apply_filters('community-server-importer-tags', $tags);
	return implode(', ', $tags);
    }

    /**
     * Performs maintenence on th content, such as converting inter-blog links
     * from the original domain to the new domain
     *
     * @todo Auto image import
     * @param String $content
     * @return String
     */
    private function parseContent($content) {
	if (!empty($this->mOrigUrl) && !empty($this->mNewUrl)) {
	    //$content = str_replace($this->mOrigUrl, $this->mNewUrl, $content);
	    $pattern = "#({$this->mOrigUrl})(.*)(\.aspx)#i";
	    $replacement = $this->mNewUrl . '$2';
	    $content = preg_replace($pattern, $replacement, $content);
	}
	$content = apply_filters('community-server-importer-content', $content);
	return $content;
    }

    /**
     * Converts the origin post timestamp into a valid timestamp for the post table
     *
     * @param String $nastyTimestamp
     * @return String
     */
    private function getTimestamp($nastyTimestamp) {
	$ts = strtotime($nastyTimestamp);
	$good = date('Y-m-d h:m:s', $ts);
	return $good;
    }

    /**
     * Retrieves the posts from the Community Server API.
     * Can paginate with the PageIndex url variable
     *
     * @return Mixed Array or false
     */
    function getPosts() {
	$creds = base64_encode("{$this->mApikey}:{$this->mUser}");
	// Get list of blog posts
	$args = array(
	    'headers' => array(
		'Rest-User-Token' => $creds
	    )
	);

	$url = trailingslashit($this->mApiEntry) . '/v2/blogs/' . $this->mBlogId . '/posts.json?UseIsoDateFormat=true';
	if (isset($_GET['PageIndex']) && is_numeric($_GET['PageIndex']))
	    $url .= '&PageIndex=' . $_GET['PageIndex'];
	$res = wp_remote_get($url, $args);

	$posts = json_decode(wp_remote_retrieve_body($res), true);

	$pages = floor($posts['TotalCount'] / $posts['PageSize']);

	// Went past the number of pages with posts
	// Community Server will not throw an error so we have to check manually
	if (isset($_GET['PageIndex']) && $_GET['PageIndex'] > $pages)
	    return false;

	if (is_array($posts['BlogPosts']))
	    return $posts['BlogPosts'];
	else
	    return false;
    }

    /**
     * Retrieves a listing of the comments on a post
     *
     * @param type $origPostId
     */
    function getComments($origPostId) {
	$creds = base64_encode("{$this->mApikey}:{$this->mUser}");
	// Get list of blog posts
	$args = array(
	    'headers' => array(
		'Rest-User-Token' => $creds
	    )
	);

	$url = trailingslashit($this->mApiEntry) . '/v2/blogs/' . $this->mBlogId . '/posts/' . $origPostId . '/comments.json?UseIsoDateFormat=true';
	if (isset($_GET['PageIndex']) && is_numeric($_GET['PageIndex']))
	    $url .= '&PageIndex=' . $_GET['PageIndex'];

	$res = wp_remote_get($url, $args);

	$posts = json_decode(wp_remote_retrieve_body($res), true);


	if (is_array($posts['Comments']))
	    return $posts['Comments'];
	else
	    return false;
    }

    /**
     * Handles running all portions of the import process
     */
    public function dispatch() {

	// Save options before moving to any new steps
	if (isset($_POST['csi_submit'])) {
	    update_option('community-server-importer-user', $_POST['csi_user']);
	    update_option('community-server-importer-apikey', $_POST['csi_apikey']);
	    update_option('community-server-importer-orig-url', $_POST['csi_origurl']);
	    update_option('community-server-importer-new-url', $_POST['csi_newurl']);
	    update_option('community-server-importer-api-entry', $_POST['csi_api_entry']);
	    update_option('community-server-importer-blog-id', $_POST['csi_blog_id']);
	    update_option('community-server-importer-add-cats', $_POST['csi_add_cats']);
	}


	// Get the step the import is on. If none start from 1
	if (empty($_GET['step']))
	    $step = 1;
	else
	    $step = (int) $_GET['step'];

	// Pretty header
	$this->header();

	// Farm out work based on steps
	switch ($step) {
	    case 1 :
		$this->greet();
		break;
	    case 2 :
		$this->import();
		break;
	}

	// Pretty footer
	$this->footer();
    }

}

// end class

$community_server_importer = new CommunityServerImporter();
register_importer('community-server-importer', 'Community Server Importer', 'Import posts and comments from the Telligent Communiter Server REST API', array($community_server_importer, 'dispatch')
);