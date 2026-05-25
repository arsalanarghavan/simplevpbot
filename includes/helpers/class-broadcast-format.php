<?php
/**
 * Broadcast compose sanitization and per-platform message formatting.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Broadcast_Format
 */
class SimpleVPBot_Broadcast_Format {

	/**
	 * Sanitize rich-editor HTML into canonical Telegram-safe HTML (newlines as \n, not <br>).
	 *
	 * @param string $text Raw editor HTML.
	 * @return string
	 */
	public static function sanitize_compose_html( $text ) {
		$t = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$t = preg_replace( '/<\/div>\s*<div[^>]*>/i', "\n", $t );
		$t = preg_replace( '/<div[^>]*>/i', '', $t );
		$t = preg_replace( '/<\/div>/i', "\n", $t );
		$t = preg_replace( '/<\/p>\s*<p[^>]*>/i', "\n", $t );
		$t = preg_replace( '/<p[^>]*>/i', '', $t );
		$t = preg_replace( '/<\/p>/i', "\n", $t );
		$t = preg_replace( '/<br\s*\/?>/i', "\n", $t );
		$t = self::normalize_newlines_outside_pre_code( $t );
		$allowed = array(
			'p'          => array(),
			'b'          => array(),
			'strong'     => array(),
			'i'          => array(),
			'em'         => array(),
			'u'          => array(),
			'ins'        => array(),
			's'          => array(),
			'strike'     => array(),
			'del'        => array(),
			'code'       => array(),
			'pre'        => array(),
			'a'          => array( 'href' => array() ),
			'blockquote' => array(
				'expandable' => true,
			),
			'tg-spoiler' => array(),
			'span'       => array(
				'class' => true,
			),
		);
		$t = wp_kses( $t, $allowed );
		$t = self::normalize_for_telegram_html( $t );
		$t = preg_replace( '/<br\s*\/?>/i', "\n", $t );
		$t = preg_replace( "/\n{3,}/", "\n\n", $t );
		return trim( $t );
	}

	/**
	 * Telegram send payload from canonical HTML.
	 *
	 * @param string $canonical Sanitized compose HTML.
	 * @return array{text:string, parse_mode:string}
	 */
	public static function format_for_telegram( $canonical ) {
		return array(
			'text'       => (string) $canonical,
			'parse_mode' => 'HTML',
		);
	}

	/**
	 * Bale Markdown body from canonical HTML (Bale auto-parses Markdown; no parse_mode).
	 *
	 * @param string $canonical Sanitized compose HTML.
	 * @return string
	 */
	public static function format_for_bale_markdown( $canonical ) {
		$html = (string) $canonical;
		if ( '' === trim( $html ) ) {
			return '';
		}
		$md = self::html_fragment_to_bale_markdown( $html );
		$md = preg_replace( "/\n{3,}/", "\n\n", $md );
		$md = trim( $md );
		if ( class_exists( 'SimpleVPBot_Bot_Runtime' ) ) {
			$md = SimpleVPBot_Bot_Runtime::scrub_bale_text( $md );
		}
		return $md;
	}

	/**
	 * Plain text fallback (strip all tags).
	 *
	 * @param string $html HTML or markdown.
	 * @return string
	 */
	public static function html_to_plain( $html ) {
		$t = (string) $html;
		$t = preg_replace( '/<\/p>\s*/i', "\n\n", $t );
		$t = preg_replace( '/<br\s*\/?>/i', "\n", $t );
		$t = preg_replace( '/<\/blockquote>\s*/i', "\n\n", $t );
		$t = preg_replace( '/<\/pre>\s*/i', "\n\n", $t );
		$t = wp_strip_all_tags( $t );
		$t = html_entity_decode( $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( "/\n{3,}/", "\n\n", $t ) );
	}

