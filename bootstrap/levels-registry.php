<?php
/**
 * Единственный файл для правки при добавлении нового уровня.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return string[]
 */
function wsergo_registered_level_ids(): array {
	$levels = [ 'country', 'city' ];
	/**
	 * @param string[] $levels
	 */
	return (array) apply_filters( 'wsergo_levels', $levels );
}
