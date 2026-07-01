<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\User;

/**
 * The admin identity is env-driven (ADMIN_USERNAME / ADMIN_PASSWORD / ADMIN_AUTH_KEY) — there
 * is one admin, id "1", and no user table. Tests set the env explicitly so they don't depend
 * on the ambient .env.
 */
final class UserTest extends \Codeception\Test\Unit
{
    protected function _before(): void
    {
        putenv('ADMIN_USERNAME=admin');
        putenv('ADMIN_AUTH_KEY=unit-test-authkey');
        putenv('ADMIN_ACCESS_TOKEN'); // ensure token auth is disabled unless a test sets it
    }

    protected function _after(): void
    {
        putenv('ADMIN_USERNAME');
        putenv('ADMIN_AUTH_KEY');
        putenv('ADMIN_ACCESS_TOKEN');
    }

    public function testFindIdentityById(): void
    {
        $user = User::findIdentity('1');

        verify($user)->notEmpty();
        verify($user->username)->equals('admin');
        verify(User::findIdentity('999'))->empty();
    }

    public function testFindByUsername(): void
    {
        $user = User::findByUsername('admin');

        verify($user)->notEmpty();
        verify($user->username)->equals('admin');
        verify(User::findByUsername('not-admin'))->empty();
    }

    public function testValidateAuthKey(): void
    {
        $user = User::findByUsername('admin');

        verify($user->validateAuthKey('unit-test-authkey'))->true();
        verify($user->validateAuthKey('wrong-key'))->false();
    }

    public function testAccessTokenDisabledWhenUnset(): void
    {
        // With no ADMIN_ACCESS_TOKEN configured, token auth matches nobody — not even '' .
        verify(User::findIdentityByAccessToken('anything'))->empty();
        verify(User::findIdentityByAccessToken(''))->empty();
    }

    public function testAccessTokenMatchesWhenSet(): void
    {
        putenv('ADMIN_ACCESS_TOKEN=secret-token');

        $user = User::findIdentityByAccessToken('secret-token');
        verify($user)->notEmpty();
        verify($user->username)->equals('admin');
        verify(User::findIdentityByAccessToken('other-token'))->empty();
    }
}
