## Spaces Sync

This WordPress plugin syncs your media library with [DigitalOcean Spaces](https://m.do.co/c/8ed78a03ae44) Container and optionally compresses and resizes them.

### Description

This WordPress plugin syncs your media library with [DigitalOcean Spaces](https://m.do.co/c/8ed78a03ae44) Container. It allows you to simuntanously upload and delete files, replacing public media URL with relative cloud storage links. You can keep local copy of the files, or delete them and keep files only in your Digital Ocean Space.

In order to use this plugin, you'll need a DigitalOcean Spaces API key. [Sign up for an account](https://m.do.co/c/8ed78a03ae44), then visit [Applications & API](https://cloud.digitalocean.com/account/api/spaces) and click `Generate New Key`.

In addition to syncing to your bucket, there are also options for "on-the-fly" image processing. Select a value for compressing images as you upload, and set a max width and height. Combined with the power of the Digital Ocean CDN, this will ensure you have incredibly fast image downloads to the client.

### Installation

#### Developers

##### Option A. (remote development)

1. Clone the repo.
2. Run `npm install` to install Prettier for some nice formatting help.
3. Run `composer install` to install the PHP dependencies.
4. Make any changes as desired.
5. Run `./pack-for-deploy.sh` from the root folder to create the installable zip file.
6. Install the zip file via the WordPress plugin installer.

##### Option B. (local development)

1. Clone the repo into the plugins folder in your WordPress environment
2. Run `npm install` to install Prettier for some nice formatting help.
3. Run `composer install` to install the PHP dependencies.
4. Activate the plugin via the WordPress plugin installer.

#### Non-Developers

1. Download the `space-sync.zip` file from this repo.
2. Install the zip file via the WordPress plugin installer.
