<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Converter;

use Parsedown;

/**
 * Converts Markdown to Gutenberg blocks.
 */
class MarkdownToBlocks {

	/**
	 * Parsedown instance.
	 *
	 * @var Parsedown
	 */
	private Parsedown $parsedown;

	/**
	 * Constructor.
	 *
	 * @param Parsedown $parsedown Parsedown instance.
	 */
	public function __construct( Parsedown $parsedown ) {
		$this->parsedown = $parsedown;
	}

	/**
	 * Convert Markdown to Gutenberg blocks.
	 *
	 * @param string $markdown Markdown content.
	 * @return array Array of Gutenberg blocks.
	 */
	public function convert( string $markdown ): array {
		$lines         = explode( "\n", $markdown );
		$blocks        = [];
		$current_block = null;
		$buffer        = [];
		$in_code_block = false;
		$code_language = '';
		$in_list       = false;
		$list_items    = [];
		$list_type     = 'ul';

		foreach ( $lines as $line ) {
			// Handle fenced code blocks.
			if ( preg_match( '/^```(\w*)$/', $line, $matches ) ) {
				if ( ! $in_code_block ) {
					// Start of code block.
					$this->flush_buffer( $blocks, $buffer );
					$in_code_block = true;
					$code_language = $matches[1] ?? '';
					$buffer        = [];
				} else {
					// End of code block.
					$blocks[]      = $this->create_code_block( implode( "\n", $buffer ), $code_language );
					$in_code_block = false;
					$code_language = '';
					$buffer        = [];
				}
				continue;
			}

			if ( $in_code_block ) {
				$buffer[] = $line;
				continue;
			}

			// Handle headings.
			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $line, $matches ) ) {
				$this->flush_list( $blocks, $list_items, $list_type );
				$in_list    = false;
				$list_items = [];
				$this->flush_buffer( $blocks, $buffer );
				$level    = strlen( $matches[1] );
				$text     = trim( $matches[2] );
				$blocks[] = $this->create_heading_block( $text, $level );
				continue;
			}

			// Handle blockquotes.
			if ( preg_match( '/^>\s*(.*)$/', $line, $matches ) ) {
				$this->flush_list( $blocks, $list_items, $list_type );
				$in_list    = false;
				$list_items = [];
				$this->flush_buffer( $blocks, $buffer );
				$quote_text = $matches[1];
				$blocks[]   = $this->create_quote_block( $quote_text );
				continue;
			}

			// Handle unordered lists.
			if ( preg_match( '/^[-*+]\s+(.+)$/', $line, $matches ) ) {
				$this->flush_buffer( $blocks, $buffer );
				if ( ! $in_list || $list_type !== 'ul' ) {
					$this->flush_list( $blocks, $list_items, $list_type );
					$in_list    = true;
					$list_type  = 'ul';
					$list_items = [];
				}
				$list_items[] = $matches[1];
				continue;
			}

			// Handle ordered lists.
			if ( preg_match( '/^\d+\.\s+(.+)$/', $line, $matches ) ) {
				$this->flush_buffer( $blocks, $buffer );
				if ( ! $in_list || $list_type !== 'ol' ) {
					$this->flush_list( $blocks, $list_items, $list_type );
					$in_list    = true;
					$list_type  = 'ol';
					$list_items = [];
				}
				$list_items[] = $matches[1];
				continue;
			}

			// Handle horizontal rules.
			if ( preg_match( '/^(---+|\*\*\*+|___+)$/', trim( $line ) ) ) {
				$this->flush_list( $blocks, $list_items, $list_type );
				$in_list    = false;
				$list_items = [];
				$this->flush_buffer( $blocks, $buffer );
				$blocks[] = $this->create_separator_block();
				continue;
			}

			// Handle empty lines.
			if ( trim( $line ) === '' ) {
				$this->flush_list( $blocks, $list_items, $list_type );
				$in_list    = false;
				$list_items = [];
				$this->flush_buffer( $blocks, $buffer );
				continue;
			}

