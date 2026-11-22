-- Unit tests for the proxy's Lua modules. Run with nginx/tests/run.sh.
-- Luajit has no ngx object, so the gate's ngx surface is mocked below.

package.path = "/etc/nginx/lua/?.lua;" .. package.path
package.cpath = "/usr/local/openresty/lualib/?.so;" .. package.cpath

local subdomain = require "subdomain"
local cjson = require "cjson"
local safe_cjson = {}
function safe_cjson.decode(value)
    local ok, decoded = pcall(cjson.decode, value)
    if ok then return decoded end
    return nil
end
package.loaded["cjson.safe"] = safe_cjson

local passed, failed = 0, 0

local function expect(name, got, want)
    if got == want then
        print("PASS " .. name)
        passed = passed + 1
    else
        print(string.format("FAIL %s: got %q, want %q", name, tostring(got), tostring(want)))
        failed = failed + 1
    end
end

-- subdomain extraction -------------------------------------------------------
local sub_cases = {
    { "localhost", "cli-smoke-db.localhost", "cli-smoke-db" },
    { "localhost", "CLI-SMOKE-DB.localhost:8080", "cli-smoke-db" },
    { "example.com", "db.example.com", "db" },
    { "example.co.uk", "db.example.co.uk", "db" },
    { "example.com", "a.b.example.com", "" },
    { "example.com", "example.com", "" },
    { "example.com", "db.other.com", "" },
    { "localhost", "db.127.0.0.1", "" },
    { "example.com", "", "" },
}
for _, case in ipairs(sub_cases) do
    expect("subdomain " .. case[1] .. " / " .. case[2],
        subdomain.from(case[1], case[2]), case[3])
end

-- authorize gate ------------------------------------------------------------
local keywords = { "INSERT", "UPDATE", "DELETE", "CREATE", "ALTER", "DROP",
    "ATTACH", "DETACH", "REPLACE", "REINDEX", "VACUUM", "PRAGMA", "GRANT", "REVOKE" }

_G.__body = nil
ngx = {
    var = {},
    HTTP_FORBIDDEN = 403,
    exit = function(code) error("GATE_EXIT:" .. code) end,
    req = {
        read_body = function() end,
        get_body_data = function() return _G.__body end,
        get_body_file = function() return nil end,
    },
    re = {
        find = function(sql)
            local upper = string.upper(sql)
            for _, word in ipairs(keywords) do
                if string.find(upper, word, 1, true) then return true end
            end
            return nil
        end,
    },
}

-- canonical Host source ----------------------------------------------------
-- The routing subdomain and the validator subdomain must come from the same
-- value. An absolute-form request line makes $host and $http_host disagree,
-- which previously let a token for one database authorize another.
local function host_case(i, header, request_line, want)
    ngx.var.http_host = header
    ngx.var.host = request_line
    expect("from_request #" .. i .. " (" .. header .. "/" .. request_line .. ")",
        subdomain.from_request("example.com"), want)
end

host_case(1, "a.example.com", "b.example.com", "")
host_case(2, "a.example.com", "a.example.com", "a")
host_case(3, "", "a.example.com", "a")
host_case(4, "a.example.com", "", "a")
host_case(5, "db.example.com:8080", "DB.EXAMPLE.COM", "db")
host_case(6, "a.example.com", "a.other.com", "")

local function pipeline(sql, kind)
    return cjson.encode({ requests = { { type = kind or "execute",
        stmt = { sql = sql, want_rows = false } } } })
end

local function gate(name, level, body, want_allowed)
    ngx.var.access_level = level
    _G.__body = body
    local allowed, err = true, nil
    local ok, result = pcall(dofile, "/etc/nginx/lua/authorize.lua")
    if not ok then
        if string.find(tostring(result), "GATE_EXIT:403", 1, true) then
            allowed = false
        else
            err = tostring(result)
        end
    end
    if err ~= nil then
        print("ERROR " .. name .. ": " .. err)
        failed = failed + 1
        return
    end
    expect("gate " .. name, allowed, want_allowed)
end

gate("missing level", nil, pipeline("SELECT 1"), false)
gate("level none", "none", pipeline("SELECT 1"), false)
gate("full-access SELECT", "full-access", pipeline("SELECT 1"), true)
gate("full-access INSERT", "full-access", pipeline("INSERT INTO t VALUES (1)"), true)
gate("read-only SELECT", "read-only", pipeline("SELECT 1"), true)
gate("read-only INSERT", "read-only", pipeline("INSERT INTO t VALUES (1)"), false)
gate("read-only lowercase update", "read-only", pipeline("update t set x=1"), false)
gate("read-only non-execute", "read-only", pipeline("ignored", "close"), true)
gate("read-only malformed", "read-only", "not-json", false)
gate("read-only empty body", "read-only", "", false)

-- nginx template drift guard ----------------------------------------------
local function count_occurrences(haystack, needle)
    local count, pos = 0, 1
    while true do
        local start = string.find(haystack, needle, pos, true)
        if not start then break end
        count = count + 1
        pos = start + #needle
    end
    return count
end

for _, name in ipairs({ "proxy-nginx.conf", "proxy-nginx-dev.conf" }) do
    local conf_file = io.open("/tmp/" .. name, "r")
    if conf_file then
        local conf = conf_file:read("*a")
        conf_file:close()
        expect("no split Host sources in " .. name,
            count_occurrences(conf, 'from("__LIBSQL_PARENT_DOMAIN__", ngx.var'), 0)
        expect("all four derivations use from_request in " .. name,
            count_occurrences(conf, 'from_request("__LIBSQL_PARENT_DOMAIN__")'), 4)
        expect("both HRANA locations forward original Host in " .. name,
            count_occurrences(conf, "proxy_set_header Host $host;"), 2)
    else
        print("SKIP drift guard for " .. name .. " (template not mounted)")
    end
end

print(string.format("TOTAL passed=%d failed=%d", passed, failed))
os.exit(failed == 0 and 0 or 1)
