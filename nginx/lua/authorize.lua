-- Shared access gate for HTTP HRANA endpoints.
--
-- auth_request only rejects non-2xx validator responses. The webapp returns
-- HTTP 200 with X-Access-Level: none for a token that is absent or unknown,
-- so the level must be checked explicitly here. This is the gate that was
-- missing in /internal-auth-pipeline (F-54).
--
-- The same file is used by `location /` and `location = /internal-auth-pipeline`
-- so the two paths cannot drift apart again.

ngx.req.read_body()

local level = ngx.var.access_level
if level == nil or level == "" or level == "none" then
    return ngx.exit(ngx.HTTP_FORBIDDEN)
end

if level ~= "read-only" then
    return
end

-- client_body_in_single_buffer is on, so get_body_data() normally has all of
-- it. Fall back to the temp file for a body larger than the memory buffer;
-- an unreadable body in read-only mode must never mean "allow".
local body = ngx.req.get_body_data()
if body == nil then
    local file = ngx.req.get_body_file()
    if file ~= nil then
        local handle = io.open(file, "rb")
        if handle ~= nil then
            body = handle:read("*a")
            handle:close()
        end
    end
end

if body == nil or body == "" then
    return ngx.exit(ngx.HTTP_FORBIDDEN)
end

local cjson = require "cjson.safe"
local data = cjson.decode(body)
if type(data) ~= "table" or type(data.requests) ~= "table" then
    return ngx.exit(ngx.HTTP_FORBIDDEN)
end

local write_pattern = "(?i)\\b(INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|ATTACH|DETACH|REPLACE|REINDEX|VACUUM|PRAGMA|GRANT|REVOKE)\\b"

for _, request in ipairs(data.requests) do
    if type(request) ~= "table" then
        return ngx.exit(ngx.HTTP_FORBIDDEN)
    end

    -- Non-execute pipeline requests (e.g. "close") carry no SQL; matching the
    -- previous `location /` behaviour, they are left alone.
    if request.type == "execute" then
        local statement = request.stmt
        if type(statement) ~= "table" or type(statement.sql) ~= "string" then
            return ngx.exit(ngx.HTTP_FORBIDDEN)
        end
        if ngx.re.find(statement.sql, write_pattern, "joi") then
            return ngx.exit(ngx.HTTP_FORBIDDEN)
        end
    end
end
