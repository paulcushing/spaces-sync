<?php

declare(strict_types=1);

$testOptions = [
    'spacessync_key' => 'test-key',
    'spacessync_secret' => 'test-secret',
    'spacessync_endpoint' => 'https://example.invalid',
    'spacessync_container' => 'test-container',
    'spacessync_storage_path' => 'sites/test',
    'spacessync_storage_file_only' => 1,
    'spacessync_storage_file_delete' => 0,
    'spacessync_filter' => '',
    'upload_url_path' => 'https://cdn.example.invalid/sites/test',
    'upload_path' => '',
    'spacessync_compression_amount' => 80,
    'spacessync_max_width' => '',
    'spacessync_max_height' => '',
];

$testUploadBase = sys_get_temp_dir() . '/spaces-sync-tests-' . getmypid() . '/uploads';
$testLog = sys_get_temp_dir() . '/spaces-sync-tests-' . getmypid() . '.log';
ini_set('error_log', $testLog);
$testAttachmentFiles = [];
$testAttachmentContexts = [];
$testAttachmentImages = [];
$testAttachmentMetadata = [];
$testPostMeta = [];
$testImageEditor = null;
$testLocalizedScripts = [];

function get_option(string $name)
{
    global $testOptions;
    return $testOptions[$name] ?? '';
}

function add_action(...$args): void {}
function add_filter(...$args): void {}
function plugin_dir_url(string $path): string { return 'https://example.invalid/plugin/'; }
function wp_enqueue_script(...$args): void {}
function wp_create_nonce(string $action): string { return 'nonce-for-' . $action; }
function wp_localize_script(string $handle, string $name, array $data): void
{
    global $testLocalizedScripts;
    $testLocalizedScripts[$handle][$name] = $data;
}
function wp_enqueue_style(...$args): void {}
function register_setting(...$args): void {}
function add_options_page(...$args): void {}

function wp_upload_dir($time = null): array
{
    global $testUploadBase;
    return [
        'basedir' => $testUploadBase,
        'baseurl' => 'https://cdn.example.invalid/sites/test',
        'path' => $testUploadBase . '/2026/09',
        'url' => 'https://cdn.example.invalid/sites/test/2026/09',
        'subdir' => '/2026/09',
        'error' => false,
    ];
}

function wp_get_upload_dir(): array
{
    return wp_upload_dir();
}

function wp_normalize_path(string $path): string
{
    return str_replace('\\', '/', $path);
}

function sanitize_text_field($value): string
{
    return trim(strip_tags((string) $value));
}

function wp_attachment_is_image(int $attachmentId): bool
{
    global $testAttachmentImages;
    return $testAttachmentImages[$attachmentId] ?? false;
}

function get_attached_file(int $attachmentId)
{
    global $testAttachmentFiles;
    return $testAttachmentFiles[$attachmentId] ?? false;
}

function wp_get_attachment_metadata(int $attachmentId)
{
    global $testAttachmentMetadata;
    return $testAttachmentMetadata[$attachmentId] ?? [];
}

function get_post_meta(int $attachmentId, string $key, bool $single = false)
{
    global $testAttachmentContexts, $testPostMeta;
    if ($key === '_wp_attachment_context') {
        return $testAttachmentContexts[$attachmentId] ?? '';
    }
    return $testPostMeta[$attachmentId][$key] ?? '';
}

function update_post_meta(int $attachmentId, string $key, $value): void
{
    global $testPostMeta;
    $testPostMeta[$attachmentId][$key] = $value;
}

function delete_post_meta(int $attachmentId, string $key): void
{
    global $testPostMeta;
    unset($testPostMeta[$attachmentId][$key]);
}

function is_wp_error($value): bool
{
    return $value instanceof WP_Error;
}

function wp_get_image_editor(string $file)
{
    global $testImageEditor;
    return $testImageEditor;
}

class WP_Error
{
    public function __construct(private string $code, private string $message) {}
    public function get_error_message(): string { return $this->message; }
}

class FakeFilesystem
{
    public array $files = [];
    public array $hasCalls = [];
    public ?int $failOnWrite = null;
    private int $writeCount = 0;