			// Accumulate paragraph text.
			$buffer[] = $line;
		}

		// Flush any remaining content.
		$this->flush_list( $blocks, $list_items, $list_type );
		$this->flush_buffer( $blocks, $buffer );

		return $blocks;
	}

	/**
	 * Flush buffer to paragraph block.
	 *
	 * @param array $blocks Blocks array (by reference).
	 * @param array $buffer Buffer array (by reference).
	 * @return void
	 */
	private function flush_buffer( array &$blocks, array &$buffer ): void {
		if ( empty( $buffer ) ) {
			return;
		}

		$text     = implode( ' ', $buffer );
		$text     = $this->convert_inline_formatting( $text );
		$blocks[] = $this->create_paragraph_block( $text );
		$buffer   = [];
	}

	/**
	 * Flush list items to list block.
	 *
	 * @param array  $blocks     Blocks array (by reference).
	 * @param array  $list_items List items array (by reference).
	 * @param string $list_type  List type (ul or ol).
	 * @return void
	 */
	private function flush_list( array &$blocks, array &$list_items, string $list_type ): void {
		if ( empty( $list_items ) ) {
			return;
		}

		$blocks[]   = $this->create_list_block( $list_items, $list_type === 'ol' );
		$list_items = [];
	}

	/**
	 * Convert inline Markdown formatting to HTML.
	 *
	 * @param string $text Text with Markdown formatting.
	 * @return string HTML formatted text.
	 */
	private function convert_inline_formatting( string $text ): string {
		// Bold and italic combined: ***text*** or ___text___.
		$text = preg_replace( '/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $text );
		$text = preg_replace( '/___(.+?)___/', '<strong><em>$1</em></strong>', $text );

		// Bold: **text** or __text__.
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/__(.+?)__/', '<strong>$1</strong>', $text );

		// Italic: *text* or _text_.
		$text = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $text );
		$text = preg_replace( '/_(.+?)_/', '<em>$1</em>', $text );

		// Inline code: `code`.
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );

		// Images: ![alt](url) - must be before links to prevent ![alt](url) matching as a link.
		$text = preg_replace( '/!\[([^\]]*)\]\(([^\)]+)\)/', '<img src="$2" alt="$1" />', $text );

		// Links: [text](url).
		$text = preg_replace( '/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $text );

		return $text;
	}

	/**
	 * Create a heading block.
	 *
	 * @param string $text  Heading text.
	 * @param int    $level Heading level (1-6).
	 * @return array Gutenberg block.
	 */
	private function create_heading_block( string $text, int $level ): array {
		$text = $this->convert_inline_formatting( $text );

		return [
			'blockName'    => 'core/heading',
			'attrs'        => [ 'level' => $level ],
			'innerBlocks'  => [],
			'innerHTML'    => sprintf( '<h%d class="wp-block-heading">%s</h%d>', $level, $text, $level ),
			'innerContent' => [ sprintf( '<h%d class="wp-block-heading">%s</h%d>', $level, $text, $level ) ],
		];
	}

	/**
	 * Create a paragraph block.
	 *
	 * @param string $text Paragraph text.
	 * @return array Gutenberg block.
	 */
	private function create_paragraph_block( string $text ): array {
		return [
			'blockName'    => 'core/paragraph',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => sprintf( '<p>%s</p>', $text ),
			'innerContent' => [ sprintf( '<p>%s</p>', $text ) ],
		];
	}

	/**
	 * Create a code block.
	 *
	 * @param string $code     Code content.
	 * @param string $language Programming language.
	 * @return array Gutenberg block.
	 */
	private function create_code_block( string $code, string $language = '' ): array {
		$attrs = [];
		if ( ! empty( $language ) ) {
			$attrs['language'] = $language;
		}

		$escaped_code = esc_html( $code );

		return [
			'blockName'    => 'core/code',
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => sprintf( '<pre class="wp-block-code"><code>%s</code></pre>', $escaped_code ),
			'innerContent' => [ sprintf( '<pre class="wp-block-code"><code>%s</code></pre>', $escaped_code ) ],
		];
	}

	/**
	 * Create a quote block.
	 *
	 * @param string $text Quote text.
	 * @return array Gutenberg block.
	 */
	private function create_quote_block( string $text ): array {
		$text = $this->convert_inline_formatting( $text );

		return [
			'blockName'    => 'core/quote',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => sprintf( '<blockquote class="wp-block-quote"><p>%s</p></blockquote>', $text ),
			'innerContent' => [ sprintf( '<blockquote class="wp-block-quote"><p>%s</p></blockquote>', $text ) ],
		];
	}

	/**
	 * Create a list block.
	 *
	 * @param array $items   List items.
	 * @param bool  $ordered Whether the list is ordered.
	 * @return array Gutenberg block.
	 */
	private function create_list_block( array $items, bool $ordered = false ): array {
		$tag       = $ordered ? 'ol' : 'ul';
		$list_html = sprintf( '<%s class="wp-block-list">', $tag );

		foreach ( $items as $item ) {
			$item      = $this->convert_inline_formatting( $item );
			$list_html .= sprintf( '<li>%s</li>', $item );
		}

		$list_html .= sprintf( '</%s>', $tag );

		return [
			'blockName'    => 'core/list',
			'attrs'        => [ 'ordered' => $ordered ],
			'innerBlocks'  => [],
			'innerHTML'    => $list_html,
			'innerContent' => [ $list_html ],
		];
	}

	/**
	 * Create a separator block.
	 *
	 * @return array Gutenberg block.
	 */
	private function create_separator_block(): array {
		return [
			'blockName'    => 'core/separator',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => '<hr class="wp-block-separator has-alpha-channel-opacity"/>',
			'innerContent' => [ '<hr class="wp-block-separator has-alpha-channel-opacity"/>' ],
		];
	}
}
