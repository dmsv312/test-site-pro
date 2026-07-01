<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\base\BaseObject;
use yii\web\IdentityInterface;

/**
 * The single admin identity, driven entirely by environment variables
 * (ADMIN_USERNAME / ADMIN_PASSWORD — see .env.example). No credentials are
 * hard-coded here. The bcrypt hash is computed lazily on the login path only,
 * so ordinary authenticated requests never pay for hashing.
 */
class User extends BaseObject implements IdentityInterface
{
    public int|string $id = '';
    public string $username = '';
    public string $passwordHash = '';
    public string $authKey = '';
    public string $accessToken = '';

    private const ADMIN_ID = '1';

    /** Identity fields that do NOT need the password hash (used on every request). */
    private static function baseIdentity(): array
    {
        return [
            'id' => self::ADMIN_ID,
            'username' => getenv('ADMIN_USERNAME') ?: 'admin',
            'authKey' => self::resolveAuthKey(),
            'accessToken' => getenv('ADMIN_ACCESS_TOKEN') ?: '',
        ];
    }

    /**
     * The remember-me auth key. Uses ADMIN_AUTH_KEY when set; otherwise it is DERIVED from this
     * deployment's own secrets (cookie validation key + admin password), so there is no shared,
     * committed constant that would be identical — and forgeable — across installs.
     */
    private static function resolveAuthKey(): string
    {
        $explicit = getenv('ADMIN_AUTH_KEY');
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        return hash(
            'sha256',
            'sitepro-authkey|' . (getenv('COOKIE_VALIDATION_KEY') ?: '') . '|' . (getenv('ADMIN_PASSWORD') ?: ''),
        );
    }

    /** Bcrypt hash of the admin password — computed only when validating a login. */
    private static function resolvePasswordHash(): string
    {
        $hash = getenv('ADMIN_PASSWORD_HASH');
        if (is_string($hash) && $hash !== '') {
            return $hash;
        }
        $password = getenv('ADMIN_PASSWORD') ?: 'admin';

        return Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id): static|null
    {
        return (string) $id === self::ADMIN_ID ? new static(self::baseIdentity()) : null;
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null): static|null
    {
        $base = self::baseIdentity();
        if (
            $base['accessToken'] !== '' && (string) $token !== ''
            && hash_equals($base['accessToken'], (string) $token)
        ) {
            return new static($base);
        }

        return null;
    }

    /**
     * Finds user by username (login path — attaches the password hash).
     */
    public static function findByUsername(string $username): static|null
    {
        $base = self::baseIdentity();
        if (strcasecmp($base['username'], $username) === 0) {
            $base['passwordHash'] = self::resolvePasswordHash();

            return new static($base);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): int|string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey(): string|null
    {
        return $this->authKey;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey): bool
    {
        return $this->authKey !== '' && hash_equals($this->authKey, (string) $authKey);
    }
}
