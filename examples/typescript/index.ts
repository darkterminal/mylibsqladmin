import { createClient } from "@libsql/client";

export const turso = createClient({
    url: 'http://foo.localhost:8080',
});

const txn = await turso.transaction("read");

await txn.execute('ATTACH "bar" AS bar');

const rs = await txn.execute("SELECT * FROM bar.bar_table");

console.log(rs.rows);
