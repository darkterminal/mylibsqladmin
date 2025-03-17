try {
  const TOKEN =
    "eyJ0eXAiOiJKV1QiLCJhbGciOiJFZERTQSJ9.eyJpYXQiOjE3NDIxMjg4ODcsIm5iZiI6MTc0MjEyODg4NywiZXhwIjoxNzQ0NzIwODg3LCJqdGkiOiJkYi10ZXN0aW5nIiwiaWQiOiJkYi10ZXN0aW5nIiwiYSI6InJvIn0.LPH_IYmlB1Z_jbJF3-WXGiYUUuTMmrc2hH6b7Q5NsVFQkjsLKS0lLcH4kj-KUc_ZvZDj3EW2HOyirUnQ0pMVBw";

  const response = await fetch("http://db-testing.localhost:8080/v2/pipeline", {
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
