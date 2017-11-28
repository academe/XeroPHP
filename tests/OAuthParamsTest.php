<?php

namespace Academe\XeroPHP;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use Carbon\Carbon;

class OAuthParamsTest extends TestCase
{
    public function setUp()
    {
    }

    public function testInvalidToken()
    {
        $invalidTokenData = json_decode(file_get_contents(__DIR__ . '/data/oauthInvalidToken.json'), true);

        $params = new OAuthParams($invalidTokenData);

        $this->assertSame('token_rejected', $params->oauthProblem);
        $this->assertSame(
            'Token D1TK0QGE29WVEWIYK0TQNUO6VGDGXG does not match an expected ACCESS token',
            $params->oauthProblemAdvice
        );

        $this->assertSame(false, $params->hasToken());
        $this->assertSame(true, $params->isRejected());
    }

    public function testRefreshedToken()
    {
        $invalidTokenData = json_decode(file_get_contents(__DIR__ . '/data/oauthRefreshedToken.json'), true);

        $params = new OAuthParams($invalidTokenData);

        $this->assertNull($params->oauthProblem);
        $this->assertNull($params->oauthProblemAdvice);

        $this->assertSame(true, $params->hasToken());
        $this->assertSame(false, $params->isRejected());

        // Lifetime is half an hour.
        $this->assertSame(30*60, $params->oauthExpiresIn);

        $params = $params->withOauthCreatedAt('2017-11-28 12:00:00');

        // Created at 12:00
        $this->assertSame('2017-11-28 12:00:00', (string)$params->oauthCreatedAt);
        // Expires hald an hour later.
        $this->assertSame('2017-11-28 12:30:00', (string)$params->oauthExpiresAt);

        $params = $params->withCreatedAt('2017-11-29 12:00:00');

        // Created at 12:00
        $this->assertSame('2017-11-29 12:00:00', (string)$params->oauthCreatedAt);
        // Expires hald an hour later.
        $this->assertSame('2017-11-29 12:30:00', (string)$params->oauthExpiresAt);
    }
}
