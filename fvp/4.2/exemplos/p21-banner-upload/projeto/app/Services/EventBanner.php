<?php

namespace App\Services;

use App\Models\Event;
use Core\Constants\Constants;
use finfo;
use RuntimeException;

/**
 * Imagem de capa (banner) do Evento, no molde do ProfileAvatar.
 *
 * O arquivo vai para public/assets/uploads/events/ com nome gerado (nunca o nome enviado pelo
 * navegador) e esse nome fica em events.banner_name. O tipo é conferido pelo conteúdo (finfo),
 * não pela extensão, e o tamanho pelo arquivo recebido.
 */
class EventBanner
{
    /** Tipos aceitos (MIME detectado pelo finfo) e a extensão usada no nome gerado. */
    public const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /** Tamanho máximo: 2 MB (o php/uploads.ini aceita até 40 MB; o limite da regra é este). */
    public const MAX_SIZE = 2 * 1024 * 1024;

    private const BASE_DIR = '/assets/uploads/events/';

    public function __construct(
        private Event $event
    ) {
    }

    /**
     * URL pública da imagem, ou null se o Evento não tem banner.
     */
    public function url(): ?string
    {
        $name = $this->event->banner_name;

        if ($name === null || $name === '') {
            return null;
        }

        return self::BASE_DIR . $name;
    }

    /**
     * Valida o arquivo de $_FILES e registra o problema em errors('banner') do Evento.
     *
     * @param array<string, mixed> $file
     */
    public function validate(array $file): bool
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $tmpPath = $file['tmp_name'] ?? null;

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            $this->event->addError('banner', 'deve ter no máximo 2 MB!');
        } elseif ($error !== UPLOAD_ERR_OK || !is_string($tmpPath) || !is_file($tmpPath)) {
            $this->event->addError('banner', 'não foi possível enviar o arquivo!');
        } elseif (filesize($tmpPath) > self::MAX_SIZE) {
            $this->event->addError('banner', 'deve ter no máximo 2 MB!');
        } elseif ($this->mimeType($tmpPath) === null) {
            $this->event->addError('banner', 'deve ser uma imagem JPG ou PNG!');
        }

        return $this->event->errors('banner') === null;
    }

    /**
     * Grava o arquivo já validado com nome gerado, apaga o banner anterior e atualiza banner_name.
     *
     * @param array<string, mixed> $file
     */
    public function store(array $file): void
    {
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $mimeType = $this->mimeType($tmpPath);

        if ($mimeType === null) {
            throw new RuntimeException('Imagem de capa inválida: valide o arquivo antes de gravar.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mimeType];

        if (!$this->moveUploadedFile($tmpPath, $this->storeDir() . $name)) {
            throw new RuntimeException('Não foi possível gravar a imagem de capa em ' . $this->storeDir());
        }

        $this->delete();
        $this->event->update(['banner_name' => $name]);
    }

    /**
     * Apaga do disco o arquivo do banner atual (o valor de banner_name não muda).
     */
    public function delete(): void
    {
        $name = $this->event->banner_name;

        if ($name === null || $name === '') {
            return;
        }

        $path = $this->storeDir() . basename($name);
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * move_uploaded_file só aceita arquivo que chegou por upload HTTP; os testes de unidade sobrescrevem.
     */
    protected function moveUploadedFile(string $from, string $to): bool
    {
        return move_uploaded_file($from, $to);
    }

    private function storeDir(): string
    {
        $path = (string) Constants::rootPath()->join('public' . self::BASE_DIR);

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    private function mimeType(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        return is_string($mimeType) && isset(self::TYPES[$mimeType]) ? $mimeType : null;
    }
}
