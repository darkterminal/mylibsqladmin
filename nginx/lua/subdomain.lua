-- Derive the single database label from the incoming Host header.
--
-- `parent` is the client-facing parent domain (localhost by default, e.g.
-- example.com or example.co.uk in production). It is baked into the nginx
-- template at container start; nginx drops undeclared process environment
-- variables, so os.getenv() is deliberately not used here.
--
-- Returns "" for anything that is not exactly <database>.<parent>. An empty
-- subdomain is rejected by the shared auth gate / validator (the validator
-- answers 400, which error_page maps to an empty 204), so a wildcard DNS
-- record cannot smuggle an arbitrary Host into an upstream query.

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

-- Derive the routing key from exactly one client-supplied source.
--
-- nginx's $host prefers the authority of an absolute-form request line while
-- $http_host is the raw Host header. If they disagree, using one for routing
-- and the other for token validation lets a token for database A authorize a
-- request that is proxied to database B. Resolve that ambiguity here instead:
-- prefer Host, fall back to $host only when Host is absent, and reject a
-- mismatch outright. Every caller must use this function so routing and
-- validator cannot drift apart again.
function _M.from_request(parent)
    local from_header = normalize(ngx.var.http_host)
    local from_request_line = normalize(ngx.var.host)

    if from_header ~= "" and from_request_line ~= ""
        and from_header ~= from_request_line then
        return ""
    end

    return _M.from(parent, from_header ~= "" and from_header or from_request_line)
end

return _M
