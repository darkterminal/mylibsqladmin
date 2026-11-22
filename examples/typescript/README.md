# typescript

Minimal `@libsql/client` example. It reads both credentials from the environment; no token is hardcoded.

To install dependencies:

```bash
bun install
```

To run against a real sqld instance:

```bash
export LIBSQL_AUTH_TOKEN=<your-token>
export LIBSQL_SYNC_URL=http://foo.localhost:8080
bun run index.ts
```

`LIBSQL_SYNC_URL` must be the client-facing proxy endpoint, including the database subdomain (`foo.localhost` above). Do not use an internal container name: the browser-side flow and this client both route by `Host`.

This project was created using `bun init` in bun v1.2.2. [Bun](https://bun.sh) is a fast all-in-one JavaScript runtime.
