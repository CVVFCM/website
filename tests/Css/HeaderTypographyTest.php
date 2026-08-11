<?php

declare(strict_types=1);

namespace App\Tests\Css;

use PHPUnit\Framework\TestCase;

/**
 * Deliberately a file-level guard, not a rendering test: the repository has no
 * headless browser (and must not gain one — it would pull a browser into CI),
 * so the only thing we can assert cheaply is that the offending declarations
 * are gone from the stylesheets and that the breadcrumb block lives in its own
 * file. The visual result is checked by a human in the browser.
 *
 * Issues #105 (words cut mid-letter in page headers) and #109 (broken
 * breadcrumb, separator glued to the next item).
 */
final class HeaderTypographyTest extends TestCase
{
    private const string STYLES_DIR = __DIR__.'/../../assets/website/styles';

    /**
     * word-break: break-all is inherited, so on .Page__headerMain it reached the
     * h1, the description and the breadcrumb, breaking lines at any character.
     */
    public function testThePageHeaderNoLongerBreaksWordsAtAnyCharacter(): void
    {
        $default = $this->readStyle('default.css');

        self::assertStringContainsString('.Page__headerMain {', $default);
        self::assertStringNotContainsString('word-break: break-all', $default);
        self::assertMatchesRegularExpression(
            '/\.Page__headerMain \{[^}]*overflow-wrap: anywhere;/s',
            $default,
            '.Page__headerMain must protect its narrow column with overflow-wrap: anywhere.',
        );
    }

    /**
     * Both pages used to opt out of the inherited break-all; the opt-out is now
     * dead code and must not linger.
     */
    public function testThePagesThatOptedOutNoLongerNeedTo(): void
    {
        self::assertStringNotContainsString('word-break', $this->readStyle('search.css'));
        self::assertStringNotContainsString('word-break', $this->readStyle('error.css'));
    }

    public function testTheBreadCrumbBlockLivesInItsOwnFile(): void
    {
        $breadcrumb = $this->readStyle('common/BreadCrumb.css');

        self::assertStringContainsString('.BreadCrumb {', $breadcrumb);
        self::assertStringContainsString('.BreadCrumb__homeIcon', $breadcrumb);
        self::assertStringContainsString('.BreadCrumb__srOnly', $breadcrumb);

        $app = $this->readStyle('app.css');

        self::assertStringContainsString("@import url('./common/BreadCrumb.css');", $app);
        self::assertStringNotContainsString('.BreadCrumb', $app);
    }

    /**
     * The '›' is an ::after pseudo-element, hence a flex item: only a margin on
     * the separator itself spaces it from the label before *and* the item after.
     */
    public function testTheBreadCrumbSeparatorIsSpacedOnBothSides(): void
    {
        $breadcrumb = $this->readStyle('common/BreadCrumb.css');

        self::assertMatchesRegularExpression(
            "/&::after \{[^}]*content: '›';[^}]*margin-inline:/s",
            $breadcrumb,
            'The breadcrumb separator must carry its own margin-inline.',
        );
        self::assertStringContainsString('&:last-child::after', $breadcrumb);
    }

    /**
     * The breadcrumb may wrap between items, never inside a page title — and it
     * cannot rely on .Page__headerMain, since the event page has its own header.
     */
    public function testTheBreadCrumbNeverCutsALabelMidWord(): void
    {
        $breadcrumb = $this->readStyle('common/BreadCrumb.css');

        self::assertStringContainsString('overflow-wrap: normal;', $breadcrumb);
        self::assertStringContainsString('word-break: normal;', $breadcrumb);
    }

    /**
     * Comments are stripped: every assertion here targets declarations, and the
     * explanatory comments legitimately quote the properties we forbid.
     */
    private function readStyle(string $path): string
    {
        $file = self::STYLES_DIR.'/'.$path;

        self::assertFileExists($file);

        return (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($file));
    }
}
