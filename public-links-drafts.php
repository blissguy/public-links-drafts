<?php

declare(strict_types=1);

/**
 * Plugin Name: Public Preview Links
 * Plugin URI: https://blisswebconcept.com/
 * Description: Generate temporary public preview links for selected post types and statuses, with expiry control.
 * Version: 0.1.0
 * Author: Emmanuel Kuebutornye
 * Author URI: https://blisswebconcept.com/
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: public-links-drafts
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('PLD_VERSION', '0.1.0');
define('PLD_SLUG', 'public-links-drafts');
define('PLD_TEXTDOMAIN', 'public-links-drafts');
define('PLD_PATH', plugin_dir_path(__FILE__));
define('PLD_URL', plugins_url('/', __FILE__));
define('PLD_OPTION', 'pld_options');

// Autoload includes.
require_once PLD_PATH . 'includes/Class_Public_Preview.php';
require_once PLD_PATH . 'includes/Admin/Class_Settings.php';


/**
 * Initialize plugin modules.
 */
function pld_bootstrap(): void {
    // Core module.
    $pld_core = new PLD_Public_Preview(PLD_OPTION);
    $pld_core->register();

    // Settings UI.
    $pld_settings = new PLD_Admin_Settings(PLD_OPTION);
    $pld_settings->register();
}
add_action('plugins_loaded', 'pld_bootstrap', 20);
