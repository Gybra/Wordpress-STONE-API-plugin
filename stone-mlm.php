<?php
/**
 * Plugin Name: Stone MLM API
 * Description: Signup and buy Stone API implementation
 * Version: 1.0
 * Author: HexLab Software
 * Author URI: https://hexlabsoftware.it
 */

const PLUGIN_PATH = ABSPATH.'wp-content/plugins/stone-mlm/stone-mlm.php';
const PLUGIN_FOLDER_PATH = ABSPATH.'wp-content/plugins/stone-mlm/';

require_once(PLUGIN_FOLDER_PATH.'db/install-db.php');
require_once(PLUGIN_FOLDER_PATH.'logic/http.class.php');
require_once(PLUGIN_FOLDER_PATH.'logic/stone.class.php');

/**
 * It listen for woocommerce thankyou order and for every wordpress page load complete hooks
 */
add_action('woocommerce_thankyou', array('StoneAPI','completeOrder'));
add_action('wp_loaded', array('StoneAPI','saveReferral'));