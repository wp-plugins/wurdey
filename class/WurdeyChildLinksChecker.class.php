<?php

class WurdeyChildLinksChecker
{   
    
    public static $instance = null;   
    
    static function Instance() {
        if (WurdeyChildLinksChecker::$instance == null) {
            WurdeyChildLinksChecker::$instance = new WurdeyChildLinksChecker();
        }
        return WurdeyChildLinksChecker::$instance;
    }  
    
    public function __construct() {
        
    }
    
    public function action() {   
        $information = array();
        if (!defined('BLC_ACTIVE')  || !function_exists('blc_init')) {
            $information['error'] = 'NO_BROKENLINKSCHECKER';
            WurdeyHelper::write($information);
        }             
        blc_init();        
        if (isset($_POST['mwp_action'])) {
            switch ($_POST['mwp_action']) {                
                case "set_showhide":
                    $information = $this->set_showhide();                    
                    break;
                case "sync_data":
                    $information = $this->sync_data();                    
                    break;
                case "edit_link":
                    $information = $this->edit_link();                    
                    break; 
                case "unlink":
                    $information = $this->unlink();                    
                    break; 
                case "set_dismiss":
                    $information = $this->set_link_dismissed();                    
                    break;
                case "discard":
                    $information = $this->discard();                    
                    break;          
                case "save_settings":
                    $information = $this->save_settings();                    
                    break; 
                case "force_recheck":
                    $information = $this->force_recheck();                    
                    break; 
            }        
        }
        WurdeyHelper::write($information);
    }  
   
     
    public function init()
    {          
        if (get_option('wurdey_linkschecker_ext_enabled') !== "Y")
            return;
        
        if (get_option('wurdey_linkschecker_hide_plugin') === "hide")
        {
            add_filter('all_plugins', array($this, 'hide_plugin'));               
            add_filter('update_footer', array(&$this, 'update_footer'), 15);   
        }        
    }    
    
    public static function hook_trashed_comment($comment_id){
        if (get_option('wurdey_linkschecker_ext_enabled') !== "Y")
            return;
        
        if (!defined('BLC_ACTIVE')  || !function_exists('blc_init')) 
            return;        
        blc_init();               
        $container = blcContainerHelper::get_container(array('comment', $comment_id));        
        $container->delete();
        blc_cleanup_links();
    }
        
    function save_settings() {
        $information = array();
        $information['result'] = 'NOTCHANGE';  
        $new_check_threshold = intval($_POST['check_threshold']);        
        if( $new_check_threshold > 0 ){
            $conf = blc_get_configuration();
            $conf->options['check_threshold'] = $new_check_threshold;
            if ($conf->save_options())
                $information['result'] = 'SUCCESS';
        }             
        return $information;
    }
    
    function force_recheck() {
        $this->initiate_recheck();
        $information = array();
        $information['result'] = 'SUCCESS';
        return $information;
    }
    
    function initiate_recheck(){
    	global $wpdb; /** @var wpdb $wpdb */

    	//Delete all discovered instances
    	$wpdb->query("TRUNCATE {$wpdb->prefix}blc_instances");
    	
    	//Delete all discovered links
    	$wpdb->query("TRUNCATE {$wpdb->prefix}blc_links");
    	
    	//Mark all posts, custom fields and bookmarks for processing.
    	blc_resynch(true);
    }
       
    
    public static function hook_post_deleted($post_id){
        if (get_option('wurdey_linkschecker_ext_enabled') !== "Y")
            return;
        
        if (!defined('BLC_ACTIVE')  || !function_exists('blc_init')) 
            return;        
        blc_init();   
        
        //Get the container type matching the type of the deleted post
        $post = get_post($post_id);
        if ( !$post ){
                return;
        }
        //Get the associated container object
        $post_container = blcContainerHelper::get_container( array($post->post_type, intval($post_id)) );

        if ( $post_container ){
                //Delete it
                $post_container->delete();
                //Clean up any dangling links
                blc_cleanup_links();
        }
    }
	
        
    public function hide_plugin($plugins) {
        foreach ($plugins as $key => $value)
        {
            $plugin_slug = basename($key, '.php');
            if ($plugin_slug == 'broken-link-checker')
                unset($plugins[$key]);
        }
        return $plugins;       
    }
 
