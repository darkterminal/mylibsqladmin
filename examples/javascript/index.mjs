const TOKEN = process.env.LIBSQL_AUTH_TOKEN;
if (!TOKEN) {
    console.error('Set LIBSQL_AUTH_TOKEN in the environment first.');
    process.exit(1);
}

const SYNC_URL = process.env.LIBSQL_SYNC_URL ?? 'http://db-testing.localhost:8080';

try {
  const response = await fetch(`${SYNC_URL}/v2/pipeline`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${TOKEN}`,
    },
    body: JSON.stringify({
      requests: [
        {
          type: "execute",
          stmt: {
            sql: "SELECT * FROM users",
          },
        },
        {
          type: "close",
        },
      ],
    }),
  });

  if (response.status > 200) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }

  const data = await response.json();
  console.log(data);
} catch (error) {
  console.error(error);
}
