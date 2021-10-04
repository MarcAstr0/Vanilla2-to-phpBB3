<?php
/********************************************************************************
|  This Conversion Script requires the ADODB Databse access library to be 
|  used as the DB-Access object, as well as working installation of phpBB3.  
|  The script must be run from within a directory within the root of the 
|  phpBB3 forum.
|  Additionally, the phpBB3 installation must have the "Alternate Logins" 
|  modification for third-party sign-ins.
********************************************************************************/

// Get "common" database object for Vanilla database.
include( "./common.php" );


//Start PHPBB's API stuff.
define('IN_PHPBB', true);

// Set scope for variables required later 
global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template;
$phpEx = substr(strrchr(__FILE__, '.'), 1);
$phpbb_root_path = '../';  //dirname( __FILE__ ) . DIRECTORY_SEPARATOR;

// Include all the libraries etc. required from phpBB3 and set up initial session data required to use the API.
require($phpbb_root_path ."common.php");
$user->session_begin();
$auth->acl($user->data);

require_once($phpbb_root_path . "includes/functions." . $phpEx );
require_once($phpbb_root_path . "includes/functions_user." . $phpEx );
require_once($phpbb_root_path . "includes/functions_posting." . $phpEx );
require_once($phpbb_root_path . "includes/functions_privmsgs." . $phpEx );
require_once($phpbb_root_path . "includes/functions_display." . $phpEx);
require_once($phpbb_root_path . "includes/functions_convert." . $phpEx);
require_once($phpbb_root_path . "includes/functions_admin." . $phpEx);

//Define some things in case we don't already have "includes/constants.php"
if( !defined( 'USER_NORMAL' ) ) 
{    define('USER_NORMAL',   0);  }

if( !defined( 'USER_INACTIVE' ) )
{    define('USER_INACTIVE', 1);  }

//define('USER_IGNORE',   2);
//define('USER_FOUNDER',  3);

/**************************************************************************
|  Adds New User to PHPBB3 database.
**************************************************************************/
function addNewUser( $userName, $email, $password, $usr_type, $regDate, $lastActive, $regIP )
{
    global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template;
    
    $user_actkey = md5(rand(0, 100) . time());
    $user_actkey = substr($user_actkey, 0, rand(6, 10));
    
    /* All the user data (I think you can set other database fields aswell, these seem to be required )*/
    $user_row = array(
      'username'        => $userName,
      'user_password'   => $password, //Inserted directly to database, must be pre-hased. Use phpbb_hash("TestPassword")
      'user_email'      => $email,
      'group_id'        => 2, //Registered User Group.
      'user_timezone'   => '1.00',
      'user_lang'       => 'en',
      'user_type'       => $usr_type, //NORMAL_USER 0
      'user_actkey'     => $user_actkey,
      'user_dateformat' => 'd M Y H:i',
      'user_style'      => 1,  // Board first style, or whatever we are using.
      'user_regdate'    => $regDate,
      'user_lastvisit'  => $lastActive,
      'user_ip'         => $regIP,
    );

    /* Now Register user */
    $phpbb_user_id = user_add($user_row);
    
	//pass back id #.
    return $phpbb_user_id;
}

/**************************************************************************
|  Checks for Email or Username errors with account info.
**************************************************************************/
function checkInvalidUser( $username, $email )
{
    $err = '';

    $valid_email = phpbb_validate_email($email);
    if( $valid_email ) 
    {
        $err .= $valid_email .".\n";
    }
    
    $valid_username = validate_username($username);
    if( $valid_username ) 
    {
        $err .= $valid_username .".\n";
    }

    if( $err == '' )
    {    $out = array( true, $err ); }
    else
    {    $out = array( false, 'Errors: '.$err ); }

    return $out;
}

/**************************************************************************
|  Adds New "Forum" to PHPBB3 database. This function handles moving categories form vanilla.
**************************************************************************/
function addNewForum( $fName, $fDescription, $fNumPosts, $fNumThreads )
{
    global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template;
    
	//Build basic data array.
    $forumName = $fName;
    $data = array(
       'forum_name'    => $forumName
    );

    // Check if fourm is already defined.
    $sql = 'SELECT forum_id
          FROM ' . FORUMS_TABLE . '
          WHERE ' . $db->sql_build_array('SELECT', $data);
    $result = $db->sql_query($sql);

    $forum_id = (int) $db->sql_fetchfield('forum_id');
    $db->sql_freeresult($result);

	// If the forum existed, don't create a new one.
    if ($forum_id) 
    {
       return false;
    }
    else 
    {
		//define array to create new forum.
        $forum_data = array(
           'parent_id'                =>   1,
           'left_id'                  =>   0,
           'right_id'                 =>   0,
           'forum_parents'            =>   '',
           'forum_name'               =>   $forumName,
           'forum_desc'               =>   $fDescription,
           'forum_desc_bitfield'      =>   '',
           'forum_desc_options'       =>   7,
           'forum_desc_uid'           =>   '',
           'forum_link'               =>   '',
           'forum_password'           =>   '',
           'forum_style'              =>   0,
           'forum_image'              =>   '',
           'forum_rules'              =>   '',
           'forum_rules_link'         =>   '',
           'forum_rules_bitfield'     =>   '',
           'forum_rules_options'      =>   7,
           'forum_rules_uid'          =>   '',
           'forum_topics_per_page'    =>   0,
           'forum_type'               =>   1,
           'forum_status'             =>   0,
           'forum_last_post_id'       =>   0,
           'forum_last_poster_id'     =>   0,
           'forum_last_post_subject'  =>   '',
           'forum_last_post_time'     =>   0,
           'forum_last_poster_name'   =>   '',
           'forum_last_poster_colour' =>   '',
           'forum_flags'              =>   32,
           'display_on_index'         =>   false,           
           'enable_indexing'          =>   true,
           'enable_icons'             =>   true,               
           'enable_prune'             =>   false,
           'prune_next'               =>   0,
           'prune_days'               =>   0,                       
           'prune_viewed'             =>   0,                   
           'prune_freq'               =>   0                       
       );
       
	   // Set the forum to nest in the order that they are inserted.
       $sql = 'SELECT MAX(right_id) AS right_id
                FROM ' . FORUMS_TABLE;
       $result = $db->sql_query($sql);
       $row = $db->sql_fetchrow($result);
       $db->sql_freeresult($result); 
       
       $forum_data['left_id'] = $row['right_id'] + 1;
       $forum_data['right_id'] = $row['right_id'] + 2;
       
       // Finally, insert the data to the forum.
       $sql = 'INSERT INTO ' . FORUMS_TABLE . ' ' . $db->sql_build_array('INSERT', $forum_data);
       $db->sql_query($sql);
       
	   //get forum ID number.
       $forum_data['forum_id'] = $db->sql_nextid();
       
       $forum_perm_from = 1; //Copy permissions from the first forum.

       // Copy permisisons from/to the acl users table (only forum_id gets changed)
       $sql = 'SELECT user_id, auth_option_id, auth_role_id, auth_setting
          FROM ' . ACL_USERS_TABLE . '
          WHERE forum_id = ' . $forum_perm_from;
       $result = $db->sql_query($sql);

       $users_sql_ary = array();
       while ($row = $db->sql_fetchrow($result))
       {
          $users_sql_ary[] = array(
             'user_id'         => (int) $row['user_id'],
             'forum_id'         => $forum_data['forum_id'],
             'auth_option_id'   => (int) $row['auth_option_id'],
             'auth_role_id'      => (int) $row['auth_role_id'],
             'auth_setting'      => (int) $row['auth_setting']
          );
       }
       $db->sql_freeresult($result);


       // Copy permisisons from/to the acl groups table (only forum_id gets changed)
       $sql = 'SELECT group_id, auth_option_id, auth_role_id, auth_setting
          FROM ' . ACL_GROUPS_TABLE . '
          WHERE forum_id = ' . $forum_perm_from;
       $result = $db->sql_query($sql);

       $groups_sql_ary = array();
       while ($row = $db->sql_fetchrow($result))
       {
          $groups_sql_ary[] = array(
             'group_id'         => (int) $row['group_id'],
             'forum_id'         => $forum_data['forum_id'],
             'auth_option_id'   => (int) $row['auth_option_id'],
             'auth_role_id'      => (int) $row['auth_role_id'],
             'auth_setting'      => (int) $row['auth_setting']
          );
       }
       $db->sql_freeresult($result);

       // Now insert the new ACL data
       $db->sql_multi_insert(ACL_USERS_TABLE, $users_sql_ary);
       $db->sql_multi_insert(ACL_GROUPS_TABLE, $groups_sql_ary);
       
	   //reset caches.
       cache_moderators();
       $auth->acl_clear_prefetch();
       
	   //pass back forum id #
       return $forum_data['forum_id'];
    }
}