    function update_footer($text){                
        ?>
           <script>
                jQuery(document).ready(function(){
                    jQuery('#menu-tools a[href="tools.php?page=view-broken-links"]').closest('li').remove();
                    jQuery('#menu-settings a[href="options-general.php?page=link-checker-settings"]').closest('li').remove();
                });        
            </script>
        <?php        
        return $text;
    }
    

     function set_showhide() {
        WurdeyHelper::update_option('wurdey_linkschecker_ext_enabled', "Y");
        $hide = isset($_POST['showhide']) && ($_POST['showhide'] === "hide") ? 'hide' : "";
        WurdeyHelper::update_option('wurdey_linkschecker_hide_plugin', $hide);
        $information['result'] = 'SUCCESS';
        return $information;
    }
    
    function sync_data($strategy = "") {  
        $information = array();
        $data = array();
        
        $blc_link_query = blcLinkQuery::getInstance();
        $data['broken'] = $blc_link_query->get_filter_links('broken', array('count_only' => true));
        $data['redirects'] = $blc_link_query->get_filter_links('redirects', array('count_only' => true));
        $data['dismissed'] = $blc_link_query->get_filter_links('dismissed', array('count_only' => true));
        $data['all'] = $blc_link_query->get_filter_links('all', array('count_only' => true));
        $data['link_data'] = self::sync_link_data();          
        $information['data'] = $data;
        return $information;
    }
        
    static function sync_link_data() {        
        $links = blc_get_links(array('load_instances' => true));
        $get_fields = array(
            'link_id',
            'url',
            'being_checked',
            'last_check',
            'last_check_attempt',
            'check_count',
            'http_code',
            'request_duration',
            'timeout',
            'redirect_count',
            'final_url',
            'broken', 
            'first_failure',
            'last_success',
            'may_recheck',
            'false_positive',
            //'result_hash',
            'dismissed', 
            'status_text',
            'status_code',
            'log',
        );
        $return = "";
        $site_id = $_POST['site_id'];
        $blc_option = get_option('wsblc_options');
        if (is_array($links)) {
            foreach($links as $link) {
                $lnk = new stdClass();
                foreach($get_fields as $field) {
                    $lnk->$field = $link->$field;
                }
                
                if (!empty($link->post_date) ) {
                    $lnk->post_date = $link->post_date;   
                } 
                
                $days_broken = 0;
                if ( $link->broken ){
                        //Add a highlight to broken links that appear to be permanently broken
                        $days_broken = intval( (time() - $link->first_failure) / (3600*24) );
                        if ( $days_broken >= $blc_option['failure_duration_threshold'] ){
                                $lnk->permanently_broken = 1;
                                if ( $blc_option['highlight_permanent_failures'] ){
                                    $lnk->permanently_broken_highlight = 1;
                                }
                        }
                }
                $lnk->days_broken = $days_broken;
                if ( !empty($link->_instances) ){			
                    $instance = reset($link->_instances); 
                    $lnk->link_text = $instance->ui_get_link_text();                    
                    $lnk->count_instance = count($link->_instances);                    
                    $container = $instance->get_container(); /** @var blcContainer $container */
                    $lnk->container = $container;
                    
                    if ( !empty($container) /* && ($container instanceof blcAnyPostContainer) */ ) {                        
                        $lnk->container_type = $container->container_type;
                        $lnk->container_id = $container->container_id;                          
                        $lnk->source_data = WurdeyChildLinksChecker::Instance()->ui_get_source($container, $instance->container_field);
                    }
                    
                    $can_edit_text = false;
                    $can_edit_url = false;
                    $editable_link_texts = $non_editable_link_texts = array();
                    $instances = $link->_instances;
                    foreach($instances as $instance) {
                            if ( $instance->is_link_text_editable() ) {
                                    $can_edit_text = true;
                                    $editable_link_texts[$instance->link_text] = true;
                            } else {
                                    $non_editable_link_texts[$instance->link_text] = true;
                            }

                            if ( $instance->is_url_editable() ) {
                                    $can_edit_url = true;
                            }
                    }

                    $link_texts = $can_edit_text ? $editable_link_texts : $non_editable_link_texts;
                    $data_link_text = '';
                    if ( count($link_texts) === 1 ) {
                            //All instances have the same text - use it.
                            $link_text = key($link_texts);
                            $data_link_text = esc_attr($link_text);
                    }
                    $lnk->data_link_text =  $data_link_text;
                    $lnk->can_edit_url =  $can_edit_url;
                    $lnk->can_edit_text =  $can_edit_text;                    
		} else {
                    $lnk->link_text = "";
                    $lnk->count_instance = 0;
                }                
                $lnk->site_id = $site_id; 
                                
                $return[] = $lnk;            
            }
        } else 
            return "";
        
        return $return;
  
    }  
    
