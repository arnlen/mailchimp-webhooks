<?php

/**
 * Plugin Name:       MailChimp WebHooks (mcwh)
 * Plugin URI:        http://example.com/plugin-name-uri/
 * Description:       Allows WordPress users to be updated with MailChimp subscribe/unsubscribe actions
 * Version:           1.0.0
 * Author:            Chris Knowles
 * Author URI:        http://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       mcwh
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

function mcwh_init(){
	
	$mcwh_settings = get_option( 'mcwh_settings' );
	
	/* if global option mcwh_settings doesn't exist then create it */
	if ( $mcwh_settings == false ) {
	
		add_option( 'mcwh_settings', array('hard_subscribe' => false, 'hard_unsubscribe' => false, 'webhook_key' => 'ybwoybbaseemc2') );	
		
		$mcwh_settings = get_option( 'mcwh_settings' ); 
	}
	
	/* hooks for plugin options */
	add_action( 'admin_menu', 'mcwh_admin_menu' );
	add_action( 'admin_init', 'mcwh_settings_init' );	

	/* hook for creating webhook endpoints */
	add_action( 'init', 'mcwh_endpoint' );
	add_action( 'parse_request', 'mcwh_parse_request' );

	/* add hooks for showing _newsletter_subscriber value in profile */
	add_action( 'show_user_profile', 'mcwh_show_user_profile' );
	add_action( 'edit_user_profile', 'mcwh_show_user_profile' );
	
}


function mcwh_admin_menu() { 
	
	add_options_page( 'MailChimp Webhooks', 'MailChimp Webhooks', 'manage_options', 'mailchimp_webhooks', 'mcwh_options_page' );
} 


function mcwh_settings_init() { 

	register_setting( 'mcwh', 'mcwh_settings' , 'mcwh_settings_check' );
	
	add_settings_section(
		'mcwh_section', 
		__( 'Control the behavior associated with MailChimp webhooks', 'mailchimp_webhooks' ), 
		'mcwh_section_callback', 
		'mcwh'
	);
	
	add_settings_field( 
		'hard_subscribe', 
		__( 'Create new user on subscribe?', 'mailchimp_webhooks' ), 
		'mcwh_hard_subscribe_render', 
		'mcwh', 
		'mcwh_section' 
	);

	add_settings_field( 
		'hard_unsubscribe', 
		__( 'Delete user on unsubscribe?', 'mailchimp_webhooks' ), 
		'mcwh_hard_unsubscribe_render', 
		'mcwh', 
		'mcwh_section' 
	);

	add_settings_field( 
		'webhook_key', 
		__( 'Specify a unique webhook key', 'mailchimp_webhooks' ), 
		'mcwh_webhook_key_render', 
		'mcwh', 
		'mcwh_section' 
	);

}


function mcwh_endpoint(){

	// access webhook at url such as http://[your site]/mailchimp/webhook
    	// add_rewrite_endpoint( 'webhook', EP_PERMALINK );
    	add_rewrite_rule( 'webhook' , 'index.php?webhook=1', 'top' );
    	add_rewrite_tag( '%webhook%' , '([^&]+)' );

}

function mcwh_parse_request( &$wp )
{
   
    	if ( array_key_exists( 'webhook', $wp->query_vars ) ) {
    
    		echo 'about to action webhook';
        
        	mcwh_action_webhook();
        
        	exit();
    	}
    
}


