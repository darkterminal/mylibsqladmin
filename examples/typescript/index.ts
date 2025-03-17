import { createClient } from "@libsql/client";

export const turso = createClient({
    url: "file:local.db",
    syncUrl: process.env.TURSO_DATABASE_URL,
    authToken: process.env.TURSO_AUTH_TOKEN,
    syncInterval: 60000,
});

const name = `Iku_${Math.random()}`;

await turso.batch(
    [
        "CREATE TABLE IF NOT EXISTS users (name TEXT)",
        {
            sql: "INSERT INTO users(name) VALUES (?)",
            args: [name],
        },
    ],
    "write",
);

const data = await turso.execute("SELECT * FROM users");
console.log(data.toJSON());

turso.sync();
