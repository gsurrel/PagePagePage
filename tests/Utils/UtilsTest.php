<?php
declare(strict_types=1);

namespace PagePagePageTests\Utils;

use PHPUnit\Framework\TestCase;
use PagePagePageUtils\Utils;

final class UtilsTest extends TestCase
{
    public function testBoldEntityIsWrappedInStrongTag(): void
    {
        $text = "Hello World";
        $entities = [['offset' => 6, 'length' => 5, 'type' => 'bold']];

        $html = Utils::formatMessageText($text, $entities);

        $this->assertStringContainsString('<b>World</b>', $html);
    }

    public function testEmptyTextReturnsEmptyString(): void
    {
        $this->assertSame('', Utils::formatMessageText(''));
    }
}