function mcwh_action_webhook() {

	$mcwh_settings = get_option( 'mcwh_settings' );

	mcwh_log('==================[ Incoming Request ]==================');

	// mcwh_log('Full _REQUEST dump:\n'.print_r($_REQUEST,true)); 
	
	if ( empty($_POST) ) {
		mcwh_log('No request details found.');
		die('No request details found.');
	}

	if ( !isset($_GET['key']) ){
    	mcwh_log('FAILED! No security key specified, ignoring request'); 
    	
	} elseif ($_GET['key'] != $mcwh_settings['webhook_key']) {
	
    	mcwh_log('FAILED: Security key specified, but not correct');
    	// mcwh_log("\t".'Wanted: "'.$webhook_key.'", but received "'.$_GET['key'].'"');
    	
	} else {
    
    		//process the request
    		mcwh_log('Processing a "'.$_POST['type'].'" request for email address ' . $_POST['data']['email'] . '...');
    	
		switch($_POST['type']){
			case 'subscribe'  : mcwh_subscribe($_POST['data']);   break;
			case 'unsubscribe': mcwh_unsubscribe($_POST['data']); break;
			case 'cleaned'    : mcwh_cleaned($_POST['data']);     break;
			case 'upemail'    : mcwh_upemail($_POST['data']);     break;
			case 'profile'    : mcwh_profile($_POST['data']);     break;
			default:
				mcwh_log('Request type "'.$_POST['type'].'" unknown, ignoring.');
		}

	}
	
	mcwh_log('Finished processing request.');		

}


function mcwh_show_user_profile( $user ) { 

	$subscribed = get_user_meta( $user->ID, '_newsletter_subscriber', true );

    	echo '<h3>MailChimp Newsletter</h3>
    		<table class="form-table">
        		<tr>
            			<th><label for="_newsletter_subscriber">Current Subscriber</label></th>
             			<td>';
             			
	echo '<input type="checkbox" name="_newsletter_subscriber" id="_newsletter_subscriber" ';
	echo $subscribed ? "checked" : ""; 
	echo ' disabled/><br />
                				<span class="description">Is this user subscribed to the MailChimp newsletter?</span>
            			</td>
        		</tr>
    		</table>';
    
}

/*
 * activate the plugin
 */
mcwh_init();


/***********************************************
    Helper Functions
***********************************************/

function mcwh_options_page() { 
	?>
	<form action='options.php' method='post'>
		
		<h2>MailChimp Webhooks</h2>
		
		<?php
		settings_fields( 'mcwh' );
		do_settings_sections( 'mcwh' );
		submit_button();
		?>
		
	</form>
	<?php
}


function mcwh_hard_subscribe_render() {

	$mcwh_settings = get_option( 'mcwh_settings' );

	?>
	<input type="checkbox" name="mcwh_settings[hard_subscribe]" <?php echo $mcwh_settings['hard_subscribe']? 'checked':''; ?> value="1">
	<?php
}


function mcwh_hard_unsubscribe_render() {

	$mcwh_settings = get_option( 'mcwh_settings' );

	?>
	<input type="checkbox" name="mcwh_settings[hard_unsubscribe]" <?php echo $mcwh_settings['hard_unsubscribe']? 'checked':''; ?> value="1">
	<?php
}


function mcwh_webhook_key_render() {

	$mcwh_settings = get_option( 'mcwh_settings' );
	
	?>
	<input type='text' name='mcwh_settings[webhook_key]' value='<?php echo $mcwh_settings['webhook_key']; ?>' >
	<p>Your Webhook URL is: <?php echo get_option('siteurl') . '/webhook.php?key=' . $mcwh_settings['webhook_key']; ?></p>
	<?php
}


function mcwh_section_callback() { 

	echo __( '<p></p>', 'mailchimp_webhooks' );
	echo '<p><a href="' . plugins_url('webhook.log', __FILE__) . '" target="_blank">View the webhook log</a></p>';
}


function mcwh_settings_check($settings){

	$new_settings['webhook_key'] = $settings['webhook_key'];

	if ( $settings['hard_subscribe'] ) {
		$new_settings['hard_subscribe'] = 1;
	} else {
		$new_settings['hard_subscribe'] = 0;
	}
	
	if ( $settings['hard_unsubscribe'] ) {
		$new_settings['hard_unsubscribe'] = 1;
	} else {
		$new_settings['hard_unsubscribe'] = 0;
	}
	
	return $new_settings;

}

/* Handles a subscribe notification from MailChimp
 * If hard_unsubscribe is true then a new user will be created if it doesn't already exist
 * User meta _newsletter_subscribe is always set to 1 
 */              
