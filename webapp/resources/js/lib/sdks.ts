export interface SDKOption {
    id: string
    language: string
    official: boolean
    /** Official documentation link (original docs, always shown alongside the snippet) */
    docs: string
    /** Install command / instructions (short, copyable) */
    install: string
    installLabel?: string
    /** Minimal working example, plaintext, no tooling required */
    snippet: string
    /** Honest one-line note shown under the snippet, when needed */
    note?: string

    /** @deprecated kept for backwards compatibility; use `docs` */
    documentation: string
}

const SELF_HOSTED_NOTE =
    'For this self-hosted stack: URL = http://<database>.localhost:8080 (dev) or https://<database>.<your-domain> (prod). Token = any full-access token from the Tokens page.'

export const sdkOptions: SDKOption[] = [
    {
        id: 'python',
        language: 'Python',
        official: true,
        docs: 'https://docs.turso.tech/sdk/python',
        install: 'pip install libsql',
        snippet: `import os
import libsql

conn = libsql.connect(
    database=os.environ["TURSO_DATABASE_URL"],
    auth_token=os.environ["TURSO_AUTH_TOKEN"],
)

conn.execute("CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY, name TEXT)")
conn.execute("INSERT INTO items (name) VALUES (?)", ("hello",))
conn.commit()

rows = conn.execute("SELECT * FROM items").fetchall()
print(rows)`,
        note: SELF_HOSTED_NOTE,
        documentation: 'https://docs.turso.tech/sdk/python',
    },
    {
        id: 'go',
        language: 'Go',
        official: true,
        docs: 'https://docs.turso.tech/sdk/go',
        install: 'go get github.com/tursodatabase/libsql-client-go/libsql',
        snippet: `package main

import (
\t"database/sql"
\t"fmt"
\t"os"

\t_ "github.com/tursodatabase/libsql-client-go/libsql"
)

func main() {
\turl := os.Getenv("TURSO_DATABASE_URL") + "?authToken=" + os.Getenv("TURSO_AUTH_TOKEN")

\tdb, _ := sql.Open("libsql", url)
\tdefer db.Close()

\tdb.Exec("CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY, name TEXT)")
\tdb.Exec("INSERT INTO items (name) VALUES (?)", "hello")

\trows, _ := db.Query("SELECT * FROM items")
\tdefer rows.Close()
\tfor rows.Next() {
\t\tvar id int
\t\tvar name string
\t\trows.Scan(&id, &name)
\t\tfmt.Printf("%d %s\\n", id, name)
\t}
}`,
        note: SELF_HOSTED_NOTE,
        documentation: 'https://docs.turso.tech/sdk/go',
    },
    {
        id: 'rust',
        language: 'Rust',
        official: true,
        docs: 'https://docs.turso.tech/sdk/rust',
        install: 'cargo add libsql --features remote tokio --features tokio/full',
        snippet: `use libsql::Builder;

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let url = std::env::var("TURSO_DATABASE_URL")?;
    let token = std::env::var("TURSO_AUTH_TOKEN")?;

    let db = Builder::new_remote(url, token).build().await?;
    let conn = db.connect()?;

    conn.execute(
        "CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY, name TEXT)",
        (),
    ).await?;
    conn.execute("INSERT INTO items (name) VALUES (?)", ("hello",)).await?;

    let mut rows = conn.query("SELECT * FROM items", ()).await?;
    while let Some(row) = rows.next().await? {
        let id: i64 = row.get(0)?;
        let name: String = row.get(1)?;
        println!("{id} {name}");
    }

    Ok(())
}`,
        note: SELF_HOSTED_NOTE,
        documentation: 'https://docs.turso.tech/sdk/rust',
    },
    {
        id: 'dart',
        language: 'Dart',
        official: true,
        docs: 'https://docs.turso.tech/sdk/flutter/quickstart.md',
        install: 'flutter pub add libsql_dart',
        snippet: `import 'package:libsql_dart/libsql_dart.dart';

final client = LibsqlClient('<TURSO_OR_LIBSQL_URL>')
  ..authToken = '<TOKEN>';

await client.connect();

await client.execute(
  'CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY, name TEXT)');
await client.query("INSERT INTO items (name) VALUES ('hello')");

print(await client.query('SELECT * FROM items'));`,
        note: 'Docs route this through the Flutter SDK; see docs for the current support status. For this self-hosted stack, TURSO_OR_LIBSQL_URL = http://<database>.localhost:8080.',
        documentation: 'https://docs.turso.tech/sdk/flutter/quickstart.md',
    },
    {
        id: 'java',
        language: 'Java',
        official: true,
        docs: 'https://docs.turso.tech/connect/java.md',
        install:
            'git clone https://github.com/tursodatabase/turso.git\n' +
            'cd turso/bindings/java\n' +
            'make linux_x86   # or macos_arm64 / windows\n' +
            'make publish_local',
        installLabel: 'Not yet on Maven Central — build & publish to maven local first',
        snippet: `// build.gradle:
dependencies {
    implementation("tech.turso:turso:0.0.1-SNAPSHOT")
}

// Usage: see https://docs.turso.tech/connect/java.md
// (driver API is still evolving; Gradle dependency above is the
// documented way to consume it today)`,
        note: 'The Java driver is not published to Maven Central yet — the docs only show building from source. Check https://docs.turso.tech/connect/java.md for updates.',
        documentation: 'https://docs.turso.tech/connect/java.md',
    },
    {
        id: 'php-extension',
        language: 'PHP Extension',
        official: false,
        docs: 'https://github.com/tursodatabase/turso-client-php',
        install: 'See "Quick Start" in the docs (Composer + turso-php-installer)',
        snippet: `<?php

$libsql = new LibSQL("file:example.db");

$libsql->execute(
    "CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY, name TEXT)");
$libsql->execute(
    "INSERT INTO items (name) VALUES (?)", ["hello"]);

$result = $libsql->query("SELECT * FROM items");
$rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

foreach ($rows as $row) {
    echo $row["id"] . " - " . $row["name"] . PHP_EOL;
}`,
        note: 'The libSQL PHP extension exposes a native class; see the docs for building/installing the extension for your platform.',
        documentation: 'https://github.com/tursodatabase/turso-client-php',
    },
    {
        id: 'laravel',
        language: 'Turso Driver Laravel',
        official: false,
        docs: 'https://github.com/tursodatabase/turso-driver-laravel',
        install: 'composer require tursodatabase/turso-driver-laravel\nphp artisan turso-php:install',
        snippet: `// .env
DB_CONNECTION=libsql
DB_DATABASE=database.sqlite
DB_SYNC_URL=https://<your-database>.turso.io
DB_AUTH_TOKEN=<your-database-auth-token>

// config/database.php -> connections:
'libsql' => [
    'driver' => 'libsql',
    'database' => env('DB_DATABASE', database_path('database.sqlite')),
    'prefix' => '',
    'url' => env('DB_SYNC_URL', ''),
    'authToken' => env('DB_AUTH_TOKEN', ''),
],

// usage
DB::table('items')->insert(['name' => 'hello']);
DB::table('items')->get();`,
        note: SELF_HOSTED_NOTE,
        documentation: 'https://github.com/tursodatabase/turso-driver-laravel',
    },
]
