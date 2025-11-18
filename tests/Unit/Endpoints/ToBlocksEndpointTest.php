<?php

declare(strict_types=1);

namespace AgenticEndpoints\Tests\Unit\Endpoints;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use AgenticEndpoints\Endpoints\ToBlocksEndpoint;
use AgenticEndpoints\Converter\MarkdownToBlocks;
use Parsedown;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Unit tests for ToBlocksEndpoint.
 */
class ToBlocksEndpointTest extends TestCase {

	private ToBlocksEndpoint $endpoint;
	private MarkdownToBlocks $converter;

	protected function setUp(): void {
		parent::setUp();

		$parsedown = new Parsedown();
		$parsedown->setSafeMode(true);
		$this->converter = new MarkdownToBlocks($parsedown);
		$this->endpoint = new ToBlocksEndpoint($this->converter);

		// Reset global mocks
		global $registered_rest_routes;
		$registered_rest_routes = [];
	}

	// =========================
	// Route Configuration Tests
	// =========================

	#[Test]
	public function it_registers_correct_route(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertCount(1, $registered_rest_routes);
		$this->assertEquals('agentic/v1', $registered_rest_routes[0]['namespace']);
		$this->assertEquals('/agentic-post', $registered_rest_routes[0]['route']);
	}

	#[Test]
	public function it_uses_post_method(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$this->assertEquals('POST', $registered_rest_routes[0]['args']['methods']);
	}

	#[Test]
	public function it_has_markdown_parameter_in_args(): void {
		global $registered_rest_routes;

		$this->endpoint->register();

		$args = $registered_rest_routes[0]['args']['args'];
		$this->assertArrayHasKey('markdown', $args);
		$this->assertEquals('string', $args['markdown']['type']);
		$this->assertTrue($args['markdown']['required']);
	}

	// =========================
	// Handle Method Tests - Success Cases
	// =========================

	#[Test]
	public function it_converts_simple_markdown_to_blocks(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', '# Hello World');

		$response = $this->endpoint->handle($request);

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertEquals(200, $response->get_status());

		$data = $response->get_data();
		$this->assertArrayHasKey('blocks', $data);
		$this->assertArrayHasKey('block_content', $data);
		$this->assertArrayHasKey('block_count', $data);
	}

	#[Test]
	public function it_returns_correct_block_count(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', "# Title\n\nParagraph\n\n## Subtitle");

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertEquals(3, $data['block_count']);
		$this->assertCount(3, $data['blocks']);
	}

	#[Test]
	public function it_returns_valid_block_structure(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', '# Test');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();
		$block = $data['blocks'][0];

		$this->assertArrayHasKey('blockName', $block);
		$this->assertArrayHasKey('attrs', $block);
		$this->assertArrayHasKey('innerBlocks', $block);
		$this->assertArrayHasKey('innerHTML', $block);
		$this->assertArrayHasKey('innerContent', $block);
	}

	#[Test]
	public function it_returns_serialized_block_content(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', '# Test');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertStringContainsString('<!-- wp:core/heading', $data['block_content']);
		$this->assertStringContainsString('<!-- /wp:core/heading -->', $data['block_content']);
	}

	#[Test]
	public function it_serializes_block_with_attributes(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', '## Level 2 Heading');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertStringContainsString('"level":2', $data['block_content']);
	}

	#[Test]
	public function it_serializes_block_without_attributes(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', 'Simple paragraph');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		// Should not have JSON attributes for paragraph
		$this->assertStringContainsString('<!-- wp:core/paragraph -->', $data['block_content']);
	}

	#[Test]
	public function it_converts_complex_markdown(): void {
		$markdown = <<<MD
# Title

Introduction paragraph.

```php
echo "code";
```

- Item 1
- Item 2
MD;

		$request = new WP_REST_Request();
		$request->set_param('markdown', $markdown);

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertEquals(4, $data['block_count']);

		// Verify block types
		$blockNames = array_column($data['blocks'], 'blockName');
		$this->assertContains('core/heading', $blockNames);
		$this->assertContains('core/paragraph', $blockNames);
		$this->assertContains('core/code', $blockNames);
		$this->assertContains('core/list', $blockNames);
	}

	// =========================
	// Handle Method Tests - Error Cases
	// =========================

	#[Test]
	public function it_returns_error_for_empty_markdown(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', '');

		$response = $this->endpoint->handle($request);

		$this->assertInstanceOf(WP_Error::class, $response);
		$this->assertEquals('empty_markdown', $response->get_error_code());
		$this->assertEquals(['status' => 400], $response->get_error_data());
	}

	#[Test]
	public function it_returns_error_for_null_markdown(): void {
		$request = new WP_REST_Request();
		// Not setting markdown parameter

		$response = $this->endpoint->handle($request);

		$this->assertInstanceOf(WP_Error::class, $response);
		$this->assertEquals('empty_markdown', $response->get_error_code());
	}

	#[Test]
	public function it_returns_zero_blocks_for_whitespace_only_markdown(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', '   ');

		$response = $this->endpoint->handle($request);

		// Whitespace-only is processed and returns zero blocks
		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$data = $response->get_data();
		$this->assertEquals(0, $data['block_count']);
	}

	// =========================
	// Edge Case Tests
	// =========================

	#[Test]
	public function it_handles_markdown_with_only_whitespace_and_newlines(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', "\n\n   \n\n");

		$response = $this->endpoint->handle($request);

		// Whitespace and newlines are processed and return zero blocks
		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$data = $response->get_data();
		$this->assertEquals(0, $data['block_count']);
	}

	#[Test]
	public function it_returns_zero_blocks_for_only_newlines(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', "\n\n");

		$response = $this->endpoint->handle($request);

		// Only newlines are processed and return zero blocks
		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$data = $response->get_data();
		$this->assertEquals(0, $data['block_count']);
	}

	#[Test]
	public function it_preserves_special_characters_in_blocks(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', 'Text with <special> & "chars"');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertEquals(1, $data['block_count']);
	}

	#[Test]
	public function it_handles_unicode_content(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', '# Привет мир 你好世界');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertStringContainsString('Привет', $data['blocks'][0]['innerHTML']);
		$this->assertStringContainsString('你好', $data['blocks'][0]['innerHTML']);
	}

	#[Test]
	public function it_handles_very_long_markdown(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', str_repeat("Paragraph\n\n", 100));

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertEquals(100, $data['block_count']);
	}

	// =========================
	// Serialization Tests
	// =========================

	#[Test]
	public function it_serializes_multiple_blocks_correctly(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', "# One\n\n## Two\n\n### Three");

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		// Count block comments
		$openComments = substr_count($data['block_content'], '<!-- wp:');
		$closeComments = substr_count($data['block_content'], '<!-- /wp:');

		$this->assertEquals(3, $openComments);
		$this->assertEquals(3, $closeComments);
	}

	#[Test]
	public function it_trims_serialized_output(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', '# Test');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		// Should not have trailing whitespace
		$this->assertEquals($data['block_content'], trim($data['block_content']));
	}

	#[Test]
	public function it_includes_inner_html_in_serialized_output(): void {
		$request = new WP_REST_Request();
		$request->set_param('markdown', 'Test paragraph');

		$response = $this->endpoint->handle($request);
		$data = $response->get_data();

		$this->assertStringContainsString('<p>Test paragraph</p>', $data['block_content']);
	}
}
