<?php
/**
 * Uninstall cleanup for Coywolf Custom Blocks — PHP Templates.
 *
 * Removes the GitHub-updater release cache and the compiled-template
 * cache directory under uploads. Block definitions themselves belong
 * to the parent Coywolf Custom Blocks plugin and are untouched —
 * deleting this companion only removes the ability to EXECUTE PHP in
 * templates (the parent falls back to an HTML comment for those
 * blocks), so the user can reinstall later and everything resumes.
 *
 * @package CoywolfCustomBlocksPHPTemplates
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_site_transient( 'coywolf_ccbpt_gh_release' );
delete_site_transient( 'coywolf_ccbpt_gh_release_neg' );
delete_site_transient( 'coywolf_ccbpt_gh_release_err' );

// Remove the compiled-template cache: every tpl-*.php plus the
// protective .htaccess / index.php, then the directory itself (and its
// parent, if this left it empty). All regenerable — the source of
// truth is the block post_content in the database.
$coywolf_ccbpt_uploads = wp_get_upload_dir();
if ( is_array( $coywolf_ccbpt_uploads ) && ! empty( $coywolf_ccbpt_uploads['basedir'] ) ) {
	$coywolf_ccbpt_dir = rtrim( $coywolf_ccbpt_uploads['basedir'], '/' ) . '/coywolf-custom-blocks/templates';
	if ( is_dir( $coywolf_ccbpt_dir ) ) {
		foreach ( (array) glob( $coywolf_ccbpt_dir . '/tpl-*.php' ) as $coywolf_ccbpt_file ) {
			wp_delete_file( $coywolf_ccbpt_file );
		}
		foreach ( [ '/.htaccess', '/index.php' ] as $coywolf_ccbpt_name ) {
			if ( file_exists( $coywolf_ccbpt_dir . $coywolf_ccbpt_name ) ) {
				wp_delete_file( $coywolf_ccbpt_dir . $coywolf_ccbpt_name );
			}
		}
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		global $wp_filesystem;
		if ( WP_Filesystem() ) {
			$wp_filesystem->rmdir( $coywolf_ccbpt_dir );
			// Parent dir is shared namespace; rmdir is a no-op unless empty.
			$wp_filesystem->rmdir( dirname( $coywolf_ccbpt_dir ) );
		}
	}
}