/**************************************************************************
|  Adds a new Topic or a new Post to PHPBB3 database.
|
|     When creating a topic, be sure to set the $TopicID field, as well as
|  the other three optional fields.
**************************************************************************/
function addTopicPost( $subject, $message, $forumID, $topicID, $userID, $userName, $postTime, $postType=POST_NORMAL, $closed=0, $tPosters=array() )
{
    global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template;
    
    // note that multibyte support is enabled here 
    $subject = utf8_normalize_nfc( $subject );
    $message = utf8_normalize_nfc( $message );
    
    // variables to hold the parameters for submit_post
    $poll = $uid = $bitfield = $options = ''; 
    
    generate_text_for_storage($subject, $uid, $bitfield, $options, false, false, false);
    generate_text_for_storage($message, $uid, $bitfield, $options, true, true, true);
    
    $data = array( 
        // General Posting Settings
        'forum_id'           => $forumID,  // The forum ID in which the post will be placed. (int)
        'topic_id'           => $topicID,  // Post a new topic or in an existing one? Set to 0 to create a new one, if not, specify your topic ID here instead.
        'icon_id'            => false,     // The Icon ID in which the post will be displayed with on the viewforum, set to false for icon_id. (int)
    
           // Defining Post Options
        'enable_bbcode'      => true,    // Enable BBcode in this post. (bool)
        'enable_smilies'     => true,    // Enabe smilies in this post. (bool)
        'enable_urls'        => true,    // Enable self-parsing URL links in this post. (bool)
        'enable_sig'         => true,    // Enable the signature of the poster to be displayed in the post. (bool)
    
        // Message Body
        'message'            => $message,        // Your text you wish to have submitted. It should pass through generate_text_for_storage() before this. (string)
        'message_md5'        => md5($message),// The md5 hash of your message
    
        // Values from generate_text_for_storage()
        'bbcode_bitfield'    => $bitfield,    // Value created from the generate_text_for_storage() function.
        'bbcode_uid'         => $uid,        // Value created from the generate_text_for_storage() function.
    
        // Other Options
        'post_edit_locked'   => 0,        // Disallow post editing? 1 = Yes, 0 = No
        'topic_title'        => $subject,    // Subject/Title of the topic. (string)
    
        // Email Notification Settings
        'notify_set'         => false,        // (bool)
        'notify'             => false,        // (bool)
        'post_time'          => $postTime,        // Set a specific time, use 0 to let submit_post() take care of getting the proper time (int)
        'topic_time_limit'   => 0,
        'forum_name'         => '',        // For identifying the name of the forum in a notification email. (string)
    
        // Indexing
        'enable_indexing'    => true,        // Allow indexing the post? (bool)
    
        // 3.0.6
        'force_approved_state'    => true, // Allow the post to be submitted without going into unapproved queue
    );
    
    submit_post('post', $subject, '', $postType, $poll, $data);
    
    //Right here we want to reset the user id who posted, so it reflects the original poster ID.
    
    //Get the Topic ID to use in queries and return from this function.
    $tsql = 'SELECT topic_id FROM ' . POSTS_TABLE . ' WHERE post_id = '. $data['post_id'];
    $result = $db->sql_query( $tsql );
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result); 
    unset($tsql);
    $topic_id = $row['topic_id'];
    
	// If this is a topic post, and not a comment-post, re-set poster information that was auto-populated by the API function.
    if( $topicID == 0 )
    {
        $userName = $db->sql_escape( $userName );
        
        $lastPosterName = $db->sql_escape( $tPosters['lp_name'] );
        $lastPosterID   = ( empty($tPosters['lp_id']) ) ? 0 : $tPosters['lp_id'] ;
        $lastPostTime   = $tPosters['lp_time'];
        
        $nameColor = '105289';
        
        $set = '`topic_poster`='. $userID .', `topic_time`='. $postTime .', 
				`topic_first_poster_name`=\''. $userName .'\', `topic_first_poster_colour`=\''. $nameColor .'\',  
        		`topic_last_poster_id`='. $lastPosterID .', `topic_last_poster_name`=\''. $lastPosterName .'\', 
				`topic_last_poster_colour`=\''. $nameColor .'\', `topic_last_post_time`='. $lastPostTime ;
        
        if( $closed == true )
        {    $set .= ', `topic_status`=1 '; }
        
        $sql ='UPDATE ' . TOPICS_TABLE . ' SET ' . $set . ' WHERE `topic_id` = ' . $topic_id ;
        $db->sql_query($sql);
    }
    else
    {    $db->sql_query( 'DELETE FROM '. TOPICS_TABLE .' WHERE `topic_id`='. $topic_id ); }
    
	// Check if this post is a comment which belongs to an existing thread, if so reset the post-topic-id, to include this post in the thread.
    $setTopic='';
    if( $topicID != 0 )
    {    $setTopic = ', `topic_id`='.$topicID;  }
    
    $sql = 'UPDATE '. POSTS_TABLE .' SET `poster_id`='. $userID .', `post_time`='. $postTime . $setTopic .'
            WHERE post_id = '. $data['post_id'] ;
    $db->sql_query( $sql );
    
    if( isset($topic_id) )
    {
        return $topic_id;
    }
}

