<?php

namespace App\Http\Controllers;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\UnencryptedToken;
use App\Models\GroupDatabaseToken;
use App\Models\UserDatabaseToken;
use Lcobucci\JWT\Token\Parser;
use App\Models\GroupDatabase;
use App\Services\SqldService;
use App\Models\UserDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use DateTimeZone;

class SubdomainValidationController extends Controller
{
    private string $subdomain;

    public function __construct()
    {
        $this->subdomain = '';
    }

    public function validateSubdomain(Request $request)
    {
        $subdomain = $request->header('X-Subdomain');

        // Missing or malformed subdomain is a client error, not a 500.
        if (!is_string($subdomain) || $subdomain === '') {
            return response('', 400)->header('X-Access-Level', 'none');
        }

        $this->subdomain = $subdomain;
        $token = $this->extractToken($request);

        if (empty($token) || $token === 'Bearer') {
            return $this->handleEmptyToken($this->subdomain);
        }

        try {
            $jwtToken = $this->parseJwtToken($token);
            $this->validateTokenExpiration($jwtToken);

            $accessLevel = $this->determineAccessLevel($jwtToken, $token, $this->subdomain);

            return $this->handleFinalResponse($this->subdomain, $accessLevel);
        } catch (\Exception $e) {
            // Not a JWT we can read — the user-token family is a random
            // string matched by SHA-256 hash, not a JWT, so it lands here.
            $accessLevel = $this->handleUserTokenAccess($token, $this->subdomain);

            return $this->handleFinalResponse($this->subdomain, $accessLevel);
        }
    }

    private function extractToken(Request $request): string
    {
        $authToken = $request->header('X-Auth-Token', '');
        return Str::startsWith($authToken, 'Bearer ')
            ? Str::after($authToken, 'Bearer ')
            : $authToken;
    }

    private function handleEmptyToken(string $subdomain)
    {
        // Default-deny: a database with zero published tokens is not reachable
        // for unauthenticated use. Accessors must hold an issued token.
        return $this->createResponse('none');
    }

    private function parseJwtToken(string $token): UnencryptedToken
    {
        $parser = new Parser(new JoseEncoder());
        return $parser->parse($token);
    }

    private function validateTokenExpiration(UnencryptedToken $token): void
    {
        $timezone = new DateTimeZone(config('app.timezone', 'UTC'));
        if ($token->isExpired(now($timezone))) {
            throw new \RuntimeException('Token expired');
        }
    }

    private function determineAccessLevel(UnencryptedToken $token, string $rawToken, string $subdomain): string
    {
        $headers = $token->headers();
        $claims = $token->claims();

        if ($this->isGroupToken($headers, $claims)) {
            return $this->handleGroupTokenAccess($claims, $rawToken, $subdomain);
        }

        return $this->handleUserTokenAccess($rawToken, $subdomain);
    }

    private function isGroupToken($headers, $claims): bool
    {
        $this->uid = $claims->get('uid');
        return $headers->get('is_group') === 'yes' && $claims->get('uid') === 'none';
    }

    private function handleGroupTokenAccess($claims, string $token, string $subdomain): string
    {
        $group = GroupDatabase::getGroupDatabasesIfContains($claims->get('gid'), $subdomain);

        if (!$group || !$groupToken = $group->tokens->first()) {
            return 'none';
        }

        return $groupToken->full_access_token === $token ? 'full-access' : 'read-only';
    }

    private function handleUserTokenAccess(string $token, string $subdomain): string
    {
        // Stored tokens are SHA-256 hashes of the bearer token — the
        // presented token must be hashed before any comparison.
        $hashed = hash('sha256', $token);

        $databaseToken = UserDatabaseToken::with('database')
            ->whereHas('database', fn($q) => $q->where('database_name', $subdomain))
            ->where(fn($q) => $q->where('full_access_token', $hashed)
                ->orWhere('read_only_token', $hashed))
            ->first();

        if (!$databaseToken) {
            return 'none';
        }

        return hash_equals((string) $databaseToken->full_access_token, $hashed)
            ? 'full-access'
            : 'read-only';
    }

    private function handleFinalResponse(string $subdomain, string $accessLevel)
    {
        $subdomainValid = $this->validateSubdomainWithBridge($subdomain);
        return $subdomainValid
            ? $this->createResponse($accessLevel)
            : response()->noContent(403)->header('X-Access-Level', $accessLevel);
    }

    private function validateSubdomainWithBridge(string $subdomain): bool
    {
        try {
            $allDatabases = config('mylibsqladmin.local_instance')
                ? SqldService::getLocalDatabases()
                : SqldService::getRemoteDatabases();
            // Rows are DB records (database_name) locally and remote API
            // objects (name) remotely — support either.
            $names = Arr::pluck($allDatabases, 'database_name', null);
            $names = array_filter(array_merge($names, Arr::pluck($allDatabases, 'name')));
            return in_array($subdomain, $names, true);
        } catch (\Exception $e) {
            logger()->error('Bridge service error: ' . $e->getMessage());
            return false;
        }
    }

    private function createResponse(string $accessLevel)
    {
        return response()->noContent(200)->header('X-Access-Level', $accessLevel);
    }
}
