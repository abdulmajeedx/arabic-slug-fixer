<?php
/**
 * Arabic → Latin transliteration for URL slugs.
 *
 * @package ArabicSlugFixer
 */

defined( 'ABSPATH' ) || exit;

final class ASF_Transliterator {

	/** Arabic / Persian letters and digits → Latin. */
	private const MAP = array(
		'ا' => 'a', 'أ' => 'a', 'إ' => 'i', 'آ' => 'a', 'ٱ' => 'a',
		'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h',
		'خ' => 'kh', 'د' => 'd', 'ذ' => 'th', 'ر' => 'r', 'ز' => 'z',
		'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't',
		'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q',
		'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h',
		'و' => 'w', 'ي' => 'y', 'ى' => 'a', 'ة' => 'a', 'ء' => '',
		'ؤ' => 'o', 'ئ' => 'e',
		// Persian / Urdu.
		'پ' => 'p', 'چ' => 'ch', 'ژ' => 'zh', 'ک' => 'k', 'گ' => 'g', 'ی' => 'y',
		// Arabic-Indic & Persian digits.
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		// Punctuation.
		'،' => ' ', '؛' => ' ', '؟' => ' ', 'ـ' => '',
	);

	/** Common Arabic stop words removed when the option is on. */
	private const STOP_WORDS = array(
		'في', 'من', 'على', 'إلى', 'الى', 'عن', 'مع', 'أو', 'او', 'ثم',
		'هذا', 'هذه', 'ذلك', 'التي', 'الذي', 'كيف', 'ما', 'هل', 'لا', 'قد',
	);

	/** True if the string contains Arabic-script characters. */
	public static function has_arabic( string $text ): bool {
		return (bool) preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $text );
	}

	/**
	 * Transliterate Arabic text into a Latin string (not yet dash-sanitized).
	 *
	 * @param string $text       Input.
	 * @param bool   $stop_words Remove common Arabic stop words.
	 * @param int    $max_words  Max words to keep (0 = unlimited).
	 */
	public static function transliterate( string $text, bool $stop_words = true, int $max_words = 8 ): string {
		// Strip tashkeel (harakat, tanween, shadda, sukun, superscript alef).
		$text = preg_replace( '/[\x{064B}-\x{065F}\x{0670}]/u', '', $text );

		$words = preg_split( '/[\s\-_]+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		if ( $stop_words && count( $words ) > 2 ) {
			$words = array_values( array_diff( $words, self::STOP_WORDS ) );
		}
		if ( $max_words > 0 ) {
			$words = array_slice( $words, 0, $max_words );
		}

		$out = strtr( implode( ' ', $words ), self::MAP );

		// Collapse letters repeated 3+ times produced by transliteration.
		return preg_replace( '/([a-z])\1{2,}/', '$1$1', $out );
	}
}