/**************************************************************************
|  Adds New Private Message to PHPBB3 database.
**************************************************************************/
function addPrivMsg( $addrList, $fromID, $fromName, $fromIP, $message, $title, $postTime, $root_level_id=0)
{
    global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template;
    
    // note that multibyte support is enabled here 
    $my_subject = utf8_normalize_nfc( $title   );
    $my_text    = utf8_normalize_nfc( $message );
    
    // variables to hold the parameters for submit_pm
    $poll = $uid = $bitfield = $options = ''; 
    generate_text_for_storage($my_subject, $uid, $bitfield, $options, false, false, false);
    generate_text_for_storage($my_text, $uid, $bitfield, $options, true, true, true);
    
    $data = array( 
        'address_list'      => $addrList, // array ('u' => array(2 => 'to')),
        'from_user_id'      => $fromID,
        'from_username'     => $fromName,
        'icon_id'           => 0,
        'from_user_ip'      => $fromIP, //$user->data['user_ip'],
         
        'enable_bbcode'     => true,
        'enable_smilies'    => true,
        'enable_urls'       => true,
        'enable_sig'        => true,
    
        'message'           => $my_text,
        'bbcode_bitfield'   => $bitfield,
        'bbcode_uid'        => $uid,
    );
    
    $put_in_outbox = true;
    
    submit_pm('post', $my_subject, $data, $put_in_outbox);
    
    // Update the message post time and root-level data.
	$sql = 'UPDATE '. PRIVMSGS_TABLE .' 
			SET `message_time`='. $postTime .', `root_level`='.$root_level_id.' 
			WHERE `msg_id`='. $data['msg_id'];
    $db->sql_query( $sql );
	
	// Update message read/new status to avoid "nostalgia-overload"
	//$sql = 'UPDATE '. PRIVMSGS_TO_TABLE .' 
	//		SET `pm_new`= 0, `pm_unread`= 0 
	//		WHERE `msg_id`='. $data['msg_id'];
	//$db->sql_query( $sql );
    
	return $data['msg_id'];
    
}

/**************************************************************************
|  Re-connects the Users with their post-counts via a passed post-count-map.
**************************************************************************/
function correctUserPostCounts( &$countAr )
{
    global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template;
    
    foreach( $countAr as $uid => $postCount )
    {
        $sql = 'UPDATE ' . USERS_TABLE . ' SET `user_posts`=' . $postCount . '
                WHERE user_id = ' . $uid ;
        $db->sql_query( $sql );
    }
}

/**************************************************************************
|  Re-connects Threads/Topics with their real post-counds.
**************************************************************************/
function correctThreadReplyCounts( &$countAr )
{
    global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template;
    
    foreach( $countAr as $tid => $postCount )
    {
        $sql = 'UPDATE ' . TOPICS_TABLE . ' SET `topic_posts_approved`='. $postCount .'
                WHERE topic_id = ' . $tid ;
        $db->sql_query( $sql );
    }
}

/**************************************************************************
|  Re-connects forum/categories with their real post/comment counts.
**************************************************************************/
function correctCategoryPostCounts( &$countAr )
{
    global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template;
    
    foreach( $countAr as $fid => $postCount )
    {
        $sql = 'UPDATE ' . FORUMS_TABLE . ' SET `forum_posts_approved`=' . $postCount . '
                WHERE forum_id = ' . $fid ;
        $db->sql_query( $sql );
    }
}

/**************************************************************************
|  Re-connects forum/categories with their real thread/topic counts.
**************************************************************************/
function correctCategoryThreadCounts( &$countAr )
{
    global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template;
    
    foreach( $countAr as $fid => $postCount )
    {
        $sql = 'UPDATE ' . FORUMS_TABLE . ' SET `forum_topics_approved` = '. $postCount .'
                WHERE forum_id = ' . $fid ;
        $db->sql_query( $sql );
    }
}


/* Function:  html2bbcode
*  Description:  Attempts to reverse basic html into suitable BBCode.
*  Arguments: 
*   1)  $html             -  string   -  Text containing HTML to be converted to BBCode.
*   2)  $removeTagScraps  -  boolean  -  Seting to TRUE enables striping left over tags from converted code. False disables this allowing HTML fragments. 
*/
function html2bbcode( $html, $removeTagScraps=true )
{
    //Basic array of conversions. 
    $bbcode = array( 
        "<(b|strong)>"       => "[b]",
        "<(/b|/strong)>"     => "[/b]",
        
        "<(s|strike)>"       => "[s]",
        "</(s|strike)>"      => "[/s]",
        
        "<(i|em)>"           => "[i]",
        "</(i|em)>"          => "[/i]",
        
        "<(u|underline)>"    => "[u]",
        "</(u|underline)>"   => "[/u]",
        
        "<blockquote>"       => "[quote]",
        "<blockquote rel=[\"']([^\"']+)[\"']>"       => "[quote=&quot;$1&quot;]",
        "</blockquote>"      => "[/quote]",
        
        "<img [^>]*src=[\"']([^>\"']+)[\"'][^>]*>"        => "[img]$1[/img]",
        
        "<div><br></div>"   => "\n\r",
        "<br\s*/?>"         => "\n\r",
        "<br>"              => "\n\r",
        "</div>"            => "\n\r", 
        "</p>"              => "\n\r",
		
		"&nbsp;"            => " ",
    );
    
    // Replace the basic tags looping the above pattern array over the content.
    foreach( $bbcode as $f => $r )
    {
        $html = preg_replace( "#". $f ."#is", $r, $html );
    }
    
    // Find all formatted links and convert them to the bbcode [url] tags.
    preg_match_all( "#<a([^>]+)>([^>]+)</a>#i", $html, $m );
    $i=0;
    foreach( $m[0] as $match )
    {
        if( preg_match( '#href=["\']([^>\'"]+)["\']#i', $m[1][$i], $tagm ) )
        {
            $html = str_replace( $match, '[url='. $tagm[1] .']'. $m[2][$i] .'[/url]', $html );
        }
        
        $i++;
    }
    
    // Find all embeded objects of type Object/Embed and convert this to the default [flash] tag used in phpbb3.
    // patterns are based on the bellow code, as it was fairly common.  
    //<object width="560" height="315"><param name="movie" value="http://www.youtube.com/v/bKRxDP--e-Y?version=3&amp;hl=ru_RU"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/bKRxDP--e-Y?version=3&amp;hl=ru_RU" type="application/x-shockwave-flash" width="560" height="315" allowscriptaccess="always" allowfullscreen="true"></embed></object>
    preg_match_all( '#<object([^>]+)>((\s*</?[^>]+/?>\s*)*)</object>#is', $html, $m );
    $i=0;
    foreach( $m[0] as $match )
    {
        if( preg_match( '#value=[\'"]([^>]+youtube[^>\'"]+)[\'"]#i', $m[2][$i], $tagm ) )
        {
            $tagm[1] = str_replace( 'embed', 'v', $tagm[1] );
			$tagm[1] = str_replace( 'watch', 'v', $tagm[1] );
			
			$html = str_replace( $match, '[flash=560,315]'. $tagm[1] .'[/flash]', $html );
        }
        $i++;
    }
    
    // Like the above pattern loop, this finds and convers the youtube iframe tags to the [flash] tags based on the following code commonly found in posts:
    //<iframe src="http://www.youtube.com/embed/UrAgb1-UKQ8" allowfullscreen="" frameborder="0" height="225" width="400"></iframe><br />
    preg_match_all( '#<iframe([^>]+)></iframe>#i', $html, $m );
    $i=0;
    foreach( $m[0] as $match )
    {
        if( preg_match( '#src=[\'"]([^>]+youtube[^>\'"]+)[\'"]#i', $m[1][$i], $tagm ) )
        {
            $tagm[1] = str_replace( 'embed', 'v', $tagm[1] );
			$tagm[1] = str_replace( 'watch', 'v', $tagm[1] );
			
			$html = str_replace( $match, '[flash=560,315]'. $tagm[1] .'[/flash]', $html );
        }
        $i++;
    }
    
    // Find the vanilla-specific font encoding and create the complementary color code for it.
    preg_match_all( '#<font([^>]+)>([^>]+)</font>#i', $html, $m );
    $i=0;
    foreach( $m[0] as $match )
    {
        if( preg_match( '#color=["\']([^>\'"]+)["\']#i', $m[1][$i], $tagm ) )
        {
            $html = str_replace( $match, '[color='. $tagm[1] .']'. $m[2][$i] .'[/color]', $html );
        }
        
        $i++;
    }
    
    if( $removeTagScraps == true )
    {
        $html = utf8_encode( strip_tags( $html ) );
        return $html;
    }
    else
    {
        return $html;
    }
}
 
 
/**************************************************************************
|  This class contains all the proceedures and variables to gather data from 
|  Vanilla (ver 2.0.18.4) translate it, and using stand-alone functions and 
|  the phpBB3 API to insert Users, categories, topics, and posts to an 
|  existing phpBB3 databas.
**************************************************************************/   
class van2phpbb
{
    // Map variables for Categories.
	private $mapCats_vn2bb       = array();
    private $mapCats_vnID2Name   = array();
    private $mapCats_postCount   = array();
	private $mapCats_threadCount = array();
    