    public function has(string $path): bool
    {
        $this->hasCalls[] = $path;
        return array_key_exists($path, $this->files);
    }

    public function write(string $path, string $contents): void
    {
        ++$this->writeCount;
        if ($this->failOnWrite === $this->writeCount) {
            throw new RuntimeException('simulated cloud failure');
        }
        $this->files[$path] = ['contents' => $contents, 'public' => false];
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->files[$path]['public'] = $visibility === 'public';
    }

    public function delete(string $path): void
    {
        unset($this->files[$path]);
    }
}

class SpacesSync_Filesystem
{
    public static FakeFilesystem $instance;

    public static function get_instance($key, $secret, $container, $endpoint): FakeFilesystem
    {
        return self::$instance;
    }
}

class FakeImageEditor
{
    public int $resizeCalls = 0;
    public int $saveCalls = 0;
    public int $qualityCalls = 0;

    public function __construct(private array $size = ['width' => 640, 'height' => 480]) {}
    public function get_size(): array { return $this->size; }
    public function set_quality($quality): bool { ++$this->qualityCalls; return true; }
    public function resize($width, $height, $crop): bool { ++$this->resizeCalls; return true; }
    public function save($file): array { ++$this->saveCalls; return ['path' => $file]; }
}

require dirname(__DIR__) . '/includes/spacessync_class.php';

function reset_fixture(): SpacesSync
{
    global $testOptions, $testUploadBase, $testAttachmentFiles, $testAttachmentContexts,
           $testAttachmentImages, $testAttachmentMetadata, $testPostMeta, $testImageEditor,
           $testLocalizedScripts;

    $testOptions['spacessync_storage_file_only'] = 1;
    $testOptions['spacessync_storage_file_delete'] = 0;
    $testOptions['spacessync_filter'] = '';
    $testOptions['upload_path'] = '';
    $testOptions['spacessync_max_width'] = '';
    $testOptions['spacessync_max_height'] = '';
    $testAttachmentFiles = [];
    $testAttachmentContexts = [];
    $testAttachmentImages = [];
    $testAttachmentMetadata = [];
    $testPostMeta = [];
    $testLocalizedScripts = [];
    $testImageEditor = new FakeImageEditor();
    SpacesSync_Filesystem::$instance = new FakeFilesystem();
    $_FILES = [];

    if (is_dir(dirname($testUploadBase))) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname($testUploadBase), FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir(dirname($testUploadBase));
    }
    mkdir($testUploadBase . '/2026/09', 0777, true);

    return new SpacesSync();
}

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$tests = [];

$tests['connection test is restricted to administrators and nonce-protected'] = function (): void {
    $source = file_get_contents(dirname(__DIR__) . '/includes/spacessync_class.php');
    assert_true(
        str_contains($source, "current_user_can('manage_options')"),
        'The connection AJAX action must require the manage_options capability.'
    );
    assert_true(
        str_contains($source, "check_ajax_referer('spacessync_test_connection', 'nonce')"),
        'The connection AJAX action must verify its request nonce.'
    );
};

$tests['connection test localizes its nonce for the settings script'] = function (): void {
    global $testLocalizedScripts;
    $plugin = reset_fixture();
    $plugin->register_scripts();

    assert_same(
        'nonce-for-spacessync_test_connection',
        $testLocalizedScripts['spacessync-core-js']['spacesSyncSettings']['connectionNonce'] ?? null,
        'The settings script must receive the connection-test nonce.'
    );
};

$tests['connection endpoint accepts only DigitalOcean Spaces HTTPS hosts'] = function (): void {
    $plugin = reset_fixture();
    $method = new ReflectionMethod($plugin, 'normalize_spaces_endpoint');

    assert_same(
        'https://nyc3.digitaloceanspaces.com',
        $method->invoke($plugin, 'https://nyc3.digitaloceanspaces.com/'),
        'A valid Spaces endpoint should be normalized.'
    );
    assert_same(false, $method->invoke($plugin, 'http://localhost:9000'), 'Local endpoints must be rejected.');
    assert_same(false, $method->invoke($plugin, 'https://digitaloceanspaces.com.evil.test'), 'Lookalike hosts must be rejected.');
};

