=== Coywolf Custom Blocks — PHP Templates ===
Contributors: jonhenshaw
Tags: blocks, custom blocks, php, templates
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Companion for Coywolf Custom Blocks that executes PHP inside block templates. GitHub-distributed only.

== Description ==

Coywolf Custom Blocks renders block templates authored in the WordPress
admin. Templates written as plain HTML — including {{field}}
substitution, {{#if}} / {{#each}} logic, scripts, and iframes — render
through the main plugin alone. Templates containing PHP need this
companion: the main plugin detects a PHP open tag in the admin-authored
template and hands the substituted markup to this plugin's executor
through the coywolf_custom_blocks_execute_php_template filter. Without
the companion, those blocks render an explanatory HTML comment instead.

Why a separate plugin? The WordPress.org plugin directory forbids
executing user-supplied code stored in the database, in any form.
Splitting the executor out keeps the main plugin eligible for the
directory while sites that want in-admin PHP templates — the same trust
level WordPress itself grants administrators through Appearance ->
Theme File Editor — install this companion from GitHub.

How it executes: the substituted template is written to a
content-hashed cache file under
wp-content/uploads/coywolf-custom-blocks/templates/ (protected by a
"Deny from all" .htaccess and an index.php) and include()d with output
buffering. Identical templates share one cache file; editing a template
produces a new hash; stale files are pruned after 30 days and the whole
cache is removed on uninstall.

== Installation ==

1. Install and activate Coywolf Custom Blocks 1.0.70 or later:
   https://github.com/coywolf-llc/custom-blocks
2. Download coywolf-custom-blocks-php-templates.zip from the latest
   release: https://github.com/coywolf-llc/custom-blocks-php-templates/releases/latest
3. In WordPress, go to Plugins -> Add New -> Upload Plugin, upload the
   zip, and click Activate.

Updates surface on Dashboard -> Updates through the built-in GitHub
self-updater (downloads restricted to GitHub-owned hosts).

== Frequently Asked Questions ==

= Who can write PHP templates? =

Editing a coywolf_custom_block post requires manage_options — the same
capability that gates Appearance -> Theme File Editor. Field values
entered by post authors pass through wp_kses_post() and cannot
introduce PHP; only the admin-authored template itself executes.

= What happens if I deactivate or delete this plugin? =

Nothing breaks destructively: blocks whose templates contain PHP render
an HTML comment explaining the companion is needed, and everything else
renders normally. Reinstalling the companion restores PHP rendering
immediately.

= Will this ever be on WordPress.org? =

No — executing database-stored PHP is precisely what the directory
disallows. This companion exists so the main plugin can be compliant
while this capability remains available from GitHub.

== Changelog ==

= 1.0.0 =
* Initial release: the PHP-template executor moved here from Coywolf Custom Blocks <= 1.0.69, hooked to the main plugin's coywolf_custom_blocks_execute_php_template filter. Same cache location, same trust model, same output.
