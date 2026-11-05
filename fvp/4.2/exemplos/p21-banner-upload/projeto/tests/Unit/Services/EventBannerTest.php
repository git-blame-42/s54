<?php

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\User;
use App\Services\EventBanner;
use Core\Constants\Constants;
use RuntimeException;
use Tests\TestCase;

class EventBannerTest extends TestCase
{
    private Event $event;

    /** @var array<int, string> arquivos temporários criados pelo teste */
    private array $tmpFiles = [];

    public function setUp(): void
    {
        parent::setUp();

        $admin = new User([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin',
        ]);
        $admin->save();

        $this->event = new Event([
            'user_id' => $admin->id,
            'title' => 'Semana Acadêmica',
            'description' => 'Palestras e minicursos.',
            'kind' => 'lecture',
            'modality' => 'in_person',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+30 days')),
            'location' => 'Auditório Central',
            'capacity' => 10,
        ]);
        $this->event->save();
    }

    public function tearDown(): void
    {
        $this->event->banner()->delete();
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        self::removeUploadDirIfEmpty();

        parent::tearDown();
    }

    public static function removeUploadDirIfEmpty(): void
    {
        $dir = (string) Constants::rootPath()->join('public/assets/uploads/events');
        if (is_dir($dir) && count((array) scandir($dir)) === 2) {
            rmdir($dir);
        }
    }

    public function test_url_is_null_without_banner(): void
    {
        $this->assertNull($this->event->banner()->url());
    }

    public function test_validate_accepts_png_detected_by_content(): void
    {
        $this->assertTrue($this->event->banner()->validate($this->upload($this->pngContent(), 'capa.png')));
        $this->assertNull($this->event->errors('banner'));
    }

    public function test_validate_rejects_text_file_with_image_extension(): void
    {
        $file = $this->upload('não sou uma imagem', 'capa.png', 'image/png');

        $this->assertFalse($this->event->banner()->validate($file));
        $this->assertSame('deve ser uma imagem JPG ou PNG!', $this->event->errors('banner'));
    }

    public function test_validate_rejects_file_larger_than_max_size(): void
    {
        $content = $this->pngContent() . str_repeat('0', EventBanner::MAX_SIZE);

        $this->assertFalse($this->event->banner()->validate($this->upload($content, 'capa.png')));
        $this->assertSame('deve ter no máximo 2 MB!', $this->event->errors('banner'));
    }

    public function test_validate_rejects_file_over_php_upload_limit(): void
    {
        $file = ['name' => 'capa.png', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0];

        $this->assertFalse($this->event->banner()->validate($file));
        $this->assertSame('deve ter no máximo 2 MB!', $this->event->errors('banner'));
    }

    public function test_validate_rejects_partial_upload(): void
    {
        $file = $this->upload($this->pngContent(), 'capa.png');
        $file['error'] = UPLOAD_ERR_PARTIAL;

        $this->assertFalse($this->event->banner()->validate($file));
        $this->assertSame('não foi possível enviar o arquivo!', $this->event->errors('banner'));
    }

    public function test_store_saves_file_with_generated_name_and_updates_event(): void
    {
        $this->fakeBanner()->store($this->upload($this->pngContent(), '../../foto do cliente.jpg'));

        $name = (string) $this->event->banner_name;
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.png$/', $name);
        $this->assertSame('/assets/uploads/events/' . $name, $this->event->banner()->url());
        $this->assertFileExists($this->storedPath($name));

        $stored = Event::findById($this->event->id);
        $this->assertNotNull($stored);
        $this->assertSame($name, $stored->banner_name);
    }

    public function test_store_replaces_previous_banner_file(): void
    {
        $this->fakeBanner()->store($this->upload($this->pngContent(), 'primeira.png'));
        $first = (string) $this->event->banner_name;

        $this->fakeBanner()->store($this->upload($this->pngContent(), 'segunda.png'));
        $second = (string) $this->event->banner_name;

        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist($this->storedPath($first));
        $this->assertFileExists($this->storedPath($second));
    }

    public function test_store_refuses_file_that_did_not_come_from_http_upload(): void
    {
        $this->expectException(RuntimeException::class);

        // move_uploaded_file só move arquivos recebidos por POST; um caminho qualquer é recusado.
        $this->event->banner()->store($this->upload($this->pngContent(), 'capa.png'));
    }

    public function test_delete_removes_file_from_disk(): void
    {
        $this->fakeBanner()->store($this->upload($this->pngContent(), 'capa.png'));
        $path = $this->storedPath((string) $this->event->banner_name);

        $this->event->banner()->delete();

        $this->assertFileDoesNotExist($path);
    }

    /**
     * Simula o $_FILES de um upload com o conteúdo informado.
     *
     * @return array<string, mixed>
     */
    private function upload(string $content, string $clientName, string $clientType = 'image/png'): array
    {
        $tmpFile = (string) tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($tmpFile, $content);
        $this->tmpFiles[] = $tmpFile;

        return [
            'name' => $clientName,
            'full_path' => $clientName,
            'type' => $clientType,
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($content),
        ];
    }

    private function pngContent(): string
    {
        return (string) file_get_contents((string) Constants::rootPath()->join('tests/Support/Data/banner.png'));
    }

    private function storedPath(string $name): string
    {
        return (string) Constants::rootPath()->join('public/assets/uploads/events/' . $name);
    }

    /**
     * EventBanner que move com rename(): fora de um POST real, move_uploaded_file sempre recusa.
     */
    private function fakeBanner(): EventBanner
    {
        return new class ($this->event) extends EventBanner {
            protected function moveUploadedFile(string $from, string $to): bool
            {
                return rename($from, $to);
            }
        };
    }
}
