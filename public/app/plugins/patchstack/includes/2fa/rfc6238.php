<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/base32.php';

class TokenAuth6238 {

	/**
	 * Verify the code & token.
	 *
	 * @param string $secretkey Secret clue (base 32).
	 * @return bool True if success, false if failure
	 */
	public static function verify( $secretkey, $code, $rangein30s = 3 ) {
		$key           = Base32Static::decode( $secretkey );
		$unixtimestamp = time() / 30;

		// Without a valid decoded key there is nothing to verify against, and
		// passing an empty key to hash_hmac() is deprecated on PHP 8.1+.
		if ( ! is_string( $key ) || $key === '' ) {
			return false;
		}

		for ( $i = -( $rangein30s ); $i <= $rangein30s; $i++ ) {
			$checktime = (int) ( $unixtimestamp + $i );
			$thiskey   = self::oath_hotp( $key, $checktime );

			// oath_truncate() returns an int, so zero-pad to 6 digits to match the
			// codes authenticator apps display (e.g. "012345").
			$computed = str_pad( (string) self::oath_truncate( $thiskey, 6 ), 6, '0', STR_PAD_LEFT );
			if ( self::stringEquals( $computed, (string) $code ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generate the random clue/key.
	 *
	 * @param integer $length
	 * @return string
	 */
	public static function generateRandomClue( $length = 16 ) {
		if ( function_exists( 'random_bytes' ) ) {
			return Base32Static::encode( random_bytes( 10 ) );
		}
		
		require_once dirname( __FILE__ ) . '/polyfill/lib/random.php';
		return Base32Static::encode( random_bytes( 10 ) );
	}

	/**
	 *
	 * @param string  $key
	 * @param integer $counter
	 * @return string
	 */
	private static function oath_hotp( $key, $counter ) {
		$cur_counter = [ 0, 0, 0, 0, 0, 0, 0, 0 ];

		for ( $i = 7; $i >= 0; $i-- ) { // C for unsigned char, * for  repeating to the end of the input data
			$cur_counter[ $i ] = pack( 'C*', $counter );
			$counter           = $counter >> 8;
		}

		$binary = implode( $cur_counter );

		// Pad to 8 characters
		$binary = str_pad( $binary, 8, chr( 0 ), STR_PAD_LEFT );
		return hash_hmac( 'sha1', $binary, $key );
	}

	/**
	 * Truncate
	 *
	 * @param string  $hash
	 * @param integer $length
	 * @return boolean
	 */
	private static function oath_truncate( $hash, $length = 6 ) {
		$hashcharacters = str_split( $hash, 2 );

		for ( $j = 0; $j < count( $hashcharacters ); $j++ ) {
			$hmac_result[] = hexdec( $hashcharacters[ $j ] );
		}

		$offset = $hmac_result[19] & 0xf;
		return (
				( ( $hmac_result[ $offset + 0 ] & 0x7f ) << 24 ) |
				( ( $hmac_result[ $offset + 1 ] & 0xff ) << 16 ) |
				( ( $hmac_result[ $offset + 2 ] & 0xff ) << 8 ) |
				( $hmac_result[ $offset + 3 ] & 0xff )
		) % pow( 10, $length );
	}

	/**
	 * Compare 2 strings with each other.
	 *
	 * @param string $own
	 * @param string $user
	 * @return boolean
	 */
	private static function stringEquals( $own, $user ) {
		if ( function_exists( 'hash_equals' ) ) {
			return hash_equals( $own, $user );
		}

		$safeLen = strlen( $own );
		$userLen = strlen( $user );

		if ( $userLen != $safeLen ) {
			return false;
		}

		$result = 0;
		for ( $i = 0; $i < $userLen; $i++ ) {
			$result |= ( ord( $own[$i] ) ^ ord( $user[$i] ) );
		}

		return $result === 0;
	}
}
