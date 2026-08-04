<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Tool\BoardMemberTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BoardMemberToolTest extends KernelTestCase
{
    public function testItListsBoardMembersOrderedByPosition(): void
    {
        $result = ($this->tool())();

        self::assertArrayHasKey('membres', $result);
        $members = $result['membres'];
        self::assertCount(3, $members);

        foreach ($members as $member) {
            self::assertSame(['nom', 'fonction', 'email'], array_keys($member));
        }

        self::assertSame('Yohan Giarelli', $members[0]['nom']);
        self::assertSame('Président', $members[0]['fonction']);
        self::assertSame('yohan@cvvfcm.fr', $members[0]['email']);

        $positions = array_column($members, 'fonction');
        self::assertSame(['Président', 'Secrétaire général', 'Trésorier'], $positions);
    }

    private function tool(): BoardMemberTool
    {
        self::bootKernel();

        return static::getContainer()->get(BoardMemberTool::class);
    }
}
