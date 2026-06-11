<?php
/**
 * Executes PHP-containing Coywolf Custom Blocks templates.
 *
 * This is the mechanism that lived in the parent plugin's
 * Coywolf\CustomBlocks\Blocks\TemplateEditor through v1.0.69, moved
 * here verbatim so the parent can be WordPress.org-compliant: the
 * directory forbids executing user-supplied code stored in the
 * database, so the capability ships as this GitHub-only companion.
 *
 * Contract: the parent applies
 *
 *     apply_filters( 'coywolf_custom_blocks_execute_php_template', null, $markup )
 *
 * for templates whose RAW admin-authored markup contains a PHP open
 * tag, after `{{field}}` substitution and template-logic interpretation
 * have already run. Returning a string short-circuits the parent's
 * HTML-comment fallback and is echoed as the block's output.
 *
 * Security: this evaluates arbitrary PHP from the block's post_content.
 * Editing a `coywolf_custom_block` requires `manage_options`, the same
 * capability as Appearance → Theme File Editor, so the trust boundary
 * is identical to what WordPress itself grants admins by default.
 * Network/host operators who do not trust their site admins should
 * simply not install this companion (the parent renders everything
 * except PHP without it).
 *
 * @package CoywolfCustomBlocksPHPTemplates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Coywolf_CCBPT_Template_Executor {

	/**
	 * Number of days a compiled `tpl-*.php` file may go untouched before
	 * `maybe_prune_cache()` reaps it.
	 *
	 * @var int
	 */
	const CACHE_TTL_DAYS = 30;

	/**
	 * Register the executor on the parent plugin's filter.
	 */
	public function init() {
		add_filter( 'coywolf_custom_blocks_execute_php_template', [ $this, 'execute' ], 10, 2 );
	}

	/**
	 * Filter callback: execute the substituted template markup.
	 *
	 * @param string|null $output  A previous executor's output, or null.
	 * @param string      $content Markup with embedded PHP tags, already
	 *                             field-substituted by the parent.
	 * @return string The PHP output.
	 */
	public function execute( $output, $content ) {
		if ( is_string( $output ) ) {
			// Another executor already handled this template.
			return $output;
		}
		return $this->execute_php( (string) $content );
	}

	/**
	 * Execute PHP-containing markup by writing it to a cached file
	 * under uploads/coywolf-custom-blocks/templates/ and `include`ing
	 * it with output buffering.
	 *
	 * Caching: filename is a hash of the rendered content. Two
	 * identical templates resolve to the same file; modifying a
	 * block's Custom HTML produces a new hash and a new file. Old
	 * cached files are reaped opportunistically by
	 * `maybe_prune_cache()` on the next cache-miss write (and the whole
	 * directory is cleared on uninstall).
	 *
	 * Failure modes: if the cache directory can't be created or the
	 * file can't be written (permission errors, disk full,
	 * open_basedir restrictions), fall back to echoing the markup as
	 * literal text. Better to show the raw PHP source than to
	 * silently lose the block's output.
	 *
	 * @param string $content Markup with embedded PHP tags.
	 * @return string The PHP output.
	 */
	protected function execute_php( $content ) {
		$cache_dir = $this->get_template_cache_dir();
		if ( '' === $cache_dir ) {
			return $content;
		}

		$hash = md5( $content );
		$file = $cache_dir . '/tpl-' . $hash . '.php';

		if ( ! file_exists( $file ) ) {
			// A cache miss is the only moment the directory grows, so
			// it's also where we prune stale files — keeps the dir from
			// accumulating one file per unique render over a site's
			// lifetime without needing a cron event.
			$this->maybe_prune_cache( $cache_dir );

			if ( false === file_put_contents( $file, $content, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				return $content;
			}
		}

		ob_start();
		try {
			include $file; // phpcs:ignore WordPress.PHP.IncludingFile -- runtime-cached, admin-authored template
		} catch ( \Throwable $err ) {
			ob_end_clean();
			return '<!-- Coywolf Custom Blocks: PHP error in Custom HTML — ' . esc_html( $err->getMessage() ) . ' -->';
		}
		return (string) ob_get_clean();
	}

	/**
	 * Delete compiled template files that haven't been modified in the
	 * last {@see CACHE_TTL_DAYS} days.
	 *
	 * Runs at most once per request (guarded by a static flag) and only
	 * from a cache-miss write path, so the cost — one `glob()` plus a
	 * handful of `unlink()`s — is amortised against the rarer event of a
	 * never-before-seen render. If a still-used template happens to be
	 * older than the TTL it's reaped and simply rewritten on its next
	 * render (a one-time cache miss), so correctness is unaffected.
	 *
	 * @param string $cache_dir Absolute path returned by get_template_cache_dir().
	 */
	protected function maybe_prune_cache( $cache_dir ) {
		static $pruned = false;
		if ( $pruned ) {
			return;
		}
		$pruned = true;

		$files = glob( $cache_dir . '/tpl-*.php' );
		if ( empty( $files ) ) {
			return;
		}

		$cutoff = time() - ( self::CACHE_TTL_DAYS * DAY_IN_SECONDS );
		foreach ( $files as $file ) {
			$mtime = @filemtime( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a racing unlink is fine.
			if ( false !== $mtime && $mtime < $cutoff ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Path to the directory where compiled template files live.
	 *
	 * Kept at the parent plugin's historical location
	 * (uploads/coywolf-custom-blocks/templates) so existing cached
	 * files keep working across the move to this companion.
	 *
	 * Created lazily on first need. Returns '' if the directory can't
	 * be created (caller falls back to echoing markup as text).
	 *
	 * @return string Absolute filesystem path, or '' on failure.
	 */
	protected function get_template_cache_dir() {
		$uploads = wp_get_upload_dir();
		if ( ! isset( $uploads['basedir'] ) || ! is_string( $uploads['basedir'] ) ) {
			return '';
		}
		$dir = rtrim( $uploads['basedir'], '/' ) . '/coywolf-custom-blocks/templates';
		if ( file_exists( $dir ) ) {
			return is_dir( $dir ) && wp_is_writable( $dir ) ? $dir : '';
		}
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		// Drop a tiny .htaccess + index.php to make sure the directory
		// isn't directly browsable and individual files aren't served
		// raw by web servers that haven't been configured to deny .php
		// in uploads. The template files are still `include`d from
		// PHP-land — the deny rules just stop a curious visitor from
		// hitting them via HTTP.
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $dir . '/.htaccess', "Order Deny,Allow\nDeny from all\n" );
		file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		// phpcs:enable
		return $dir;
	}
}
