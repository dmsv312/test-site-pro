<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\LoginForm;
use Yii;
use yii\base\Security;

/**
 * Login is validated against the env-driven admin (ADMIN_USERNAME / ADMIN_PASSWORD).
 */
final class LoginFormTest extends \Codeception\Test\Unit
{
    private ?LoginForm $_model = null;

    protected function _before(): void
    {
        putenv('ADMIN_USERNAME=admin');
        putenv('ADMIN_PASSWORD=admin');
    }

    protected function _after(): void
    {
        Yii::$app->user->logout();
        putenv('ADMIN_USERNAME');
        putenv('ADMIN_PASSWORD');
    }

    public function testLoginNoUser(): void
    {
        $this->_model = new LoginForm(
            new Security(),
            [
                'username' => 'not_existing_username',
                'password' => 'not_existing_password',
            ],
        );

        verify($this->_model->login())->false();
        verify(Yii::$app->user->isGuest)->true();
    }

    public function testLoginWrongPassword(): void
    {
        $this->_model = new LoginForm(
            new Security(),
            [
                'username' => 'admin',
                'password' => 'wrong_password',
            ],
        );

        verify($this->_model->login())->false();
        verify(Yii::$app->user->isGuest)->true();
        verify($this->_model->errors)->arrayHasKey('password');
    }

    public function testLoginCorrect(): void
    {
        $this->_model = new LoginForm(
            new Security(),
            [
                'username' => 'admin',
                'password' => 'admin',
            ],
        );

        verify($this->_model->login())->true();
        verify(Yii::$app->user->isGuest)->false();
        verify($this->_model->errors)->arrayHasNotKey('password');
    }
}
