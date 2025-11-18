<?php

declare(strict_types=1);

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
		$parsedown->setSafeMode(true);
		$this->converter = new MarkdownToBlocks($parsedown);
	}

	// =========================
	// Empty/Basic Input Tests
	// =========================

	#[Test]
	public function it_returns_empty_array_for_empty_input(): void {
		$result = $this->converter->convert('');
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	#[Test]
	public function it_returns_empty_array_for_whitespace_only_input(): void {
		$result = $this->converter->convert('   ');
		$this->assertEmpty($result);
	}

	// =========================
	// Heading Tests
	// =========================

	#[Test]
	#[DataProvider('headingLevelProvider')]
	public function it_converts_headings_at_all_levels(int $level, string $markdown): void {
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		$this->assertEquals('core/heading', $result[0]['blockName']);
		$this->assertEquals($level, $result[0]['attrs']['level']);
		$this->assertStringContainsString('Test Heading', $result[0]['innerHTML']);
		$this->assertStringContainsString("<h{$level}", $result[0]['innerHTML']);
		$this->assertStringContainsString("</h{$level}>", $result[0]['innerHTML']);
	}

	public static function headingLevelProvider(): array {
		return [
			'h1' => [1, '# Test Heading'],
			'h2' => [2, '## Test Heading'],
			'h3' => [3, '### Test Heading'],
			'h4' => [4, '#### Test Heading'],
			'h5' => [5, '##### Test Heading'],
			'h6' => [6, '###### Test Heading'],
		];
	}

	#[Test]
	public function it_converts_heading_with_inline_formatting(): void {
		$result = $this->converter->convert('# Heading with **bold** text');

		$this->assertCount(1, $result);
		$this->assertEquals('core/heading', $result[0]['blockName']);
		$this->assertStringContainsString('<strong>bold</strong>', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_multiple_headings(): void {
		$markdown = "# First Heading\n\n## Second Heading\n\n### Third Heading";
		$result = $this->converter->convert($markdown);

		$this->assertCount(3, $result);
		$this->assertEquals(1, $result[0]['attrs']['level']);
		$this->assertEquals(2, $result[1]['attrs']['level']);
		$this->assertEquals(3, $result[2]['attrs']['level']);
	}

	// =========================
	// Paragraph Tests
	// =========================

	#[Test]
	public function it_converts_simple_paragraph(): void {
		$result = $this->converter->convert('This is a simple paragraph.');

		$this->assertCount(1, $result);
		$this->assertEquals('core/paragraph', $result[0]['blockName']);
		$this->assertStringContainsString('This is a simple paragraph.', $result[0]['innerHTML']);
		$this->assertStringContainsString('<p>', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_multi_line_paragraph(): void {
		$markdown = "This is line one\nThis is line two";
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		$this->assertEquals('core/paragraph', $result[0]['blockName']);
		// Lines should be joined with space
		$this->assertStringContainsString('This is line one This is line two', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_separates_paragraphs_by_empty_lines(): void {
		$markdown = "First paragraph.\n\nSecond paragraph.";
		$result = $this->converter->convert($markdown);

		$this->assertCount(2, $result);
		$this->assertEquals('core/paragraph', $result[0]['blockName']);
		$this->assertEquals('core/paragraph', $result[1]['blockName']);
		$this->assertStringContainsString('First paragraph.', $result[0]['innerHTML']);
		$this->assertStringContainsString('Second paragraph.', $result[1]['innerHTML']);
	}

	// =========================
	// Code Block Tests
	// =========================

	#[Test]
	public function it_converts_code_block_without_language(): void {
		$markdown = "```\nconst x = 1;\n```";
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		$this->assertEquals('core/code', $result[0]['blockName']);
		$this->assertEmpty($result[0]['attrs']);
		$this->assertStringContainsString('const x = 1;', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_code_block_with_language(): void {
		$markdown = "```javascript\nconst x = 1;\n```";
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		$this->assertEquals('core/code', $result[0]['blockName']);
		$this->assertEquals('javascript', $result[0]['attrs']['language']);
		$this->assertStringContainsString('const x = 1;', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_code_block_with_multiple_lines(): void {
		$markdown = "```php\nfunction test() {\n    return true;\n}\n```";
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		$this->assertEquals('core/code', $result[0]['blockName']);
		$this->assertEquals('php', $result[0]['attrs']['language']);
		$this->assertStringContainsString('function test()', $result[0]['innerHTML']);
		$this->assertStringContainsString('return true;', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_escapes_html_in_code_blocks(): void {
		$markdown = "```\n<script>alert('xss')</script>\n```";
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		// HTML should be escaped
		$this->assertStringContainsString('&lt;script&gt;', $result[0]['innerHTML']);
	}

	// =========================
	// Blockquote Tests
	// =========================

	#[Test]
	public function it_converts_simple_blockquote(): void {
		$result = $this->converter->convert('> This is a quote');

		$this->assertCount(1, $result);
		$this->assertEquals('core/quote', $result[0]['blockName']);
		$this->assertStringContainsString('<blockquote', $result[0]['innerHTML']);
		$this->assertStringContainsString('This is a quote', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_blockquote_with_inline_formatting(): void {
		$result = $this->converter->convert('> Quote with **bold** and *italic*');

		$this->assertCount(1, $result);
		$this->assertEquals('core/quote', $result[0]['blockName']);
		$this->assertStringContainsString('<strong>bold</strong>', $result[0]['innerHTML']);
		$this->assertStringContainsString('<em>italic</em>', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_empty_blockquote(): void {
		$result = $this->converter->convert('>');

		$this->assertCount(1, $result);
		$this->assertEquals('core/quote', $result[0]['blockName']);
	}

	// =========================
	// Unordered List Tests
	// =========================

	#[Test]
	#[DataProvider('unorderedListMarkerProvider')]
	public function it_converts_unordered_lists_with_different_markers(string $markdown): void {
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		$this->assertEquals('core/list', $result[0]['blockName']);
		$this->assertFalse($result[0]['attrs']['ordered']);
		$this->assertStringContainsString('<ul', $result[0]['innerHTML']);
		$this->assertStringContainsString('<li>', $result[0]['innerHTML']);
	}

	public static function unorderedListMarkerProvider(): array {
		return [
			'dash marker'     => ["- Item one\n- Item two"],
			'asterisk marker' => ["* Item one\n* Item two"],
			'plus marker'     => ["+ Item one\n+ Item two"],
		];
	}

	#[Test]
	public function it_converts_unordered_list_with_multiple_items(): void {
		$markdown = "- First item\n- Second item\n- Third item";
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		$this->assertEquals('core/list', $result[0]['blockName']);
		$this->assertFalse($result[0]['attrs']['ordered']);

		// Check all items are present
		$html = $result[0]['innerHTML'];
		$this->assertStringContainsString('First item', $html);
		$this->assertStringContainsString('Second item', $html);
		$this->assertStringContainsString('Third item', $html);

		// Count li tags
		$this->assertEquals(3, substr_count($html, '<li>'));
	}

	#[Test]
	public function it_converts_unordered_list_with_inline_formatting(): void {
		$markdown = "- Item with **bold**\n- Item with `code`";
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		$this->assertStringContainsString('<strong>bold</strong>', $result[0]['innerHTML']);
		$this->assertStringContainsString('<code>code</code>', $result[0]['innerHTML']);
	}

	// =========================
	// Ordered List Tests
	// =========================

	#[Test]
	public function it_converts_ordered_list(): void {
		$markdown = "1. First item\n2. Second item\n3. Third item";
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		$this->assertEquals('core/list', $result[0]['blockName']);
		$this->assertTrue($result[0]['attrs']['ordered']);
		$this->assertStringContainsString('<ol', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_ordered_list_with_different_starting_numbers(): void {
		// Even if numbers don't start at 1, it should still work
		$markdown = "5. Fifth item\n6. Sixth item";
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		$this->assertEquals('core/list', $result[0]['blockName']);
		$this->assertTrue($result[0]['attrs']['ordered']);
	}

	#[Test]
	public function it_keeps_separate_lists_when_type_changes(): void {
		$markdown = "- Unordered item\n\n1. Ordered item";
		$result = $this->converter->convert($markdown);

		$this->assertCount(2, $result);
		$this->assertFalse($result[0]['attrs']['ordered']);
		$this->assertTrue($result[1]['attrs']['ordered']);
	}

	// =========================
	// Horizontal Rule Tests
	// =========================

	#[Test]
	#[DataProvider('horizontalRuleProvider')]
	public function it_converts_horizontal_rules(string $markdown): void {
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		$this->assertEquals('core/separator', $result[0]['blockName']);
		$this->assertStringContainsString('<hr', $result[0]['innerHTML']);
	}

	public static function horizontalRuleProvider(): array {
		return [
			'dashes'    => ['---'],
			'asterisks' => ['***'],
			'underscores' => ['___'],
			'long dashes' => ['------'],
		];
	}

	// =========================
	// Inline Formatting Tests
	// =========================

	#[Test]
	public function it_converts_bold_with_asterisks(): void {
		$result = $this->converter->convert('This is **bold** text');

		$this->assertCount(1, $result);
		$this->assertStringContainsString('<strong>bold</strong>', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_bold_with_underscores(): void {
		$result = $this->converter->convert('This is __bold__ text');

		$this->assertCount(1, $result);
		$this->assertStringContainsString('<strong>bold</strong>', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_italic_with_asterisks(): void {
		$result = $this->converter->convert('This is *italic* text');

		$this->assertCount(1, $result);
		$this->assertStringContainsString('<em>italic</em>', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_italic_with_underscores(): void {
		$result = $this->converter->convert('This is _italic_ text');

		$this->assertCount(1, $result);
		$this->assertStringContainsString('<em>italic</em>', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_bold_and_italic_combined(): void {
		$result = $this->converter->convert('This is ***bold and italic*** text');

		$this->assertCount(1, $result);
		$this->assertStringContainsString('<strong><em>bold and italic</em></strong>', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_inline_code(): void {
		$result = $this->converter->convert('Use the `convert()` function');

		$this->assertCount(1, $result);
		$this->assertStringContainsString('<code>convert()</code>', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_links(): void {
		$result = $this->converter->convert('Visit [Google](https://google.com)');

		$this->assertCount(1, $result);
		$this->assertStringContainsString('<a href="https://google.com">Google</a>', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_images(): void {
		$result = $this->converter->convert('![Alt text](https://example.com/image.png)');

		$this->assertCount(1, $result);
		$this->assertStringContainsString('<img src="https://example.com/image.png" alt="Alt text"', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_images_with_empty_alt(): void {
		$result = $this->converter->convert('![](https://example.com/image.png)');

		$this->assertCount(1, $result);
		$this->assertStringContainsString('src="https://example.com/image.png"', $result[0]['innerHTML']);
		$this->assertStringContainsString('alt=""', $result[0]['innerHTML']);
	}

	#[Test]
	public function it_converts_multiple_inline_formats_in_same_paragraph(): void {
		$markdown = 'Text with **bold**, *italic*, and `code`';
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		$this->assertStringContainsString('<strong>bold</strong>', $result[0]['innerHTML']);
		$this->assertStringContainsString('<em>italic</em>', $result[0]['innerHTML']);
		$this->assertStringContainsString('<code>code</code>', $result[0]['innerHTML']);
	}

	// =========================
	// Complex Document Tests
	// =========================

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

		$result = $this->converter->convert($markdown);

		// Should have: h1, paragraph, h2, code, quote, h3, list, separator, paragraph
		$this->assertCount(9, $result);

		$this->assertEquals('core/heading', $result[0]['blockName']);
		$this->assertEquals(1, $result[0]['attrs']['level']);

		$this->assertEquals('core/paragraph', $result[1]['blockName']);

		$this->assertEquals('core/heading', $result[2]['blockName']);
		$this->assertEquals(2, $result[2]['attrs']['level']);

		$this->assertEquals('core/code', $result[3]['blockName']);
		$this->assertEquals('php', $result[3]['attrs']['language']);

		$this->assertEquals('core/quote', $result[4]['blockName']);

		$this->assertEquals('core/heading', $result[5]['blockName']);
		$this->assertEquals(3, $result[5]['attrs']['level']);

		$this->assertEquals('core/list', $result[6]['blockName']);
		$this->assertFalse($result[6]['attrs']['ordered']);

		$this->assertEquals('core/separator', $result[7]['blockName']);

		$this->assertEquals('core/paragraph', $result[8]['blockName']);
	}

	// =========================
	// Block Structure Tests
	// =========================

	#[Test]
	public function it_creates_blocks_with_correct_structure(): void {
		$result = $this->converter->convert('# Test');

		$this->assertArrayHasKey('blockName', $result[0]);
		$this->assertArrayHasKey('attrs', $result[0]);
		$this->assertArrayHasKey('innerBlocks', $result[0]);
		$this->assertArrayHasKey('innerHTML', $result[0]);
		$this->assertArrayHasKey('innerContent', $result[0]);

		$this->assertIsArray($result[0]['attrs']);
		$this->assertIsArray($result[0]['innerBlocks']);
		$this->assertIsArray($result[0]['innerContent']);
		$this->assertIsString($result[0]['innerHTML']);
	}

	#[Test]
	public function it_creates_inner_content_matching_inner_html(): void {
		$result = $this->converter->convert('Test paragraph');

		$this->assertEquals($result[0]['innerHTML'], $result[0]['innerContent'][0]);
	}

	#[Test]
	public function it_creates_empty_inner_blocks_array(): void {
		$result = $this->converter->convert('Test');

		$this->assertEmpty($result[0]['innerBlocks']);
	}

	// =========================
	// Edge Case Tests
	// =========================

	#[Test]
	public function it_handles_consecutive_empty_lines(): void {
		$markdown = "First\n\n\n\nSecond";
		$result = $this->converter->convert($markdown);

		$this->assertCount(2, $result);
	}

	#[Test]
	public function it_handles_code_block_at_end_without_closing(): void {
		// If code block is not closed, the content stays in buffer
		$markdown = "```php\necho 'test';";
		$result = $this->converter->convert($markdown);

		// Should still have some output (might be empty or buffered as paragraph)
		$this->assertIsArray($result);
	}

	#[Test]
	public function it_handles_list_at_end_of_document(): void {
		$markdown = "# Title\n\n- Item 1\n- Item 2";
		$result = $this->converter->convert($markdown);

		$this->assertCount(2, $result);
		$this->assertEquals('core/heading', $result[0]['blockName']);
		$this->assertEquals('core/list', $result[1]['blockName']);
	}

	#[Test]
	public function it_handles_special_characters_in_content(): void {
		$result = $this->converter->convert('Text with special chars: <>&"\'');

		$this->assertCount(1, $result);
		$this->assertEquals('core/paragraph', $result[0]['blockName']);
	}

	#[Test]
	public function it_preserves_whitespace_in_code_blocks(): void {
		$markdown = "```\n    indented\n        more indented\n```";
		$result = $this->converter->convert($markdown);

		$this->assertCount(1, $result);
		$this->assertStringContainsString('    indented', $result[0]['innerHTML']);
		$this->assertStringContainsString('        more indented', $result[0]['innerHTML']);
	}
}
