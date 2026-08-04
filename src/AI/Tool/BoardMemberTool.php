<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\BoardMemberRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('board_members', 'Les membres du bureau / comité de direction du club, avec leur fonction et leur email de contact.')]
final readonly class BoardMemberTool
{
    public function __construct(
        private BoardMemberRepository $boardMemberRepository,
    ) {
    }

    /**
     * @return array{membres: list<array{nom: string, fonction: ?string, email: ?string}>}|array{erreur: string}
     */
    public function __invoke(): array
    {
        $members = $this->boardMemberRepository->findBoardMembers();

        if ([] === $members) {
            return ['erreur' => 'Aucun membre du bureau trouvé.'];
        }

        return ['membres' => $members];
    }
}