	// Map variables for Users.
    private $mapUsrs_vn2bb       = array();
    private $mapUsrs_vnID2Name   = array();
    private $mapUsrs_postCount   = array();
    
	// Map variables for Threads.
    private $mapThreads_vnID2Name   = array();
    private $mapThreads_vn2bb       = array();
    private $mapThreads_topic2cat   = array();
    private $mapThreads_postCount   = array();
	private $mapThreads_postCopied  = array();
	
	// Map variables for Posts/Comments.
	private $mapPosts_postCopied    = array();
	
	// Map variables for PM's.
	private $mapPrivMsgs_vn2bb  = array();
	
	// Buffer to store debug and other output until the end of the script.
    private $outputBuffer = '';
	
	// Database access object variable.
    private $vdb = NULL;
    
	// Debug and testing control variables.
    private $sqlLimit      = ''; // LIMIT 0,1 ';
	private $execTimeStart = 0;
	private $execTimeEnd   = 0;  
	
	
	/**************************************************************************
	|  Main entry(object constructor) function, code starts running here. 
	**************************************************************************/
    public function van2phpbb()
    {
        // Import the Vanilla database object set up in the "common.php" file.
		global $vdb;
        $this->vdb = $vdb;
        
		// Update script execution time, and tracking.
        set_time_limit( 180 ); 
		ini_set('memory_limit','128M');  //Should be much more than enough to convert any board.
		$this->execTimeStart = microtime( true );
		
		//conditions for converting third-party sign-on-IDs alone.
		if( isset($_GET['uids']) )
		{
			if( file_exists( './cache.Users.dat' ) )
        	{
            	$dat = unserialize( file_get_contents( './cache.Users.dat' ) );
            	
            	$this->mapUsrs_vn2bb = $dat['vn2bb'];
            	$this->mapUsrs_vnID2Name = $dat['ID2Name'];
				
				$this->reconnectUserIDs();
        	}
			
			// Calculate total running time.
			$this->execTimeEnd = microtime( true );
			$execTotalTime = $this->execTimeEnd - $this->execTimeStart;
			
			echo "<strong>Total Time Taken:</strong> &nbsp; {$execTotalTime}<br />\n<br />\n";
			
			exit();
		}
		
		//conditions for converting PM's alone.
		if( isset($_GET['msgs']) )
		{
			if( file_exists( './cache.Users.dat' ) )
        	{
            	$dat = unserialize( file_get_contents( './cache.Users.dat' ) );
            	
            	$this->mapUsrs_vn2bb = $dat['vn2bb'];
            	$this->mapUsrs_vnID2Name = $dat['ID2Name'];
				
				$this->migrateUserMessages(  );
        	}
			
			// Calculate total running time.
			$this->execTimeEnd = microtime( true );
			$execTotalTime = $this->execTimeEnd - $this->execTimeStart;
			
			echo "<strong>Total Time Taken:</strong> &nbsp; {$execTotalTime}<br />\n<br />\n";
			
			exit();
		}
		
        
        //==========================[ Migrate Users ]==================================
		// Check for cached data from a previous conversion run.
        if( file_exists( './cache.Users.dat' ) )
        {
            $dat = unserialize( file_get_contents( './cache.Users.dat' ) );
            
            $this->mapUsrs_vn2bb = $dat['vn2bb'];
            $this->mapUsrs_vnID2Name = $dat['ID2Name'];
        }
        else
        {
            $this->migrateUsers();
			$this->reconnectUserIDs(); 
            
            $dat = array( 'vn2bb' => $this->mapUsrs_vn2bb,  'ID2Name' => $this->mapUsrs_vnID2Name );
            file_put_contents( './cache.Users.dat', serialize( $dat ) );
            unset( $dat );
        }
        
        //==========================[ Categories ]==================================
        // Check for cached Category/Forum data.
		if( file_exists( './cache.Cats.dat' ) )
        {
            $dat = unserialize( file_get_contents( './cache.Cats.dat' ) );
            
            $this->mapCats_vn2bb = $dat['vn2bb'];
            $this->mapCats_vnID2Name = $dat['ID2Name'];
        }
        else
        {
            $this->migrateCategories();
            
            $dat = array( 'vn2bb' => $this->mapCats_vn2bb,  'ID2Name' => $this->mapCats_vnID2Name );
            file_put_contents( './cache.Cats.dat', serialize( $dat ) );
            unset( $dat );
        }
        
        //==========================[ Threads ]==================================
		// Check for cached Threads/Topic data.
        if( file_exists( './cache.Threads.dat' ) )
        {
            $dat = unserialize( file_get_contents( './cache.Threads.dat' ) );
            
            $this->mapThreads_vn2bb = $dat['vn2bb'];
            $this->mapThreads_vnID2Name = $dat['ID2Name'];
        }
        else
        {
            $this->migrateThreads();
            
            $dat = array( 'vn2bb' => $this->mapThreads_vn2bb,  'ID2Name' => $this->mapThreads_vnID2Name );
            file_put_contents( './cache.Threads.dat', serialize( $dat ) );
            unset( $dat );
        }
        
        //==========================[  Posts ]==================================
		// Move thread Posts.  This step must be done here, otherwise data-maps will not be correctly populated.
        $this->migratePosts();
        
        //==================[ Post Count Corrections ]============================
		// Update all comment, reply and thread counts accross the forum.
        correctUserPostCounts( $this->mapUsrs_postCount );
        
        correctCategoryPostCounts( $this->mapCats_postCount );
		
		correctCategoryThreadCounts( $this->mapCats_threadCount );
        
        correctThreadReplyCounts( $this->mapThreads_postCount );
		
        
        //==========================[ Private Messages ]==================================
		// Finally, we move PMs.  All required data-maps should still be in place.
        $this->migrateUserMessages();
		
		
		// Calculate total running time.
		$this->execTimeEnd = microtime( true );
		$execTotalTime = $this->execTimeEnd - $this->execTimeStart;
		
		echo "<strong>Total Time Taken:</strong> &nbsp; {$execTotalTime}<br />\n<br />\n";
		
        //==========================[ Output Errors ]==================================
        echo $this->outputBuffer;
    }
    
