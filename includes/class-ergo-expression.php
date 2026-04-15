<?php
/**
 * Безопасный разбор и вычисление математических выражений (DSL) без eval.
 *
 * Допускаются: числа, + - * / ^, скобки, идентификаторы из белого списка,
 * функции: min, max, avg, clamp, abs, sqrt.
 *
 * @package WorldStatErgonomics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSErgo_Expression {

	public const FUNCTIONS = [ 'min', 'max', 'avg', 'clamp', 'abs', 'sqrt' ];

	/**
	 * @param array<string, string> $allowed_ids Разрешённые имена переменных.
	 * @return array{ok:bool, error?:string}
	 */
	public static function validate( string $expr, array $allowed_ids ): array {
		$expr = trim( $expr );
		if ( $expr === '' ) {
			return [ 'ok' => true ];
		}
		$whitelist = array_fill_keys( array_keys( $allowed_ids ), true );
		try {
			$tokens = self::tokenize( $expr );
			self::parse_add( $tokens, 0, count( $tokens ), $whitelist, null );
			return [ 'ok' => true ];
		} catch ( Exception $e ) {
			return [ 'ok' => false, 'error' => $e->getMessage() ];
		}
	}

	/**
	 * @param array<string, float|int> $vars Значения переменных.
	 */
	public static function evaluate( string $expr, array $vars ): float {
		$expr = trim( $expr );
		if ( $expr === '' ) {
			throw new InvalidArgumentException( 'Empty expression' );
		}
		$whitelist = array_fill_keys( array_keys( $vars ), true );
		$tokens    = self::tokenize( $expr );
		[ $val, $pos ] = self::parse_add( $tokens, 0, count( $tokens ), $whitelist, $vars );
		if ( $pos !== count( $tokens ) ) {
			throw new InvalidArgumentException( 'Unexpected token after expression' );
		}
		return (float) $val;
	}

	/**
	 * @return list<array{type:string,value?:string|float,num?:float}>
	 */
	private static function tokenize( string $expr ): array {
		$tokens = [];
		$n      = strlen( $expr );
		$i      = 0;
		while ( $i < $n ) {
			$c = $expr[ $i ];
			if ( ctype_space( $c ) ) {
				++$i;
				continue;
			}
			if ( ctype_digit( $c ) || ( '.' === $c && $i + 1 < $n && ctype_digit( $expr[ $i + 1 ] ) ) ) {
				$start = $i;
				if ( '.' === $expr[ $i ] ) {
					++$i;
				}
				while ( $i < $n && ctype_digit( $expr[ $i ] ) ) {
					++$i;
				}
				if ( $i < $n && '.' === $expr[ $i ] ) {
					++$i;
					while ( $i < $n && ctype_digit( $expr[ $i ] ) ) {
						++$i;
					}
				}
				$num = (float) substr( $expr, $start, $i - $start );
				$tokens[] = [ 'type' => 'NUM', 'num' => $num ];
				continue;
			}
			if ( '(' === $c ) {
				$tokens[] = [ 'type' => 'LP' ];
				++$i;
				continue;
			}
			if ( ')' === $c ) {
				$tokens[] = [ 'type' => 'RP' ];
				++$i;
				continue;
			}
			if ( ',' === $c ) {
				$tokens[] = [ 'type' => 'COMMA' ];
				++$i;
				continue;
			}
			if ( in_array( $c, [ '+', '-', '*', '/', '^' ], true ) ) {
				$tokens[] = [ 'type' => 'OP', 'value' => $c ];
				++$i;
				continue;
			}
			if ( ctype_alpha( $c ) || '_' === $c ) {
				$start = $i;
				while ( $i < $n && ( ctype_alnum( $expr[ $i ] ) || '_' === $expr[ $i ] ) ) {
					++$i;
				}
				$name = substr( $expr, $start, $i - $start );
				$tokens[] = [ 'type' => 'IDENT', 'value' => $name ];
				continue;
			}
			throw new InvalidArgumentException( sprintf( 'Invalid character at position %d', $i ) );
		}
		return $tokens;
	}

	/**
	 * @param array<string, bool>                    $allowed_ids
	 * @param array<string, float|int>|null         $vars null при валидации.
	 * @return array{0: float, 1: int}
	 */
	private static function parse_add( array $tokens, int $start, int $end, array $allowed_ids, ?array $vars ): array {
		[ $left, $pos ] = self::parse_mul( $tokens, $start, $end, $allowed_ids, $vars );
		while ( $pos < $end && isset( $tokens[ $pos ] ) && 'OP' === $tokens[ $pos ]['type'] && in_array( $tokens[ $pos ]['value'], [ '+', '-' ], true ) ) {
			$op = $tokens[ $pos ]['value'];
			++$pos;
			[ $right, $pos ] = self::parse_mul( $tokens, $pos, $end, $allowed_ids, $vars );
			if ( null !== $vars ) {
				$left = '+' === $op ? $left + $right : $left - $right;
			}
		}
		return [ $left, $pos ];
	}

	/**
	 * @return array{0: float, 1: int}
	 */
	private static function parse_mul( array $tokens, int $start, int $end, array $allowed_ids, ?array $vars ): array {
		[ $left, $pos ] = self::parse_pow( $tokens, $start, $end, $allowed_ids, $vars );
		while ( $pos < $end && isset( $tokens[ $pos ] ) && 'OP' === $tokens[ $pos ]['type'] && in_array( $tokens[ $pos ]['value'], [ '*', '/' ], true ) ) {
			$op = $tokens[ $pos ]['value'];
			++$pos;
			[ $right, $pos ] = self::parse_pow( $tokens, $pos, $end, $allowed_ids, $vars );
			if ( null !== $vars ) {
				if ( '/' === $op && abs( $right ) < 1e-15 ) {
					throw new InvalidArgumentException( 'Division by zero' );
				}
				$left = '*' === $op ? $left * $right : $left / $right;
			}
		}
		return [ $left, $pos ];
	}

	/**
	 * @return array{0: float, 1: int}
	 */
	private static function parse_pow( array $tokens, int $start, int $end, array $allowed_ids, ?array $vars ): array {
		[ $left, $pos ] = self::parse_unary( $tokens, $start, $end, $allowed_ids, $vars );
		if ( $pos < $end && isset( $tokens[ $pos ] ) && 'OP' === $tokens[ $pos ]['type'] && '^' === $tokens[ $pos ]['value'] ) {
			++$pos;
			[ $right, $pos ] = self::parse_pow( $tokens, $pos, $end, $allowed_ids, $vars );
			if ( null !== $vars ) {
				$left = pow( $left, $right );
			}
		}
		return [ $left, $pos ];
	}

	/**
	 * @return array{0: float, 1: int}
	 */
	private static function parse_unary( array $tokens, int $start, int $end, array $allowed_ids, ?array $vars ): array {
		if ( $start >= $end ) {
			throw new InvalidArgumentException( 'Unexpected end of expression' );
		}
		if ( 'OP' === $tokens[ $start ]['type'] && '-' === $tokens[ $start ]['value'] ) {
			[ $v, $pos ] = self::parse_unary( $tokens, $start + 1, $end, $allowed_ids, $vars );
			if ( null !== $vars ) {
				$v = - $v;
			}
			return [ $v, $pos ];
		}
		if ( 'OP' === $tokens[ $start ]['type'] && '+' === $tokens[ $start ]['value'] ) {
			return self::parse_unary( $tokens, $start + 1, $end, $allowed_ids, $vars );
		}
		return self::parse_primary( $tokens, $start, $end, $allowed_ids, $vars );
	}

	/**
	 * @return array{0: float, 1: int}
	 */
	private static function parse_primary( array $tokens, int $start, int $end, array $allowed_ids, ?array $vars ): array {
		if ( $start >= $end ) {
			throw new InvalidArgumentException( 'Unexpected end of expression' );
		}
		$t = $tokens[ $start ];
		if ( 'NUM' === $t['type'] ) {
			$v = null === $vars ? 0.0 : (float) $t['num'];
			return [ $v, $start + 1 ];
		}
		if ( 'IDENT' === $t['type'] ) {
			$name = (string) $t['value'];
			if ( ! isset( $allowed_ids[ $name ] ) ) {
				throw new InvalidArgumentException( sprintf( 'Unknown identifier: %s', $name ) );
			}
			$next  = $start + 1;
			$lname = strtolower( $name );
			if ( $next < $end && 'LP' === $tokens[ $next ]['type'] && in_array( $lname, self::FUNCTIONS, true ) ) {
				return self::parse_function_call( $tokens, $start, $end, $allowed_ids, $vars );
			}
			if ( null === $vars ) {
				return [ 0.0, $next ];
			}
			if ( ! array_key_exists( $name, $vars ) ) {
				throw new InvalidArgumentException( sprintf( 'Missing value for %s', $name ) );
			}
			return [ (float) $vars[ $name ], $next ];
		}
		if ( 'LP' === $t['type'] ) {
			[ $v, $pos ] = self::parse_add( $tokens, $start + 1, $end, $allowed_ids, $vars );
			if ( $pos >= $end || ! isset( $tokens[ $pos ] ) || 'RP' !== $tokens[ $pos ]['type'] ) {
				throw new InvalidArgumentException( 'Missing closing parenthesis' );
			}
			return [ $v, $pos + 1 ];
		}
		throw new InvalidArgumentException( 'Unexpected token' );
	}

	/**
	 * @return array{0: float, 1: int}
	 */
	private static function parse_function_call( array $tokens, int $start, int $end, array $allowed_ids, ?array $vars ): array {
		$fname = strtolower( (string) $tokens[ $start ]['value'] );
		$pos   = $start + 1;
		if ( $pos >= $end || 'LP' !== $tokens[ $pos ]['type'] ) {
			throw new InvalidArgumentException( 'Expected (' );
		}
		++$pos;
		$args = [];
		if ( $pos < $end && 'RP' !== $tokens[ $pos ]['type'] ) {
			while ( $pos < $end ) {
				[ $arg, $pos ] = self::parse_add( $tokens, $pos, $end, $allowed_ids, $vars );
				$args[] = $arg;
				if ( $pos < $end && isset( $tokens[ $pos ] ) && 'COMMA' === $tokens[ $pos ]['type'] ) {
					++$pos;
					continue;
				}
				break;
			}
		}
		if ( $pos >= $end || ! isset( $tokens[ $pos ] ) || 'RP' !== $tokens[ $pos ]['type'] ) {
			throw new InvalidArgumentException( 'Missing ) in function call' );
		}
		++$pos;
		if ( null === $vars ) {
			return [ 0.0, $pos ];
		}
		$res = self::call_function( $fname, $args );
		return [ $res, $pos ];
	}

	/**
	 * @param float[] $args
	 */
	private static function call_function( string $fname, array $args ): float {
		switch ( $fname ) {
			case 'min':
				return count( $args ) ? (float) min( $args ) : 0.0;
			case 'max':
				return count( $args ) ? (float) max( $args ) : 0.0;
			case 'avg':
				return count( $args ) ? (float) ( array_sum( $args ) / count( $args ) ) : 0.0;
			case 'clamp':
				if ( count( $args ) < 3 ) {
					throw new InvalidArgumentException( 'clamp needs 3 arguments' );
				}
				$x  = $args[0];
				$lo = $args[1];
				$hi = $args[2];
				return (float) max( $lo, min( $hi, $x ) );
			case 'abs':
				if ( count( $args ) < 1 ) {
					throw new InvalidArgumentException( 'abs needs 1 argument' );
				}
				return (float) abs( $args[0] );
			case 'sqrt':
				if ( count( $args ) < 1 ) {
					throw new InvalidArgumentException( 'sqrt needs 1 argument' );
				}
				if ( $args[0] < 0 ) {
					throw new InvalidArgumentException( 'sqrt of negative' );
				}
				return (float) sqrt( $args[0] );
			default:
				throw new InvalidArgumentException( 'Unknown function' );
		}
	}
}
