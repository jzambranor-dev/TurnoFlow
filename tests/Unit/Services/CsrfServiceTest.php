<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CsrfService;
use PHPUnit\Framework\TestCase;

class CsrfServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER = array_merge($_SERVER, ['HTTP_X_CSRF_TOKEN' => '']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    public function testTokenGeneratesHexString(): void
    {
        $token = CsrfService::token();

        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testTokenReturnsSameValueOnRepeatedCalls(): void
    {
        $first = CsrfService::token();
        $second = CsrfService::token();

        $this->assertSame($first, $second);
    }

    public function testTokenStoresInSession(): void
    {
        $token = CsrfService::token();

        $this->assertSame($token, $_SESSION['_csrf_token']);
    }

    public function testFieldReturnsHiddenInput(): void
    {
        $html = CsrfService::field();

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="_csrf_token"', $html);
        $this->assertStringContainsString('value="', $html);
    }

    public function testValidateReturnsTrueWithCorrectPostToken(): void
    {
        $token = CsrfService::token();
        $_POST['_csrf_token'] = $token;

        $this->assertTrue(CsrfService::validate());
    }

    public function testValidateReturnsTrueWithCorrectHeaderToken(): void
    {
        $token = CsrfService::token();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        $this->assertTrue(CsrfService::validate());
    }

    public function testValidateReturnsFalseWithWrongToken(): void
    {
        CsrfService::token();
        $_POST['_csrf_token'] = 'wrong_token';

        $this->assertFalse(CsrfService::validate());
    }

    public function testValidateReturnsFalseWithNoToken(): void
    {
        CsrfService::token();
        // No token in POST or header

        $this->assertFalse(CsrfService::validate());
    }

    public function testValidateReturnsFalseWithEmptyToken(): void
    {
        CsrfService::token();
        $_POST['_csrf_token'] = '';

        $this->assertFalse(CsrfService::validate());
    }
}
