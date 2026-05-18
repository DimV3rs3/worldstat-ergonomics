<?php
/**
 * Загрузка уровней (country, city, territory) по манифестам.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Level_Registry {

	/** @var array<string, array<string, mixed>> */
	private static $manifests = [];

	/**
	 * @param string[] $level_ids
	 */
	public static function boot( array $level_ids ): void {
		foreach ( $level_ids as $level_id ) {
			self::load_level( (string) $level_id );
		}
	}

	public static function load_level( string $level_id ): void {
		$level_id = sanitize_key( $level_id );
		if ( $level_id === '' || isset( self::$manifests[ $level_id ] ) ) {
			return;
		}

		$dir = WSERGO_DIR . 'levels/' . $level_id . '/';
		$manifest_file = $dir . 'level.php';
		if ( ! is_readable( $manifest_file ) ) {
			return;
		}

		/** @var array<string, mixed> $manifest */
		$manifest = require $manifest_file;
		self::$manifests[ $level_id ] = $manifest;

		foreach ( (array) ( $manifest['requires'] ?? [] ) as $rel ) {
			$path = $dir . ltrim( (string) $rel, '/' );
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}

		$bootstrap = isset( $manifest['bootstrap'] ) ? (string) $manifest['bootstrap'] : '';
		if ( $bootstrap !== '' ) {
			$boot_path = $dir . ltrim( $bootstrap, '/' );
			if ( is_readable( $boot_path ) ) {
				require_once $boot_path;
			}
		}
	}

	public static function url( string $level_id, string $relative = '' ): string {
		return WSERGO_URL . 'levels/' . sanitize_key( $level_id ) . '/' . ltrim( $relative, '/' );
	}

	public static function path( string $level_id, string $relative = '' ): string {
		return WSERGO_DIR . 'levels/' . sanitize_key( $level_id ) . '/' . ltrim( $relative, '/' );
	}

	public static function asset_version( string $level_id, string $relative ): string {
		$path = self::path( $level_id, $relative );
		if ( is_readable( $path ) ) {
			return (string) filemtime( $path );
		}
		return defined( 'WSERGO_VERSION' ) ? (string) WSERGO_VERSION : '1';
	}

	/**
	 * Подключить admin/panel.php уровня.
	 *
	 * @param string               $level_id country|city|territory|…
	 * @param array<string, mixed> $vars     Переменные для шаблона (из render_settings_page).
	 */
	public static function has_admin_panel( string $level_id ): bool {
		$level_id = sanitize_key( $level_id );
		return $level_id !== '' && is_readable( self::path( $level_id, 'admin/panel.php' ) );
	}

	/**
	 * ID уровней с подключаемой панелью admin/panel.php (порядок — levels-registry).
	 *
	 * @return string[]
	 */
	public static function ids_with_admin_panel(): array {
		$out = [];
		foreach ( wsergo_registered_level_ids() as $level_id ) {
			$level_id = sanitize_key( (string) $level_id );
			if ( $level_id !== '' && self::has_admin_panel( $level_id ) ) {
				$out[] = $level_id;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_manifest( string $level_id ): ?array {
		$level_id = sanitize_key( $level_id );
		if ( $level_id === '' || ! isset( self::$manifests[ $level_id ] ) ) {
			return null;
		}
		return self::$manifests[ $level_id ];
	}

	/**
	 * Заголовок вкладки в настройках (admin_nav в манифесте или фильтр).
	 */
	public static function get_admin_nav_label( string $level_id ): string {
		$level_id = sanitize_key( $level_id );
		$manifest = self::get_manifest( $level_id );
		if ( is_array( $manifest ) && ! empty( $manifest['admin_nav'] ) ) {
			$label = (string) $manifest['admin_nav'];
		} elseif ( is_array( $manifest ) && ! empty( $manifest['label'] ) ) {
			/* translators: %s: level name (Страна, Город, …) */
			$label = sprintf( __( 'Эргономичность (%s)', 'worldstat-ergonomics' ), (string) $manifest['label'] );
		} else {
			$label = ucfirst( $level_id );
		}
		/**
		 * @param string $label    Текст вкладки.
		 * @param string $level_id country|city|…
		 */
		return (string) apply_filters( 'wsergo_level_admin_nav_label', $label, $level_id );
	}

	public static function include_admin_panel( string $level_id, array $vars = [] ): bool {
		$level_id = sanitize_key( $level_id );
		if ( ! self::has_admin_panel( $level_id ) ) {
			return false;
		}
		$path = self::path( $level_id, 'admin/panel.php' );
		/**
		 * Перед выводом панели настроек уровня.
		 *
		 * @param string               $level_id country|city|territory|…
		 * @param array<string, mixed> $vars
		 */
		do_action( 'wsergo_before_admin_panel', $level_id, $vars );
		if ( ! empty( $vars ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- шаблон panel.php ожидает локальные переменные.
			extract( $vars, EXTR_SKIP );
		}
		include $path;
		do_action( 'wsergo_after_admin_panel', $level_id, $vars );
		return true;
	}
}
