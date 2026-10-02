<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Parser de especificaciones del catalogo central (mismo criterio que idirecto).
 *
 * En idirecto los datos tecnicos vienen en dos campos de `productos_ext`:
 *   - `caracteristicas`  -> cadena corta "Clave: valor, Clave: valor..."
 *                           (se usa para los puntos de la descripcion)
 *   - `especificaciones` -> HTML con grupos (<h3>) y filas "<td>Clave: valor</td>"
 *                           (se usa para la tabla de especificaciones)
 */
final class Specs
{
    /** Nombres de grupo que no aportan nada. */
    private const GENERIC_HEADINGS = [
        'especificaciones', 'caracteristicas', 'características',
        'ficha tecnica', 'ficha técnica', 'general', 'descripcion', 'descripción',
    ];

    /** Atributos "clave" para el resumen, ordenados por relevancia (como idirecto). */
    private const SUMMARY_PRIORITY = [
        ['marca', 'marca comercial', 'fabricante'],
        ['modelo', 'numero de modelo', 'part number', 'referencia', 'modelo del producto'],
        ['sistema operativo instalado', 'sistema operativo incluido', 'sistema operativo'],
        ['memoria interna', 'memoria ram', 'ram', 'memoria instalada', 'memoria interna ram'],
        ['capacidad total de almacenaje', 'capacidad total de ssd', 'capacidad total de hdd',
         'capacidad de almacenamiento', 'almacenamiento interno', 'disco duro', 'ssd'],
        ['diagonal de la pantalla', 'tamano de pantalla', 'tamano pantalla', 'pantalla'],
        ['familia de procesador', 'modelo del procesador', 'tipo de procesador', 'procesador',
         'fabricante de procesador', 'cpu', 'chipset'],
        ['resolucion de la pantalla', 'resolucion maxima', 'resolucion'],
        ['conectividad', 'conexiones', 'puertos', 'interfaces', 'bluetooth', 'wifi', 'wi-fi'],
        ['peso', 'peso neto', 'dimensiones'],
        ['color del producto', 'color'],
        ['duracion de la garantia', 'garantia'],
        ['capacidad de la bateria', 'bateria'],
        ['camara principal', 'camara'],
        ['numero de nucleos', 'nucleos', 'velocidad del procesador'],
    ];

    /**
     * Orden de preferencia de "De un vistazo" (valor + etiqueta).
     * Cada entrada es una "ranura": se elige el primer atributo del resumen
     * que coincida exactamente con alguno de sus alias, de modo que el bloque
     * sea estable y no repita el mismo dato dos veces.
     */
    private const GLANCE_SLOTS = [
        ['capacidad total de almacenaje', 'capacidad total de ssd', 'capacidad total de hdd', 'capacidad de almacenamiento'],
        ['peso', 'peso neto'],
        ['diagonal de la pantalla', 'tamano de pantalla', 'tamano pantalla'],
        ['memoria interna', 'memoria ram', 'ram', 'memoria instalada'],
        ['familia de procesador', 'modelo del procesador', 'tipo de procesador'],
        ['sistema operativo instalado', 'sistema operativo incluido'],
        ['color del producto', 'color'],
        ['capacidad de la bateria', 'bateria'],
        ['duracion de la garantia'],
    ];

    // =====================================================================
    // CARACTERISTICAS (cadena corta)
    // =====================================================================

    /**
     * Convierte `caracteristicas` en pares clave/valor.
     * @return array<int, array{k:string, v:string}>
     */
    public static function pairs(?string $raw): array
    {
        $text = self::toPlainText($raw);
        if ($text === '') {
            return [];
        }

        $normalized = preg_replace('/(?<=[,.])\s+(?=[^:,]{1,80}:)/u', "\n", $text) ?? $text;

        $pairs = [];
        foreach (preg_split('/\r?\n/', $normalized) ?: [] as $line) {
            $line = trim($line, " \t,.;");
            if ($line === '') {
                continue;
            }
            $pair = self::splitPair($line);
            if ($pair !== null) {
                $pairs[] = $pair;
            } elseif ($pairs !== []) {
                $last = count($pairs) - 1;
                $pairs[$last]['v'] .= ', ' . $line;
            }
        }

        foreach ($pairs as &$p) {
            $p['v'] = trim($p['v'], " \t,.;");
        }
        unset($p);

        return array_values(array_filter($pairs, static fn (array $p) => $p['k'] !== '' && $p['v'] !== ''));
    }

