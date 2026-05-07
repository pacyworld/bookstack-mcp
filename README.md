# BookStack MCP Server

A pure PHP Model Context Protocol server for managing multiple [BookStack](https://www.bookstackapp.com/) wiki instances via AI assistants.

## Features

- **Multi-instance** — manage multiple BookStack instances from a single server
- **Token authentication** — uses BookStack API token ID/secret pairs
- **Comprehensive coverage** — books, pages, chapters, shelves, search, attachments, images
- Built on the [EnchiladaMCP](https://buenapp.org/docs/enchilada-mcp) library

## Requirements

- PHP 8.4+
- BookStack instance(s) with API access enabled

## Quick Start

```sh
# Configure instances
cp config/instances.json.sample config/instances.json
# Edit instances.json with your BookStack URL and API tokens

# Run
php bin/bookstack-mcp
```

## Configuration

Create a BookStack API token from your user profile in BookStack (Settings → API Tokens).

```json
{
    "default": "my-wiki",
    "instances": {
        "my-wiki": {
            "url": "https://wiki.example.com",
            "token_id": "abc123",
            "token_secret": "xyz789...",
            "description": "My BookStack Wiki"
        }
    }
}
```

## License

BSD 2-Clause — see [LICENSE](LICENSE).
