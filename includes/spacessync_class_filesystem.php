<?php

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;

require plugin_dir_path(__DIR__) . 'vendor/autoload.php';

class SpacesSync_Filesystem
{

  public static function get_instance($key, $secret, $container, $endpoint)
  {

    $client = new S3Client([
      'credentials' => [
        'key'    => $key,
        'secret' => $secret,
      ],
      'bucket' => 'do-spaces',
      'endpoint' => $endpoint,
      'version' => 'latest',
      'region' => 'us-west-2' // Default - not used by DO
    ]);

    $connection = new AwsS3V3Adapter(
      $client,
      $container
    );
    $filesystem = new Filesystem(
      $connection
    );

    return $filesystem;
  }
}
