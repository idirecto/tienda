<?php

declare(strict_types=1);

namespace Tienda\Core\Storage;

/**
 * Contrato de almacenamiento de ficheros.
 *
 * Los drivers reciben un fichero **ya preparado en disco** (subida optimizada
 * por `Tienda\Core\Media\MediaUploader`), no la entrada de $_FILES: asi la
 * validacion y el tratamiento de la imagen viven en un solo sitio.
 */
interface StorageInterface
{
    /**
     * Guarda un fichero local y devuelve sus metadatos.
     *
     * @param string $localPath Fichero a subir (temporal o definitivo)
     * @param string $mime      Mime real del fichero
     * @param int    $storeId   Tienda propietaria (0 = global)
     * @param string $folder    Tipo logico: banners, productos, logo, general...
     *
     * @return array{key:string,url:string,mime:string,bytes:int,width:?int,height:?int}
     */
    public function put(string $localPath, string $mime, int $storeId, string $folder): array;

    public function delete(string $key): bool;

    public function driver(): string;
}
