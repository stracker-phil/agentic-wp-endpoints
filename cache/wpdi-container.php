<?php

// Auto-generated WPDI cache - do not edit
// Generated: 2025-11-17 21:50:18
// Contains: 5 discovered classes
// Format: class => [path, mtime, dependencies]

return array (
  'AgenticEndpoints\\Endpoints\\ToBlocksEndpoint' => 
  array (
    'path' => '/var/www/html/src/Endpoints/ToBlocksEndpoint.php',
    'mtime' => 1763412505,
    'dependencies' => 
    array (
      0 => 'AgenticEndpoints\\Converter\\MarkdownToBlocks',
    ),
  ),
  'AgenticEndpoints\\Endpoints\\ToMarkdownEndpoint' => 
  array (
    'path' => '/var/www/html/src/Endpoints/ToMarkdownEndpoint.php',
    'mtime' => 1763412505,
    'dependencies' => 
    array (
      0 => 'AgenticEndpoints\\Converter\\BlocksToMarkdown',
    ),
  ),
  'AgenticEndpoints\\Converter\\BlocksToMarkdown' => 
  array (
    'path' => '/var/www/html/src/Converter/BlocksToMarkdown.php',
    'mtime' => 1763412471,
    'dependencies' => 
    array (
      0 => 'League\\HTMLToMarkdown\\HtmlConverter',
    ),
  ),
  'AgenticEndpoints\\Converter\\MarkdownToBlocks' => 
  array (
    'path' => '/var/www/html/src/Converter/MarkdownToBlocks.php',
    'mtime' => 1763412429,
    'dependencies' => 
    array (
      0 => 'Parsedown',
    ),
  ),
  'AgenticEndpoints\\Application' => 
  array (
    'path' => '/var/www/html/src/Application.php',
    'mtime' => 1763413487,
    'dependencies' => 
    array (
      0 => 'AgenticEndpoints\\Endpoints\\ToBlocksEndpoint',
      1 => 'AgenticEndpoints\\Endpoints\\ToMarkdownEndpoint',
    ),
  ),
);
