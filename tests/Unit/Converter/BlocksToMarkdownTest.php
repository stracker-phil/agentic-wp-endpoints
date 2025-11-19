<?php

declare( strict_types = 1 );

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
		$htmlConverter   = new HtmlConverter( [
			'strip_tags' => false,
			'hard_break' => true,
		] );
		$this->converter = new BlocksToMarkdown( $htmlConverter );
	}

	// =========================
	// Empty/Basic Input Tests
	// =========================

	/**
	 * GIVEN empty blocks array
	 * WHEN converting to markdown
	 * THEN empty markdown is returned with no fallback flag
	 */
	#[Test]
	public function it_returns_empty_markdown_for_empty_blocks(): void {
		$result = $this->converter->convert( [] );

		$this->assertArrayHasKey( 'markdown', $result );
		$this->assertArrayHasKey( 'has_html_fallback', $result );
		$this->assertEquals( '', $result['markdown'] );
		$this->assertFalse( $result['has_html_fallback'] );
	}

	/**
	 * GIVEN blocks with empty or null blockName
	 * WHEN converting to markdown
	 * THEN those blocks are skipped
	 *
	 * @dataProvider empty_block_name_provider
	 */
	#[Test]
	#[DataProvider( 'empty_block_name_provider' )]
	public function it_skips_blocks_with_empty_or_null_block_name( $block_name ): void {
		$blocks = [
			[
				'blockName'   => $block_name,
				'attrs'       => [],
				'innerHTML'   => 'Some content',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( '', $result['markdown'] );
	}

	public static function empty_block_name_provider(): array {
		return [
			'empty string' => [ '' ],
			'null value'   => [ null ],
		];
	}

	// =========================
	// Heading Tests
	// =========================

	/**
	 * GIVEN a core/heading block at any level (1-6)
	 * WHEN converting to markdown
	 * THEN the correct heading prefix is generated
	 *
	 * @dataProvider heading_level_provider
	 */
	#[Test]
	#[DataProvider( 'heading_level_provider' )]
	public function it_converts_headings_at_all_levels( int $level, string $expected_prefix ): void {
		$blocks = [
			[
				'blockName'   => 'core/heading',
				'attrs'       => [ 'level' => $level ],
				'innerHTML'   => "<h{$level}>Test Heading</h{$level}>",
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( "{$expected_prefix} Test Heading", $result['markdown'] );
		$this->assertFalse( $result['has_html_fallback'] );
	}

	public static function heading_level_provider(): array {
		return [
			'h1' => [ 1, '#' ],
			'h2' => [ 2, '##' ],
			'h3' => [ 3, '###' ],
			'h4' => [ 4, '####' ],
			'h5' => [ 5, '#####' ],
			'h6' => [ 6, '######' ],
		];
	}

	/**
	 * GIVEN a core/heading block without level attribute
	 * WHEN converting to markdown
	 * THEN it defaults to h2 level
	 */
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

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( '## Test Heading', $result['markdown'] );
	}

	/**
	 * GIVEN a core/heading block with inline formatting
	 * WHEN converting to markdown
	 * THEN the formatting is preserved in markdown syntax
	 */
	#[Test]
	public function it_converts_heading_with_inline_formatting(): void {
		$blocks = [
			[
				'blockName'   => 'core/heading',
				'attrs'       => [ 'level' => 2 ],
				'innerHTML'   => '<h2>Heading with <strong>bold</strong></h2>',
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( '## Heading with **bold**', $result['markdown'] );
	}

	// =========================
	// Paragraph Tests
	// =========================

	/**
	 * GIVEN a core/paragraph block with inline HTML formatting
	 * WHEN converting to markdown
	 * THEN the formatting is converted to markdown syntax
	 *
	 * @dataProvider paragraph_inline_formatting_provider
	 */
	#[Test]
	#[DataProvider( 'paragraph_inline_formatting_provider' )]
	public function it_converts_paragraph_with_inline_formatting( string $inner_html, string $expected_markdown ): void {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => $inner_html,
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( $expected_markdown, $result['markdown'] );
	}

	public static function paragraph_inline_formatting_provider(): array {
		return [
			'simple text' => [ '<p>This is a paragraph.</p>', 'This is a paragraph.' ],
			'strong tag'  => [
				'<p>Text with <strong>bold</strong> word.</p>',
				'Text with **bold** word.',
			],
			'b tag'       => [ '<p>Text with <b>bold</b> word.</p>', 'Text with **bold** word.' ],
			'em tag'      => [
				'<p>Text with <em>italic</em> word.</p>',
				'Text with *italic* word.',
			],
			'i tag'       => [ '<p>Text with <i>italic</i> word.</p>', 'Text with *italic* word.' ],
			'code tag'    => [
				'<p>Use the <code>convert()</code> function.</p>',
				'Use the `convert()` function.',
			],
			'link'        => [
				'<p>Visit <a href="https://example.com">Example</a> site.</p>',
				'Visit [Example](https://example.com) site.',
			],
			'image'       => [
				'<p><img src="https://example.com/img.png" alt="Test" /></p>',
				'![Test](https://example.com/img.png)',
			],
		];
	}

	/**
	 * GIVEN a core/paragraph block with br tag
	 * WHEN converting to markdown
	 * THEN line breaks are preserved
	 */
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

		$result = $this->converter->convert( $blocks );

		$this->assertStringContainsString( "Line one\nLine two", $result['markdown'] );
	}

	// =========================
	// Code Block Tests
	// =========================

	/**
	 * GIVEN a core/code block
	 * WHEN converting to markdown
	 * THEN fenced code block syntax is generated
	 *
	 * @dataProvider code_block_provider
	 */
	#[Test]
	#[DataProvider( 'code_block_provider' )]
	public function it_converts_code_blocks( array $attrs, string $inner_html, string $expected_markdown ): void {
		$blocks = [
			[
				'blockName'   => 'core/code',
				'attrs'       => $attrs,
				'innerHTML'   => $inner_html,
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( $expected_markdown, $result['markdown'] );
	}

	public static function code_block_provider(): array {
		return [
			'without language'  => [
				[],
				'<pre class="wp-block-code"><code>const x = 1;</code></pre>',
				"```\nconst x = 1;\n```",
			],
			'with language'     => [
				[ 'language' => 'javascript' ],
				'<pre class="wp-block-code"><code>const x = 1;</code></pre>',
				"```javascript\nconst x = 1;\n```",
			],
			'without code tags' => [
				[],
				'<pre>plain code</pre>',
				"```\nplain code\n```",
			],
		];
	}

	/**
	 * GIVEN a core/code block with HTML entities
	 * WHEN converting to markdown
	 * THEN entities are decoded
	 */
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

		$result = $this->converter->convert( $blocks );

		$this->assertStringContainsString( '<div>test</div>', $result['markdown'] );
	}

	// =========================
	// Quote Tests
	// =========================

	/**
	 * GIVEN a core/quote block
	 * WHEN converting to markdown
	 * THEN blockquote syntax is generated
	 */
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

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( '> This is a quote.', $result['markdown'] );
	}

	/**
	 * GIVEN a core/quote block with inline formatting
	 * WHEN converting to markdown
	 * THEN both quote syntax and formatting are preserved
	 */
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

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( '> Quote with **bold**', $result['markdown'] );
	}

	// =========================
	// List Tests
	// =========================

	/**
	 * GIVEN a core/list block
	 * WHEN converting to markdown
	 * THEN list syntax is generated based on ordered attribute
	 *
	 * @dataProvider list_provider
	 */
	#[Test]
	#[DataProvider( 'list_provider' )]
	public function it_converts_lists( array $attrs, string $inner_html, string $expected_markdown ): void {
		$blocks = [
			[
				'blockName'   => 'core/list',
				'attrs'       => $attrs,
				'innerHTML'   => $inner_html,
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( $expected_markdown, $result['markdown'] );
	}

	public static function list_provider(): array {
		return [
			'unordered list'       => [
				[ 'ordered' => false ],
				'<ul class="wp-block-list"><li>Item one</li><li>Item two</li><li>Item three</li></ul>',
				"- Item one\n- Item two\n- Item three",
			],
			'ordered list'         => [
				[ 'ordered' => true ],
				'<ol class="wp-block-list"><li>First</li><li>Second</li><li>Third</li></ol>',
				"1. First\n2. Second\n3. Third",
			],
			'default to unordered' => [
				[],
				'<ul><li>Item</li></ul>',
				'- Item',
			],
		];
	}

	// =========================
	// Separator Tests
	// =========================

	/**
	 * GIVEN a core/separator block
	 * WHEN converting to markdown
	 * THEN horizontal rule syntax is generated
	 */
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

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( '---', $result['markdown'] );
	}

	// =========================
	// Image Tests
	// =========================

	/**
	 * GIVEN a core/image block
	 * WHEN converting to markdown
	 * THEN image syntax is generated from attrs or HTML
	 *
	 * @dataProvider image_provider
	 */
	#[Test]
	#[DataProvider( 'image_provider' )]
	public function it_converts_images( array $attrs, string $inner_html, string $expected_markdown ): void {
		$blocks = [
			[
				'blockName'   => 'core/image',
				'attrs'       => $attrs,
				'innerHTML'   => $inner_html,
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( $expected_markdown, $result['markdown'] );
	}

	public static function image_provider(): array {
		return [
			'from attrs'              => [
				[ 'url' => 'https://example.com/image.png', 'alt' => 'Example image' ],
				'<figure><img src="https://example.com/image.png" alt="Example image"/></figure>',
				'![Example image](https://example.com/image.png)',
			],
			'from html when no attrs' => [
				[],
				'<figure><img src="https://example.com/image.png" alt="Alt text"/></figure>',
				'![Alt text](https://example.com/image.png)',
			],
			'with empty alt'          => [
				[ 'url' => 'https://example.com/image.png', 'alt' => '' ],
				'',
				'![](https://example.com/image.png)',
			],
			'no url available'        => [
				[],
				'<figure></figure>',
				'',
			],
		];
	}

	// =========================
	// Unsupported Block Tests
	// =========================

	/**
	 * GIVEN an unsupported block type
	 * WHEN converting to markdown
	 * THEN HTML fallback comment is generated
	 *
	 * @dataProvider unsupported_block_provider
	 */
	#[Test]
	#[DataProvider( 'unsupported_block_provider' )]
	public function it_handles_unsupported_blocks( string $block_name, string $inner_html, string $expected_content ): void {
		$blocks = [
			[
				'blockName'   => $block_name,
				'attrs'       => [],
				'innerHTML'   => $inner_html,
				'innerBlocks' => [],
			],
		];

		$result = $this->converter->convert( $blocks );

		$this->assertTrue( $result['has_html_fallback'] );
		$this->assertStringContainsString( "<!-- HTML BLOCK: {$block_name} -->", $result['markdown'] );

		if ( ! empty( $inner_html ) ) {
			$this->assertStringContainsString( '<!-- END HTML BLOCK -->', $result['markdown'] );
		}
	}

	public static function unsupported_block_provider(): array {
		return [
			'gallery with content' => [
				'core/gallery',
				'<figure class="wp-block-gallery">Gallery content</figure>',
				'Gallery content',
			],
			'spacer with no html'  => [ 'core/spacer', '', '' ],
		];
	}

	/**
	 * GIVEN only supported blocks
	 * WHEN converting to markdown
	 * THEN has_html_fallback is false
	 */
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

		$result = $this->converter->convert( $blocks );

		$this->assertFalse( $result['has_html_fallback'] );
	}

	// =========================
	// Multiple Block Tests
	// =========================

	/**
	 * GIVEN multiple blocks
	 * WHEN converting to markdown
	 * THEN blocks are joined with double newlines
	 */
	#[Test]
	public function it_joins_multiple_blocks_with_double_newline(): void {
		$blocks = [
			[
				'blockName'   => 'core/heading',
				'attrs'       => [ 'level' => 1 ],
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

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( "# Title\n\nContent", $result['markdown'] );
	}

	/**
	 * GIVEN a complex document with multiple block types
	 * WHEN converting to markdown
	 * THEN all blocks are correctly converted
	 */
	#[Test]
	public function it_converts_complex_document(): void {
		$blocks = [
			[
				'blockName'   => 'core/heading',
				'attrs'       => [ 'level' => 1 ],
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
				'attrs'       => [ 'language' => 'php' ],
				'innerHTML'   => '<pre class="wp-block-code"><code>echo "Hello";</code></pre>',
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/list',
				'attrs'       => [ 'ordered' => false ],
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

		$result = $this->converter->convert( $blocks );

		$this->assertStringContainsString( '# Main Title', $result['markdown'] );
		$this->assertStringContainsString( '**bold**', $result['markdown'] );
		$this->assertStringContainsString( '```php', $result['markdown'] );
		$this->assertStringContainsString( '- Item 1', $result['markdown'] );
		$this->assertStringContainsString( '---', $result['markdown'] );
		$this->assertFalse( $result['has_html_fallback'] );
	}

	// =========================
	// Edge Case Tests
	// =========================

	/**
	 * GIVEN a block with missing optional keys
	 * WHEN converting to markdown
	 * THEN conversion handles it gracefully
	 *
	 * @dataProvider missing_keys_provider
	 */
	#[Test]
	#[DataProvider( 'missing_keys_provider' )]
	public function it_handles_missing_optional_keys( array $block, string $expected_markdown ): void {
		$result = $this->converter->convert( [ $block ] );

		$this->assertEquals( $expected_markdown, $result['markdown'] );
	}

	public static function missing_keys_provider(): array {
		return [
			'missing attrs key'     => [
				[
					'blockName'   => 'core/paragraph',
					'innerHTML'   => '<p>No attrs</p>',
					'innerBlocks' => [],
				],
				'No attrs',
			],
			'missing innerHTML key' => [
				[
					'blockName'   => 'core/separator',
					'attrs'       => [],
					'innerBlocks' => [],
				],
				'---',
			],
		];
	}

	/**
	 * GIVEN a paragraph with HTML entities
	 * WHEN converting to markdown
	 * THEN entities are decoded
	 */
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

		$result = $this->converter->convert( $blocks );

		$this->assertStringContainsString( '&', $result['markdown'] );
		$this->assertStringContainsString( '<', $result['markdown'] );
		$this->assertStringContainsString( '>', $result['markdown'] );
	}

	/**
	 * GIVEN the converter is used multiple times
	 * WHEN converting different block sets
	 * THEN the fallback flag resets correctly
	 */
	#[Test]
	public function it_resets_fallback_flag_on_each_convert(): void {
		// First conversion with unsupported block.
		$blocks1 = [
			[
				'blockName'   => 'core/gallery',
				'attrs'       => [],
				'innerHTML'   => '<figure>Gallery</figure>',
				'innerBlocks' => [],
			],
		];
		$result1 = $this->converter->convert( $blocks1 );
		$this->assertTrue( $result1['has_html_fallback'] );

		// Second conversion with only supported blocks.
		$blocks2 = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerHTML'   => '<p>Paragraph</p>',
				'innerBlocks' => [],
			],
		];
		$result2 = $this->converter->convert( $blocks2 );
		$this->assertFalse( $result2['has_html_fallback'] );
	}

	/**
	 * GIVEN paragraph with link containing extra attributes
	 * WHEN converting to markdown
	 * THEN only href is preserved in markdown
	 */
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

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( '[Link](https://example.com)', $result['markdown'] );
	}

	/**
	 * GIVEN paragraph with img where alt comes before src
	 * WHEN converting to markdown
	 * THEN image is correctly parsed
	 */
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

		$result = $this->converter->convert( $blocks );

		$this->assertEquals( '![Test](https://example.com/img.png)', $result['markdown'] );
	}
}
