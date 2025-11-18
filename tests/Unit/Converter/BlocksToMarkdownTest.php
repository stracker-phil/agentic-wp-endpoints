<?php

declare(strict_types=1);

namespace AgenticEndpoints\Tests\Unit\Converter;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use AgenticEndpoints\Converter\BlocksToMarkdown;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Unit tests for BlocksToMarkdown converter.
 */
class BlocksToMarkdownTest extends TestCase {

	private BlocksToMarkdown $converter;

	protected function setUp(): void {
		parent::setUp();
		$htmlConverter = new HtmlConverter([
			'strip_tags' => false,
			'hard_break' => true,
		]);
		$this->converter = new BlocksToMarkdown($htmlConverter);
	}

	// =========================
	// Empty/Basic Input Tests
	// =========================

	#[Test]
	public function it_returns_empty_markdown_for_empty_blocks(): void {
		$result = $this->converter->convert([]);

		$this->assertArrayHasKey('markdown', $result);
		$this->assertArrayHasKey('has_html_fallback', $result);
		$this->assertEquals('', $result['markdown']);
		$this->assertFalse($result['has_html_fallback']);
	}

	#[Test]
	public function it_skips_blocks_with_empty_block_name(): void {
		$blocks = [
			[
				'blockName'   => '',
				'attrs'       => [],
				'innerHTML'   => 'Some content',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('', $result['markdown']);
	}

	#[Test]
	public function it_skips_blocks_with_null_block_name(): void {
		$blocks = [
			[
				'blockName'   => null,
				'attrs'       => [],
				'innerHTML'   => 'Some content',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('', $result['markdown']);
	}

	// =========================
	// Heading Tests
	// =========================

	#[Test]
	#[DataProvider('headingLevelProvider')]
	public function it_converts_headings_at_all_levels(int $level, string $expectedPrefix): void {
		$blocks = [
			[
				'blockName'   => 'core/heading',
				'attrs'       => ['level' => $level],
				'innerHTML'   => "<h{$level}>Test Heading</h{$level}>",
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals("{$expectedPrefix} Test Heading", $result['markdown']);
		$this->assertFalse($result['has_html_fallback']);
	}

	public static function headingLevelProvider(): array {
		return [
			'h1' => [1, '#'],
			'h2' => [2, '##'],
			'h3' => [3, '###'],
			'h4' => [4, '####'],
			'h5' => [5, '#####'],
			'h6' => [6, '######'],
		];
	}

	#[Test]
	public function it_defaults_to_h2_when_level_not_specified(): void {
		$blocks = [
			[
				'blockName'   => 'core/heading',
				'attrs'       => [],
				'innerHTML'   => '<h2>Test Heading</h2>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('## Test Heading', $result['markdown']);
	}

	#[Test]
	public function it_converts_heading_with_inline_formatting(): void {
		$blocks = [
			[
				'blockName'   => 'core/heading',
				'attrs'       => ['level' => 2],
				'innerHTML'   => '<h2>Heading with <strong>bold</strong></h2>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('## Heading with **bold**', $result['markdown']);
	}

	// =========================
	// Paragraph Tests
	// =========================

	#[Test]
	public function it_converts_simple_paragraph(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>This is a paragraph.</p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('This is a paragraph.', $result['markdown']);
		$this->assertFalse($result['has_html_fallback']);
	}

	#[Test]
	public function it_converts_paragraph_with_strong_tag(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Text with <strong>bold</strong> word.</p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('Text with **bold** word.', $result['markdown']);
	}

	#[Test]
	public function it_converts_paragraph_with_b_tag(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Text with <b>bold</b> word.</p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('Text with **bold** word.', $result['markdown']);
	}

	#[Test]
	public function it_converts_paragraph_with_em_tag(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Text with <em>italic</em> word.</p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('Text with *italic* word.', $result['markdown']);
	}

	#[Test]
	public function it_converts_paragraph_with_i_tag(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Text with <i>italic</i> word.</p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('Text with *italic* word.', $result['markdown']);
	}

	#[Test]
	public function it_converts_paragraph_with_code_tag(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Use the <code>convert()</code> function.</p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('Use the `convert()` function.', $result['markdown']);
	}

	#[Test]
	public function it_converts_paragraph_with_link(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Visit <a href="https://example.com">Example</a> site.</p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('Visit [Example](https://example.com) site.', $result['markdown']);
	}

	#[Test]
	public function it_converts_paragraph_with_image(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p><img src="https://example.com/img.png" alt="Test" /></p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('![Test](https://example.com/img.png)', $result['markdown']);
	}

	#[Test]
	public function it_converts_paragraph_with_br_tag(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Line one<br />Line two</p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertStringContainsString("Line one\nLine two", $result['markdown']);
	}

	// =========================
	// Code Block Tests
	// =========================

	#[Test]
	public function it_converts_code_block_without_language(): void {
		$blocks = [
			[
				'blockName'   => 'core/code',
				'attrs'       => [],
				'innerHTML'   => '<pre class="wp-block-code"><code>const x = 1;</code></pre>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$expected = "```\nconst x = 1;\n```";
		$this->assertEquals($expected, $result['markdown']);
	}

	#[Test]
	public function it_converts_code_block_with_language(): void {
		$blocks = [
			[
				'blockName'   => 'core/code',
				'attrs'       => ['language' => 'javascript'],
				'innerHTML'   => '<pre class="wp-block-code"><code>const x = 1;</code></pre>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$expected = "```javascript\nconst x = 1;\n```";
		$this->assertEquals($expected, $result['markdown']);
	}

	#[Test]
	public function it_decodes_html_entities_in_code(): void {
		$blocks = [
			[
				'blockName'   => 'core/code',
				'attrs'       => [],
				'innerHTML'   => '<pre class="wp-block-code"><code>&lt;div&gt;test&lt;/div&gt;</code></pre>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertStringContainsString('<div>test</div>', $result['markdown']);
	}

	#[Test]
	public function it_handles_code_without_code_tags(): void {
		$blocks = [
			[
				'blockName'   => 'core/code',
				'attrs'       => [],
				'innerHTML'   => '<pre>plain code</pre>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertStringContainsString('plain code', $result['markdown']);
	}

	// =========================
	// Quote Tests
	// =========================

	#[Test]
	public function it_converts_simple_quote(): void {
		$blocks = [
			[
				'blockName'   => 'core/quote',
				'attrs'       => [],
				'innerHTML'   => '<blockquote class="wp-block-quote"><p>This is a quote.</p></blockquote>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('> This is a quote.', $result['markdown']);
	}

	#[Test]
	public function it_converts_quote_with_inline_formatting(): void {
		$blocks = [
			[
				'blockName'   => 'core/quote',
				'attrs'       => [],
				'innerHTML'   => '<blockquote><p>Quote with <strong>bold</strong></p></blockquote>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('> Quote with **bold**', $result['markdown']);
	}

	// =========================
	// List Tests
	// =========================

	#[Test]
	public function it_converts_unordered_list(): void {
		$blocks = [
			[
				'blockName'   => 'core/list',
				'attrs'       => ['ordered' => false],
				'innerHTML'   => '<ul class="wp-block-list"><li>Item one</li><li>Item two</li><li>Item three</li></ul>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$expected = "- Item one\n- Item two\n- Item three";
		$this->assertEquals($expected, $result['markdown']);
	}

	#[Test]
	public function it_converts_ordered_list(): void {
		$blocks = [
			[
				'blockName'   => 'core/list',
				'attrs'       => ['ordered' => true],
				'innerHTML'   => '<ol class="wp-block-list"><li>First</li><li>Second</li><li>Third</li></ol>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$expected = "1. First\n2. Second\n3. Third";
		$this->assertEquals($expected, $result['markdown']);
	}

	#[Test]
	public function it_defaults_to_unordered_when_ordered_not_specified(): void {
		$blocks = [
			[
				'blockName'   => 'core/list',
				'attrs'       => [],
				'innerHTML'   => '<ul><li>Item</li></ul>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('- Item', $result['markdown']);
	}

	// =========================
	// Separator Tests
	// =========================

	#[Test]
	public function it_converts_separator(): void {
		$blocks = [
			[
				'blockName'   => 'core/separator',
				'attrs'       => [],
				'innerHTML'   => '<hr class="wp-block-separator"/>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('---', $result['markdown']);
	}

	// =========================
	// Image Tests
	// =========================

	#[Test]
	public function it_converts_image_from_attrs(): void {
		$blocks = [
			[
				'blockName'   => 'core/image',
				'attrs'       => [
					'url' => 'https://example.com/image.png',
					'alt' => 'Example image',
				],
				'innerHTML'   => '<figure><img src="https://example.com/image.png" alt="Example image"/></figure>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('![Example image](https://example.com/image.png)', $result['markdown']);
	}

	#[Test]
	public function it_converts_image_from_html_when_no_attrs(): void {
		$blocks = [
			[
				'blockName'   => 'core/image',
				'attrs'       => [],
				'innerHTML'   => '<figure><img src="https://example.com/image.png" alt="Alt text"/></figure>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('![Alt text](https://example.com/image.png)', $result['markdown']);
	}

	#[Test]
	public function it_returns_empty_for_image_without_url(): void {
		$blocks = [
			[
				'blockName'   => 'core/image',
				'attrs'       => [],
				'innerHTML'   => '<figure></figure>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('', $result['markdown']);
	}

	#[Test]
	public function it_converts_image_with_empty_alt(): void {
		$blocks = [
			[
				'blockName'   => 'core/image',
				'attrs'       => [
					'url' => 'https://example.com/image.png',
					'alt' => '',
				],
				'innerHTML'   => '',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('![](https://example.com/image.png)', $result['markdown']);
	}

	// =========================
	// Unsupported Block Tests
	// =========================

	#[Test]
	public function it_falls_back_to_html_comment_for_unsupported_blocks(): void {
		$blocks = [
			[
				'blockName'   => 'core/gallery',
				'attrs'       => [],
				'innerHTML'   => '<figure class="wp-block-gallery">Gallery content</figure>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertTrue($result['has_html_fallback']);
		$this->assertStringContainsString('<!-- HTML BLOCK: core/gallery -->', $result['markdown']);
		$this->assertStringContainsString('Gallery content', $result['markdown']);
		$this->assertStringContainsString('<!-- END HTML BLOCK -->', $result['markdown']);
	}

	#[Test]
	public function it_handles_unsupported_block_with_empty_html(): void {
		$blocks = [
			[
				'blockName'   => 'core/spacer',
				'attrs'       => [],
				'innerHTML'   => '',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertTrue($result['has_html_fallback']);
		$this->assertEquals('<!-- HTML BLOCK: core/spacer -->', $result['markdown']);
	}

	#[Test]
	public function it_sets_fallback_flag_only_when_unsupported_blocks_present(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Supported</p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertFalse($result['has_html_fallback']);
	}

	// =========================
	// Multiple Block Tests
	// =========================

	#[Test]
	public function it_joins_multiple_blocks_with_double_newline(): void {
		$blocks = [
			[
				'blockName'   => 'core/heading',
				'attrs'       => ['level' => 1],
				'innerHTML'   => '<h1>Title</h1>',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Content</p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals("# Title\n\nContent", $result['markdown']);
	}

	#[Test]
	public function it_converts_complex_document(): void {
		$blocks = [
			[
				'blockName'   => 'core/heading',
				'attrs'       => ['level' => 1],
				'innerHTML'   => '<h1>Main Title</h1>',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Introduction paragraph with <strong>bold</strong> text.</p>',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/code',
				'attrs'       => ['language' => 'php'],
				'innerHTML'   => '<pre class="wp-block-code"><code>echo "Hello";</code></pre>',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/list',
				'attrs'       => ['ordered' => false],
				'innerHTML'   => '<ul><li>Item 1</li><li>Item 2</li></ul>',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/separator',
				'attrs'       => [],
				'innerHTML'   => '<hr/>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertStringContainsString('# Main Title', $result['markdown']);
		$this->assertStringContainsString('**bold**', $result['markdown']);
		$this->assertStringContainsString('```php', $result['markdown']);
		$this->assertStringContainsString('- Item 1', $result['markdown']);
		$this->assertStringContainsString('---', $result['markdown']);
		$this->assertFalse($result['has_html_fallback']);
	}

	// =========================
	// Edge Case Tests
	// =========================

	#[Test]
	public function it_handles_missing_attrs_key(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'innerHTML'   => '<p>No attrs</p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('No attrs', $result['markdown']);
	}

	#[Test]
	public function it_handles_missing_inner_html_key(): void {
		$blocks = [
			[
				'blockName'   => 'core/separator',
				'attrs'       => [],
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('---', $result['markdown']);
	}

	#[Test]
	public function it_decodes_html_entities_in_paragraph(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>&amp; &lt; &gt; &quot; &#039;</p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertStringContainsString('&', $result['markdown']);
		$this->assertStringContainsString('<', $result['markdown']);
		$this->assertStringContainsString('>', $result['markdown']);
	}

	#[Test]
	public function it_resets_fallback_flag_on_each_convert(): void {
		// First conversion with unsupported block
		$blocks1 = [
			[
				'blockName'   => 'core/gallery',
				'attrs'       => [],
				'innerHTML'   => '<figure>Gallery</figure>',
				'innerBlocks' => [],
			],
		];
		$result1 = $this->converter->convert($blocks1);
		$this->assertTrue($result1['has_html_fallback']);

		// Second conversion with only supported blocks
		$blocks2 = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Paragraph</p>',
				'innerBlocks' => [],
			],
		];
		$result2 = $this->converter->convert($blocks2);
		$this->assertFalse($result2['has_html_fallback']);
	}

	#[Test]
	public function it_handles_link_with_other_attributes(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p><a href="https://example.com" target="_blank" rel="noopener">Link</a></p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('[Link](https://example.com)', $result['markdown']);
	}

	#[Test]
	public function it_handles_image_with_alt_before_src(): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p><img alt="Test" src="https://example.com/img.png" /></p>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert($blocks);

		$this->assertEquals('![Test](https://example.com/img.png)', $result['markdown']);
	}
}