$tests['upgrader attachments are never uploaded or deleted'] = function (): void {
    global $testAttachmentFiles, $testAttachmentContexts;
    $plugin = reset_fixture();
    $path = wp_upload_dir()['path'] . '/sample-plugin.zip';
    file_put_contents($path, 'zip payload');
    $testAttachmentFiles[101] = $path;
    $testAttachmentContexts[101] = 'upgrader';

    $plugin->action_add_attachment(101);

    assert_true(file_exists($path), 'The installer package must remain available to WordPress.');
    assert_same([], SpacesSync_Filesystem::$instance->files, 'The installer package must not be copied to Spaces.');
};

$tests['upgrader filename checks never contact Spaces'] = function (): void {
    $plugin = reset_fixture();
    $_FILES['pluginzip'] = ['name' => 'sample-plugin.zip'];

    $result = $plugin->filter_wp_unique_filename(
        'sample-plugin.zip',
        '.zip',
        wp_upload_dir()['path']
    );

    assert_same('sample-plugin.zip', $result, 'WordPress installer filenames must pass through unchanged.');
    assert_same([], SpacesSync_Filesystem::$instance->hasCalls, 'Installer uploads must not query Spaces.');
};

$tests['upgrader cleanup never deletes from Spaces'] = function (): void {
    global $testOptions, $testAttachmentFiles, $testAttachmentContexts;
    reset_fixture();
    $testOptions['spacessync_storage_file_delete'] = 1;
    $plugin = new SpacesSync();
    $path = wp_upload_dir()['path'] . '/sample-theme.zip';
    $testAttachmentFiles[105] = $path;
    $testAttachmentContexts[105] = 'upgrader';
    SpacesSync_Filesystem::$instance->files['sites/test/2026/09/sample-theme.zip'] = [
        'contents' => 'existing package',
        'public' => true,
    ];

    $plugin->action_delete_attachment(105);

    assert_true(
        isset(SpacesSync_Filesystem::$instance->files['sites/test/2026/09/sample-theme.zip']),
        'WordPress upgrader cleanup must not delete anything from Spaces.'
    );
};

$tests['scaled image deletion removes every uploaded remote object'] = function (): void {
    global $testOptions, $testAttachmentFiles, $testAttachmentImages, $testAttachmentMetadata;
    reset_fixture();
    $testOptions['spacessync_storage_file_delete'] = 1;
    $plugin = new SpacesSync();
    $testAttachmentFiles[109] = wp_upload_dir()['path'] . '/photo-scaled.jpg';
    $testAttachmentImages[109] = true;
    $testAttachmentMetadata[109] = [
        'file' => '2026/09/photo-scaled.jpg',
        'original_image' => 'photo.jpg',
        'sizes' => ['thumbnail' => ['file' => 'photo-150x150.jpg']],
    ];
    foreach (['photo-scaled.jpg', 'photo.jpg', 'photo-150x150.jpg'] as $name) {
        SpacesSync_Filesystem::$instance->files['sites/test/2026/09/' . $name] = ['contents' => $name, 'public' => true];
    }

    $plugin->action_delete_attachment(109);

    assert_same([], SpacesSync_Filesystem::$instance->files, 'Deleting a scaled image must remove its scaled file, original, and sizes.');
};

$tests['PDF deletion removes the source and generated preview objects'] = function (): void {
    global $testOptions, $testAttachmentFiles, $testAttachmentMetadata;
    reset_fixture();
    $testOptions['spacessync_storage_file_delete'] = 1;
    $plugin = new SpacesSync();
    $testAttachmentFiles[110] = wp_upload_dir()['path'] . '/document.pdf';
    $testAttachmentMetadata[110] = [
        'sizes' => [
            'full' => ['file' => 'document-pdf.jpg'],
            'thumbnail' => ['file' => 'document-pdf-150x150.jpg'],
        ],
    ];
    foreach (['document.pdf', 'document-pdf.jpg', 'document-pdf-150x150.jpg'] as $name) {
        SpacesSync_Filesystem::$instance->files['sites/test/2026/09/' . $name] = ['contents' => $name, 'public' => true];
    }

    $plugin->action_delete_attachment(110);

    assert_same([], SpacesSync_Filesystem::$instance->files, 'Deleting a PDF must remove its source and generated previews.');
};

