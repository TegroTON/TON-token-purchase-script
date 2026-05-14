<?php

declare(strict_types=1);

namespace Tegro\Purchase\Tests\Signature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tegro\Purchase\Postback\OrderIdParser;

#[CoversClass(OrderIdParser::class)]
final class OrderIdParserTest extends TestCase
{
    public function testHappyPath(): void
    {
        [$chatId, $rowId] = OrderIdParser::parse('12345:abc_DEF-1');
        $this->assertSame('12345', $chatId);
        $this->assertSame('abc_DEF-1', $rowId);
    }

    public function testNegativeChatIdAllowed(): void
    {
        // Telegram group chat IDs can be negative.
        [$chatId, $rowId] = OrderIdParser::parse('-100123456789:abc');
        $this->assertSame('-100123456789', $chatId);
        $this->assertSame('abc', $rowId);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badInputs(): iterable
    {
        yield 'empty'              => [''];
        yield 'no colon'           => ['abc'];
        yield 'empty chatId'       => [':abc'];
        yield 'empty rowId'        => ['abc:'];
        yield 'sql injection like' => ["1' OR '1'='1:abc"];
        yield 'shell metachar'     => ['1:abc;rm -rf /'];
        yield 'multiline'          => ["1:abc\nfake"];
    }

    #[DataProvider('badInputs')]
    public function testRejectsBadInputs(string $orderId): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OrderIdParser::parse($orderId);
    }
}
