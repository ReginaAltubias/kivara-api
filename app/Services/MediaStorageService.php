<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MediaStorageService
{
    /**
     * Armazena arquivo de mídia genérica (vídeos, áudios, imagens, documentos).
     */
    public function storeMedia(UploadedFile $file, string $folder = 'media'): array
    {
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml',
            'video/mp4', 'video/webm', 'video/quicktime',
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4',
            'application/pdf', 'application/json', 'text/plain',
        ];

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $allowedMimes)) {
            throw new InvalidArgumentException("Tipo de ficheiro não suportado ({$mimeType}). Formatos aceitos: imagens, vídeos MP4/WebM, áudios, PDF e JSON.");
        }

        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $filename = Str::uuid() . '.' . $extension;
        $path = $file->storeAs("public/{$folder}", $filename);

        $relativePath = "storage/{$folder}/{$filename}";
        $url = asset($relativePath);

        return [
            'data' => [
                'url' => $url,
                'path' => $relativePath,
                'mime_type' => $mimeType,
                'size_bytes' => $file->getSize(),
            ],
            'url' => $url,
        ];
    }

    /**
     * Armazena avatar de perfil de usuário / criança.
     */
    public function storeAvatar(UploadedFile $file, $userOrProfile = null): array
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $mimeType = $file->getMimeType();

        if (!in_array($mimeType, $allowedMimes)) {
            throw new InvalidArgumentException("Formato de avatar inválido ({$mimeType}). Aceitos: JPG, PNG, WEBP.");
        }

        $extension = $file->getClientOriginalExtension() ?: 'webp';
        $filename = 'avatar_' . Str::uuid() . '.' . $extension;
        $path = $file->storeAs('public/avatars', $filename);

        $url = asset("storage/avatars/{$filename}");

        if ($userOrProfile && is_object($userOrProfile)) {
            $userOrProfile->avatar = $url;
            $userOrProfile->avatar_url = $url;
            $userOrProfile->save();
        }

        return [
            'data' => [
                'avatar_url' => $url,
            ],
        ];
    }

    /**
     * Armazena o logotipo de um Tenant institucional.
     */
    public function storeTenantLogo(Tenant $tenant, UploadedFile $file): array
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
        $mimeType = $file->getMimeType();

        if (!in_array($mimeType, $allowedMimes)) {
            throw new InvalidArgumentException("Formato de logotipo inválido ({$mimeType}). Aceitos: JPG, PNG, WEBP, SVG.");
        }

        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'tenant_logo_' . $tenant->id . '_' . Str::random(6) . '.' . $extension;
        $path = $file->storeAs('public/tenants/logos', $filename);

        $url = asset("storage/tenants/logos/{$filename}");

        $tenant->logo_url = $url;
        $tenant->save();

        return [
            'data' => [
                'logo_url' => $url,
            ],
        ];
    }
}
