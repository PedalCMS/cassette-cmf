<?php
/**
 * Strauss-style prefixed copy generator
 *
 * Test helper: copies a source tree and rewrites a namespace prefix the way
 * Strauss does (namespace declarations, `use` imports and fully qualified
 * references), then loads the copy with its own classmap autoloader. Lets a
 * test load a second, namespace-prefixed copy of the library next to the
 * plain one.
 *
 * @package PedalCMS\CassetteCMF\Tests
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- Test helper working on a throwaway directory.

/**
 * Class Prefixed_Copy
 */
class Prefixed_Copy {

	/**
	 * Copy a source tree and rewrite the given namespace roots.
	 *
	 * @param string               $source Directory to copy (for example `src/`).
	 * @param string               $dest   Destination directory (created, replaced if present).
	 * @param array<string,string> $map    Namespace root => replacement root (no leading or trailing backslash).
	 * @return void
	 */
	public static function generate( string $source, string $dest, array $map ): void {
		self::remove( $dest );
		mkdir( $dest, 0777, true );

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$relative = substr( $item->getPathname(), strlen( rtrim( $source, '/' ) ) + 1 );
			$target   = rtrim( $dest, '/' ) . '/' . $relative;

			if ( $item->isDir() ) {
				mkdir( $target, 0777, true );
				continue;
			}

			$contents = (string) file_get_contents( $item->getPathname() );

			if ( 'php' === $item->getExtension() ) {
				$contents = self::rewrite( $contents, $map );
			}

			file_put_contents( $target, $contents );
		}
	}

	/**
	 * Rewrite namespace roots in PHP source.
	 *
	 * Only whole namespace segments are matched, so `PedalCMS\CassetteCMF`
	 * does not touch `PedalCMS\CassetteCMFBlocks`.
	 *
	 * @param string               $php PHP source.
	 * @param array<string,string> $map Namespace root => replacement root.
	 * @return string
	 */
	public static function rewrite( string $php, array $map ): string {
		foreach ( $map as $from => $to ) {
			$pattern = '/(?<![A-Za-z0-9_\\\\])' . preg_quote( $from, '/' ) . '(?![A-Za-z0-9_])/';
			$php     = (string) preg_replace( $pattern, str_replace( '\\', '\\\\', $to ), $php );
		}

		return $php;
	}

	/**
	 * Register a classmap autoloader for a (rewritten) tree.
	 *
	 * @param string $dir Directory to scan.
	 * @return array<string,string> The classmap (class name => file).
	 */
	public static function autoload( string $dir ): array {
		$classmap = self::classmap( $dir );

		spl_autoload_register(
			static function ( $class_name ) use ( $classmap ) {
				$key = ltrim( $class_name, '\\' );
				if ( isset( $classmap[ $key ] ) ) {
					require_once $classmap[ $key ];
				}
			}
		);

		return $classmap;
	}

	/**
	 * Build a classmap by scanning for class, interface and trait declarations.
	 *
	 * @param string $dir Directory to scan.
	 * @return array<string,string>
	 */
	public static function classmap( string $dir ): array {
		$classmap = [];
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$contents  = (string) file_get_contents( $file->getPathname() );
			$namespace = '';
			if ( preg_match( '/^namespace\s+([^;{\s]+)/m', $contents, $match ) ) {
				$namespace = $match[1] . '\\';
			}

			if ( preg_match_all( '/^(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+(\w+)/m', $contents, $matches ) ) {
				foreach ( $matches[1] as $name ) {
					$classmap[ $namespace . $name ] = $file->getPathname();
				}
			}
		}

		return $classmap;
	}

	/**
	 * Remove a directory tree.
	 *
	 * @param string $dir Directory.
	 * @return void
	 */
	public static function remove( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $dir );
	}
}
