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
    private string|int $uid;

    public function __construct()
    {
        $this->subdomain = '';
        $this->uid = 0;
    }

    public function validateSubdomain(Request $request)
    {
        $this->subdomain = $request->header('X-Subdomain');
        $token = $this->extractToken($request);

        if (empty($token) || $token === 'Bearer') {
            return $this->handleEmptyToken($this->subdomain);
        }

        try {
            $jwtToken = $this->parseJwtToken($token);
            $this->validateTokenExpiration($jwtToken);
        } catch (\Exception $e) {
            logger()->error('Token validation failed: ' . $e->getMessage());
            return $this->createResponse('none');
        }

        $accessLevel = $this->determineAccessLevel($jwtToken, $token, $this->subdomain);
        return $this->handleFinalResponse($this->subdomain, $accessLevel);
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
        if ($this->subdomainHasAssociatedTokens($subdomain)) {
            return $this->createResponse('none');
        }

        return $this->createResponse('full-access');
    }

    private function subdomainHasAssociatedTokens(string $subdomain): bool
    {
        if (!UserDatabase::where('database_name', $subdomain)->exists()) {
            return true;
        }

        return GroupDatabaseToken::whereHas('group', fn($q) => $q->whereHas('members', fn($q) => $q->where('database_name', $subdomain)))->exists()
            || UserDatabaseToken::whereHas('database', fn($q) => $q->where('database_name', $subdomain))->exists();
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
        $databaseToken = UserDatabaseToken::with('database')
            ->whereHas('database', fn($q) => $q->where('database_name', $subdomain))
            ->where('user_id', $this->uid)
            ->latest()->first();

        if (!$databaseToken) {
            return 'none';
        }

        if (env('APP_DEBUG') === true) {
            logger()->debug('Access level', [
                'subdomain' => $subdomain,
                'full_access_token' => $databaseToken->full_access_token,
                'read_only_token' => $databaseToken->read_only_token,
                'access_level' => $databaseToken->full_access_token === $token ? 'full-access' : 'read-only'
            ]);
        }

        return $databaseToken->full_access_token === $token ? 'full-access' : 'read-only';
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
            $allDatabases = SqldService::getDatabases(config('mylibsqladmin.local_instance'));
            $names = Arr::pluck($allDatabases, 'name');
            return in_array($subdomain, $names);
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
