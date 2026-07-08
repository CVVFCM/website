<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ForgieUploadController;
use App\Repository\ForgieUploadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class ForgieUploadControllerTest extends WebTestCase
{
    private const string UUID = '019779c9-2f74-7a3e-8bcb-1d6c02f0d251';

    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    public function testItStoresAValidImageAndReturnsItsId(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/forgie/uploads', ['conversationId' => self::UUID], ['file' => $this->pngFile()]);

        $this->assertResponseStatusCodeSame(201);

        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('id', $body);

        /** @var ForgieUploadRepository $repository */
        $repository = static::getContainer()->get(ForgieUploadRepository::class);
        $upload = $repository->find($body['id']);
        $this->assertNotNull($upload);
        $this->assertSame(self::UUID, $upload->conversationId);
        $this->assertSame('image/png', $upload->mimeType);
    }

    public function testItRejectsANonImageFile(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/forgie/uploads', ['conversationId' => self::UUID], ['file' => $this->textFile()]);

        $this->assertResponseStatusCodeSame(422);
        /** @var array{error: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Format non supporté', $body['error']);
    }

    public function testItRejectsAnOversizedFile(): void
    {
        // Called directly: BrowserKit replaces an over-limit upload with an errored,
        // path-less file (a harness artifact), so exercise the guard on the controller.
        $file = $this->createStub(UploadedFile::class);
        $file->method('getSize')->willReturn(17 * 1024 * 1024);

        $request = new Request(request: ['conversationId' => self::UUID]);
        $request->files->set('file', $file);

        $controller = new ForgieUploadController($this->createStub(EntityManagerInterface::class), $this->unlimited());
        $response = $controller($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('volumineuse', (string) $response->getContent());
    }

    private function unlimited(): RateLimiterFactory
    {
        return new RateLimiterFactory(['id' => 'forgie_upload_api', 'policy' => 'no_limit'], new InMemoryStorage());
    }

    public function testItRejectsAMissingFile(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/forgie/uploads', ['conversationId' => self::UUID]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testItRejectsAnInvalidConversationId(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/forgie/uploads', ['conversationId' => 'not-a-uuid'], ['file' => $this->pngFile()]);

        $this->assertResponseStatusCodeSame(422);
    }

    private function pngFile(): UploadedFile
    {
        $image = new \Imagick();
        $image->newImage(32, 32, new \ImagickPixel('blue'));
        $image->setImageFormat('png');

        return $this->uploadedFile((string) $image->getImageBlob(), 'photo.png', 'image/png');
    }

    private function textFile(): UploadedFile
    {
        return $this->uploadedFile('not an image', 'note.txt', 'text/plain');
    }

    private function uploadedFile(string $content, string $name, string $mime): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'forgie_upload_test');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        // test: true — bypasses is_uploaded_file() so the fixture behaves like a real upload.
        return new UploadedFile($path, $name, $mime, null, true);
    }
}
