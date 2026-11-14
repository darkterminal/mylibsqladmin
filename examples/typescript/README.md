# typescript

To install dependencies:

```bash
bun install
```

To run:

```bash
bun run index.ts
```

This project was created using `bun init` in bun v1.2.2. [Bun](https://bun.sh) is a fast all-in-one JavaScript runtime.

To run against a real sqld instance:

```bash
export LIBSQL_AUTH_TOKEN=<your-token>
export LIBSQL_SYNC_URL=http://your-sqld:8080
npm run start
```
