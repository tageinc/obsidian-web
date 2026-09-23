<?php

namespace Tests\Unit;

use Tests\TestCase;

class MailEnvironmentTest extends TestCase
{
    /** @dataProvider environments */
    public function test_default_mail_transport_depends_on_environment(string $environment, string $expected): void
    {
        $values = [
            'APP_ENV' => $environment,
            'MAIL_MAILER' => 'log',
            'MAILPIT_HOST' => 'mailpit',
            'MAILPIT_PORT' => '1025',
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_USERNAME' => 'provider-user',
        ];
        $originalEnv = $_ENV;
        $originalServer = $_SERVER;
        try {
            foreach ($values as $key => $value) {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
            $mail = require base_path('config/mail.php');
            $this->assertSame($expected, $mail['default']);
            $this->assertSame('mailpit', $mail['mailers']['mailpit']['host']);
            $this->assertSame(1025, (int) $mail['mailers']['mailpit']['port']);
            $this->assertNull($mail['mailers']['mailpit']['username']);
            $this->assertNull($mail['mailers']['mailpit']['password']);
            $this->assertNull($mail['mailers']['mailpit']['encryption']);
            $this->assertSame('smtp.example.test', $mail['mailers']['smtp']['host']);
            $this->assertSame('provider-user', $mail['mailers']['smtp']['username']);
        } finally {
            $_ENV = $originalEnv;
            $_SERVER = $originalServer;
        }
    }

    public static function environments(): array
    {
        return [
            'local captures regardless of provider' => ['local', 'mailpit'],
            'production preserves configured mailer' => ['production', 'log'],
            'testing preserves test mailer selection' => ['testing', 'log'],
            'staging preserves configured mailer' => ['staging', 'log'],
        ];
    }
}