	/**
	 * Preserve literal newlines outside pre/code blocks.
	 *
	 * @param string $t HTML fragment.
	 * @return string
	 */
	private static function normalize_newlines_outside_pre_code( $t ) {
		$pre_parts = preg_split( '/(<pre\b[^>]*>.*?<\/pre>)/is', (string) $t, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $pre_parts ) ) {
			return (string) $t;
		}
		$buf = '';
		foreach ( $pre_parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( preg_match( '/^<pre\b/is', $part ) ) {
				$buf .= $part;
				continue;
			}
			$code_parts = preg_split( '/(<code\b[^>]*>.*?<\/code>)/is', $part, -1, PREG_SPLIT_DELIM_CAPTURE );
			if ( ! is_array( $code_parts ) ) {
				$buf .= $part;
				continue;
			}
			foreach ( $code_parts as $cp ) {
				if ( '' !== $cp ) {
					$buf .= $cp;
				}
			}
		}
		return $buf;
	}

	/**
	 * Telegram rejects nested entities inside <code> and <pre>.
	 *
	 * @param string $html HTML fragment.
	 * @return string
	 */
	private static function flatten_pre_code_for_telegram( $html ) {
		$t     = (string) $html;
		$guard = 0;
		while ( preg_match( '/<code\b[^>]*>.*?<\/code>/is', $t ) && $guard < 500 ) {
			++$guard;
			$t = preg_replace_callback(
				'/<code\b[^>]*>(.*?)<\/code>/is',
				static function ( array $m ) {
					$inner = html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
					$inner = wp_strip_all_tags( $inner );
					return '<code>' . htmlspecialchars( $inner, ENT_HTML5 | ENT_QUOTES, 'UTF-8', false ) . '</code>';
				},
				$t,
				1
			);
		}
		$guard = 0;
		while ( preg_match( '/<pre\b[^>]*>.*?<\/pre>/is', $t ) && $guard < 500 ) {
			++$guard;
			$t = preg_replace_callback(
				'/<pre\b[^>]*>(.*?)<\/pre>/is',
				static function ( array $m ) {
					$inner = html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
					$inner = wp_strip_all_tags( $inner );
					return '<pre>' . htmlspecialchars( $inner, ENT_HTML5 | ENT_QUOTES, 'UTF-8', false ) . '</pre>';
				},
				$t,
				1
			);
		}
		return $t;
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	private static function normalize_blockquote_expandable_for_telegram( $html ) {
		return preg_replace_callback(
			'/<blockquote\b[^>]*>/i',
			static function ( array $m ) {
				if ( preg_match( '/\bexpandable\b/i', $m[0] ) ) {
					return '<blockquote expandable>';
				}
				return '<blockquote>';
			},
			(string) $html
		);
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	private static function normalize_anchors_for_telegram( $html ) {
		return preg_replace_callback(
			'/<a\b([^>]*)>(.*?)<\/a>/is',
			static function ( array $m ) {
				if ( ! preg_match( '/href\s*=\s*([\'"])([^\'"]*)\1/i', $m[1], $hm ) ) {
					return $m[2];
				}
				$href = trim( html_entity_decode( $hm[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( '' === $href || ! preg_match( '#\A(https?://|tg:)#i', $href ) ) {
					return $m[2];
				}
				return '<a href="' . esc_attr( $href ) . '">' . $m[2] . '</a>';
			},
			(string) $html
		);
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	private static function normalize_spoiler_tags_for_telegram( $html ) {
		return preg_replace_callback(
			'/<tg-spoiler\b[^>]*>(.*?)<\/tg-spoiler>/is',
			static function ( array $m ) {
				return '<span class="tg-spoiler">' . $m[1] . '</span>';
			},
			(string) $html
		);
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	private static function normalize_for_telegram_html( $html ) {
		$t = self::normalize_spoiler_tags_for_telegram( $html );
		$t = self::normalize_blockquote_expandable_for_telegram( $t );
		$t = self::normalize_anchors_for_telegram( $t );
		$t = self::flatten_pre_code_for_telegram( $t );
		return $t;
	}

	/**
	 * Convert canonical HTML fragment to Bale Markdown.
	 *
	 * @param string $html Canonical HTML.
	 * @return string
	 */
	private static function html_fragment_to_bale_markdown( $html ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return self::html_to_plain( $html );
		}
		$wrap = '<?xml encoding="utf-8"?><div id="svp-root">' . $html . '</div>';

		$dom = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML( $wrap, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $loaded ) {
			return self::html_to_plain( $html );
		}
		$root = $dom->getElementById( 'svp-root' );
		if ( ! $root ) {
			return self::html_to_plain( $html );
		}
		return self::dom_children_to_bale( $root );
	}

	/**
	 * @param DOMNode $node Node.
	 * @return string
	 */
	private static function dom_node_to_bale( DOMNode $node ) {
		if ( XML_TEXT_NODE === $node->nodeType ) {
			return self::escape_bale_plain( $node->textContent ?? '' );
		}
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return '';
		}
		/** @var DOMElement $el */
		$el    = $node;
		$tag   = strtolower( $el->tagName );
		$inner = self::dom_children_to_bale( $el );

		switch ( $tag ) {
			case 'b':
			case 'strong':
				return self::bale_bold( $inner );
			case 'i':
			case 'em':
				return self::bale_italic( $inner );
			case 'a':
				$href = trim( (string) $el->getAttribute( 'href' ) );
				if ( '' === $href || ! preg_match( '#\Ahttps?://#i', $href ) ) {
					return $inner;
				}
				$label = trim( $inner ) !== '' ? trim( $inner ) : $href;
				return '[' . self::escape_bale_link_label( $label ) . '](' . $href . ')';
			case 'code':
				return $inner;
			case 'pre':
				return "\n" . trim( $inner ) . "\n";
			case 'blockquote':
				return "\n« " . trim( preg_replace( "/\s+/", ' ', $inner ) ) . " »\n";
			case 'span':
				if ( stripos( (string) $el->getAttribute( 'class' ), 'tg-spoiler' ) !== false ) {
					return '▒' . trim( $inner ) . '▒';
				}
				return $inner;
			case 'tg-spoiler':
				return '▒' . trim( $inner ) . '▒';
			case 'u':
			case 'ins':
			case 's':
			case 'strike':
			case 'del':
				return $inner;
			case 'br':
				return "\n";
			case 'p':
			case 'div':
				return trim( $inner ) . "\n";
			default:
				return $inner;
		}
	}

	/**
	 * @param DOMElement $el Parent element.
	 * @return string
	 */
	private static function dom_children_to_bale( DOMElement $el ) {
		$out = '';
		foreach ( $el->childNodes as $child ) {
			$out .= self::dom_node_to_bale( $child );
		}
		return $out;
	}

	/**
	 * Bale bold requires spaces around asterisks.
	 *
	 * @param string $inner Inner text.
	 * @return string
	 */
	private static function bale_bold( $inner ) {
		$t = trim( (string) $inner );
		if ( '' === $t ) {
			return '';
		}
		return ' *' . $t . '* ';
	}

	/**
	 * Bale italic uses underscores with spaces.
	 *
	 * @param string $inner Inner text.
	 * @return string
	 */
	private static function bale_italic( $inner ) {
		$t = trim( (string) $inner );
		if ( '' === $t ) {
			return '';
		}
		return ' _' . $t . '_ ';
	}

	/**
	 * Escape characters that break Bale link labels.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	private static function escape_bale_link_label( $text ) {
		return str_replace( array( '[', ']', '(', ')' ), array( '\\[', '\\]', '\\(', '\\)' ), (string) $text );
	}

	/**
	 * @param string $text Plain text.
	 * @return string
	 */
	private static function escape_bale_plain( $text ) {
		return (string) $text;
	}
}