    /**
     * Puntos para la descripcion ("clave: valor").
     * @return array<int, string>
     */
    public static function bullets(?string $raw, int $limit = 8): array
    {
        $bullets = [];
        foreach (self::pairs($raw) as $pair) {
            $bullets[] = $pair['k'] . ': ' . $pair['v'];
            if (count($bullets) >= $limit) {
                break;
            }
        }
        return $bullets;
    }

    // =====================================================================
    // ESPECIFICACIONES (HTML agrupado)
    // =====================================================================

    /**
     * Parsea el HTML de `especificaciones` en grupos.
     *
     * Estructura del origen:
     *   <table><tr><th><h3>Procesador</h3></th></tr>
     *          <tr><td>Familia de procesador: Intel Core 5</td></tr></table>
     *   <table><tr><td>Fabricante de procesador: Intel</td></tr></table>
     *   <table><tr><th><h3>Memoria</h3></th></tr> ...
     *
     * Las filas pertenecen al ultimo <h3> encontrado.
     *
     * @return array<int, array{name:string, rows:array<int, array{k:string,v:string}>}>
     */
    public static function groupsFromHtml(?string $html): array
    {
        $html = trim((string) $html);
        if ($html === '') {
            return [];
        }

        // El origen trae secuencias literales (barra invertida + n/t) en el HTML.
        $html = str_replace(['\\n', '\\t', '\\r', '\\"'], ' ', $html);

        if (!preg_match('/<[a-z]/i', $html)) {
            // Sin etiquetas: se trata como lista de pares
            $pairs = self::pairs($html);
            return $pairs === [] ? [] : [['name' => 'Especificaciones', 'rows' => $pairs]];
        }

        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<?xml encoding="utf-8" ?>' . $html,
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            return [];
        }

        $groups = [];
        $index = -1;

        // getElementsByTagName('*') devuelve los nodos en orden de documento.
        foreach ($doc->getElementsByTagName('*') as $element) {
            $tag = strtolower($element->nodeName);

            if (in_array($tag, ['h2', 'h3', 'h4', 'strong'], true)) {
                $name = self::cleanText($element->textContent);
                if ($name === '' || self::isGenericHeading($name)) {
                    continue;
                }
                $index = self::groupIndex($groups, $name);
                continue;
            }

            if ($tag !== 'td' && $tag !== 'li') {
                continue;
            }
            if ($index < 0) {
                continue;
            }
            // Ignorar celdas contenedoras que engloban otras celdas (evita duplicados)
            if ($element->getElementsByTagName('td')->length > 0
                || $element->getElementsByTagName('li')->length > 0) {
                continue;
            }

            $pair = self::splitPair(self::cleanText($element->textContent));
            if ($pair === null) {
                continue;
            }
            if (count($groups[$index]['rows']) >= 80) {
                continue;
            }
            $groups[$index]['rows'][] = $pair;
        }