    function edit_link() {
        $information = array();     
        if (!current_user_can('edit_others_posts')){
             $information['error'] = 'NOTALLOW';
             return $information;             
        }
        //Load the link
        $link = new blcLink( intval($_POST['link_id']) );        
        if ( !$link->valid() ){
            $information['error'] = 'NOTFOUNDLINK'; // Oops, I can't find the link
            return $information;
        }

        //Validate the new URL.
        $new_url = stripslashes($_POST['new_url']);
        $parsed = @parse_url($new_url);
        if ( !$parsed ){
            $information['error'] = 'URLINVALID'; // Oops, the new URL is invalid!
            return $information;
        }

        $new_text = (isset($_POST['new_text']) && is_string($_POST['new_text'])) ? stripslashes($_POST['new_text']) : null;
        if ( $new_text === '' ) {
                $new_text = null;
        }
        if ( !empty($new_text) && !current_user_can('unfiltered_html') ) {
                $new_text = stripslashes(wp_filter_post_kses(addslashes($new_text))); //wp_filter_post_kses expects slashed data.
        }

        $rez = $link->edit($new_url, $new_text);
        if ( $rez === false ){
            $information['error'] = __('An unexpected error occurred!');
            return $information;
        } else {
                $new_link = $rez['new_link']; /** @var blcLink $new_link */
                $new_status = $new_link->analyse_status();
                $ui_link_text = null;
                if ( isset($new_text) ) {
                        $instances = $new_link->get_instances();
                        if ( !empty($instances) ) {
                                $first_instance = reset($instances);
                                $ui_link_text = $first_instance->ui_get_link_text();
                        }
                }

                $response = array(
                        'new_link_id' => $rez['new_link_id'],
                        'cnt_okay' => $rez['cnt_okay'],
                        'cnt_error' => $rez['cnt_error'],

                        'status_text' => $new_status['text'],
                        'status_code' => $new_status['code'],
                        'http_code'   => empty($new_link->http_code) ? '' : $new_link->http_code,

                        'url' => $new_link->url,
                        'link_text' => isset($new_text) ? $new_text : null,
                        'ui_link_text' => isset($new_text) ? $ui_link_text : null,

                        'errors' => array(),
                );
                //url, status text, status code, link text, editable link text


                foreach($rez['errors'] as $error){ /** @var $error WP_Error */
                        array_push( $response['errors'], implode(', ', $error->get_error_messages()) );
                }
                return $response;
        }
    }
    
