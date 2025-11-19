<?php

declare( strict_types = 1 );

namespace AgenticEndpoints\Tests\Unit\Converter;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use AgenticEndpoints\Converter\MarkdownToBlocks;
use Parsedown;

/**
 * Unit tests for MarkdownToBlocks converter.
 */
class MarkdownToBlocksTest extends TestCase {

	private MarkdownToBlocks $converter;

	protected function setUp(): void {
		parent::setUp();
		$parsedown = new Parsedown();
		$parsedown->setSafeMode( true );
		$this->converter = new MarkdownToBlocks( $parsedown );
	}

	// =========================
	// Empty/Basic Input Tests
	// =========================

	/**
	 * GIVEN empty or whitespace-only input
	 * WHEN converting to blocks
	 * THEN an empty array is returned
	 *
	 * @dataProvider empty_input_provider
	 */
	#[Test]
	#[DataProvider( 'empty_input_provider' )]
	public function it_returns_empty_array_for_empty_input( string $input ): void {
		$result = $this->converter->convert( $input );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public static function empty_input_provider(): array {
		return [
			'empty string'   => [''],
			'whitespace only' => ['   '],
		];
	}

	// =========================
	// Heading Tests
	// =========================

	/**
	 * GIVEN markdown with a heading at any level (1-6)
	 * WHEN converting to blocks
	 * THEN a core/heading block is created with the correct level attribute
	 *
	 * @dataProvider heading_level_provider
	 */
	#[Test]
	#[DataProvider( 'heading_level_provider' )]
	public function it_converts_headings_at_all_levels( int $level, string $markdown ): void {
		$result = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'core/heading', $result[0]['blockName'] );
		$this->assertEquals( $level, $result[0]['attrs']['level'] );
		$this->assertStringContainsString( 'Test Heading', $result[0]['innerHTML'] );
		$this->assertStringContainsString( '<h' . $level, $result[0]['innerHTML'] );
		$this->assertStringContainsString( '</h' . $level . '>', $result[0]['innerHTML'] );
	}

	public static function heading_level_provider(): array {
		return [
			'h1' => [ 1, '# Test Heading' ],
			'h2' => [ 2, '## Test Heading' ],
			'h3' => [ 3, '### Test Heading' ],
			'h4' => [ 4, '#### Test Heading' ],
			'h5' => [ 5, '##### Test Heading' ],
			'h6' => [ 6, '###### Test Heading' ],
		];
	}

