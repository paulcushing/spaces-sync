<?php
if (!class_exists('SpacesSync')) {
  class SpacesSync
  {
    private        $key;
    private        $secret;
    private        $endpoint;
    private        $container;
    private        $storage_path;
    private        $storage_file_only;
    private        $storage_file_delete;
    private        $filter;
    private        $upload_url_path;
    private        $upload_path;
    private        $compression_amount;
    private        $max_width;
    private        $max_height;
    private        $last_upload_error;

    /**
     *
     * @return SpacesSync
     */

    public function __construct()
    {
      $this->key                 = get_option('spacessync_key');
      $this->secret              = get_option('spacessync_secret');
      $this->endpoint            = get_option('spacessync_endpoint');
      $this->container           = get_option('spacessync_container');
      $this->storage_path        = get_option('spacessync_storage_path');
      $this->storage_file_only   = get_option('spacessync_storage_file_only');
      $this->storage_file_delete = get_option('spacessync_storage_file_delete');
      $this->filter              = get_option('spacessync_filter');
      $this->upload_url_path     = get_option('upload_url_path');
      $this->upload_path         = get_option('upload_path');
      $this->compression_amount  = get_option('spacessync_compression_amount');
      $this->max_width           = get_option('spacessync_max_width');
      $this->max_height          = get_option('spacessync_max_height');
    }

    public function setup()
    {

      $this->register_actions();
      $this->register_filters();
    }


    private function register_actions()
    {

      add_action('admin_menu', array($this, 'register_menu'));
      add_action('admin_init', array($this, 'register_settings'));
      add_action('admin_enqueue_scripts', array($this, 'register_scripts'));
      add_action('admin_enqueue_scripts', array($this, 'register_styles'));

      add_action('wp_ajax_spacessync_test_connection', array($this, 'test_connection'));

      add_filter('wp_handle_upload', array($this, 'processUploadedImages'), 10, 2);
      add_action('add_attachment', array($this, 'action_add_attachment'), 10, 1);
      add_action('delete_attachment', array($this, 'action_delete_attachment'), 10, 1);
    }

    private function register_filters()
    {
      add_filter('wp_generate_attachment_metadata', array($this, 'filter_wp_generate_attachment_metadata'), 20, 3);
      add_filter('wp_unique_filename', array($this, 'filter_wp_unique_filename'), 10, 3);
    }

    public function register_scripts()
    {
      wp_enqueue_script('spacessync-core-js', plugin_dir_url(__DIR__) . '/assets/scripts/core.js', array('jquery'), '1.4.0', true);
      wp_localize_script('spacessync-core-js', 'spacesSyncSettings', array(
        'connectionNonce' => wp_create_nonce('spacessync_test_connection'),
      ));
    }

    public function register_styles()
    {

      wp_enqueue_style('spacessync-flexboxgrid', plugin_dir_url(__DIR__) . '/assets/styles/flexboxgrid.min.css');
      wp_enqueue_style('spacessync-core-css', plugin_dir_url(__DIR__) . '/assets/styles/core.css');
    }

    public function register_settings()
    {

      register_setting('spacessync_settings', 'spacessync_key');
      register_setting('spacessync_settings', 'spacessync_secret');
      register_setting('spacessync_settings', 'spacessync_endpoint');
      register_setting('spacessync_settings', 'spacessync_container');
      register_setting('spacessync_settings', 'spacessync_storage_path');
      register_setting('spacessync_settings', 'spacessync_storage_file_only');
      register_setting('spacessync_settings', 'spacessync_storage_file_delete');
      register_setting('spacessync_settings', 'spacessync_filter');
      register_setting('spacessync_settings', 'upload_url_path');
      register_setting('spacessync_settings', 'upload_path');
      register_setting('spacessync_settings', 'spacessync_compression_amount');
      register_setting('spacessync_settings', 'spacessync_max_width');
      register_setting('spacessync_settings', 'spacessync_max_height');
    }

    public function register_setting_page()
    {
      include_once('spacessync_settings_page.php');
    }

    public function register_menu()
    {
      add_options_page(
        'Spaces Sync',
        'Spaces Sync',
        'manage_options',
        'setting_page.php',
        array($this, 'register_setting_page')
      );
    }

    public function processUploadedImages($image_data, $context = 'upload') {
      $valid_types = array('image/gif', 'image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/avif');

      /* Process images only */
      if (isset($image_data['type'], $image_data['file']) && in_array($image_data['type'], $valid_types, true)) {

        $image_editor = wp_get_image_editor($image_data['file']);

        if (is_wp_error($image_editor)) {
          error_log('Spaces Sync: ' . $image_editor->get_error_message());
          return $image_data;
        }

        $sizes = $image_editor->get_size();
        if (!is_array($sizes)) {
          return $image_data;
        }

        $image_changed = false;

        /* Compress jpegs */
        if (
          in_array($image_data['type'], array('image/jpeg', 'image/jpg'), true) &&
          $this->compression_amount > 0 &&
          $this->compression_amount < 100
        ) {
          $quality_result = $image_editor->set_quality((int) $this->compression_amount);
          if (is_wp_error($quality_result)) {
            error_log('Spaces Sync: ' . $quality_result->get_error_message());
            return $image_data;
          }
          $image_changed = true;
        }

        /* Resize within max dimensions */
        $max_width = max(0, (int) $this->max_width);
        $max_height = max(0, (int) $this->max_height);
        $needs_resize =
          ($max_width > 0 && isset($sizes['width']) && $sizes['width'] > $max_width) ||
          ($max_height > 0 && isset($sizes['height']) && $sizes['height'] > $max_height);

        if ($needs_resize) {
          $resize_result = $image_editor->resize(
            $max_width > 0 ? $max_width : null,
            $max_height > 0 ? $max_height : null,
            false
          );

          if (is_wp_error($resize_result)) {
            error_log('Spaces Sync: ' . $resize_result->get_error_message());
            return $image_data;
          }
          $image_changed = true;
        }

        if ($image_changed) {
          $save_result = $image_editor->save($image_data['file']);
          if (is_wp_error($save_result)) {
            error_log('Spaces Sync: ' . $save_result->get_error_message());
          }
        }
      }
      return $image_data;
    }

    public function filter_wp_generate_attachment_metadata($metadata, $attachment_id = 0, $context = 'create')
    {
      $paths = array();
      $upload_dir = wp_get_upload_dir();

      if ($attachment_id && get_post_meta($attachment_id, '_wp_attachment_context', true) === 'upgrader') {
        return $metadata;
      }

      $attached_file = $attachment_id ? get_attached_file($attachment_id) : false;

      // collect original file path
      if (is_array($metadata) && isset($metadata['file'])) {
        $path = rtrim($upload_dir['basedir'], '/\\') . DIRECTORY_SEPARATOR . ltrim($metadata['file'], '/\\');
      } elseif ($attached_file) {
        $path = $attached_file;
      }

      if (isset($path)) {
        $paths[] = $path;
        // set basepath for other sizes
        $basepath = rtrim(dirname($path), '/\\') . DIRECTORY_SEPARATOR;
      }

      // collect size files path
      if (is_array($metadata) && isset($metadata['sizes']) && isset($basepath)) {

        foreach ($metadata['sizes'] as $size) {
          if (isset($size['file'])) {
            $paths[] = $basepath . $size['file'];
          }
        }
      }

      // WordPress keeps the pre-scaled full-resolution image separately.
      if (is_array($metadata) && isset($metadata['original_image']) && isset($basepath)) {
        $paths[] = $basepath . $metadata['original_image'];
      }

      if ($attachment_id && $this->upload_files($paths, $context === 'create')) {
        delete_post_meta($attachment_id, '_spaces_sync_error');
      } elseif ($attachment_id && $this->last_upload_error) {
        update_post_meta($attachment_id, '_spaces_sync_error', $this->last_upload_error);
      }

      return $metadata;
    }


    /* Checks for existing file and increments the filename if necessary */
    public function filter_wp_unique_filename($filename, $ext = '', $dir = '')
    {
      if ($this->is_upgrader_upload_request()) {
        return $filename;
      }

      $upload_dir = wp_get_upload_dir();
      // This is the resolved absolute directory even when upload_path is relative.
      $local_base = $upload_dir['basedir'];
      $local_base = rtrim(wp_normalize_path($local_base), '/');
      $target_dir = rtrim(wp_normalize_path($dir), '/');

      if ($target_dir === $local_base) {
        $subdir = '';
      } elseif (strpos($target_dir, $local_base . '/') === 0) {
        $subdir = substr($target_dir, strlen($local_base));
      } else {
        $subdir = wp_upload_dir()['subdir'];
      }

      $number = 1;
      $new_filename = $filename;
      $fileparts = pathinfo($filename);
      $extension = isset($fileparts['extension']) ? '.' . $fileparts['extension'] : '';
      $remote_dir = trim($this->storage_path . '/' . ltrim($subdir, '/'), '/');

      try {
        $filesystem = SpacesSync_Filesystem::get_instance($this->key, $this->secret, $this->container, $this->endpoint);
        $cdnPath = $remote_dir . '/' . $new_filename;
        while ($filesystem->has($cdnPath)) {
          $new_filename = $fileparts['filename'] . "-$number" . $extension;
          $number = (int) $number + 1;
          $cdnPath = $remote_dir . '/' . $new_filename;
        }
      } catch (Throwable $e) {
        error_log('Spaces Sync: remote filename check failed: ' . $e->getMessage());
        $safe_suffix = substr(bin2hex(random_bytes(4)), 0, 8);
        $new_filename = $fileparts['filename'] . '-' . $safe_suffix . $extension;
      }

      return $new_filename;
    }

    private function is_upgrader_upload_request()
    {
      return isset($_FILES['pluginzip']) || isset($_FILES['themezip']);
    }

    public function action_add_attachment($postID)
    {
      // Offloading is intentionally deferred until WordPress has generated metadata.
      // This keeps PDFs, audio/video files, and upgrader ZIPs available locally while
      // core finishes processing them.
      return true;
    }

    public function action_delete_attachment($postID)
    {
      if (get_post_meta($postID, '_wp_attachment_context', true) === 'upgrader') {
        return;
      }

      $paths = array();
      $metadata = wp_get_attachment_metadata($postID);
      $upload_dir = wp_get_upload_dir();
      $basepath = null;

      if (is_array($metadata) && !empty($metadata['file'])) {
        $path = rtrim($upload_dir['basedir'], '/\\') . DIRECTORY_SEPARATOR . ltrim($metadata['file'], '/\\');
        $paths[] = $path;
        $basepath = dirname($path) . DIRECTORY_SEPARATOR;
      } else {
        $attached_file = get_attached_file($postID);
        if ($attached_file) {
          $paths[] = $attached_file;
          $basepath = dirname($attached_file) . DIRECTORY_SEPARATOR;
        }
      }

      if ($basepath !== null && is_array($metadata)) {
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
          foreach ($metadata['sizes'] as $size) {
            if (!empty($size['file'])) {
              $paths[] = $basepath . $size['file'];
            }
          }
        }

        if (!empty($metadata['original_image'])) {
          $paths[] = $basepath . $metadata['original_image'];
        }
      }

      foreach (array_unique($paths) as $filepath) {
        $this->file_delete($filepath);
      }
    }

    /* Check Connection to remote account */
    public function test_connection()
    {
      if (!current_user_can('manage_options')) {
        wp_die(__('You are not allowed to test this connection.', 'spacessync'), '', array('response' => 403));
      }

      check_ajax_referer('spacessync_test_connection', 'nonce');

      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postData = wp_unslash($_POST);
        $keys = array('key' => 'spacessync_key', 'secret' => 'spacessync_secret', 'container' => 'spacessync_container');
        foreach ($keys as $prop => $key) {
          if (isset($postData[$key])) {
            $this->$prop = sanitize_text_field($postData[$key]);
          }
        }

        if (isset($postData['spacessync_endpoint'])) {
          $endpoint = $this->normalize_spaces_endpoint($postData['spacessync_endpoint']);
          if ($endpoint === false) {
            $this->show_message(__('Enter a valid HTTPS DigitalOcean Spaces endpoint.', 'spacessync'), true);
            wp_die();
          }
          $this->endpoint = $endpoint;
        }

        if (!preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $this->container)) {
          $this->show_message(__('Enter a valid Spaces bucket name.', 'spacessync'), true);
          wp_die();
        }
      }

      try {
        $filesystem = SpacesSync_Filesystem::get_instance($this->key, $this->secret, $this->container, $this->endpoint);
        $filesystem->write('test.txt', 'test');
        $filesystem->delete('test.txt');

        $this->show_message(__('Connection is successfully established. Save the settings.', 'spacessync'));
        wp_die();
      } catch (Throwable $e) {
        error_log('Spaces Sync: ' . $e->getMessage());
        $this->show_message(__('Connection is not established.', 'spacessync') . ' : ' . $e->getMessage() . ($e->getCode() == 0 ? '' : ' - ' . $e->getCode()), true);
        wp_die();
      }
    }

    private function normalize_spaces_endpoint($endpoint)
    {
      $endpoint = trim(sanitize_text_field($endpoint));
      $parts = parse_url($endpoint);

      if (
        !is_array($parts) ||
        strtolower($parts['scheme'] ?? '') !== 'https' ||
        empty($parts['host']) ||
        isset($parts['user']) ||
        isset($parts['pass']) ||
        isset($parts['port']) ||
        isset($parts['query']) ||
        isset($parts['fragment']) ||
        !empty(trim($parts['path'] ?? '', '/'))
      ) {
        return false;
      }

      $host = strtolower(rtrim($parts['host'], '.'));
      if (!preg_match('/^[a-z0-9-]+\.digitaloceanspaces\.com$/', $host)) {
        return false;
      }

      return 'https://' . $host;
    }

    public function show_message($message, $errormsg = false)
    {

      if ($errormsg) {

        echo '<div id="message" class="error">';
      } else {

        echo '<div id="message" class="updated fade">';
      }

      echo '<p><strong>' . esc_html($message) . '</strong></p></div>';
    }

    public function file_path($file)
    {
      $base_path = wp_get_upload_dir()['basedir'];
      $normalized_file = wp_normalize_path($file);
      $normalized_base = rtrim(wp_normalize_path($base_path), '/');

      if ($normalized_file === $normalized_base) {
        $path = '';
      } elseif (strpos($normalized_file, $normalized_base . '/') === 0) {
        $path = substr($normalized_file, strlen($normalized_base));
      } else {
        $path = '/' . basename($normalized_file);
      }

      return trim($this->storage_path . '/' . ltrim($path, '/'), '/');
    }

    private function should_ignore_file($file)
    {
      $regex_string = $this->filter;

      if ($regex_string == '*' || !strlen($regex_string)) {
        return false;
      }

      $matched = @preg_match($regex_string, $file);
      if ($matched === false) {
        $this->last_upload_error = 'Invalid ignore-file regular expression.';
        error_log('Spaces Sync: ' . $this->last_upload_error);
        return true;
      }

      return $matched === 1;
    }

    private function upload_files($paths, $require_all = false)
    {
      $this->last_upload_error = '';
      $upload_paths = array();

      foreach (array_unique($paths) as $path) {
        if ($this->should_ignore_file($path)) {
          if ($this->last_upload_error) {
            return false;
          }
          continue;
        }

        if (!is_readable($path)) {
          if ($require_all) {
            $this->last_upload_error = 'An expected attachment file is not readable: ' . $path;
            error_log('Spaces Sync: ' . $this->last_upload_error);
            return false;
          }
          continue;
        }

        $upload_paths[] = $path;
      }

      foreach ($upload_paths as $path) {
        if (!$this->file_upload($path)) {
          return false;
        }
      }

      if ($this->storage_file_only == 1) {
        foreach ($upload_paths as $path) {
          if (is_file($path) && !unlink($path)) {
            $this->last_upload_error = 'A synced local file could not be removed: ' . $path;
            error_log('Spaces Sync: ' . $this->last_upload_error);
            return false;
          }
        }
      }

      return true;
    }

    public function file_upload($file)
    {
      if (!is_readable($file) || $this->should_ignore_file($file)) {
        return false;
      }

      try {
        $filesystem = SpacesSync_Filesystem::get_instance($this->key, $this->secret, $this->container, $this->endpoint);
        $remote_path = $this->file_path($file);
        $filesystem->write($remote_path, file_get_contents($file));
        $filesystem->setVisibility($remote_path, 'public');

        return true;
      } catch (Throwable $e) {
        $this->last_upload_error = $e->getMessage();
        error_log('Spaces Sync: ' . $e->getMessage());

        return false;
      }
    }

    public function file_delete($file)
    {

      if ($this->storage_file_delete == 1) {

        try {

          $filepath = $this->file_path($file);
          $filesystem = SpacesSync_Filesystem::get_instance($this->key, $this->secret, $this->container, $this->endpoint);

          $filesystem->delete($filepath);
        } catch (Exception $e) {
          error_log($e);
        }
      }

      return $file;
    }
  }
}
