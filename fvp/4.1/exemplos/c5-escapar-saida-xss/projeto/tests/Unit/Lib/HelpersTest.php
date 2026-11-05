<?php

namespace Tests\Unit\Lib;

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function test_e_should_escape_html_tags(): void
    {
        $this->assertSame('&lt;b&gt;x&lt;/b&gt;', e('<b>x</b>'));
        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            e('<script>alert(1)</script>')
        );
    }

    public function test_e_should_escape_quotes_and_ampersand(): void
    {
        $this->assertSame('&quot;a&quot; &#039;b&#039; &amp; c', e('"a" \'b\' & c'));
    }

    public function test_e_should_keep_plain_text_and_accents(): void
    {
        $this->assertSame('Fulano de Tal - Câmpus', e('Fulano de Tal - Câmpus'));
    }

    public function test_e_should_return_empty_string_for_null(): void
    {
        $this->assertSame('', e(null));
    }
}