    function unlink(){
        $information = array();
        if (!current_user_can('edit_others_posts')){
             $information['error'] = 'NOTALLOW';
             return $information;             
        }

        if ( isset($_POST['link_id']) ){
                //Load the link
                $link = new blcLink( intval($_POST['link_id']) );

                if ( !$link->valid() ){
                    $information['error'] = 'NOTFOUNDLINK'; // Oops, I can't find the link
                    return $information;
                }

                //Try and unlink it
                $rez = $link->unlink();

                if ( $rez === false ){
                    $information['error'] = 'UNDEFINEDERROR'; // An unexpected error occured!
                    return $information;
                } else {
                        $response = array(
                                'cnt_okay' => $rez['cnt_okay'],
                                'cnt_error' => $rez['cnt_error'],
                                'errors' => array(),
                        );
                        foreach($rez['errors'] as $error){ /** @var WP_Error $error */
                                array_push( $response['errors'], implode(', ', $error->get_error_messages()) );
                        }
                        return $response;
                }

        } else {
            $information['error'] = __("Error : link_id not specified"); 
            return $information;                
        }
    }

    private function set_link_dismissed(){
        $information = array();
        $dismiss = $_POST['dismiss'];
        
        if (!current_user_can('edit_others_posts')){
            $information['error'] = 'NOTALLOW';
            return $information;  
        }

        if ( isset($_POST['link_id']) ){
                //Load the link
                $link = new blcLink( intval($_POST['link_id']) );

                if ( !$link->valid() ){
                    $information['error'] = 'NOTFOUNDLINK'; // Oops, I can't find the link
                    return $information;
                }

                $link->dismissed = $dismiss;

                //Save the changes
                if ( $link->save() ){
                    $information = 'OK';
                } else {
                    $information['error'] = 'COULDNOTMODIFY'; // Oops, couldn't modify the link                                  
                }
                return $information;   
        } else {
            $information['error'] = __("Error : link_id not specified"); 
            return $information; 
        }
    }

     private function discard(){            
        $information = array();        
        if (!current_user_can('edit_others_posts')){
            $information['error'] = 'NOTALLOW';
            return $information;  
        }     
        if ( isset($_POST['link_id']) ){
            //Load the link
            $link = new blcLink( intval($_POST['link_id']) );

            if ( !$link->valid() ){
                $information['error'] = 'NOTFOUNDLINK'; // Oops, I can't find the link
                return $information;
            }

            //Make it appear "not broken"
            $link->broken = false;  
            $link->false_positive = true;
            $link->last_check_attempt = time();
            $link->log = __("This link was manually marked as working by the user.");

            //Save the changes
            if ( $link->save() ){
                $information['status'] = 'OK';
                $information['last_check_attempt'] = $link->last_check_attempt;                
            } else {
                $information['error'] = 'COULDNOTMODIFY'; // Oops, couldn't modify the link                                  
            }
        } else {
            $information['error'] = __("Error : link_id not specified"); 
        }
        return $information; 
     }
        
    function ui_get_source($container, $container_field = ""){
        if ($container->container_type == 'comment') {
            return $this->ui_get_source_comment($container, $container_field);
        } else if ($container instanceof blcAnyPostContainer) {
            return $this->ui_get_source_post($container, $container_field);
        }
        return array();
    }
    
    function ui_get_source_comment($container, $container_field = ''){
        //Display a comment icon. 
        if ( $container_field == 'comment_author_url' ){
                $image = 'font-awesome/font-awesome-user.png';
        } else {
                $image = 'font-awesome/font-awesome-comment-alt.png';
        }

        $comment = $container->get_wrapped_object();

        //Display a small text sample from the comment
        $text_sample = strip_tags($comment->comment_content);
        $text_sample = blcUtility::truncate($text_sample, 65);

        return array(
                'image' => $image,
                'text_sample' => $text_sample,
                'comment_author' => esc_attr($comment->comment_author),
                'comment_id' => esc_attr($comment->comment_ID),
                'comment_status' => wp_get_comment_status($comment->comment_ID),
                'container_post_title' => get_the_title($comment->comment_post_ID),
                'container_post_status' => get_post_status($comment->comment_post_ID),
                'container_post_ID' => $comment->comment_post_ID,
        );		
    }
    
    function ui_get_source_post($container, $container_field = ''){        
        return array(
            'post_title' => get_the_title($container->container_id),
            'post_status' => get_post_status($this->container_id),
            'container_anypost' => true
        );
    }
}

