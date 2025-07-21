<?php

namespace App\Services;

use App\Models\UserDatabase;
use Illuminate\Support\Str;
use Lcobucci\JWT\JwtFacade;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

class DatabaseTokenGenerator
{
    protected ?string $publicKeyPem;
    protected ?string $privateKey;
    protected ?string $pubKeyBase64;
    protected int $tokenExpiration;
    protected InMemory $key;
    protected $temp_dir;

    public function __construct()
    {
        $this->temp_dir = sys_get_temp_dir();
        $this->generatePublicAndPrivateKey();
    }

    public function __destruct()
    {
        unlink("{$this->temp_dir}/jwt_private.pem");
        unlink("{$this->temp_dir}/jwt_public.pem");
    }

    public function setTokenExpiration(int $expiration): void
    {
        $this->tokenExpiration = $expiration;
    }

    protected function generatePublicAndPrivateKey()
    {
        shell_exec("openssl genpkey -algorithm ed25519 -out {$this->temp_dir}/jwt_private.pem");
        shell_exec("openssl pkey -in {$this->temp_dir}/jwt_private.pem -outform DER | tail -c 32 > {$this->temp_dir}/jwt_private.binary");
        shell_exec("openssl pkey -in {$this->temp_dir}/jwt_private.pem -pubout -out {$this->temp_dir}/jwt_public.pem");

        $this->privateKey = sodium_crypto_sign_secretkey(
            sodium_crypto_sign_seed_keypair(
                file_get_contents("{$this->temp_dir}/jwt_private.binary")
            )
        );
        unlink("{$this->temp_dir}/jwt_private.binary");

        $this->publicKeyPem = trim(file_get_contents("{$this->temp_dir}/jwt_public.pem"));
        $this->pubKeyBase64 = str_replace(["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\n", "\r"], '', $this->publicKeyPem);

        $this->key = InMemory::base64Encoded(
            base64_encode($this->privateKey)
        );
    }

    public function generateToken(
        int|string $databaseId,
        int|null $userOrGroupId = null,
        int $tokenExpiration = 0,
        bool $isGroup = false
    ): array|false {

        $tokenExpiration = $tokenExpiration === 0 ? "100 years" : "{$tokenExpiration} days";

        if (is_string($databaseId) && !is_numeric($databaseId)) {
            $fullAccessToken = (new JwtFacade())->issue(
                new Eddsa(),
                $this->key,
                static fn(
                Builder $builder,
                \DateTimeImmutable $issuedAt
            ): Builder => $builder
                    ->identifiedBy($databaseId)
                    ->withClaim('id', $databaseId)
                    ->withClaim('uid', $isGroup ? 'none' : $userOrGroupId)
                    ->withClaim('gid', $isGroup ? $userOrGroupId : 'none')
                    ->withHeader('is_group', $isGroup ? 'yes' : 'no')
                    ->expiresAt(
                        $issuedAt
                            ->setTimezone(new \DateTimeZone(env('APP_TIMEZONE', 'UTC')))
                            ->modify("+{$tokenExpiration}")
                    )
            );

            $readOnlyToken = (new JwtFacade())->issue(
                new Eddsa(),
                $this->key,
                static fn(
                Builder $builder,
                \DateTimeImmutable $issuedAt
            ): Builder => $builder
                    ->identifiedBy($databaseId)
                    ->withClaim('id', $databaseId)
                    ->withClaim('a', 'ro')
                    ->withClaim('uid', $isGroup ? 'none' : $userOrGroupId)
                    ->withClaim('gid', $isGroup ? $userOrGroupId : 'none')
                    ->withHeader('is_group', $isGroup ? 'yes' : 'no')
                    ->expiresAt(
                        $issuedAt
                            ->setTimezone(new \DateTimeZone(env('APP_TIMEZONE', 'UTC')))
                            ->modify("+{$tokenExpiration}")
                    )
            );
        } else {
            $userDatabase = UserDatabase::select(['id', 'database_name', 'user_id'])->find($databaseId);

            $fullAccessToken = (new JwtFacade())->issue(
                new Eddsa(),
                $this->key,
                static fn(
                Builder $builder,
                \DateTimeImmutable $issuedAt
            ): Builder => $builder
                    ->identifiedBy($userDatabase->database_name)
                    ->withClaim('id', $userDatabase->database_name)
                    ->withClaim('uid', $isGroup ? 'none' : $userOrGroupId)
                    ->withClaim('gid', $isGroup ? $userOrGroupId : 'none')
                    ->withHeader('is_group', $isGroup ? 'yes' : 'no')
                    ->expiresAt(
                        $issuedAt
                            ->setTimezone(new \DateTimeZone(env('APP_TIMEZONE', 'UTC')))
                            ->modify("+{$tokenExpiration}")
                    )
            );

            $readOnlyToken = (new JwtFacade())->issue(
                new Eddsa(),
                $this->key,
                static fn(
                Builder $builder,
                \DateTimeImmutable $issuedAt
            ): Builder => $builder
                    ->identifiedBy($userDatabase->database_name)
                    ->withClaim('id', $userDatabase->database_name)
                    ->withClaim('a', 'ro')
                    ->withClaim('uid', $isGroup ? 'none' : $userOrGroupId)
                    ->withClaim('gid', $isGroup ? $userOrGroupId : 'none')
                    ->withHeader('is_group', $isGroup ? 'yes' : 'no')
                    ->expiresAt(
                        $issuedAt
                            ->setTimezone(new \DateTimeZone(env('APP_TIMEZONE', 'UTC')))
                            ->modify("+{$tokenExpiration}")
                    )
            );
        }

        return [
            'full_access_token' => $fullAccessToken->toString(),
            'read_only_token' => $readOnlyToken->toString(),
            'expiration_day' => Str::of($tokenExpiration)->contains('days') ? Str::of($tokenExpiration)->replace(' days', '') : $tokenExpiration,
        ];
    }
}
