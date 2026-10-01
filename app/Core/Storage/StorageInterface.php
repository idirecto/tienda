<?php

declare(strict_types=1);

namespace Tienda\Core\Storage;

/**
 * Contrato de almacenamiento de ficheros.
 */
interface StorageInterface
{
    /**
     * Guarda un fichero subido y devuelve sus metadatos.
     *
     * @param array  $file    Entrada de $_FILES (tmp_name, name, size, error)
     * @param int    $storeId Tienda propietaria (0 = global)
     * @param string $folder  Subcarpeta logica (banners, productos, logo...)
     * @return array{key:string,url:string,mime:string,bytes:int,width:?int,height:?int}
     */
    public function put(array $file, int $storeId, string $folder): array;

    public function delete(string $key): bool;

    public function driver(): string;
}
