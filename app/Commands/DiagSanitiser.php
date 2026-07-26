<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Attacks the HTML sanitiser with payloads that actually get used.
 *
 * A sanitiser you have not tried to break is a sanitiser you should not trust.
 */
class DiagSanitiser extends BaseCommand
{
    protected $group       = 'Rasmein';
    protected $name        = 'rasmein:diag-sanitiser';
    protected $description = 'Attack the HTML sanitiser with XSS payloads.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        CLI::newLine();
        CLI::write('  Must be neutralised', 'white');
        CLI::write('  ' . str_repeat('-', 66), 'dark_gray');

        // Each payload, plus the fragments that must NOT survive.
        $attacks = [
            '<script>alert(1)</script>'                                   => ['script', 'alert'],
            '<img src=x onerror=alert(1)>'                                => ['onerror'],
            '<a href="javascript:alert(1)">click</a>'                     => ['javascript:'],
            '<a href="JaVaScRiPt:alert(1)">click</a>'                     => ['javascript:', 'JaVaScRiPt'],
            "<a href=\"jav\tascript:alert(1)\">x</a>"                     => ['ascript:'],
            '<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>'          => ['data:text/html'],
            '<div onclick="steal()">text</div>'                           => ['onclick', 'steal'],
            '<p style="background:url(javascript:alert(1))">x</p>'        => ['style', 'javascript'],
            '<iframe src="https://evil.test"></iframe>'                   => ['iframe', 'evil.test'],
            '<svg/onload=alert(1)>'                                       => ['onload', 'svg'],
            '<body onload=alert(1)>'                                      => ['onload'],
            '<form action="https://evil.test"><input name="a"></form>'    => ['form', 'input', 'evil.test'],
            '<object data="evil.swf"></object>'                           => ['object', 'evil.swf'],
            '<meta http-equiv="refresh" content="0;url=https://evil.test">' => ['meta', 'refresh'],
            '<style>body{display:none}</style>'                           => ['style', 'display:none'],
            '<!--[if IE]><script>alert(1)</script><![endif]-->'           => ['script', 'alert'],
            '<a href="#" onmouseover="alert(1)">hover</a>'                => ['onmouseover'],
            '<img src="x" srcset="evil.test 1x">'                         => ['srcset', 'evil.test'],
            '<base href="https://evil.test/">'                            => ['base', 'evil.test'],
            '<textarea><script>alert(1)</script></textarea>'              => ['script'],
            '<p>ok</p><script>alert(1)</script><p>also ok</p>'            => ['script', 'alert'],
            '<button formaction="https://evil.test">go</button>'          => ['formaction', 'button'],
            '<link rel="stylesheet" href="https://evil.test/x.css">'      => ['link', 'evil.test'],
            '<a href="vbscript:msgbox(1)">x</a>'                          => ['vbscript'],
        ];

        foreach ($attacks as $payload => $forbidden) {
            $clean = (string) service('sanitiser')->clean($payload);
            $leaks = [];

            foreach ($forbidden as $needle) {
                if (stripos($clean, $needle) !== false) {
                    $leaks[] = $needle;
                }
            }

            $label = mb_substr(str_replace(["\n", "\t"], ' ', $payload), 0, 44);

            if ($leaks === []) {
                $this->pass++;
                CLI::write(sprintf('  [ ok ] %-46s → %s', $label, mb_substr($clean, 0, 22)), 'green');
            } else {
                $this->fail++;
                CLI::write(sprintf('  [FAIL] %-46s LEAKED: %s', $label, implode(', ', $leaks)), 'red');
                CLI::write('         got: ' . mb_substr($clean, 0, 90), 'yellow');
            }
        }

        CLI::newLine();
        CLI::write('  Must be preserved', 'white');
        CLI::write('  ' . str_repeat('-', 66), 'dark_gray');

        $keep = [
            '<p>A normal paragraph.</p>'                          => ['A normal paragraph'],
            '<h2>Heading</h2><p>Body <strong>bold</strong>.</p>'  => ['<h2>', '<strong>', 'Heading'],
            '<ul><li>One</li><li>Two</li></ul>'                   => ['<ul>', '<li>', 'One'],
            '<a href="/shipping">Shipping</a>'                    => ['href="/shipping"', 'Shipping'],
            '<a href="https://rasmein.com">Us</a>'                => ['https://rasmein.com', 'noopener'],
            '<a href="mailto:hi@rasmein.com">Mail</a>'            => ['mailto:'],
            '<blockquote>Quoted.</blockquote>'                    => ['<blockquote>'],
            '<table><tr><td>Cell</td></tr></table>'               => ['<td>', 'Cell'],
            '<p>Unicode: दार्जिलिंग ₹1,500</p>'                    => ['दार्जिलिंग', '₹1,500'],
            '<p>5 &lt; 10 &amp; 10 &gt; 5</p>'                    => ['&lt;', '&amp;'],
        ];

        foreach ($keep as $input => $expected) {
            $clean   = (string) service('sanitiser')->clean($input);
            $missing = [];

            foreach ($expected as $needle) {
                if (! str_contains($clean, $needle)) {
                    $missing[] = $needle;
                }
            }

            $label = mb_substr($input, 0, 44);

            if ($missing === []) {
                $this->pass++;
                CLI::write(sprintf('  [ ok ] %-46s kept', $label), 'green');
            } else {
                $this->fail++;
                CLI::write(sprintf('  [FAIL] %-46s LOST: %s', $label, implode(', ', $missing)), 'red');
                CLI::write('         got: ' . mb_substr($clean, 0, 90), 'yellow');
            }
        }

        CLI::newLine();
        CLI::write('  Edge cases', 'white');
        CLI::write('  ' . str_repeat('-', 66), 'dark_gray');

        $edges = [
            'null input'        => service('sanitiser')->clean(null) === null,
            'empty string'      => service('sanitiser')->clean('') === null,
            'whitespace only'   => service('sanitiser')->clean("   \n  ") === null,
            'plain text no tags'=> str_contains((string) service('sanitiser')->clean('Just words.'), 'Just words.'),
            'unclosed tags'     => service('sanitiser')->clean('<p>one<p>two') !== null,
            'deeply nested'     => service('sanitiser')->clean(str_repeat('<div>', 40) . 'x' . str_repeat('</div>', 40)) !== null,
        ];

        foreach ($edges as $label => $ok) {
            $ok ? $this->pass++ : $this->fail++;
            CLI::write(sprintf('  [%s] %s', $ok ? ' ok ' : 'FAIL', $label), $ok ? 'green' : 'red');
        }

        CLI::newLine();
        CLI::write(sprintf('  %d passed, %d failed', $this->pass, $this->fail), $this->fail === 0 ? 'green' : 'red');
        CLI::newLine();

        return $this->fail === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }
}