        return array_values(array_filter($groups, static fn (array $g) => $g['rows'] !== []));
    }

    /** Devuelve el indice del grupo con ese nombre, creandolo si no existe. */
    private static function groupIndex(array &$groups, string $name): int
    {
        foreach ($groups as $i => $group) {
            if ($group['name'] === $name) {
                return $i;
            }
        }
        $groups[] = ['name' => $name, 'rows' => []];
        return count($groups) - 1;
    }

    // =====================================================================
    // RESUMEN DE ESPECIFICACIONES CLAVE
    // =====================================================================

    /**
     * Resumen corto que se muestra junto al bloque de compra.
     * Mismo enfoque que idirecto: marca + modelo y, despues, los atributos
     * mas relevantes para el comprador.
     *
     * @param array $groups  Grupos parseados de `especificaciones`
     * @param array $product Datos del producto (marca_nombre, part_number)
     * @return array<int, array{k:string, v:string}>
     */
    public static function summary(array $groups, array $product, int $max = 12): array
    {
        $items = [];
        $seen = [];

        $marca = trim((string) ($product['marca_nombre'] ?? ''));
        if ($marca !== '') {
            $items[] = ['k' => 'Marca', 'v' => $marca];
            $seen[self::normalize('Marca')] = true;
        }

        $referencia = trim((string) ($product['part_number'] ?? ''));
        if ($referencia !== '') {
            $items[] = ['k' => 'Modelo', 'v' => $referencia];
            $seen[self::normalize('Modelo')] = true;
        }

        // Aplanar los grupos conservando el orden original
        $all = [];
        foreach ($groups as $group) {
            foreach ($group['rows'] as $row) {
                $all[] = $row + ['norm' => self::normalize($row['k'])];
            }
        }

        // Dos pasadas por prioridad: primero coincidencia exacta de nombre y
        // despues por contenido, para no elegir "Fabricante de procesador"
        // cuando existe "Familia de procesador".
        foreach (self::SUMMARY_PRIORITY as $aliases) {
            foreach ([true, false] as $exactOnly) {
                if (count($items) >= $max) {
                    break;
                }
                foreach ($aliases as $alias) {
                    $aliasNorm = self::normalize($alias);
                    if (isset($seen[$aliasNorm])) {
                        continue;
                    }
                    foreach ($all as $row) {
                        if (isset($seen[$row['norm']]) || $row['v'] === '') {
                            continue;
                        }
                        $match = $exactOnly
                            ? $row['norm'] === $aliasNorm
                            : str_contains($row['norm'], $aliasNorm);
                        if ($match) {
                            $items[] = ['k' => $row['k'], 'v' => $row['v']];
                            $seen[$row['norm']] = true;
                            break;
                        }
                    }
                    if (count($items) >= $max) {
                        break;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Valores destacados para "De un vistazo" (valor + etiqueta).
     *
     * @param array<int, array{k:string,v:string}> $summary
     * @return array<int, array{value:string, label:string}>
     */
    public static function highlights(array $summary, int $max = 4): array
    {
        $highlights = [];
        $used = [];

        foreach (self::GLANCE_SLOTS as $slotAliases) {
            foreach ($slotAliases as $alias) {
                $matched = false;
                foreach ($summary as $i => $item) {
                    if (isset($used[$i])) {
                        continue;
                    }
                    if (self::normalize($item['k']) === $alias) {
                        $highlights[] = ['value' => $item['v'], 'label' => $item['k']];
                        $used[$i] = true;
                        $matched = true;
                        break;
                    }
                }
                if ($matched) {
                    break;
                }
            }
            if (count($highlights) >= $max) {
                break;
            }
        }

        return $highlights;
    }

    // =====================================================================
    // TARJETA DE PRODUCTO (chips de especificaciones)
    // =====================================================================

    /**
     * Dos o tres datos tecnicos para la tarjeta del listado, elegidos por
     * prioridad y con etiqueta corta ("Socket: AM5").
     *
     * Se alimenta de `caracteristicas` (cadena corta), asi que es barato: la
     * tarjeta no necesita parsear el HTML completo de especificaciones. Si el
     * producto no trae caracteristicas (pasa en buena parte del catalogo), los
     * datos se extraen del propio nombre, que en informatica suele llevarlos.
     *
     * @return array<int, array{k:string,v:string}>
     */
    public static function quickHighlights(?string $raw, int $limit = 3, string $name = ''): array
    {
        $limit = max(1, $limit);
        $out = [];

        foreach (self::highlightsFromPairs(self::pairs($raw), $limit) as $item) {
            $out[] = $item;
        }

        if (count($out) < $limit && $name !== '') {
            foreach (self::highlightsFromName($name, $limit) as $item) {
                if (count($out) >= $limit) {
                    break;
                }
                // Sin repetir etiquetas ya resueltas.
                foreach ($out as $existing) {
                    if ($existing['k'] === $item['k']) {
                        continue 2;
                    }
                }
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Elige los chips a partir de los pares clave/valor de `caracteristicas`.
     *
     * @param array<int, array{k:string,v:string}> $pairs
     * @return array<int, array{k:string,v:string}>
     */
    private static function highlightsFromPairs(array $pairs, int $limit): array
    {
        if ($pairs === []) {
            return [];
        }

        $slots = (array) Config::get('catalog.card_specs', []);
        if ($slots === []) {
            return [];
        }

        // Indice normalizado de los pares disponibles.
        $index = [];
        foreach ($pairs as $pair) {
            $norm = self::normalize($pair['k']);
            if ($norm === '' || $pair['v'] === '' || isset($index[$norm])) {
                continue;
            }
            $index[$norm] = $pair['v'];
        }

        $out = [];
        $used = [];
        foreach ($slots as $slot) {
            if (count($out) >= $limit) {
                break;
            }
            $label = (string) ($slot['label'] ?? '');
            $keys = (array) ($slot['keys'] ?? []);

            // Primero coincidencia exacta; si no, coincidencia por contenido.
            $matched = false;
            foreach ([true, false] as $exact) {
                if ($matched) {
                    break;
                }
                foreach ($keys as $key) {
                    if ($matched) {
                        break;
                    }
                    $key = self::normalize((string) $key);
                    foreach ($index as $norm => $value) {
                        // Una clave numerica ("1440") se guarda como entero en el
                        // array: sin convertirla, str_contains() recibe un int y
                        // el listado se cae con TypeError.
                        $norm = (string) $norm;
                        if (isset($used[$norm])) {
                            continue;
                        }
                        if ($exact ? $norm !== $key : !str_contains($norm, $key)) {
                            continue;
                        }
                        $out[] = ['k' => $label !== '' ? $label : $key, 'v' => self::shortenValue($value)];
                        $used[$norm] = true;
                        $matched = true;
                        break;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Chips extraidos del nombre del producto (fallback para el catalogo sin
     * caracteristicas): "…32 GB DDR5" -> Memoria DDR5, Capacidad 32 GB.
     *
     * @return array<int, array{k:string,v:string}>
     */
    private static function highlightsFromName(string $name, int $limit): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        if ($name === '') {
            return [];
        }

        $found = [];

        // Cada regla: etiqueta + patron + que se muestra (0 = todo, 1 = grupo).
        $rules = [
            ['Socket', '/\b(LGA\s?-?\d{3,4}|AM[3-5]|FM[12]|SP[3-6]\b|sTR5|TR4)\b/iu', 1],
            ['Grafica', '/\b(RTX\s?(?:PRO\s?)?\d{4}\s?(?:Ti|SUPER)?|RX\s?\d{4}\s?(?:XTX|XT)?|GTX\s?\d{3,4}\s?(?:Ti)?|Arc\s?[AB]\d{3})\b/iu', 1],
            ['Memoria', '/\b(DDR[345])\b/iu', 1],
            ['Capacidad', '/\b(\d{1,4}\s?(?:TB|GB))\b/iu', 1],
            ['CPU', '/\b(Ryzen\s?\d|Core\s?(?:Ultra\s?)?[iI][3579]|Core\s?Ultra\s?\d|Xeon|Celeron|Pentium|Threadripper)\b/u', 1],
            ['Almacenamiento', '/\b(NVMe|SSD|HDD)\b/iu', 1],
            ['Formato', '/\b(E-ATX|Micro-?ATX|Micro\sATX|mATX|Mini-?ITX|Mini\sITX|ATX)\b/iu', 1],
            ['Pantalla', '/\b(\d{2}(?:[.,]\d)?\s?"|\d{2}(?:[.,]\d)?\s?pulgadas)\b/iu', 1],
            ['Velocidad', '/\b(\d{2,4}\s?(?:Hz|MT\/s|MHz))\b/iu', 1],
            ['Garantia', '/\b(\d+\s?(?:anos|años|meses)\s?(?:de\s?)?garant[ií]a)\b/iu', 1],
        ];

        foreach ($rules as $rule) {
            if (count($found) >= $limit) {
                break;
            }
            [$label, $pattern, $group] = $rule;
            if (!preg_match($pattern, $name, $m)) {
                continue;
            }
            $value = trim((string) ($m[$group] ?? $m[0]));
            if ($value === '') {
                continue;
            }
            $found[] = ['k' => $label, 'v' => self::shortenValue($value)];
        }

        return $found;
    }

    /**
     * Recorta un valor para que quepa en un chip: quita prefijos redundantes
     * ("Socket AM5" -> "AM5") y limita la longitud.
     */
    private static function shortenValue(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        $value = (string) preg_replace('/^(socket|z[oó]calo)\s+/iu', '', $value);

        if (mb_strlen($value) <= 32) {
            return $value;
        }
        // Se corta por la coma mas cercana al limite para no partir una palabra.
        $corte = mb_substr($value, 0, 32);
        $pos = mb_strrpos($corte, ',');
        if ($pos !== false && $pos > 12) {
            $corte = mb_substr($corte, 0, $pos);
        }

        return rtrim($corte, ' ,;.-') . '...';
    }

    // =====================================================================
    // INTERNOS
    // =====================================================================

    private static function splitPair(string $text): ?array
    {
        $text = trim($text);
        if ($text === '' || !str_contains($text, ':')) {
            return null;
        }
        if (!preg_match('/^(.{1,90}?):\s*(.+)$/u', $text, $m)) {
            return null;
        }
        $k = trim($m[1]);
        $v = trim($m[2]);
        if ($k === '' || $v === '') {
            return null;
        }
        return ['k' => $k, 'v' => $v];
    }

    private static function isGenericHeading(string $name): bool
    {
        return in_array(mb_strtolower($name), self::GENERIC_HEADINGS, true);
    }

    /** Normaliza para comparar: minusculas, sin acentos, espacios simples. */
    private static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false && $converted !== '') {
            $text = $converted;
        }
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private static function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(['\\n', '\\t', '\\r'], ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private static function toPlainText(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        $withBreaks = str_replace(
            ['<br>', '<br/>', '<br />', '</li>', '<li>', '</p>', '<p>', '</tr>', '</div>'],
            "\n",
            $raw
        );

        $text = strip_tags($withBreaks);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(['\\n', '\\t', '\\r'], ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