function mcwh_subscribe($data){

	$mcwh_settings = get_option( 'mcwh_settings' );

	$user_email = $data['email'];

	/* get existing user record */
    	$thisuser = get_user_by( 'email', $user_email );

   	/* if new user... */
    	if ( $thisuser === false ) 
    	
    		/* if hard_subscribe then create new user record */
    		if ( $mcwh_settings['hard_subscribe'] ) {

    			$userdata = array(
    				'user_pass' 	=> wp_generate_password( $length=12, $include_standard_special_chars=false ),
    				'user_login' 	=> $data['id'],
    				'user_email' 	=> $user_email,
    				'first_name' 	=> $data['merges']['FNAME'],
    				'last_name' 	=> $data['merges']['LNAME'],
    				'role' 		=> 'subscriber'
    			);
    		
    			$user_id = wp_insert_user( $userdata );
    
    			if ( !is_wp_error( $user_id ) ) {
    		
    				mcwh_log( 'SUBSCRIBE: Created new user [ ' . $user_email . ' ]' );
    			
    			} else {
    		
    				mcwh_log( 'SUBSCRIBE: FAILED! Problem encountered trying to create new user [ ' . $user_email . ' ]' );
    				return;
    			}
    			
    		} else {
    			
    			/* if no user found and not creating accounts then exit */
    			mcwh_log( 'SUBSCRIBE: FAILED! No user found with this email address and hard_(un)subscribe is false' );
    			return;

    	} else {

    		mcwh_log( 'SUBSCRIBE: Existing user found with this email address  ' . $user_email );
    		$user_id = $thisuser->ID;
	}

	/* update the user meta regardless - if hard_unsubscribe just won't be shown on the user profile */
	$subscribed = update_user_meta( $user_id, '_newsletter_subscriber', 1, 0 );
	
	mcwh_log( 'SUBSCRIBE: ' . ( $subscribed ? 'SUCCESS! User subscribed' : 'FAILED! User already subscribed' ) );  
}


/* Handles an unsubscribe notification from MailChimp
 * If hard_unsubscribe is true then user will be deleted, otherwise user meta _newsletter_subscribe is set to 0 
 */ 
function mcwh_unsubscribe($data){

	$mcwh_settings = get_option( 'mcwh_settings' );
	
	$user_email = $data['email'];

	/* get existing user */
    	$thisuser = get_user_by( 'email', $user_email );
    

	/* if user exists then unsubscribe */
    	if( $thisuser ) {
    	
    		/* if hard unsubscribe then delete the user */
    		if ( $mcwh_settings['hard_unsubscribe'] ) {
    
    			/* have to include this file to get access to wp_delete_user function */
    			require_once(ABSPATH.'wp-admin/includes/user.php' );
    
    			wp_delete_user( $thisuser->ID );

    			mcwh_log( 'UNSUBSCRIBE: SUCCESS! ' . $user_email . ' deleted (hard unsubscribe) ');
    		
    		} else {
    		
    			/* soft unsubscribe - just change _newsletter_subscriber meta to 0 */
    			$unsubscribed = update_user_meta( $thisuser->ID, '_newsletter_subscriber', 0, 1 );
			mcwh_log( 'UNSUBSCRIBE: ' . ( $unsubscribed ? 'SUCCESS! User unsubscribed' : 'FAILED: User already unsubscribed' ) );  
    		
    		}
    
    	} else {
    
    		mcwh_log ( 'UNSUBSCRIBE: FAILED! User with email address ' . $user_email . ' does not exist. Cannot unsubscribe.' );
    	}
    	
}

function mchw_cleaned($data){
    mcwh_log($data['email'] . ' was cleaned from your list!');
}

function mchw_upemail($data){
    mcwh_log($data['old_email'] . ' changed their email address to '. $data['new_email']. '!');
}

function mchw_profile($data){
    mcwh_log($data['email'] . ' updated their profile!');
}

function mcwh_log($msg){

    	$logfile = plugin_dir_path(__FILE__) . 'webhook.log';
    	file_put_contents($logfile,date("Y-m-d H:i:s")." | ".$msg."\n",FILE_APPEND);
    
    	// echo $msg;
}