<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Converter;

use League\HTMLToMarkdown\HtmlConverter;

/**
 * Converts Gutenberg blocks to Markdown.
 */
class BlocksToMarkdown {

	/**
	 * HTML to Markdown converter.
	 *
	 * @var HtmlConverter
	 */
	private HtmlConverter $html_converter;

	/**
	 * Track if any blocks couldn't be converted.
	 *
	 * @var bool
	 */
	private bool $has_html_fallback = false;

	/**
	 * Constructor.
	 *
	 * @param HtmlConverter $html_converter HTML to Markdown converter.
	 */
	public function __construct( HtmlConverter $html_converter ) {
		$this->html_converter = $html_converter;
	}

	/**
	 * Convert Gutenberg blocks to Markdown.
	 *
	 * @param array $blocks Array of Gutenberg blocks.
	 * @return array Result with markdown and metadata.
	 */
	public function convert( array $blocks ): array {
		$this->has_html_fallback = false;
		$markdown_parts          = [];

		foreach ( $blocks as $block ) {
			$converted = $this->convert_block( $block );
			if ( ! empty( $converted ) ) {
				$markdown_parts[] = $converted;
			}
		}

		return [
			'markdown'          => implode( "\n\n", $markdown_parts ),
			'has_html_fallback' => $this->has_html_fallback,
		];
	}

	/**
	 * Convert a single block to Markdown.
	 *
	 * @param array $block Gutenberg block.
	 * @return string Markdown representation.
	 */
	private function convert_block( array $block ): string {
		$block_name = $block['blockName'] ?? '';
		$attrs      = $block['attrs'] ?? [];
		$inner_html = $block['innerHTML'] ?? '';

		switch ( $block_name ) {
			case 'core/heading':
				return $this->convert_heading( $inner_html, $attrs );

			case 'core/paragraph':
				return $this->convert_paragraph( $inner_html );

			case 'core/code':
				return $this->convert_code( $inner_html, $attrs );

			case 'core/quote':
				return $this->convert_quote( $inner_html );

			case 'core/list':
				return $this->convert_list( $inner_html, $attrs );

			case 'core/separator':
				return '---';

			case 'core/image':
				return $this->convert_image( $inner_html, $attrs );

			case null:
			case '':
				// Empty block or whitespace.
				return '';

			default:
				// Unsupported block - fallback to HTML comment.
				return $this->fallback_to_html( $block_name, $inner_html );
		}
	}

	/**
	 * Convert heading block to Markdown.
	 *
	 * @param string $html  HTML content.
	 * @param array  $attrs Block attributes.
	 * @return string Markdown heading.
	 */
	private function convert_heading( string $html, array $attrs ): string {
		$level = $attrs['level'] ?? 2;
		$text  = $this->extract_text_content( $html );
		$text  = $this->html_to_markdown_inline( $text );

		return str_repeat( '#', $level ) . ' ' . $text;
	}

	/**
	 * Convert paragraph block to Markdown.
	 *
	 * @param string $html HTML content.
	 * @return string Markdown paragraph.
	 */
	private function convert_paragraph( string $html ): string {
		$text = $this->extract_text_content( $html );

		return $this->html_to_markdown_inline( $text );
	}

	/**
	 * Convert code block to Markdown.
	 *
	 * @param string $html  HTML content.
	 * @param array  $attrs Block attributes.
	 * @return string Markdown code block.
	 */
	private function convert_code( string $html, array $attrs ): string {
		// Extract code from <pre><code> tags.
		if ( preg_match( '/<code[^>]*>(.*?)<\/code>/s', $html, $matches ) ) {
			$code = html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5 );
		} else {
			$code = strip_tags( $html );
		}

		$language = $attrs['language'] ?? '';

