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

      add_action('add_attachment', array($this, 'action_add_attachment'), 10, 1);
      add_action('delete_attachment', array($this, 'action_delete_attachment'), 10, 1);
    }

    private function register_filters()
    {

      add_filter('wp_generate_attachment_metadata', array($this, 'filter_wp_generate_attachment_metadata'), 20, 1);
      add_filter('wp_unique_filename', array($this, 'filter_wp_unique_filename'));
    }

    public function register_scripts()
    {

      wp_enqueue_script('spacessync-core-js', plugin_dir_url(__DIR__) . '/assets/scripts/core.js', array('jquery'), '1.4.0', true);
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

    public function filter_wp_generate_attachment_metadata($metadata)
    {

      $paths = array();
      $upload_dir = wp_upload_dir();

      // collect original file path
      if (isset($metadata['file'])) {

        $path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $metadata['file'];
        array_push($paths, $path);

        // set basepath for other sizes
        $file_info = pathinfo($path);
        $basepath = isset($file_info['extension'])
          ? str_replace($file_info['filename'] . "." . $file_info['extension'], "", $path)
          : $path;
      }

      // collect size files path
      if (isset($metadata['sizes'])) {

        foreach ($metadata['sizes'] as $size) {

          if (isset($size['file'])) {

            $path = $basepath . $size['file'];
            array_push($paths, $path);
          }
        }
      }

      // process paths
      foreach ($paths as $filepath) {

        // upload file
        $this->file_upload($filepath, 0, true);
      }

      return $metadata;
    }


    /* Checks for existing file and increments the filename if necessary */
    public function filter_wp_unique_filename($filename)
    {

      $upload_dir = wp_upload_dir();
      $subdir = $upload_dir['subdir'];

      $filesystem = SpacesSync_Filesystem::get_instance($this->key, $this->secret, $this->container, $this->endpoint);

      $number = 1;
      $new_filename = $filename;
      $fileparts = pathinfo($filename);
      $cdnPath = rtrim($this->storage_path, '/') . '/' . ltrim($subdir, '/') . '/' . $new_filename;
      while ($filesystem->has($cdnPath)) {
        $new_filename = $fileparts['filename'] . "-$number." . $fileparts['extension'];
        $number = (int) $number + 1;
        $cdnPath = rtrim($this->storage_path, '/') . '/' . ltrim($subdir, '/') . '/' . $new_filename;
      }

      return $new_filename;
    }

    public function action_add_attachment($postID)
    {

      if (wp_attachment_is_image($postID) == false) {

        $file = get_attached_file($postID);

        $this->file_upload($file);
      }

      return true;
    }

    public function action_delete_attachment($postID)
    {

      $paths = array();
      $upload_dir = wp_upload_dir();

      if (wp_attachment_is_image($postID) == false) {

        $file = get_attached_file($postID);

        $this->file_delete($file);
      } else {

        $metadata = wp_get_attachment_metadata($postID);

        // collect original file path
        if (isset($metadata['file'])) {

          $path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $metadata['file'];

          if (!in_array($path, $paths)) {
            array_push($paths, $path);
          }

          // set basepath for other sizes
          $file_info = pathinfo($path);
          $basepath = isset($file_info['extension'])
            ? str_replace($file_info['filename'] . "." . $file_info['extension'], "", $path)
            : $path;
        }

        // collect size files path
        if (isset($metadata['sizes'])) {

          foreach ($metadata['sizes'] as $size) {

            if (isset($size['file'])) {

              $path = $basepath . $size['file'];
              array_push($paths, $path);
            }
          }
        }

        // process paths
        foreach ($paths as $filepath) {

          // delete file
          $this->file_delete($filepath);
        }
      }
    }

    /* Check Connection to remote account */
    public function test_connection()
    {
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postData = $_POST;
        $keys = ['key' => 'spacessync_key', 'secret' => 'spacessync_secret', 'endpoint' => 'spacessync_endpoint', 'container' => 'spacessync_container'];
        foreach ($keys as $prop => $key) {
          if (isset($postData[$key])) {
            $this->$prop = $postData[$key];
          }
        }
      }
      try {

        $filesystem = SpacesSync_Filesystem::get_instance($this->key, $this->secret, $this->container, $this->endpoint);
        $filesystem->write('test.txt', 'test');
        $filesystem->delete('test.txt');

        $this->show_message(__('Connection is successfully established. Save the settings.', 'spacessync'));
        exit();
      } catch (Exception $e) {
        error_log($e);
        $this->show_message(__('Connection is not established.', 'spacessync') . ' : ' . $e->getMessage() . ($e->getCode() == 0 ? '' : ' - ' . $e->getCode()), true);
        exit();
      }
    }

    public function show_message($message, $errormsg = false)
    {

      if ($errormsg) {

        echo '<div id="message" class="error">';
      } else {

        echo '<div id="message" class="updated fade">';
      }

      echo "<p><strong>$message</strong></p></div>";
    }

    public function file_path($file)
    {
      $path = strlen($this->upload_path) ? str_replace($this->upload_path, '', $file)
        : str_replace(wp_upload_dir()['basedir'], '', $file);

      return $this->storage_path . $path;
    }

    public function file_upload($file)
    {
      $filesystem = SpacesSync_Filesystem::get_instance($this->key, $this->secret, $this->container, $this->endpoint);
      $regex_string = $this->filter;

      // prepare regex
      if ($regex_string == '*' || !strlen($regex_string)) {
        $regex = false;
      } else {
        $regex = preg_match($regex_string, $file);
      }

      try {

        // check if readable and regex matched
        if (is_readable($file) && !$regex) {

          // write to remote bucket
          $filesystem->write($this->file_path($file), file_get_contents($file));
          $filesystem->setVisibility($this->file_path($file), 'public');

          // remove on upload
          if ($this->storage_file_only == 1) {

            unlink($file);
          }
        }

        return true;
      } catch (Exception $e) {
        error_log($e);

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
