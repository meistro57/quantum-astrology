<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Core;

use PHPUnit\Framework\TestCase;
use QuantumAstrology\Core\Session;

final class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    /** @runInSeparateProcess */
    public function testSetAndGet(): void
    {
        Session::set('foo', 'bar');
        $this->assertSame('bar', Session::get('foo'));
    }

    /** @runInSeparateProcess */
    public function testFlashStoresAndClears(): void
    {
        Session::flash('message', 'hello');
        $this->assertTrue(Session::hasFlash('message'));
        $this->assertSame('hello', Session::getFlash('message'));
        $this->assertFalse(Session::hasFlash('message'));
    }

    /** @runInSeparateProcess */
    public function testSessionExpiresAfterInactivity(): void
    {
        Session::set('foo', 'bar');
        $_SESSION['LAST_ACTIVITY'] = time() - 4000;
        Session::start();
        $this->assertNull(Session::get('foo'));
    }
}
