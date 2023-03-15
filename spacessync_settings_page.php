<div class="spacessync__loader">

</div>

<div class="spacessync__page row">

  <div class="col-xs-12 col-sm-12 col-md-8 col-lg-8">

    <div class="spacessync__message"></div>

    <div class="row">

      <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
        <h2>Spaces Sync <?php _e('Settings', 'spacessync'); ?></h2>
      </div>

    </div>

    <div class="row">

      <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
        <?php _e('Your DigitalOcean Spaces access settings.', 'spacessync'); ?>
        <?php _e('No Digital Ocean account yet? <a href="https://m.do.co/c/8ed78a03ae44">Create it!</a>', 'spacessync'); ?>
      </div>

    </div>

    <form method="POST" action="options.php">

      <?php settings_fields('spacessync_settings'); ?>

      <div class="row">

        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
          <h4>
            <?php _e('Connection Settings', 'spacessync'); ?>
          </h4>
        </div>

      </div>

      <div class="spacessync__block">

        <div class="row">

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-2">
            <label for="spacessync_key">
              <?php _e('Spaces Key', 'spacessync'); ?>:
            </label>
          </div>

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-10">
            <input id="spacessync_key" name="spacessync_key" type="text" class="regular-text code" data-lpignore="true" value="<?php echo get_option('spacessync_key'); ?>" />
          </div>

        </div>

        <div class="row">

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-2">
            <label for="spacessync_secret">
              <?php _e('Spaces Secret', 'spacessync'); ?>:
            </label>
          </div>

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-10">
            <input id="spacessync_secret" name="spacessync_secret" type="password" class="regular-text code" data-lpignore="true" value="<?php echo get_option('spacessync_secret'); ?>" />
          </div>

        </div>

        <div class="row">

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-2">
            <label for="spacessync_container">
              <?php _e('Spaces Bucket Name', 'spacessync'); ?>:
            </label>
          </div>

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-10">
            <input id="spacessync_container" name="spacessync_container" type="text" class="regular-text code" data-lpignore="true" value="<?php echo get_option('spacessync_container'); ?>" />
          </div>

        </div>

        <div class="row">

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-2">
            <label for="spacessync_endpoint">
              <?php _e('Endpoint', 'spacessync'); ?>:
            </label>
          </div>

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-10">
            <input id="spacessync_endpoint" name="spacessync_endpoint" type="text" class="regular-text code" data-lpignore="true" value="<?php echo get_option('spacessync_endpoint'); ?>" />
            <div class="spacessync__description">
              <?php _e('By default', 'spacessync'); ?>: <code>https://ams3.digitaloceanspaces.com</code>
            </div>
          </div>

        </div>

        <div class="row">

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-2">
            <input type="button" name="test" class="button button-primary spacessync__test__connection" value="<?php _e('Check the connection', 'spacessync'); ?>" />
          </div>

        </div>

      </div>

      <div class="row">

        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
          <h4>
            <?php _e('File & Path settings', 'spacessync'); ?>
          </h4>
        </div>

      </div>

      <div class="spacessync__block">

        <div class="row larger">

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-2">
            <label for="upload_url_path">
              <?php _e('Full URL-path to files', 'spacessync'); ?>:
            </label>
          </div>

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-10">
            <input id="upload_url_path" name="upload_url_path" type="text" class="regular-text code" value="<?php echo get_option('upload_url_path'); ?>" />
            <div class="spacessync__description">
              <?php _e('Enter storage public domain or subdomain if the files are stored only in the cloud storage', 'spacessync'); ?>
              <code>(http://uploads.example.com)</code>,
              <?php _e('or full URL path, if are kept both in cloud and on the server.', 'spacessync'); ?>
              <code>(http://example.com/wp-content/uploads)</code>.</p>
              <?php _e('In that case duplicates are created. If you change one, you change and the other,', 'spacessync'); ?>
            </div>
          </div>

        </div>

        <div class="row">

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-2">
            <label for="upload_path">
              <?php _e('Local path', 'spacessync'); ?>:
            </label>
          </div>

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-10">
            <input id="upload_path" name="upload_path" type="text" class="regular-text code" value="<?php echo get_option('upload_path'); ?>" />
            <div class="spacessync__description">
              <?php _e('Local path to the uploaded files. By default', 'spacessync'); ?>: <code>wp-content/uploads</code>
              <?php _e('Setting duplicates of the same name from the mediafiles settings. Changing one, you change and other', 'spacessync'); ?>.
            </div>
          </div>

        </div>

        <div class="row">

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-2">
            <label for="spacessync_storage_path">
              <?php _e('Storage prefix', 'spacessync'); ?>:
            </label>
          </div>

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-10">
            <input id="spacessync_storage_path" name="spacessync_storage_path" type="text" class="regular-text code" value="<?php echo get_option('spacessync_storage_path'); ?>" />
            <div class="spacessync__description">
              <?php _e('The path to the file in the storage will appear as a prefix / path.<br />For example, in your case:', 'spacessync'); ?>
              <code><?php echo get_option('spacessync_storage_path'); ?></code>
            </div>
          </div>

        </div>

        <div class="row">

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-2">
            <label for="spacessync_filter">
              <?php _e('Filemask/Regex for ignored files', 'spacessync'); ?>:
            </label>
          </div>

          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-10">
            <input id="spacessync_filter" name="spacessync_filter" type="text" class="regular-text code" value="<?php echo get_option('spacessync_filter'); ?>" />
            <div class="spacessync__description">
              <?php _e('By default empty or', 'spacessync'); ?><code>*</code>
              <?php _e('Will upload all the files by default, you are free to use any Regular Expression to match and ignore the selection you need, for example:', 'spacessync'); ?>
              <code>/^.*\.(zip|rar|docx)$/i</code>
            </div>
          </div>

        </div>

      </div>

      <div class="row">

        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
          <h4>
            <?php _e('Sync settings', 'spacessync'); ?>
          </h4>
        </div>

      </div>

      <div class="row" style="padding-bottom: 10px;">

        <div class="col-xs-1 col-sm-1 col-md-1 col-lg-1" style="text-align: right;">
          <input id="onlystorage" type="checkbox" name="spacessync_storage_file_only" value="1" <?php echo checked(get_option('spacessync_storage_file_only'), 1); ?>" />
        </div>

        <div class="col-xs-11 col-sm-11 col-md-11 col-lg-11">
          <?php _e('Store files only in the cloud and delete after successful upload.', 'spacessync'); ?>
          <?php _e('In that case file will be removed from your server after being uploaded to cloud storage, that saves you space.', 'spacessync'); ?>
        </div>

      </div>

      <div class="row">

        <div class="col-xs-1 col-sm-1 col-md-1 col-lg-1" style="text-align: right;">
          <input id="spacessync_storage_file_delete" type="checkbox" name="spacessync_storage_file_delete" value="1" <?php echo checked(get_option('spacessync_storage_file_delete'), 1); ?>" />
        </div>

        <div class="col-xs-11 col-sm-11 col-md-11 col-lg-11">
          <?php _e('Delete file from bucket as soon as it is removed from your library.', 'spacessync'); ?>
        </div>

      </div>

      <div class="row">

        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
          <input type="hidden" name="action" value="update" />
          <?php submit_button(); ?>
        </div>

      </div>

    </form>

  </div>

  <div class="col-xs-12 col-xs-12 col-md-4 col-lg-4">

    <p>
      <img id="img-spinner" src="<?php echo plugins_url() . '/' . dirname(plugin_basename(__FILE__)); ?>/assets/images/do_logo.svg" alt="DigitalOcean" style="width: 150px;" />
    </p>

    <p>
      This plugin syncs your WordPress media to your DigitalOcean Spaces Bucket.
    </p>

    <p>
      <a href="https://github.com/paulcushing/Spaces-Sync/" target="_blank">Spaces Sync on GitHub</a>
    </p>

  </div>

</div>