$tests['remote collision checks use WordPress actual target directory'] = function (): void {
    global $testUploadBase;
    $plugin = reset_fixture();
    SpacesSync_Filesystem::$instance->files['sites/test/2015/07/photo.jpg'] = ['contents' => 'old', 'public' => true];

    $result = $plugin->filter_wp_unique_filename(
        'photo.jpg',
        '.jpg',
        $testUploadBase . '/2015/07'
    );

    assert_same('photo-1.jpg', $result, 'A collision in an older post directory must increment the filename.');
    assert_same(
        'sites/test/2015/07/photo.jpg',
        SpacesSync_Filesystem::$instance->hasCalls[0] ?? null,
        'The collision lookup must use the directory selected by WordPress.'
    );
};

$tests['relative custom upload path still uses actual absolute target directory'] = function (): void {
    global $testOptions, $testUploadBase;
    reset_fixture();
    $testOptions['upload_path'] = 'wp-content/uploads';
    $plugin = new SpacesSync();
    SpacesSync_Filesystem::$instance->files['sites/test/2015/07/photo.jpg'] = ['contents' => 'old', 'public' => true];

    $result = $plugin->filter_wp_unique_filename('photo.jpg', '.jpg', $testUploadBase . '/2015/07');

    assert_same('photo-1.jpg', $result, 'Relative upload_path options must not discard WordPress actual target directory.');
};

$tests['non-image files wait for metadata generation before offload'] = function (): void {
    global $testAttachmentFiles;
    $plugin = reset_fixture();
    $path = wp_upload_dir()['path'] . '/document.pdf';
    file_put_contents($path, 'pdf payload');
    $testAttachmentFiles[102] = $path;

    $plugin->action_add_attachment(102);

    assert_true(file_exists($path), 'A new non-image attachment must remain local while WordPress generates metadata.');
    assert_same([], SpacesSync_Filesystem::$instance->files, 'The add_attachment hook must not offload non-images early.');
};

$tests['metadata completion offloads a non-image and then removes its local file'] = function (): void {
    global $testAttachmentFiles;
    $plugin = reset_fixture();
    $path = wp_upload_dir()['path'] . '/document.pdf';
    file_put_contents($path, 'pdf payload');
    $testAttachmentFiles[103] = $path;

    $plugin->filter_wp_generate_attachment_metadata([], 103, 'create');

    assert_true(!file_exists($path), 'The local file should be removed only after metadata processing and cloud success.');
    assert_same(
        'pdf payload',
        SpacesSync_Filesystem::$instance->files['sites/test/2026/09/document.pdf']['contents'] ?? null,
        'The completed attachment must be stored at the expected remote path.'
    );
};

$tests['partial cloud failure retains every local attachment file'] = function (): void {
    global $testAttachmentFiles, $testPostMeta;
    $plugin = reset_fixture();
    $original = wp_upload_dir()['path'] . '/photo.jpg';
    $thumbnail = wp_upload_dir()['path'] . '/photo-150x150.jpg';
    file_put_contents($original, 'original');
    file_put_contents($thumbnail, 'thumbnail');
    $testAttachmentFiles[104] = $original;
    SpacesSync_Filesystem::$instance->failOnWrite = 2;

    $metadata = [
        'file' => '2026/09/photo.jpg',
        'sizes' => ['thumbnail' => ['file' => 'photo-150x150.jpg']],
    ];
    $plugin->filter_wp_generate_attachment_metadata($metadata, 104, 'create');

    assert_true(file_exists($original), 'The original must remain local when any cloud write fails.');
    assert_true(file_exists($thumbnail), 'Generated sizes must remain local when any cloud write fails.');
    assert_true(
        !empty($testPostMeta[104]['_spaces_sync_error']),
        'The attachment must record a sync error for diagnosis.'
    );
};

