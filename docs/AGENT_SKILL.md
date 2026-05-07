---
name: bookstack-mcp
description: Install and configure the BookStack MCP server for wiki management via AI assistants
---

# BookStack MCP Server — Installation Skill

## Prerequisites

- PHP 8.4+ with curl extension
- A BookStack instance with API access enabled
- An API token (create from User Menu → API Tokens → Create Token in BookStack)

## Install (PHAR method — recommended)

```sh
# Download
curl -LO https://pacyworld.dev/pacyworld/bookstack-mcp/releases/latest/download/bookstack-mcp.phar
chmod +x bookstack-mcp.phar

# Place somewhere in PATH (optional)
sudo mv bookstack-mcp.phar /usr/local/bin/bookstack-mcp
```

## Configure

Create the config file at `~/.config/bookstack-mcp/instances.json`:

```json
{
    "default": "my-wiki",
    "instances": {
        "my-wiki": {
            "url": "https://your-bookstack-url.com",
            "token_id": "YOUR_TOKEN_ID",
            "token_secret": "YOUR_TOKEN_SECRET",
            "description": "My Wiki"
        }
    }
}
```

Multiple instances are supported — add more entries to `instances`.

## Add to Windsurf

Edit `~/.codeium/windsurf/mcp_config.json` and add:

```json
"bookstack": {
    "command": "php",
    "args": ["/usr/local/bin/bookstack-mcp"],
    "env": {
        "BOOKSTACK_CONFIG": "/home/YOUR_USER/.config/bookstack-mcp/instances.json"
    }
}
```

Or if running from source:

```json
"bookstack": {
    "command": "php",
    "args": ["/path/to/bookstack-mcp/bin/bookstack-mcp"],
    "env": {
        "BOOKSTACK_CONFIG": "/path/to/bookstack-mcp/config/instances.json"
    }
}
```

## Verify

After restarting Windsurf, the `bookstack_*` tools should be available. Test with:
- `bookstack_list_instances` — shows configured instances
- `bookstack_books_list` — lists books on the default instance
- `bookstack_search` — searches content

## Available Tool Categories

- **Content**: books, pages, chapters, shelves (CRUD + export)
- **Media**: attachments, images
- **Search**: full-text with advanced syntax
- **Admin**: users, roles, permissions, audit log, recycle bin
- **System**: instance info, help, error guides

## Multi-Instance Usage

All tools accept an optional `instance` parameter to target a specific instance:
```
bookstack_books_list instance="other-wiki"
```

Or switch the default at runtime:
```
bookstack_switch_instance instance="other-wiki"
```

## Source Repository

https://pacyworld.dev/pacyworld/bookstack-mcp
