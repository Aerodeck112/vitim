<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ChatText;
use PHPUnit\Framework\TestCase;

final class ChatTextTest extends TestCase
{
    public function test_formats_safely(): void
    {
        $this->assertSame('<strong>da</strong>', (string) ChatText::render('**da**'));
        $this->assertSame('&lt;script&gt;x&lt;/script&gt;', (string) ChatText::render('<script>x</script>'));
        $this->assertStringContainsString('href="/servicii"', (string) ChatText::render('[Servicii](/servicii)'));
        $this->assertStringContainsString('href="https://firma.ro/x"', (string) ChatText::render('[x](https://firma.ro/x)'));
        foreach (['[x](javascript:alert(1))', '[x](//evil.example)', '[x](http://evil.example)', '[x](data:text/html,1)'] as $bad) {
            $this->assertStringNotContainsString('<a ', (string) ChatText::render($bad), $bad);
        }
        $this->assertStringNotContainsString('" onmouseover', (string) ChatText::render('[x](/a" onmouseover="b)'), 'ghilimelele rămân escapate');
    }
}