	/************************************************************************************
	|  Function to collect user data from Vanilla, and carry out conversion to phpBB3. 
	************************************************************************************/
    private function migrateUsers()
    {
        $rs = $this->vdb->Execute( "SELECT `UserID`, `Name`, `Password`, `Email`, 
                                     `CountDiscussions`, 
                                     UNIX_TIMESTAMP(`DateLastActive`) AS `DateLastActive`,
                                     UNIX_TIMESTAMP(`DateFirstVisit`) AS `DateFirstVisit`, 
                                     `LastIPAddress`, `DateOfBirth`, `HashMethod`, `Banned`
                                   FROM `GDN_User`
                                   WHERE `Deleted` = 0 
								   ORDER BY `DateFirstVisit` ASC " . $this->sqlLimit );
        
        if( $rs->RecordCount() > 0 )
        {
            $err_users=0; $c=0;
            $emailList = $acctErrorList = '';
            foreach( $rs as $usr )
            {
                // Check for user accounts we aren't using or won't migrate, 
				// as well as reject accounts from spam-domains.
				if( empty($usr['Name']) || 
                    $usr['Name'] == 'admin' || $usr['Name'] == 'Anonymous' || 
                    preg_match('#.*@foo\.com#is', $usr['Email']) 
                )
                {    continue; }
                
				// Manually map the admin account, so admin of this name/ID 
				// can continue updating forum settings after conversion. 
                if( $usr['Name'] == 'WebWizard' )
                {
                    $this->mapUsrs_vnID2Name[  $usr['UserID'] ] = "WebWizard";
                    $this->mapUsrs_vn2bb[ $usr['UserID'] ] = 2;
                    continue;
                }
                
				// Re-encode string data, and check the user name and email for validity.
                $usr['Name'] = utf8_encode( $usr['Name'] );
                $ck = checkInvalidUser( $usr['Name'], $usr['Email'] );
				
				// If user name and email are valid, carry out insertion of user.
                if( $ck[0] == true )
                {
                    $cln_name  = str_replace( ' ', '_', $usr['Name']);
                    $regDate   = $usr['DateFirstVisit'] ; 
                    $lastVisit = $usr['DateLastActive'] ;
                    $ut = USER_NORMAL; //$ut = ( $usr['Banned'] == 0 ) ? USER_NORMAL : USER_INACTIVE ;
                
                    $bbID = addNewUser( $usr['Name'], $usr['Email'], $usr['Password'], $ut, $regDate, $lastVisit, $usr['LastIPAddress'] );
                    
                    if( $bbID )
                    {
                        $this->mapUsrs_vnID2Name[  $usr['UserID'] ] = $usr['Name'];
                        $this->mapUsrs_vn2bb[ $usr['UserID'] ] = $bbID; 
                    }
                }
                else // Log debug information.
                {
                    $this->outputBuffer .= "Invalid User: ". $usr['Name'] ." (". $usr['Email'] .")<br />\n &nbsp; --> ". $ck[1] ."<br />\n<br />\n";
                    $acctErrorList .= $usr['Name'] .' &lt;'.$usr['Email'] . "&gt;, ";
                }
                
				// Make a list of users with password hashes invalid in phpBB3.
                //if( strtolower($usr['HashMethod']) == 'random' )
                //{
                //    $err_users++;
                //    $emailList .= $usr['Email'] . ", ";
                //}

                //Regularly increase the execution times to prevent script-timeout-failure.
                if( $c % 10 == 0 )
                {
                    set_time_limit( 15 );
                }
                $c++;
            }
            
			if( $err_users != 0 )
			{
	            $this->outputBuffer .= '<strong>Total Users With Bad Passwords:</strong>  '. $err_users . 
				"<br />\n<strong>Email List:</strong><br />\n<br />\n{$emailList}<br />\n<br />\n";
			}
            $this->outputBuffer .= "<strong>Users With Account Issues:</strong><br />\n<br />\n{$acctErrorList}<br />\n<br />\n";
        }
        
        
    }
	
	/***************************************************************************************************************
	|  Function to connect User Authentication data from Vanilla to the required "Alternate Logins" modification. 
	***************************************************************************************************************/
	private function reconnectUserIDs()
	{
		global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template;
		
		foreach( $this->mapUsrs_vn2bb as $vnID => $bbID )
		{
			$rs = $this->vdb->Execute( 'SELECT `ForeignUserKey`, `ProviderKey` FROM `GDN_UserAuthentication` WHERE `UserID` = '. $vnID );
			
			if( $rs->RecordCount() > 0 )
			{
				foreach( $rs as $usrAuth )
				{
					$fbID = $wlID = $twID = $oiID = 0;
					
					switch( strtolower( $usrAuth['ProviderKey'] ) )
					{
					case 'facebook':
						$fbID = $db->sql_escape($usrAuth['ForeignUserKey']);
					break;
					
					case 'twitter':
						$twID = $db->sql_escape($usrAuth['ForeignUserKey']);
					break;
					
					case 'openid':
						$oiID = $db->sql_escape($usrAuth['ForeignUserKey']);
					break;	
					
					//cases to skip, as they deal with internal vanilla hashing or JS-connect settings from wordpress.
					case 'kdfe04a6c11d62b8c92dec2cdfa1bbcb67d54c598':
					case 'spirit-science':
						$x=0; // just doing some nothing here....
					break;
					
					default:
						$this->outputBuffer .= "<strong>ExtID Error</strong> VNID: {$vnID}  |  BBID: {$bbID}<br />\n";
					break;
					}
				}
				if($fbID != 0 || $twID != 0 || $oiID != 0) {
					$userinfo = $this->vdb->Execute('SELECT `Name` FROM `GDN_User` WHERE `UserID` = ' . $vnID);
					$userrow = $userinfo->GetRowAssoc();
					$username = $userrow['Name'];
					print "User: {$username} (vnID: {$vnID} / bbID {$bbID}) has social logins fbID: {$fbID} twID: {$twID} oiID: {$oiID} NOT IMPORTED!<br />";
				}
				//al_fb_id 	al_wl_id 	al_tw_id 	al_oi_id
// 				$sql = 'UPDATE ' . USERS_TABLE . ' SET `al_fb_id`=\''. $fbID .'\', `al_tw_id`=\''. $twID .'\', `al_oi_id`=\''. $oiID .'\'
//                         WHERE user_id = ' . $bbID ;
// 				$db->sql_query( $sql );
			}
		}
	}
    
	/*********************************************************************************************
	|  Function to collect Category/Forum data from Vanilla, and carry out conversion to phpBB3. 
	*********************************************************************************************/
    private function migrateCategories()
    {
        $rs = $this->vdb->Execute( 'SELECT `CategoryID`, `Name`, `Description`, `CountDiscussions`, `CountComments` 
                              FROM `GDN_Category` 
                              ORDER BY `Sort`' . $this->sqlLimit );
        
        if( $rs->RecordCount() > 0 )
        {
            $c=0;
            foreach( $rs as $cat )
            {
                // Check for catgories that aren't categories, or shouldn't need to be moved.
			    if( strtolower($cat['Name']) == 'root' )
                {    continue; }
                
				// Add the catagory to the forum.
                $fID = addNewForum( $cat['Name'], $cat['Description'], $cat['CountComments'], $cat['CountDiscussions'] );
                
				// Update all data-maps.
                if( $fID )
				{
					$this->mapCats_vnID2Name[ $cat['CategoryID'] ] = $cat['Name'];
                	$this->mapCats_vn2bb[ $cat['CategoryID'] ] = $fID;
				}
				else
				{	$this->outputBuffer = "<strong>Category Map Error: </strong> No ID returned for category: {$cat['Name']} <br />\n\n";  }
				
                //$this->outputBuffer .= "<strong>Moved Category:</strong> {$cat['Name']} (BBID: {$fID})<br />\n<br />\n";
                
                if( $c % 10 == 0 )
                {
                    //flush();
                    //ob_flush();
                    
                    set_time_limit( 15 );
                }
                $c++;
            }
        }
    }
    
	/*********************************************************************************************
	|  Function to collect Thread/Topic data from Vanilla, and carry out conversion to phpBB3. 
	**********************************************************************************************/
    private function migrateThreads()
    {
        $rs = $this->vdb->Execute( "SELECT `CategoryID`, `DiscussionID`, `InsertUserID`, `Name`, `Body`, `CountComments`,
                                      `CountViews`, `LastCommentUserID`, `Announce`, `Closed`,
                                      UNIX_TIMESTAMP(`DateLastComment`) AS `DateLastComment`,
                                      UNIX_TIMESTAMP(`DateInserted`) AS `DateInserted`
                                    FROM `GDN_Discussion` 
                                    WHERE `Format` <> 'Deleted' 
									ORDER BY `DateInserted` ASC " . $this->sqlLimit  );
                                    
        if( $rs->RecordCount() > 0 )
        {
            $c=0;
            foreach( $rs as $thread )
            {
				// Check for data-mapping issues and fetch the correct insert ID's.
                if( isset($this->mapThreads_postCopied[ $thread['DiscussionID'] ]) )
				{	continue; }
				else
				{	$this->mapThreads_postCopied[ $thread['DiscussionID'] ] = $thread['DiscussionID'];  }
				
				if( isset( $this->mapCats_vn2bb[ $thread['CategoryID'] ] ) )
                {    $newCatID = $this->mapCats_vn2bb[ $thread['CategoryID'] ];  }
                else
                {    $this->outputBuffer .= "<strong>CatMap Error:</strong> ID: {$thread['CategoryID']} <br />\n";  continue;  }
                
                if( isset( $this->mapUsrs_vn2bb[ $thread['InsertUserID'] ] ) )
                {    $newUsrID = $this->mapUsrs_vn2bb[ $thread['InsertUserID'] ];  }
                else
                {    $this->outputBuffer .= "<strong>UsrMap Error:</strong> ID: {$thread['InsertUserID']} <br />\n";  continue;  }
                
                if( isset( $this->mapUsrs_vnID2Name[ $thread['InsertUserID'] ] ) )
                {    $userName = utf8_encode( $this->mapUsrs_vnID2Name[ $thread['InsertUserID'] ] );  }
                else
                {    $this->outputBuffer .= "<strong>UsrMap Error:</strong> ID: {$thread['InsertUserID']} <br />\n";  continue;  }
                
                // If no issues are found, continue to format and insert the Topic post.
                if( !empty($newCatID) && !empty($newUsrID) && !empty($userName) )
                {
                    $body = html2bbcode( $thread['Body'] );
                    if( empty($body) )
                    {    $this->outputBuffer .= "<strong>PostBody Error:</strong> ID: {$thread['DiscussionID']} <br />\n";  continue;  }
                    
                    if( $thread['Announce'] == 1 )
                    {    $postType = POST_ANNOUNCE;  }
                    else
                    {    $postType = POST_NORMAL;  }
                    
                    if( isset($this->mapUsrs_vnID2Name[ $thread['LastCommentUserID'] ]) )
                    {    $lpName = $this->mapUsrs_vnID2Name[ $thread['LastCommentUserID'] ];  }
                    else
                    {    $lpName = ''; }
                    
					// Format "Last Post(er)" data for addition to this thread.
                    $tPosterAr = array('lp_name' => utf8_encode( $lpName ),
                                       'lp_id'   => $thread['LastCommentUserID'],
                                       'lp_time' => (empty($thread['DateLastComment'])) ? $thread['DateInserted'] : $thread['DateLastComment'] );
                    
                    // Run statement to insert new thread at $newCatID relating to user of $newUsrID.
                    $tID = addTopicPost( $thread['Name'], $body, $newCatID, 0, $newUsrID, $userName, $thread['DateInserted'], $postType, $thread['Closed'], $tPosterAr );
                    // Update all data maps.
                    $this->mapThreads_vnID2Name[ $thread['DiscussionID'] ] = $thread['Name'];
                    $this->mapThreads_topic2cat[ $thread['DiscussionID'] ] = $thread['CategoryID'];
                    $this->mapThreads_vn2bb[ $thread['DiscussionID'] ] = $tID;
                    
                    if( isset($this->mapUsrs_postCount[ $newUsrID ]) )
                    {    $this->mapUsrs_postCount[ $newUsrID ] += 1 ;  }
                    else
                    {    $this->mapUsrs_postCount[ $newUsrID ] = 1;  }
                    
                    if( isset($this->mapCats_postCount[ $newCatID ]) )
                    {    $this->mapCats_postCount[ $newCatID ] += 1 ;  }
                    else
                    {    $this->mapCats_postCount[ $newCatID ] = 1;  }
					
					if( isset($this->mapCats_threadCount[ $newCatID ]) )
                    {    $this->mapCats_threadCount[ $newCatID ] += 1 ;  }
                    else
                    {    $this->mapCats_threadCount[ $newCatID ] = 1;  }
                    
                    if( isset($this->mapThreads_postCount[ $tID ]) )
                    {    $this->mapThreads_postCount[ $tID ] += 1 ;  }
                    else
                    {    $this->mapThreads_postCount[ $tID ] = 1;  }
                    
					// Debuging output info.
                    //$this->outputBuffer .= "<strong>Moved Thread:</strong> {$thread['Name']} (BBID: {$tID}) <br />\n<br />\n";
                    
                    if( $c % 10 == 0 )
                    {
                        //flush();
                        //ob_flush();
                        
                        set_time_limit( 15 );
                    }
                    $c++;
                }
                else
                {    $this->outputBuffer .= '<strong>ERROR!!</strong> - CatID: '. $newCatID .' UserID: '. $newUsrID . "<br />\n<br />\n";  }
            }
        }
    }
    
	/*********************************************************************************************
	|  Function to collect Post/Comment data from Vanilla, and carry out conversion to phpBB3. 
	**********************************************************************************************/
    private function migratePosts( $testMode=false )
    {
        //Get the posts from the database.
        $rs = $this->vdb->Execute( "SELECT `CommentID`, `DiscussionID`, `InsertUserID`, `Body`, UNIX_TIMESTAMP(`DateInserted`) AS `DateInserted`, `DateUpdated`
                              FROM `GDN_Comment` 
                              WHERE `Format` <> 'Deleted' 
							  ORDER BY `DateInserted` ASC " . $this->sqlLimit );
                              
        if( $rs->RecordCount() > 0 )
        {
            //Allows direct output of HTML post after passing through conversion.
            if( $testMode == false )
            {
                // Loop 
                
                $this->outputBuffer .= "<br />\n<strong>Moving Thread Posts</strong><br />\n";
                $c=0;
                foreach( $rs as $post )
                {
                    // Test to see if we've moved this post id before, and if so stop it.
					if( isset($this->mapPosts_postCopied[ $post['CommentID'] ]))
					{	continue; }
					else
					{	$this->mapPosts_postCopied[ $post['CommentID'] ] = $post['CommentID'];  }
					
					// Ensure we don't have issues with users and categories not currently in the map.
                    if( isset( $this->mapThreads_topic2cat[ $post['DiscussionID'] ] ) )
                    {    $newCatID = $this->mapThreads_topic2cat[ $post['DiscussionID'] ];  }
                    else
                    {    $this->outputBuffer .= "<strong>CatMap Error:</strong> ID: {$post['DiscussionID']} <br />\n";  continue;  }
                    
                    if( isset( $this->mapUsrs_vn2bb[ $post['InsertUserID'] ] ) )
                    {    $newUsrID = $this->mapUsrs_vn2bb[ $post['InsertUserID'] ];  }
                    else
                    {    $this->outputBuffer .= "<strong>UsrMap Error:</strong> ID: {$post['InsertUserID']} <br />\n";  continue;  }
                    
                    if( isset( $this->mapThreads_vn2bb[ $post['DiscussionID'] ] ) )
                    {    $newTopicID = $this->mapThreads_vn2bb[ $post['DiscussionID'] ];  }
                    else
                    {    $this->outputBuffer .= "<strong>TopicMap Error:</strong> ID: {$post['DiscussionID']} <br />\n";  continue;  }
                    
                    if( isset( $this->mapUsrs_vnID2Name[ $post['InsertUserID'] ] ) )
                    {    $userName = utf8_encode( $this->mapUsrs_vnID2Name[ $post['InsertUserID'] ] );  }
                    else
                    {    $this->outputBuffer .= "<strong>UsrMap Error:</strong> ID: {$post['InsertUserID']} <br />\n";  continue;  }
                    
                    if( isset($this->mapThreads_vnID2Name[ $post['DiscussionID'] ]) )
                    {    $postTitle = strip_tags( $this->mapThreads_vnID2Name[ $post['DiscussionID'] ] );  }
                    else
                    {    $this->outputBuffer .= "<strong>TopicMap Error:</strong> ID: {$post['DiscussionID']} <br />\n";  continue;  }
                    
					// If we have the map-variables are valid, continue with the post setup.
                    if( !empty($newCatID) && !empty($newUsrID) && !empty($userName) && !empty($postTitle) )
                    {
                        //Convert the html post body.
                        $body = html2bbcode( $post['Body'] );
                        if( empty($body) )
                        {    $this->outputBuffer .= "<strong>PostBody Error:</strong> ID: {$post['DiscussionID']} <br />\n";  continue;  }
                        
                        //Update all count-maps to ensure accurate post counts.
                        if( isset($this->mapUsrs_postCount[ $newUsrID ]) )
                        {    $this->mapUsrs_postCount[ $newUsrID ] += 1 ;  }
                        else
                        {    $this->mapUsrs_postCount[ $newUsrID ] = 1;  }
                        
                        if( isset($this->mapCats_postCount[ $newCatID ]) )
                        {    $this->mapCats_postCount[ $newCatID ] += 1 ;  }
                        else
                        {    $this->mapCats_postCount[ $newCatID ] = 1;  }
                        
                        if( isset($this->mapThreads_postCount[ $newTopicID ]) )
                        {    $this->mapThreads_postCount[ $newTopicID ] += 1 ;  }
                        else
                        {    $this->mapThreads_postCount[ $newTopicID ] = 1;  }
                        
                        //run statement to insert new thread at $newCatID relating to user of $newUsrID.
                        //This instance also requires the $newTopicID to insert to the correct thread.
                        addTopicPost( $postTitle, $body, $newCatID, $newTopicID, $newUsrID, $userName, $post['DateInserted'] );
                    }
                    else
                    {    $this->outputBuffer .= "<strong>Post Error:</strong> CatID: {$newCatID} | UsrID: {$newUsrID} | UsrName: {$userName} | Title: {$postTitle} <br />\n";  continue;  }
                    
                    //Update clock.
                    if( $c % 10 == 0 )
                    {
                        set_time_limit( 45 );
                    }
                    $c++;
                }
            }
            else
            {
                // Like the code above, but without any database activity; strictly convert and view the code.
                
                $c=0;  $out='';
                $buffer = array();
                foreach( $rs as $post )
                {
                    $body = html2bbcode( $post['Body'], false );
                    $body = htmlentities( $body, ENT_QUOTES );
                    
                    $out .= '<div style="padding: 4px; border: 1px solid black; background: rgb(187, 187, 187); width: 800px; position: relative; display: inline-block;">
                    <pre style="white-space: pre-line;">'.
                    $body
                    .'</pre>
                    </div><br /><hr /><br />';
                    /**/
                    
                    if( $c % 10 == 0 )
                    {
                        print $out;
                        $out='';
                        
                        @flush();
                        @ob_flush();
                        @flush();
                        
                        set_time_limit( 15 );
                    }
                    $c++;
                }
                print $out;
                @flush();
                @ob_flush();
                @flush();
            }
        }
    }
    
	/*********************************************************************************************
	|  Function to collect Private Message data from Vanilla, and carry out conversion to phpBB3. 
	**********************************************************************************************/
    private function migrateUserMessages( $forceUID=NULL )
    {
        // Using the User-Map, get the PM's sent from each user.
		foreach( $this->mapUsrs_vn2bb as $vnID => $bbID )
		{
			/*$rs = $this->vdb->Execute( "SELECT  cm.`MessageID`, `Subject`, `Contributors`,
            	                        UNIX_TIMESTAMP(  cm.`DateInserted` ) AS `DateInserted`,
                	                    `Body`, `Format`, cm.`InsertUserID`, cm.`InsertIPAddress`,
										`FirstMessageID`
                        	            FROM `GDN_Conversation` AS c
                            	        INNER JOIN `GDN_ConversationMessage` AS cm 
                                	    ON c.`ConversationID` = cm.`ConversationID` 
                                    	WHERE c.`ConversationID` <> 0 
										ORDER BY cm.`DateInserted` ASC " . $this->sqlLimit  ); /**/
			
			//testing switch to only convert messages from a single user via VN_ID #.
			if( is_numeric( $forceUID ) && $forceUID != $vnID )
			{	continue; }
			
			// Get all messages for the current user.
			$rs = $this->vdb->Execute( "SELECT cm.`MessageID`, `Subject`, `Contributors`,
                                    	UNIX_TIMESTAMP(  cm.`DateInserted` ) AS `DateInserted`,
                                    	`Body`, `Format`, cm.`InsertUserID`, cm.`InsertIPAddress`,
										`FirstMessageID`
										FROM `GDN_ConversationMessage` AS cm 
										JOIN `GDN_Conversation` AS c 
										ON cm.`ConversationID` = c.`ConversationID`
										WHERE cm.`InsertUserID` = {$vnID} AND c.`ConversationID` <> 0 
										ORDER BY cm.`DateInserted` ASC " . $this->sqlLimit  );
                              
	        if( $rs->RecordCount() > 0 )
	        {
    	        $c=0;
    	        foreach( $rs as $msg )
    	        {
    	             // Test to see if we've moved this post id before, and if so halt a duplicate insert.
					if( isset($this->mapPrivMsgs_vn2bb[ $msg['MessageID'] ]))
					{	continue; }
					else
					{	$this->mapPrivMsgs_vn2bb[ $msg['MessageID'] ] = 0 ; }
					
					// Check that the user is mapped..  given the loop to migrate this shouldn't be necisary here; but just in case...
					if( !isset($this->mapUsrs_vn2bb[ $msg['InsertUserID'] ]) || !isset($this->mapUsrs_vnID2Name[ $msg['InsertUserID'] ]) )
    	            {    $this->outputBuffer .= "<strong>PrivMsg Error:</strong> ID: {$msg['InsertUserID']} <br />\n<br />\n";  continue;  }
    	            
					// Format the insertion data.
    	            $fromID   = $this->mapUsrs_vn2bb[ $msg['InsertUserID'] ];
    	            $fromName = utf8_encode( $this->mapUsrs_vnID2Name[ $msg['InsertUserID'] ] );
    	            $fromIP   = ( empty($msg['InsertIPAddress']) ) ? '127.0.0.1' : $msg['InsertIPAddress'] ;
					$rootLevelID = 0;
    	            
					// Format message title, create one if none logged.
					$title = ( empty($msg['Subject']) ) ? 'Message From '. $fromName : strip_tags( $msg['Subject'] ) ;
    	            
					// Prepend "Re: " if message has a FirstMessageID or "root_level" ID in BB3.
					if( $msg['FirstMessageID'] != 0 && $this->mapPrivMsgs_vn2bb[ $msg['FirstMessageID'] ] != 0 &&
					    isset( $this->mapPrivMsgs_vn2bb[ $msg['FirstMessageID'] ] ) )
					{
						$title = 'Re: ' . $title;
						$rootLevelID = $this->mapPrivMsgs_vn2bb[ $msg['FirstMessageID'] ];
					}
					
					// Format message Body.
					$body  = html2bbcode( $msg['Body'], true );
                	
					//Make an address list..
    	            $to = unserialize( $msg['Contributors'] );
    	            $addrList = array( 'u' => array() );
    	            $addrListError = false;
    	            foreach( $to as $idx => $vID )
    	            {
						$addrListError = false;
						$vID = intval( $vID );
   		                
						// Check if user is in the map list, if not the message is invalid.
						// But instead of breaking the message, just don't add the user to the address list.
						//  this could save us some trouble with messages not being moved.
    	                if( isset($this->mapUsrs_vn2bb[ $vID ]) )
    	                {
							//Get users BB3 ID.
    	                	$bID = $this->mapUsrs_vn2bb[ $vID ];
    	                	
							//Ensure we don't send a message to the sender.
    	                	if( $bID == $bbID )
    	                	{    continue;  }
    	                
							// Add the receiving user to the address array.
    	                	$addrList['u'][ $bID ] = 'to';
						}
						else
						{
							$addrListError = true;
						}
    	            }
    	            
					// If the message has an address issue, then report it and attempt to send the message anyays..
    	            if( $addrListError == true )
    	            {    $this->outputBuffer .= "<strong>PrivMsg Address Error:</strong> ID: {$msg['InsertUserID']} <br />\n<br />\n"; }
    	            
					// If their are no users in the address list, discard this message.
					if( count( $addrList['u'] ) <= 0 )
					{	continue;  }
					
					// Insert the new PM
    	            $pmID = addPrivMsg( $addrList, $fromID, $fromName, $fromIP, $body, $title, $msg['DateInserted'], $rootLevelID );
    	            
					// Update the message map array.
					$this->mapPrivMsgs_vn2bb[ $msg['MessageID'] ] = $pmID;
					
    	            if( $c % 10 == 0 )
    	            {
    	                set_time_limit( 45 );
    	            }
    	            $c++;
    	        }
			}
        }
    }
}

// Debug code used to quickly add a test user to the PHPBB database.
// addNewUser( 'TestUser', 'testuser@gmail.com', 'as6d5fa4sdf45asd6g5a4g', USER_NORMAL, time(), time(), '127.0.0.1' );

// Output mode switch.  By default the conversion code runs, but passing ?mkCode=1 in the URI will output a 
// pre-formated HTML-translation of this code file for re-pasting on the net. Using ?mkCode=2 will output a
// color-highlighted version of the code.
if( isset($_GET['mkCode']) )
{
    $code = file_get_contents( __FILE__ );
	
	if( $_GET['mkCode'] == 1 )
	{
		$code = '<pre style="white-space: pre-wrap;">'. htmlentities( $code, ENT_QUOTES ) . '</pre>';
	}
	elseif( $_GET['mkCode'] == 2 )
	{
		$code = highlight_string( $code, true );
	}
	
    echo '<div style="padding: 4px; border: 1px solid black; background:#DFDFDF; width: 650px; height: 220px; position: relative; display: inline-block; overflow: scroll;">'.
	 $code .'</div>';

}
else
{
    ob_start();
    $v2b = new van2phpbb();
}

?>
