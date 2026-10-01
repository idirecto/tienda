<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Media\MediaUploader;
use Tienda\Core\Storage\StorageManager;
use Tienda\Core\ValidationException;
use Tienda\Core\StorageException;
use Tienda\Models\Media;

/**
 * Subida de imagenes del panel (AJAX).
 *
 * El trabajo fino (validar, optimizar a WebP y colocarla en la carpeta
 * `tienda_<tipo>` con el id de la tienda en el nombre) lo hace MediaUploader;
 * aqui solo se persisten los metadatos en `mt_media`.
 */
final class MediaController extends Controller
{
    public function upload(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->requireStoreId();

        if (!$this->csrfValid()) {
            return $this->json(['ok' => false, 'error' => 'Token de seguridad invalido.'], 419);
        }

        if (empty($_FILES['file'])) {
            return $this->json(['ok' => false, 'error' => 'No se ha recibido ningun fichero.'], 422);
        }

        $folder = (string) ($_POST['folder'] ?? 'general');

        try {
            $stored = MediaUploader::upload($_FILES['file'], $storeId, $folder);
        } catch (ValidationException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (StorageException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => 'Error inesperado al subir el fichero.'], 500);
        }

        $mediaId = Media::create([
            'store_id' => $storeId,
            'folder'   => $folder,
            'driver'   => StorageManager::driver()->driver(),
            'file_key' => $stored['key'],
            'url'      => $stored['url'],
            'mime'     => $stored['mime'],
            'bytes'    => $stored['bytes'],
            'width'    => $stored['width'],
            'height'   => $stored['height'],
        ]);

        return $this->json([
            'ok'    => true,
            'media' => [
                'id'             => $mediaId,
                'url'            => $stored['url'],
                'key'            => $stored['key'],
                'driver'         => StorageManager::driver()->driver(),
                'mime'           => $stored['mime'],
                'bytes'          => $stored['bytes'],
                'width'          => $stored['width'],
                'height'         => $stored['height'],
                'optimized'      => $stored['optimized'],
                'original_mime'  => $stored['original_mime'],
                'original_bytes' => $stored['original_bytes'],
            ],
        ]);
    }

    public function destroy(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->requireStoreId();

        $mediaId = (int) ($params['id'] ?? 0);
        $media = Media::findForStore($mediaId, $storeId);

        if ($media === null) {
            return $this->json(['ok' => false, 'error' => 'Recurso no encontrado.'], 404);
        }

        // Borra el objeto fisico y el registro.
        StorageManager::driver()->delete((string) $media['file_key']);
        Media::deleteById($mediaId);

        return $this->json(['ok' => true]);
    }

    private function csrfValid(): bool
    {
        $token = $_POST['_token'] ?? null;
        return \Tienda\Core\Csrf::validate(is_string($token) ? $token : null);
    }

    private function requireStoreId(): int
    {
        $id = Auth::storeId();
        if ($id === null || $id <= 0) {
            $this->json(['ok' => false, 'error' => 'Sesion no valida.'], 401);
        }
        return $id;
    }
}
