<?php
/*
Plugin Name: BEA - WP-CLI Media Cleanup
Version: 1.0
Plugin URI: https://github.com/BeAPI/wp-cli-media-cleanup
Description: A WP-CLI command for remove invalid (nonexistent files) medias from WordPress.
Author: Be API
Author URI: https://beapi.fr
Domain Path: languages

----

Copyright 2018 Be API (human@beapi.fr)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

namespace BEA\WP_CLI_Media_Cleanup;

use WP_CLI;

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

class Command {
	/**
	 * Clear the WP object cache after this many regenerations/imports.
	 *
	 * @var integer
	 */
	const WP_CLEAR_OBJECT_CACHE_INTERVAL = 500;

	/**
	 * Remove invalid (nonexistent files) medias from WordPress
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Run the entire cleanup operation and show report, but don't save
	 * changes to the database.
	 *
	 * ## EXAMPLES
	 *
	 *     # Cleanup medias
	 *     $ wp media cleanup
	 *
	 *     # Run cleanup operation but dont delete medias in database
	 *     $ wp media cleanup --dry-run
	 *
	 * @param $args
	 * @param array $assoc_args
	 */
	public static function cleanup( $args, $assoc_args = array() ) {
		$dry_run = false;
		if ( isset( $assoc_args['dry-run'] ) ) {
			$dry_run = true;
		}

		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => - 1,
			'fields'         => 'ids',
		);
		$medias     = new \WP_Query( $query_args );
		$count      = $medias->post_count;
		if ( ! $count ) {
			WP_CLI::warning( 'No media found.' );

			return;
		}

		WP_CLI::log( sprintf( 'Found %1$d %2$s to check & cleanup.', $count, _n( 'media', 'medias', $count ) ) );

		$number = $successes = $missings = $errors = 0;

		$progress = \WP_CLI\Utils\make_progress_bar( 'Check if media exists', $count );
		foreach ( $medias->posts as $id ) {
			$number ++;
			if ( 0 === $number % self::WP_CLEAR_OBJECT_CACHE_INTERVAL ) {
				WP_CLI\Utils\wp_clear_object_cache();
			}

			$fullsize_path = get_attached_file( $id ); // Full path
			if ( empty( $fullsize_path ) ) {
				$errors ++;
			} else {
				if ( is_file( $fullsize_path ) ) {
					$successes ++;
				} else {
					$missings ++;

					if ( false === $dry_run ) {
						wp_delete_attachment( $id, true );
					}
				}
			}

			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success( sprintf( 'Found %1$d %2$s, %3$d %4$s, %5$d %6$s and %7$d %8$s.',
			$count, _n( 'media', 'medias', $count ),
			$successes, _n( 'valid media', 'valid medias', $successes ),
			$missings, _n( 'cleanup media', 'cleanup medias', $missings ),
			$errors, _n( 'skip media', 'skip medias', $errors )
		) );
	}
}

WP_CLI::add_command( 'media cleanup', array( 'BEA\WP_CLI_Media_Cleanup\Command', 'cleanup' ) );