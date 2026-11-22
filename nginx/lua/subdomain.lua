-- Derive the single database label from the incoming Host header.
--
-- `parent` is the client-facing parent domain (localhost by default, e.g.
-- example.com or example.co.uk in production). It is baked into the nginx
-- template at container start; nginx drops undeclared process environment
-- variables, so os.getenv() is deliberately not used here.
--
-- Returns "" for anything that is not exactly <database>.<parent>. The server
-- rejects that with 421 before auth_request or any upstream call, so a
-- wildcard DNS record cannot smuggle an arbitrary Host into the validator.

local _M = {}

local function normalize(value)
    if type(value) ~= "string" then
        return ""
    end

    value = value:lower()
    value = value:gsub("^%s+", ""):gsub("%s+", "")
    value = value:gsub("^%.+", ""):gsub("%.+$", "")
    value = value:gsub(":%d+$", "")

    return value
end

function _M.from(parent, host)
    parent = normalize(parent)
    host = normalize(host)

    if parent == "" or host == "" then
        return ""
    end
    if parent:find("[^a-z0-9%.%-]") or host:find("[^a-z0-9%.%-]") then
        return ""
    end

    local suffix = "." .. parent
    if #host <= #suffix or host:sub(-#suffix) ~= suffix then
        return ""
    end

    local sub = host:sub(1, #host - #suffix)
    if sub == "" or sub:find("%.") or not sub:match("^[a-z0-9_%-]+$") then
        return ""
    end

    return sub
end

return _M
