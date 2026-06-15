<?php
/**
 * Plugin Name: Coywolf Custom Blocks — PHP Templates
 * Plugin URI: https://github.com/coywolf-llc/custom-blocks-php-templates
 * Description: Companion for Coywolf Custom Blocks that executes PHP inside block templates. Distributed on GitHub only — executing PHP stored in the database is not permitted in the WordPress.org plugin directory.
 * Version: 1.0.1
 * Requires at least: 6.0
 * Requires PHP: 7.0
 * Author: Coywolf
 * Author URI: https://coywolf.com/jon-henshaw/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: coywolf-custom-blocks-php-templates
 * Update URI: https://github.com/coywolf-llc/custom-blocks-php-templates
 *
 * @package CoywolfCustomBlocksPHPTemplates
 *
 * Coywolf Custom Blocks — PHP Templates
 * Copyright (C) 2026 Coywolf LLC
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License, version 2, as published
 * by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, see https://www.gnu.org/licenses/gpl-2.0.html.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COYWOLF_CCBPT_VERSION', '1.0.1' );
define( 'COYWOLF_CCBPT_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-github-updater.php';
require_once __DIR__ . '/includes/class-template-executor.php';

// Pull updates from GitHub Releases via the standard WP update flow.
( new Coywolf_CCBPT_GitHub_Updater( __FILE__, COYWOLF_CCBPT_VERSION ) )->init();

// Hook the executor into Coywolf Custom Blocks' render pipeline. The
// parent plugin applies `coywolf_custom_blocks_execute_php_template`
// when (and only when) a block's admin-authored template contains PHP
// tags; without this companion it falls back to an HTML comment.
( new Coywolf_CCBPT_Template_Executor() )->init();

/**
 * Surface a notice when the parent plugin isn't active — the executor
 * idles harmlessly without it, but a site admin who installed this
 * companion on its own should learn why nothing is happening.
 */
add_action(
	'admin_notices',
	function () {
		if ( function_exists( 'coywolf_custom_blocks' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Coywolf Custom Blocks — PHP Templates is active, but the Coywolf Custom Blocks plugin is not. Install and activate Coywolf Custom Blocks for this companion to do anything.', 'coywolf-custom-blocks-php-templates' )
		);
	}
);
