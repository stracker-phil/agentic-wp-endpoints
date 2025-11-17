<?php
/**
 * WPDI Container Configuration
 *
 * Binds interfaces to concrete implementations.
 */

declare( strict_types = 1 );

use League\HTMLToMarkdown\HtmlConverter;

return [
	// Bind Parsedown with safe mode enabled.
	Parsedown::class     => static function (): Parsedown {
		$parsedown = new Parsedown();
		$parsedown->setSafeMode( true );

		return $parsedown;
	},

	// Bind HTML to Markdown converter with options.
	HtmlConverter::class => static function (): HtmlConverter {
		return new HtmlConverter( [
			'strip_tags' => false,
			'hard_break' => true,
		] );
	},
];
