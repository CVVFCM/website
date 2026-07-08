<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ForgieUpload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

use function Symfony\Component\Clock\now;

/**
 * Receives an image a visitor attaches to a Forgie message. Stores it (base64) as a
 * ForgieUpload row and returns its id; the client then references that id in the
 * message POST so the handler can show the image to the model and, if asked, forward
 * it to the admins. Kept separate from the message API (which is a fire-and-forget
 * 202) because the client needs the id back synchronously.
 */
#[Route('/api/forgie/uploads', name: 'forgie_upload', methods: ['POST'])]
final readonly class ForgieUploadController
{
    private const int MAX_BYTES = 16 * 1024 * 1024;

    /**
     * @var list<string>
     */
    private const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RateLimiterFactory $forgieUploadApiLimiter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $limit = $this->forgieUploadApiLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $conversationId = (string) $request->request->get('conversationId', '');
        if (!Uuid::isValid($conversationId)) {
            return $this->error('Identifiant de conversation invalide.');
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->error('Aucun fichier reçu.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return $this->error('Image trop volumineuse (16 Mo maximum).');
        }

        // getMimeType() sniffs the content (finfo), not the client-declared type.
        $mimeType = $file->getMimeType() ?? '';
        if (!\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return $this->error('Format non supporté (JPEG, PNG, WebP ou GIF attendu).');
        }

        $upload = new ForgieUpload(
            Uuid::v4()->toRfc4122(),
            $conversationId,
            base64_encode((string) file_get_contents($file->getPathname())),
            $mimeType,
            $this->sanitizeFilename($file->getClientOriginalName()),
            (int) $file->getSize(),
            now(),
        );

        $this->entityManager->persist($upload);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $upload->id], Response::HTTP_CREATED);
    }

    private function error(string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function sanitizeFilename(string $name): string
    {
        $name = basename($name);

        return '' === $name ? 'image' : mb_substr($name, 0, 255);
    }
}
