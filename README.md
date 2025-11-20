# Agentic Endpoints

WordPress plugin that provides REST API endpoints for converting between Markdown and Gutenberg blocks, with support for AI agent note-taking.

## Features

- Convert WordPress post content (Gutenberg blocks) to Markdown
- Update WordPress posts with Markdown content (automatically converted to blocks)
- Manage AI agent notes for posts via dedicated endpoints
- Full REST API with proper authentication

## Authentication

All endpoints require the `edit_posts` capability. Users must be authenticated and have permission to edit posts.

## Base URL

All endpoints are namespaced under:

```
/wp-json/agentic/v1/
```

## Endpoints

### Get Post as Markdown

Retrieve a WordPress post with its content converted to Markdown.

**Endpoint:** `GET /wp-json/agentic/v1/agentic-post/{id}`

**Parameters:**
- `id` (required): Post ID in URL path

**Response:**
```json
{
  "post_id": 123,
  "post_title": "My Post Title",
  "post_status": "publish",
  "post_name": "my-post-slug",
  "post_date": "2025-01-15 10:30:00",
  "post_modified": "2025-01-15 12:45:00",
  "markdown": "# Heading\n\nParagraph text...",
  "has_html_fallback": false,
  "agent_notes": "Previous notes about this post"
}
```

**Example:**
```bash
curl -X GET "https://example.com/wp-json/agentic/v1/agentic-post/123" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Replace Post Content

Update a WordPress post by providing Markdown content that will be converted to Gutenberg blocks.

**Endpoint:** `POST /wp-json/agentic/v1/agentic-post/{id}`

**Parameters:**
- `id` (required): Post ID in URL path
- `markdown` (required): Markdown content to convert to blocks
- `agent_notes` (optional): Notes for the AI agent to track progress

**Request Body:**
```json
{
  "markdown": "# Updated Heading\n\nNew paragraph content...",
  "agent_notes": "Updated heading and first paragraph"
}
```

**Response:**
```json
{
  "post_id": 123,
  "blocks": [
    {
      "blockName": "core/heading",
      "attrs": { "level": 1 },
      "innerHTML": "<h1>Updated Heading</h1>"
    },
    {
      "blockName": "core/paragraph",
      "attrs": {},
      "innerHTML": "<p>New paragraph content...</p>"
    }
  ],
  "block_content": "<!-- wp:heading {\"level\":1} -->\n<h1>Updated Heading</h1>\n<!-- /wp:heading -->...",
  "block_count": 2
}
```

**Example:**
```bash
curl -X POST "https://example.com/wp-json/agentic/v1/agentic-post/123" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "markdown": "# Updated Heading\n\nNew content",
    "agent_notes": "Made updates to heading"
  }'
```

---

### Get Agent Notes

Retrieve agent notes for a specific post. Returns plain text.

**Endpoint:** `GET /wp-json/agentic/v1/agentic-post/{id}/notes`

**Parameters:**
- `id` (required): Post ID in URL path

**Response:**
Plain text string containing the notes (empty string if no notes exist).

**Example:**
```bash
curl -X GET "https://example.com/wp-json/agentic/v1/agentic-post/123/notes" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Replace Agent Notes

Update or create agent notes for a specific post.

**Endpoint:** `POST /wp-json/agentic/v1/agentic-post/{id}/notes`

**Parameters:**
- `id` (required): Post ID in URL path
- `notes` (required): Notes content for the AI agent

**Request Body:**
```json
{
  "notes": "Agent processed this post on 2025-01-15. Updated heading structure."
}
```

**Response:**
```json
{
  "post_id": 123,
  "notes": "Agent processed this post on 2025-01-15. Updated heading structure."
}
```

**Example:**
```bash
curl -X POST "https://example.com/wp-json/agentic/v1/agentic-post/123/notes" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "notes": "Processing notes here"
  }'
```

---

### Clear Agent Notes

Delete agent notes for a specific post.

**Endpoint:** `DELETE /wp-json/agentic/v1/agentic-post/{id}/notes`

**Parameters:**
- `id` (required): Post ID in URL path

**Response:**
```json
{
  "post_id": 123,
  "deleted": true
}
```

**Example:**
```bash
curl -X DELETE "https://example.com/wp-json/agentic/v1/agentic-post/123/notes" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Error Responses

All endpoints return standard WordPress REST API error responses:

**Post Not Found (404):**
```json
{
  "code": "post_not_found",
  "message": "Post not found.",
  "data": {
    "status": 404
  }
}
```

**Permission Denied (403):**
```json
{
  "code": "rest_forbidden",
  "message": "You do not have permission to access this endpoint.",
  "data": {
    "status": 403
  }
}
```

**Empty Markdown (400):**
```json
{
  "code": "empty_markdown",
  "message": "Markdown content cannot be empty.",
  "data": {
    "status": 400
  }
}
```

**Conversion Failed (500):**
```json
{
  "code": "conversion_failed",
  "message": "Failed to convert Markdown: [error details]",
  "data": {
    "status": 500
  }
}
```
