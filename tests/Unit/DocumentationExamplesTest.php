<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Every PHP example in the documentation has to parse. A doc example that does not compile is
 * worse than no example: it is followed, and it fails.
 *
 * Complete files are linted as they are; a fragment that starts with a visibility keyword is
 * wrapped in a class first, since that is where it belongs.
 */
class DocumentationExamplesTest extends AbstractUnitTestCase
{
    private const ROOT = __DIR__ . '/../..';

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function exampleProvider(): array
    {
        $files = array_merge(
            glob(self::ROOT . '/docs/*.md') ?: [],
            [self::ROOT . '/Readme.md']
        );

        $examples = [];

        foreach ($files as $file) {
            preg_match_all('/```php\n(.*?)```/s', (string)file_get_contents($file), $matches);

            foreach ($matches[1] as $index => $code) {
                $examples[basename($file) . ' #' . ($index + 1)] = [
                    basename($file),
                    $code,
                ];
            }
        }

        return $examples;
    }

    #[Test]
    #[DataProvider('exampleProvider')]
    public function everyDocumentationExampleParses(string $file, string $code): void
    {
        $trimmed = ltrim($code);

        if (str_starts_with($trimmed, '<?php')) {
            $source = $code;
        } elseif (preg_match('/^(public|protected|private|abstract|final)\s/', $trimmed) === 1) {
            $source = "<?php\nclass DocFragment\n{\n$code\n}\n";
        } else {
            $source = "<?php\n$code";
        }

        $path = tempnam(sys_get_temp_dir(), 'doc') . '.php';
        file_put_contents($path, $source);

        $output = [];
        $status = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);

        @unlink($path);

        static::assertSame(0, $status, "$file does not parse:\n" . implode("\n", $output));
    }

    #[Test]
    public function everyDocumentationPageIsLinkedFromTheReadme(): void
    {
        $readme = (string)file_get_contents(self::ROOT . '/Readme.md');

        foreach (glob(self::ROOT . '/docs/*.md') ?: [] as $page) {
            static::assertStringContainsString(
                basename($page),
                $readme,
                basename($page) . ' is not listed in the readme, so nothing links to it'
            );
        }
    }
}
