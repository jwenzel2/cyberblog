# CyberBlog

CyberBlog is a PHP/MariaDB web app for a cybersecurity-themed blog with:

- public blog pages
- a private admin area
- passkey login with recovery codes
- a standalone `public/installer.php`
- WordPress import from a `.tar.gz` archive containing a SQL dump and site files

## Runtime requirements

- PHP 8.2+
- MariaDB 10.6+
- PHP extensions: `pdo_mysql`, `openssl`, `mbstring`, `json`, `fileinfo`, `phar`, `session`

## Local layout

- `public/index.php`: app front controller
- `public/installer.php`: install/bootstrap workflow
- `app/`: application code
- `database/schema.sql`: schema initialization
- `storage/`: runtime data, media, logs, temp files

## Deployment notes

This workspace does not include a local PHP runtime, so the app was authored without executing it here. Deploy it to a PHP-capable host, point the web root at `public/`, visit `/installer.php`, and complete the setup flow there.

## Suggested first validation on the server

1. Open `/installer.php` and confirm all extension checks pass.
2. Install against a fresh MariaDB database and create the first admin.
3. Register the first passkey and save the generated recovery codes.
4. Create a nested category tree and a published post with a featured image.
5. Import a representative WordPress `.tar.gz` archive and verify:
   - posts appear with expected slugs
   - nested categories are preserved
   - featured images and inline images load from `/media/...`
   - import history records the archive and counters

## Important implementation notes

- This codebase is self-contained PHP and does not rely on a generated Laravel runtime, because PHP/Composer were unavailable in the authoring environment.
- WebAuthn support is implemented directly with browser passkey APIs and server-side attestation/assertion verification for ES256 credentials.
- The WordPress importer targets posts, categories, attachments, featured images, and inline media URL rewriting. It intentionally does not migrate comments, tags, menus, or plugin data.