		return sprintf( "```%s\n%s\n```", $language, $code );
	}

	/**
	 * Convert quote block to Markdown.
	 *
	 * @param string $html HTML content.
	 * @return string Markdown blockquote.
	 */
	private function convert_quote( string $html ): string {
		$text  = $this->extract_text_content( $html );
		$text  = $this->html_to_markdown_inline( $text );
		$lines = explode( "\n", $text );

		return implode( "\n", array_map( function ( $line ) {
			return '> ' . $line;
		}, $lines ) );
	}

	/**
	 * Convert list block to Markdown.
	 *
	 * @param string $html  HTML content.
	 * @param array  $attrs Block attributes.
	 * @return string Markdown list.
	 */
	private function convert_list( string $html, array $attrs ): string {
		$ordered = $attrs['ordered'] ?? false;
		$items   = [];

		// Extract list items.
		if ( preg_match_all( '/<li[^>]*>(.*?)<\/li>/s', $html, $matches ) ) {
			$items = $matches[1];
		}

		$markdown_items = [];
		$counter        = 1;

		foreach ( $items as $item ) {
			$text = $this->html_to_markdown_inline( strip_tags( $item ) );

			if ( $ordered ) {
				$markdown_items[] = sprintf( '%d. %s', $counter ++, $text );
			} else {
				$markdown_items[] = '- ' . $text;
			}
		}

		return implode( "\n", $markdown_items );
	}

	/**
	 * Convert image block to Markdown.
	 *
	 * @param string $html  HTML content.
	 * @param array  $attrs Block attributes.
	 * @return string Markdown image.
	 */
	private function convert_image( string $html, array $attrs ): string {
		$url = '';
		$alt = '';

		// Try to get URL from attrs first.
		if ( ! empty( $attrs['url'] ) ) {
			$url = $attrs['url'];
		} elseif ( preg_match( '/src="([^"]+)"/', $html, $matches ) ) {
			$url = $matches[1];
		}

		// Try to get alt from attrs first.
		if ( ! empty( $attrs['alt'] ) ) {
			$alt = $attrs['alt'];
		} elseif ( preg_match( '/alt="([^"]*)"/', $html, $matches ) ) {
			$alt = $matches[1];
		}

		if ( empty( $url ) ) {
			return '';
		}

		return sprintf( '![%s](%s)', $alt, $url );
	}

	/**
	 * Fallback to HTML comment for unsupported blocks.
	 *
	 * @param string $block_name Block name.
	 * @param string $html       HTML content.
	 * @return string HTML comment block.
	 */
	private function fallback_to_html( string $block_name, string $html ): string {
		$this->has_html_fallback = true;

		$html = trim( $html );
		if ( empty( $html ) ) {
			return sprintf( '<!-- HTML BLOCK: %s -->', $block_name );
		}

		return sprintf(
			"<!-- HTML BLOCK: %s -->\n%s\n<!-- END HTML BLOCK -->",
			$block_name,
			$html
		);
	}

	/**
	 * Extract text content from HTML, removing outer tags.
	 *
	 * @param string $html HTML content.
	 * @return string Text content.
	 */
	private function extract_text_content( string $html ): string {
		// Remove outer block tags (p, h1-h6, blockquote, etc.).
		$html = preg_replace( '/^<[^>]+>/', '', trim( $html ) );
		$html = preg_replace( '/<\/[^>]+>$/', '', $html );

		return trim( $html );
	}

	/**
	 * Convert inline HTML to Markdown.
	 *
	 * @param string $html HTML with inline formatting.
	 * @return string Markdown formatted text.
	 */
	private function html_to_markdown_inline( string $html ): string {
		// Strong/bold.
		$html = preg_replace( '/<strong>(.+?)<\/strong>/s', '**$1**', $html );
		$html = preg_replace( '/<b>(.+?)<\/b>/s', '**$1**', $html );

		// Emphasis/italic.
		$html = preg_replace( '/<em>(.+?)<\/em>/s', '*$1*', $html );
		$html = preg_replace( '/<i>(.+?)<\/i>/s', '*$1*', $html );

		// Inline code.
		$html = preg_replace( '/<code>(.+?)<\/code>/s', '`$1`', $html );

		// Links.
		$html = preg_replace( '/<a[^>]+href="([^"]+)"[^>]*>(.+?)<\/a>/s', '[$2]($1)', $html );

		// Images.
		$html = preg_replace( '/<img[^>]+src="([^"]+)"[^>]*alt="([^"]*)"[^>]*\/?>/s', '![$2]($1)', $html );
		$html = preg_replace( '/<img[^>]+alt="([^"]*)"[^>]*src="([^"]+)"[^>]*\/?>/s', '![$1]($2)', $html );

		// Line breaks.
		$html = preg_replace( '/<br\s*\/?>/i', "\n", $html );

		// Strip any remaining tags.
		$html = strip_tags( $html );

		// Decode HTML entities.
		$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5 );

		return trim( $html );
	}
}