$tests['scaled images include the preserved original in one safe batch'] = function (): void {
    global $testAttachmentFiles;
    $plugin = reset_fixture();
    $scaled = wp_upload_dir()['path'] . '/photo-scaled.jpg';
    $original = wp_upload_dir()['path'] . '/photo.jpg';
    file_put_contents($scaled, 'scaled');
    file_put_contents($original, 'original');
    $testAttachmentFiles[106] = $scaled;

    $plugin->filter_wp_generate_attachment_metadata([
        'file' => '2026/09/photo-scaled.jpg',
        'original_image' => 'photo.jpg',
        'sizes' => [],
    ], 106, 'create');

    assert_same(
        'original',
        SpacesSync_Filesystem::$instance->files['sites/test/2026/09/photo.jpg']['contents'] ?? null,
        'The full-resolution original must be copied to Spaces.'
    );
    assert_true(!file_exists($scaled) && !file_exists($original), 'Local scaled and original images are removed only after both uploads succeed.');
};

$tests['missing expected file prevents every local deletion'] = function (): void {
    global $testAttachmentFiles, $testPostMeta;
    $plugin = reset_fixture();
    $original = wp_upload_dir()['path'] . '/photo.jpg';
    file_put_contents($original, 'original');
    $testAttachmentFiles[107] = $original;

    $plugin->filter_wp_generate_attachment_metadata([
        'file' => '2026/09/photo.jpg',
        'sizes' => ['thumbnail' => ['file' => 'missing-150x150.jpg']],
    ], 107, 'create');

    assert_true(file_exists($original), 'No local attachment files may be deleted when an expected size is missing.');
    assert_true(!empty($testPostMeta[107]['_spaces_sync_error']), 'A missing expected file must be recorded as a sync error.');
};

$tests['invalid ignore regex retains local files and records an error'] = function (): void {
    global $testOptions, $testAttachmentFiles, $testPostMeta;
    reset_fixture();
    $testOptions['spacessync_filter'] = '[';
    $plugin = new SpacesSync();
    $path = wp_upload_dir()['path'] . '/photo.jpg';
    file_put_contents($path, 'original');
    $testAttachmentFiles[108] = $path;

    $plugin->filter_wp_generate_attachment_metadata(['file' => '2026/09/photo.jpg'], 108, 'create');

    assert_true(file_exists($path), 'An invalid ignore regex must fail safely without deleting the local file.');
    assert_true(!empty($testPostMeta[108]['_spaces_sync_error']), 'An invalid ignore regex must be recorded as a sync error.');
};

$tests['blank resize limits do not trigger resize'] = function (): void {
    global $testImageEditor;
    $plugin = reset_fixture();

    $plugin->processUploadedImages([
        'file' => wp_upload_dir()['path'] . '/photo.jpg',
        'url' => 'https://cdn.example.invalid/sites/test/2026/09/photo.jpg',
        'type' => 'image/jpeg',
    ]);

    assert_same(0, $testImageEditor->resizeCalls, 'Blank maximum dimensions must disable resizing.');
    assert_same(1, $testImageEditor->saveCalls, 'JPEG compression should still save the image once.');
};

$tests['image editor errors leave upload data unchanged'] = function (): void {
    global $testImageEditor;
    $plugin = reset_fixture();
    $testImageEditor = new WP_Error('invalid_image', 'cannot load image');
    $upload = [
        'file' => wp_upload_dir()['path'] . '/broken.jpg',
        'url' => 'https://cdn.example.invalid/sites/test/2026/09/broken.jpg',
        'type' => 'image/jpeg',
    ];

    $result = $plugin->processUploadedImages($upload);

    assert_same($upload, $result, 'An image-editor failure must not turn the upload filter into a fatal error.');
};

$failures = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS: {$name}\n";
    } catch (Throwable $error) {
        ++$failures;
        echo "FAIL: {$name}\n{$error->getMessage()}\n";
    }
}

exit($failures === 0 ? 0 : 1);
