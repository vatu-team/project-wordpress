<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encode in Base32 based on RFC 4648.
 * Requires 20% more space than base64
 * Great for case-insensitive filesystems like Windows and URL's  (except for = char which can be excluded using the pad option for urls)
 *
 * @package default
 * @author Bryan Ruiz
 **/
class Base32Static {

	private static $map = [
		'A',
		'B',
		'C',
		'D',
		'E',
		'F',
		'G',
		'H', // 7
		'I',
		'J',
		'K',
		'L',
		'M',
		'N',
		'O',
		'P', // 15
		'Q',
		'R',
		'S',
		'T',
		'U',
		'V',
		'W',
		'X', // 23
		'Y',
		'Z',
		'2',
		'3',
		'4',
		'5',
		'6',
		'7', // 31
		'=',  // padding character
	];

	private static $flipped_map = [
		'A' => '0',
		'B' => '1',
		'C' => '2',
		'D' => '3',
		'E' => '4',
		'F' => '5',
		'G' => '6',
		'H' => '7',
		'I' => '8',
		'J' => '9',
		'K' => '10',
		'L' => '11',
		'M' => '12',
		'N' => '13',
		'O' => '14',
		'P' => '15',
		'Q' => '16',
		'R' => '17',
		'S' => '18',
		'T' => '19',
		'U' => '20',
		'V' => '21',
		'W' => '22',
		'X' => '23',
		'Y' => '24',
		'Z' => '25',
		'2' => '26',
		'3' => '27',
		'4' => '28',
		'5' => '29',
		'6' => '30',
		'7' => '31',
	];

	/**
	 * Use padding false when encoding for urls
	 *
	 * @return base32 encoded string
	 * @author Bryan Ruiz
	 **/
	public static function encode( $input, $padding = true ) {
		if ( empty( $input ) ) {
			return '';
		}

		$input         = str_split( $input );
		$binary_string = '';

		for ( $i = 0; $i < count( $input ); $i++ ) {
			$binary_string .= str_pad( base_convert( ord( $input[ $i ] ), 10, 2 ), 8, '0', STR_PAD_LEFT );
		}

		$five_bit_binary_array = str_split( $binary_string, 5 );
		$base32                = '';
		$i                     = 0;

		while ( $i < count( $five_bit_binary_array ) ) {
			$base32 .= self::$map[ base_convert( str_pad( $five_bit_binary_array[ $i ], 5, '0' ), 2, 10 ) ];
			$i++;
		}

		if ( $padding && ( $x = strlen( $binary_string ) % 40 ) != 0 ) {
			if ( $x == 8 ) {
				$base32 .= str_repeat( self::$map[32], 6 );
			} elseif ( $x == 16 ) {
				$base32 .= str_repeat( self::$map[32], 4 );
			} elseif ( $x == 24 ) {
				$base32 .= str_repeat( self::$map[32], 3 );
			} elseif ( $x == 32 ) {
				$base32 .= self::$map[32];
			}
		}

		return $base32;
	}

	public static function decode( $input ) {
		if ( empty( $input ) ) {
			return;
		}

		$padding_char_count = substr_count( $input, self::$map[32] );
		$allowed_values     = [ 6, 4, 3, 1, 0 ];

		if ( ! in_array( $padding_char_count, $allowed_values ) ) {
			return false;
		}

		for ( $i = 0; $i < 4; $i++ ) {
			if ( $padding_char_count == $allowed_values[ $i ] && substr( $input, -( $allowed_values[ $i ] ) ) != str_repeat( self::$map[32], $allowed_values[ $i ] ) ) {
				return false;
			}
		}

		$input         = str_replace( '=', '', $input );
		$input         = str_split( $input );
		$binary_string = '';

		for ( $i = 0; $i < count( $input ); $i = $i + 8 ) {
			$x = '';

			if ( ! in_array( $input[ $i ], self::$map ) ) {
				return false;
			}

			for ( $j = 0; $j < 8; $j++ ) {
				$x .= str_pad( base_convert( @self::$flipped_map[ @$input[ $i + $j ] ], 10, 2 ), 5, '0', STR_PAD_LEFT );
			}

			$eight_bits = str_split( $x, 8 );

			for ( $z = 0; $z < count( $eight_bits ); $z++ ) {
				$binary_string .= chr( base_convert( $eight_bits[ $z ], 2, 10 ) );
			}
		}

		return $binary_string;
	}
}
