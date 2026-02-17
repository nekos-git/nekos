#!/usr/bin/env python3
"""
Simple Memory MCP Server
Provides basic memory storage and retrieval functionality for Claude Code
"""
import json
import sqlite3
import os
from typing import Dict, Any, List, Optional
from pathlib import Path

from mcp.server import NotificationOptions, Server
from mcp.server.models import InitializationOptions
import mcp.server.stdio
import mcp.types as types

# Initialize database
DB_PATH = Path(__file__).parent / "memory.db"

def init_db():
    """Initialize SQLite database for memory storage"""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS memories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content TEXT NOT NULL,
            tags TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    """)
    
    conn.commit()
    conn.close()

def store_memory(content: str, tags: str = "") -> int:
    """Store a memory with optional tags"""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    cursor.execute(
        "INSERT INTO memories (content, tags) VALUES (?, ?)",
        (content, tags)
    )
    
    memory_id = cursor.lastrowid
    conn.commit()
    conn.close()
    return memory_id

def recall_memory(query: str = "", tags: str = "", limit: int = 10) -> List[Dict]:
    """Recall memories based on content or tags"""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    if tags:
        cursor.execute(
            "SELECT id, content, tags, created_at FROM memories WHERE tags LIKE ? ORDER BY created_at DESC LIMIT ?",
            (f"%{tags}%", limit)
        )
    elif query:
        cursor.execute(
            "SELECT id, content, tags, created_at FROM memories WHERE content LIKE ? ORDER BY created_at DESC LIMIT ?",
            (f"%{query}%", limit)
        )
    else:
        cursor.execute(
            "SELECT id, content, tags, created_at FROM memories ORDER BY created_at DESC LIMIT ?",
            (limit,)
        )
    
    results = []
    for row in cursor.fetchall():
        results.append({
            "id": row[0],
            "content": row[1],
            "tags": row[2],
            "created_at": row[3]
        })
    
    conn.close()
    return results

# Initialize server
server = Server("memory")

@server.list_tools()
async def handle_list_tools() -> List[types.Tool]:
    """List available memory tools"""
    return [
        types.Tool(
            name="store_memory",
            description="Store a memory with content and optional tags",
            inputSchema={
                "type": "object",
                "properties": {
                    "content": {"type": "string", "description": "Content to store"},
                    "tags": {"type": "string", "description": "Comma-separated tags"}
                },
                "required": ["content"]
            }
        ),
        types.Tool(
            name="recall_memory",
            description="Recall memories based on content or tags",
            inputSchema={
                "type": "object",
                "properties": {
                    "query": {"type": "string", "description": "Search query for content"},
                    "tags": {"type": "string", "description": "Search by tags"},
                    "limit": {"type": "integer", "description": "Number of results", "default": 10}
                }
            }
        ),
        types.Tool(
            name="search_by_tag",
            description="Search memories by specific tags",
            inputSchema={
                "type": "object",
                "properties": {
                    "tags": {"type": "string", "description": "Tags to search for"},
                    "limit": {"type": "integer", "description": "Number of results", "default": 10}
                },
                "required": ["tags"]
            }
        )
    ]

@server.call_tool()
async def handle_call_tool(name: str, arguments: Dict[str, Any]) -> List[types.TextContent]:
    """Handle tool calls"""
    if name == "store_memory":
        content = arguments.get("content", "")
        tags = arguments.get("tags", "")
        
        if not content:
            return [types.TextContent(type="text", text="Error: Content is required")]
        
        memory_id = store_memory(content, tags)
        return [types.TextContent(
            type="text", 
            text=f"Memory stored with ID: {memory_id}"
        )]
    
    elif name == "recall_memory":
        query = arguments.get("query", "")
        tags = arguments.get("tags", "")
        limit = arguments.get("limit", 10)
        
        memories = recall_memory(query, tags, limit)
        
        if not memories:
            return [types.TextContent(type="text", text="No memories found")]
        
        result = "Found memories:\n\n"
        for memory in memories:
            result += f"ID: {memory['id']}\n"
            result += f"Content: {memory['content']}\n"
            result += f"Tags: {memory['tags']}\n"
            result += f"Created: {memory['created_at']}\n\n"
        
        return [types.TextContent(type="text", text=result)]
    
    elif name == "search_by_tag":
        tags = arguments.get("tags", "")
        limit = arguments.get("limit", 10)
        
        if not tags:
            return [types.TextContent(type="text", text="Error: Tags are required")]
        
        memories = recall_memory("", tags, limit)
        
        if not memories:
            return [types.TextContent(type="text", text=f"No memories found with tags: {tags}")]
        
        result = f"Found {len(memories)} memories with tags '{tags}':\n\n"
        for memory in memories:
            result += f"ID: {memory['id']}\n"
            result += f"Content: {memory['content']}\n"
            result += f"Created: {memory['created_at']}\n\n"
        
        return [types.TextContent(type="text", text=result)]
    
    else:
        return [types.TextContent(type="text", text=f"Unknown tool: {name}")]

async def main():
    """Main entry point"""
    # Initialize database
    init_db()
    
    # Run the server using stdio transport
    async with mcp.server.stdio.stdio_server() as (read_stream, write_stream):
        await server.run(
            read_stream,
            write_stream,
            InitializationOptions(
                server_name="memory",
                server_version="1.0.0",
                capabilities=server.get_capabilities(
                    notification_options=NotificationOptions(),
                    experimental_capabilities={},
                )
            )
        )

if __name__ == "__main__":
    import asyncio
    asyncio.run(main())