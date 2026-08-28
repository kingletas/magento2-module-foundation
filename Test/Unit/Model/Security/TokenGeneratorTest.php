<?php
/**
 * TokenGeneratorTest.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Model\Security;

use Commerce\Foundation\Model\Security\TokenGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TokenGeneratorTest extends TestCase
{
    private TokenGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new TokenGenerator();
    }

    public function testGeneratesHexOfTwiceTheRequestedByteLength(): void
    {
        self::assertSame(64, strlen($this->generator->generate(32)));
        self::assertMatchesRegularExpression('/^[a-f0-9]+$/', $this->generator->generate(32));
    }

    /**
     * The minimum is a floor, not a default: a caller asking for less silently
     * gets more rather than a guessable token.
     */
    public function testRequestingLessThanTheMinimumStillYieldsTheMinimum(): void
    {
        self::assertSame(TokenGenerator::MIN_BYTES * 2, strlen($this->generator->generate(1)));
        self::assertSame(TokenGenerator::MIN_BYTES * 2, strlen($this->generator->generate(-5)));
    }

    public function testTokensAreNotRepeated(): void
    {
        $tokens = [];

        for ($i = 0; $i < 200; $i++) {
            $tokens[] = $this->generator->generate();
        }

        self::assertCount(200, array_unique($tokens));
    }

    public function testHashIsStableAndDiffersPerToken(): void
    {
        self::assertSame($this->generator->hash('abc'), $this->generator->hash('abc'));
        self::assertNotSame($this->generator->hash('abc'), $this->generator->hash('abd'));
        self::assertSame(64, strlen($this->generator->hash('abc')));
    }

    public function testMatchesComparesACandidateAgainstAStoredDigest(): void
    {
        $token = $this->generator->generate();
        $stored = $this->generator->hash($token);

        self::assertTrue($this->generator->matches($token, $stored));
        self::assertFalse($this->generator->matches($token . 'x', $stored));
        self::assertFalse($this->generator->matches($token, 'not-a-digest'));
    }

    public function testRejectsAnUnavailableAlgorithmAtConstructionRatherThanAtUse(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TokenGenerator('definitely-not-an-algorithm');
    }
}