	/**
	 * GIVEN markdown with a heading containing inline formatting
	 * WHEN converting to blocks
	 * THEN the inline formatting is preserved in the HTML output
	 */
	#[Test]
	public function it_converts_heading_with_inline_formatting(): void {
		$result = $this->converter->convert( '# Heading with **bold** text' );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'core/heading', $result[0]['blockName'] );
		$this->assertStringContainsString( '<strong>bold</strong>', $result[0]['innerHTML'] );
	}

	/**
	 * GIVEN markdown with multiple headings at different levels
	 * WHEN converting to blocks
	 * THEN each heading becomes a separate block with the correct level
	 */
	#[Test]
	public function it_converts_multiple_headings(): void {
		$markdown = "# First Heading\n\n## Second Heading\n\n### Third Heading";
		$result   = $this->converter->convert( $markdown );

		$this->assertCount( 3, $result );
		$this->assertEquals( 1, $result[0]['attrs']['level'] );
		$this->assertEquals( 2, $result[1]['attrs']['level'] );
		$this->assertEquals( 3, $result[2]['attrs']['level'] );
	}

	// =========================
	// Paragraph Tests
	// =========================

	/**
	 * GIVEN markdown with a simple paragraph
	 * WHEN converting to blocks
	 * THEN a core/paragraph block is created with the content wrapped in p tags
	 */
	#[Test]
	public function it_converts_simple_paragraph(): void {
		$result = $this->converter->convert( 'This is a simple paragraph.' );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'core/paragraph', $result[0]['blockName'] );
		$this->assertStringContainsString( 'This is a simple paragraph.', $result[0]['innerHTML'] );
		$this->assertStringContainsString( '<p>', $result[0]['innerHTML'] );
	}

	/**
	 * GIVEN markdown with a multi-line paragraph (no blank line between)
	 * WHEN converting to blocks
	 * THEN lines are joined together in a single paragraph block
	 */
	#[Test]
	public function it_converts_multi_line_paragraph(): void {
		$markdown = "This is line one\nThis is line two";
		$result   = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'core/paragraph', $result[0]['blockName'] );
		$this->assertStringContainsString( 'This is line one This is line two', $result[0]['innerHTML'] );
	}

	/**
	 * GIVEN markdown with paragraphs separated by empty lines
	 * WHEN converting to blocks
	 * THEN each paragraph becomes a separate block
	 */
	#[Test]
	public function it_separates_paragraphs_by_empty_lines(): void {
		$markdown = "First paragraph.\n\nSecond paragraph.";
		$result   = $this->converter->convert( $markdown );

		$this->assertCount( 2, $result );
		$this->assertEquals( 'core/paragraph', $result[0]['blockName'] );
		$this->assertEquals( 'core/paragraph', $result[1]['blockName'] );
		$this->assertStringContainsString( 'First paragraph.', $result[0]['innerHTML'] );
		$this->assertStringContainsString( 'Second paragraph.', $result[1]['innerHTML'] );
	}

	// =========================
	// Code Block Tests
	// =========================

	/**
	 * GIVEN markdown with a fenced code block
	 * WHEN converting to blocks
	 * THEN a core/code block is created with the correct language attribute
	 *
	 * @dataProvider code_block_provider
	 */
	#[Test]
	#[DataProvider( 'code_block_provider' )]
	public function it_converts_code_blocks( string $markdown, ?string $expected_language, string $expected_content ): void {
		$result = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'core/code', $result[0]['blockName'] );

		if ( $expected_language === null ) {
			$this->assertEmpty( $result[0]['attrs'] );
		} else {
			$this->assertEquals( $expected_language, $result[0]['attrs']['language'] );
		}

		$this->assertStringContainsString( $expected_content, $result[0]['innerHTML'] );
	}

	public static function code_block_provider(): array {
		return [
			'without language'     => [ "```\nconst x = 1;\n```", null, 'const x = 1;' ],
			'with javascript'      => [ "```javascript\nconst x = 1;\n```", 'javascript', 'const x = 1;' ],
			'with php multiline'   => [ "```php\nfunction test() {\n    return true;\n}\n```", 'php', 'function test()' ],
		];
	}

	/**
	 * GIVEN markdown with HTML in a code block
	 * WHEN converting to blocks
	 * THEN the HTML is escaped for safety
	 */
	#[Test]
	public function it_escapes_html_in_code_blocks(): void {
		$markdown = "```\n<script>alert('xss')</script>\n```";
		$result   = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result[0]['innerHTML'] );
	}

	/**
	 * GIVEN markdown with indented content in a code block
	 * WHEN converting to blocks
	 * THEN whitespace and indentation is preserved
	 */
	#[Test]
	public function it_preserves_whitespace_in_code_blocks(): void {
		$markdown = "```\n    indented\n        more indented\n```";
		$result   = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertStringContainsString( '    indented', $result[0]['innerHTML'] );
		$this->assertStringContainsString( '        more indented', $result[0]['innerHTML'] );
	}

	// =========================
	// Blockquote Tests
	// =========================

	/**
	 * GIVEN markdown with a blockquote
	 * WHEN converting to blocks
	 * THEN a core/quote block is created
	 */
	#[Test]
	public function it_converts_simple_blockquote(): void {
		$result = $this->converter->convert( '> This is a quote' );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'core/quote', $result[0]['blockName'] );
		$this->assertStringContainsString( '<blockquote', $result[0]['innerHTML'] );
		$this->assertStringContainsString( 'This is a quote', $result[0]['innerHTML'] );
	}

	/**
	 * GIVEN markdown with a blockquote containing inline formatting
	 * WHEN converting to blocks
	 * THEN the inline formatting is preserved
	 */
	#[Test]
	public function it_converts_blockquote_with_inline_formatting(): void {
		$result = $this->converter->convert( '> Quote with **bold** and *italic*' );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'core/quote', $result[0]['blockName'] );
		$this->assertStringContainsString( '<strong>bold</strong>', $result[0]['innerHTML'] );
		$this->assertStringContainsString( '<em>italic</em>', $result[0]['innerHTML'] );
	}

	/**
	 * GIVEN markdown with an empty blockquote marker
	 * WHEN converting to blocks
	 * THEN a core/quote block is still created
	 */
	#[Test]
	public function it_converts_empty_blockquote(): void {
		$result = $this->converter->convert( '>' );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'core/quote', $result[0]['blockName'] );
	}

	// =========================
	// List Tests
	// =========================

	/**
	 * GIVEN markdown with an unordered list using different markers
	 * WHEN converting to blocks
	 * THEN a core/list block is created with ordered=false
	 *
	 * @dataProvider unordered_list_marker_provider
	 */
	#[Test]
	#[DataProvider( 'unordered_list_marker_provider' )]
	public function it_converts_unordered_lists_with_different_markers( string $markdown ): void {
		$result = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'core/list', $result[0]['blockName'] );
		$this->assertFalse( $result[0]['attrs']['ordered'] );
		$this->assertStringContainsString( '<ul', $result[0]['innerHTML'] );
		$this->assertStringContainsString( '<li>', $result[0]['innerHTML'] );
	}

	public static function unordered_list_marker_provider(): array {
		return [
			'dash marker'     => [ "- Item one\n- Item two" ],
			'asterisk marker' => [ "* Item one\n* Item two" ],
			'plus marker'     => [ "+ Item one\n+ Item two" ],
		];
	}

	/**
	 * GIVEN markdown with an unordered list with multiple items
	 * WHEN converting to blocks
	 * THEN all items are included in the list
	 */
	#[Test]
	public function it_converts_unordered_list_with_multiple_items(): void {
		$markdown = "- First item\n- Second item\n- Third item";
		$result   = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'core/list', $result[0]['blockName'] );
		$this->assertFalse( $result[0]['attrs']['ordered'] );

		$html = $result[0]['innerHTML'];
		$this->assertStringContainsString( 'First item', $html );
		$this->assertStringContainsString( 'Second item', $html );
		$this->assertStringContainsString( 'Third item', $html );
		$this->assertEquals( 3, substr_count( $html, '<li>' ) );
	}

	/**
	 * GIVEN markdown with a list containing inline formatting
	 * WHEN converting to blocks
	 * THEN the inline formatting is preserved in list items
	 */
	#[Test]
	public function it_converts_unordered_list_with_inline_formatting(): void {
		$markdown = "- Item with **bold**\n- Item with `code`";
		$result   = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertStringContainsString( '<strong>bold</strong>', $result[0]['innerHTML'] );
		$this->assertStringContainsString( '<code>code</code>', $result[0]['innerHTML'] );
	}

	/**
	 * GIVEN markdown with an ordered list
	 * WHEN converting to blocks
	 * THEN a core/list block is created with ordered=true
	 */
	#[Test]
	public function it_converts_ordered_list(): void {
		$markdown = "1. First item\n2. Second item\n3. Third item";
		$result   = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'core/list', $result[0]['blockName'] );
		$this->assertTrue( $result[0]['attrs']['ordered'] );
		$this->assertStringContainsString( '<ol', $result[0]['innerHTML'] );
	}

	/**
	 * GIVEN markdown with an ordered list not starting at 1
	 * WHEN converting to blocks
	 * THEN the list is still correctly converted
	 */
	#[Test]
	public function it_converts_ordered_list_with_different_starting_numbers(): void {
		$markdown = "5. Fifth item\n6. Sixth item";
		$result   = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'core/list', $result[0]['blockName'] );
		$this->assertTrue( $result[0]['attrs']['ordered'] );
	}

	/**
	 * GIVEN markdown with consecutive lists of different types
	 * WHEN converting to blocks
	 * THEN separate list blocks are created for each type
	 */
	#[Test]
	public function it_keeps_separate_lists_when_type_changes(): void {
		$markdown = "- Unordered item\n\n1. Ordered item";
		$result   = $this->converter->convert( $markdown );

		$this->assertCount( 2, $result );
		$this->assertFalse( $result[0]['attrs']['ordered'] );
		$this->assertTrue( $result[1]['attrs']['ordered'] );
	}

	// =========================
	// Horizontal Rule Tests
	// =========================

	/**
	 * GIVEN markdown with horizontal rule syntax
	 * WHEN converting to blocks
	 * THEN a core/separator block is created
	 *
	 * @dataProvider horizontal_rule_provider
	 */
	#[Test]
	#[DataProvider( 'horizontal_rule_provider' )]
	public function it_converts_horizontal_rules( string $markdown ): void {
		$result = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'core/separator', $result[0]['blockName'] );
		$this->assertStringContainsString( '<hr', $result[0]['innerHTML'] );
	}

	public static function horizontal_rule_provider(): array {
		return [
			'dashes'       => [ '---' ],
			'asterisks'    => [ '***' ],
			'underscores'  => [ '___' ],
			'long dashes'  => [ '------' ],
		];
	}

	// =========================
	// Inline Formatting Tests
	// =========================

	/**
	 * GIVEN markdown with inline text formatting
	 * WHEN converting to blocks
	 * THEN the formatting is converted to appropriate HTML tags
	 *
	 * @dataProvider inline_formatting_provider
	 */
	#[Test]
	#[DataProvider( 'inline_formatting_provider' )]
	public function it_converts_inline_formatting( string $markdown, string $expected_html ): void {
		$result = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertStringContainsString( $expected_html, $result[0]['innerHTML'] );
	}

	public static function inline_formatting_provider(): array {
		return [
			'bold with asterisks'       => [ 'This is **bold** text', '<strong>bold</strong>' ],
			'bold with underscores'     => [ 'This is __bold__ text', '<strong>bold</strong>' ],
			'italic with asterisks'     => [ 'This is *italic* text', '<em>italic</em>' ],
			'italic with underscores'   => [ 'This is _italic_ text', '<em>italic</em>' ],
			'bold and italic combined'  => [ 'This is ***bold and italic*** text', '<strong><em>bold and italic</em></strong>' ],
			'inline code'               => [ 'Use the `convert()` function', '<code>convert()</code>' ],
		];
	}

	/**
	 * GIVEN markdown with a link
	 * WHEN converting to blocks
	 * THEN an anchor tag with correct href is generated
	 */
	#[Test]
	public function it_converts_links(): void {
		$result = $this->converter->convert( 'Visit [Google](https://google.com)' );

		$this->assertCount( 1, $result );
		$this->assertStringContainsString( '<a href="https://google.com">Google</a>', $result[0]['innerHTML'] );
	}

	/**
	 * GIVEN markdown with an image
	 * WHEN converting to blocks
	 * THEN an img tag with correct src and alt is generated
	 *
	 * @dataProvider image_provider
	 */
	#[Test]
	#[DataProvider( 'image_provider' )]
	public function it_converts_images( string $markdown, string $expected_src, string $expected_alt ): void {
		$result = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertStringContainsString( 'src="' . $expected_src . '"', $result[0]['innerHTML'] );
		$this->assertStringContainsString( 'alt="' . $expected_alt . '"', $result[0]['innerHTML'] );
	}

	public static function image_provider(): array {
		return [
			'image with alt text' => [ '![Alt text](https://example.com/image.png)', 'https://example.com/image.png', 'Alt text' ],
			'image with empty alt' => [ '![](https://example.com/image.png)', 'https://example.com/image.png', '' ],
		];
	}

	/**
	 * GIVEN markdown with multiple inline formats in the same paragraph
	 * WHEN converting to blocks
	 * THEN all formats are converted correctly
	 */
	#[Test]
	public function it_converts_multiple_inline_formats_in_same_paragraph(): void {
		$markdown = 'Text with **bold**, *italic*, and `code`';
		$result   = $this->converter->convert( $markdown );

		$this->assertCount( 1, $result );
		$this->assertStringContainsString( '<strong>bold</strong>', $result[0]['innerHTML'] );
		$this->assertStringContainsString( '<em>italic</em>', $result[0]['innerHTML'] );
		$this->assertStringContainsString( '<code>code</code>', $result[0]['innerHTML'] );
	}

	// =========================
	// Complex Document Tests
	// =========================

	/**
	 * GIVEN a complex markdown document with mixed elements
	 * WHEN converting to blocks
	 * THEN all elements are correctly converted to their respective block types
	 */
	#[Test]
	public function it_converts_complex_document_with_mixed_elements(): void {
		$markdown = <<<MARKDOWN
# Main Title

This is the introduction paragraph.

## Code Example

```php
echo "Hello World";
```

> Important note about the code.

### Features

- Feature one
- Feature two
- Feature three

---

Final paragraph with **bold** and *italic* text.
MARKDOWN;

		$result = $this->converter->convert( $markdown );

		$this->assertCount( 9, $result );

		$this->assertEquals( 'core/heading', $result[0]['blockName'] );
		$this->assertEquals( 1, $result[0]['attrs']['level'] );

		$this->assertEquals( 'core/paragraph', $result[1]['blockName'] );

		$this->assertEquals( 'core/heading', $result[2]['blockName'] );
		$this->assertEquals( 2, $result[2]['attrs']['level'] );

		$this->assertEquals( 'core/code', $result[3]['blockName'] );
		$this->assertEquals( 'php', $result[3]['attrs']['language'] );

		$this->assertEquals( 'core/quote', $result[4]['blockName'] );

		$this->assertEquals( 'core/heading', $result[5]['blockName'] );
		$this->assertEquals( 3, $result[5]['attrs']['level'] );

		$this->assertEquals( 'core/list', $result[6]['blockName'] );
		$this->assertFalse( $result[6]['attrs']['ordered'] );

		$this->assertEquals( 'core/separator', $result[7]['blockName'] );

		$this->assertEquals( 'core/paragraph', $result[8]['blockName'] );
	}

	// =========================
	// Block Structure Tests
	// =========================

	/**
	 * GIVEN any markdown input
	 * WHEN converting to blocks
	 * THEN each block has the required structure keys
	 */
	#[Test]
	public function it_creates_blocks_with_correct_structure(): void {
		$result = $this->converter->convert( '# Test' );

		$this->assertArrayHasKey( 'blockName', $result[0] );
		$this->assertArrayHasKey( 'attrs', $result[0] );
		$this->assertArrayHasKey( 'innerBlocks', $result[0] );
		$this->assertArrayHasKey( 'innerHTML', $result[0] );
		$this->assertArrayHasKey( 'innerContent', $result[0] );

		$this->assertIsArray( $result[0]['attrs'] );
		$this->assertIsArray( $result[0]['innerBlocks'] );
		$this->assertIsArray( $result[0]['innerContent'] );
		$this->assertIsString( $result[0]['innerHTML'] );
	}

	/**
	 * GIVEN any markdown input
	 * WHEN converting to blocks
	 * THEN innerContent contains the same value as innerHTML
	 */
	#[Test]
	public function it_creates_inner_content_matching_inner_html(): void {
		$result = $this->converter->convert( 'Test paragraph' );

		$this->assertEquals( $result[0]['innerHTML'], $result[0]['innerContent'][0] );
	}

	/**
	 * GIVEN any markdown input
	 * WHEN converting to blocks
	 * THEN innerBlocks is always an empty array (flat structure)
	 */
	#[Test]
	public function it_creates_empty_inner_blocks_array(): void {
		$result = $this->converter->convert( 'Test' );

		$this->assertEmpty( $result[0]['innerBlocks'] );
	}

	// =========================
	// Edge Case Tests
	// =========================

	/**
	 * GIVEN markdown with edge case input
	 * WHEN converting to blocks
	 * THEN the conversion handles it gracefully
	 *
	 * @dataProvider edge_case_provider
	 */
	#[Test]
	#[DataProvider( 'edge_case_provider' )]
	public function it_handles_edge_cases( string $markdown, int $expected_count ): void {
		$result = $this->converter->convert( $markdown );

		$this->assertIsArray( $result );
		$this->assertCount( $expected_count, $result );
	}

	public static function edge_case_provider(): array {
		return [
			'consecutive empty lines'           => [ "First\n\n\n\nSecond", 2 ],
			'unclosed code block'               => [ "```php\necho 'test';", 1 ], // Content becomes paragraph.
			'list at end of document'           => [ "# Title\n\n- Item 1\n- Item 2", 2 ],
			'special characters in content'     => [ 'Text with special chars: <>&"\'', 1 ],
		];
	}
}
