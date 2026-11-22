import { createClient } from "@libsql/client";

const url = process.env.LIBSQL_SYNC_URL;
const authToken = process.env.LIBSQL_AUTH_TOKEN;

if (!url || !authToken) {
    throw new Error("Set LIBSQL_SYNC_URL and LIBSQL_AUTH_TOKEN before running this example.");
}

export const turso = createClient({
    url,
    authToken,
});

const txn = await turso.transaction("read");

await txn.execute('ATTACH "bar" AS bar');

const rs = await txn.execute("SELECT * FROM bar.bar_table");

console.log(rs.rows);
