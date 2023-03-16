<?php

/**
 * Plugin Name: Spaces Sync
 * Plugin URI: https://github.com/paulcushing/Spaces-Sync
 * Description: This WordPress plugin syncs your media library with DigitalOcean Spaces Bucket.
 * Version: 1.0.2
 * Author: paulcushing
 * Author URI: https://github.com/paulcushing
 * License: MIT
 * Text Domain: spacessync
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
  die;
}

define('SPACES_SYNC_VERSION', '1.0.2');


require plugin_dir_path(__FILE__) . 'includes/spacessync_class.php';
require plugin_dir_path(__FILE__) . 'includes/spacessync_class_filesystem.php';

/** 
 * Add button to plugin list below plugin
 * @since 1.0.0
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
  $links['spacessync-settings-link'] = '<a href="' . admin_url('/options-general.php?page=setting_page.php') . '">' . __('Settings', 'spacessync') . '</a>';
  return $links;
});

function start_spaces_sync()
{

  $spacessync = new SpacesSync();
  $spacessync->setup();
}
start_spaces_sync();
