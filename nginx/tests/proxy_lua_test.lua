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

print(string.format("TOTAL passed=%d failed=%d", passed, failed))
os.exit(failed == 0 and 0 or 1)
