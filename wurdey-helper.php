<?php
/*
  Plugin Name: Wurdey
  Plugin URI: http://wurdey.com/
  Description: The Wurdey plugin is a managed service remote site management plugin provided by <a href="www.wurdey.com" target="_blank">www.wurdey.com</a> for WordPress Maintenance
  Author: Wurdey
  Author URI: http://wurdey.com
  Version: 1.0.0
 */
header('X-Frame-Options: ALLOWALL');
//header('X-Frame-Options: GOFORIT');
include_once(ABSPATH . 'wp-includes' . DIRECTORY_SEPARATOR . 'version.php'); //Version information from wordpress

$classDir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace(basename(__FILE__), '', plugin_basename(__FILE__)) . 'class' . DIRECTORY_SEPARATOR;
function wurdey_child_autoload($class_name) {
    $class_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace(basename(__FILE__), '', plugin_basename(__FILE__)) . 'class' . DIRECTORY_SEPARATOR . $class_name . '.class.php';
    if (file_exists($class_file)) {
        require_once($class_file);
    }
}
if (function_exists('spl_autoload_register'))
{
    spl_autoload_register('wurdey_child_autoload');
}
else
{
    function __autoload($class_name) {
        wurdey_child_autoload($class_name);
    }
}

$wurdeyChild = new WurdeyChild(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . plugin_basename(__FILE__));
define("WP_CONTENT_PATH", trim(str_replace($_SERVER['DOCUMENT_ROOT'], "", str_replace("\\", "/", WP_CONTENT_DIR)), "/"));
register_activation_hook(__FILE__, array($wurdeyChild, 'activation'));
register_deactivation_hook(__FILE__, array($wurdeyChild, 'deactivation'));
wp_enqueue_style('wurdey', plugin_dir_url(__FILE__).'css/wurdey.css');

function activation_notice() {
    ?>
    <div id="message" class="wurdey_activation error">
        <span>
            Please ensure you have an active <a href="http://wurdey.com" style="font-weight: bold;color:#ffffff;"><strong>Wurdey Account</strong></a> prior to activation
        </span>
        <img class="logo right" src="<?php echo plugin_dir_url(__FILE__).'images/wurdey-activation.png'; ?>"/>
        <a class="wurdey-button right" href="https://wurdey.com/wordpress-maintenance-packages">about wurdey services</a>
    </div>
<?php
}
add_action( 'admin_notices', 'activation_notice' );
?>