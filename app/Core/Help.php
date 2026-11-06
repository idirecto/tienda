<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Catalogo y buscador del Centro de Ayuda.
 *
 * El contenido vive en archivos PHP versionados dentro de `app/Help`. No se
 * guarda en la base de datos: es comun a todas las tiendas, no contiene datos
 * de clientes y no necesita una pantalla de administracion.
 */
final class Help
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $rawCategories = null;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $articles = null;

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $faqGroups = null;

    /** Resultado de busqueda para una consulta normalizada. */
    private const MIN_QUERY_LENGTH = 2;

    /**
     * Categorias visibles, con el numero de contenidos de cada una.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function categories(bool $includePlatform = false): array
    {
        $categories = self::rawCategories();
        $articles = self::articleData();

        foreach ($categories as $slug => $category) {
            if (!$includePlatform && !empty($category['platform_only'])) {
                unset($categories[$slug]);
                continue;
            }

            $categories[$slug]['article_count'] = ($category['type'] ?? 'articles') === 'faq'
                ? self::faqCount()
                : count(array_filter(
                    $articles,
                    static fn (array $article): bool => $article['category'] === $slug
                ));
        }

        uasort($categories, static fn (array $a, array $b): int => (int) $a['order'] <=> (int) $b['order']);

        return $categories;
    }

    /** @return array<string, mixed>|null */
    public static function category(string $slug, bool $includePlatform = false): ?array
    {
        $categories = self::categories($includePlatform);

        return $categories[$slug] ?? null;
    }

    /**
     * Articulos de una categoria, ordenados.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function articlesForCategory(string $category, bool $includePlatform = false): array
    {
        $categoryData = self::category($category, $includePlatform);
        if ($categoryData === null || ($categoryData['type'] ?? 'articles') !== 'articles') {
            return [];
        }

        return array_values(array_filter(
            self::articleData(),
            static fn (array $article): bool => $article['category'] === $category
        ));
    }

    /**
     * Busca un articulo por su ruta `categoria/slug`.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $path, bool $includePlatform = false): ?array
    {
        $article = self::articleData()[$path] ?? null;
        if ($article === null || !$includePlatform && !empty($article['platform_only'])) {
            return null;
        }

        return $article;
    }

    /**
     * Articulo concreto de una categoria.
     *
     * @return array<string, mixed>|null
     */
    public static function article(string $category, string $slug, bool $includePlatform = false): ?array
    {
        return self::find($category . '/' . $slug, $includePlatform);
    }

    /**
     * Articulos relacionados ya resueltos y filtrados por permisos.
     *
     * @param array<string, mixed> $article
     * @return array<int, array<string, mixed>>
     */
    public static function related(array $article, bool $includePlatform = false): array
    {
        $related = [];
        foreach ((array) ($article['related'] ?? []) as $path) {
            $item = self::find((string) $path, $includePlatform);
            if ($item !== null) {
                $related[] = $item;
            }
        }

        return $related;
    }

    /**
     * Grupos de preguntas frecuentes.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function faqGroups(): array
    {
        if (self::$faqGroups === null) {
            $file = TIENDA_BASE . '/app/Help/faq.php';
            $groups = is_file($file) ? require $file : [];
            if (!is_array($groups)) {
                $groups = [];
            }

            foreach ($groups as $groupIndex => &$group) {
                $items = [];
                foreach ((array) ($group['items'] ?? []) as $itemIndex => $item) {
                    if (!is_array($item) || trim((string) ($item['question'] ?? '')) === '') {
                        continue;
                    }

                    $slug = trim((string) ($item['slug'] ?? ''));
                    if ($slug === '') {
                        $slug = 'pregunta-' . ((int) $groupIndex + 1) . '-' . ((int) $itemIndex + 1);
                    }

                    $item['slug'] = $slug;
                    $item['anchor'] = 'faq-' . $slug;
                    $item['search_text'] = self::fold(
                        (string) ($item['question'] ?? '') . ' '
                        . self::plainText((string) ($item['answer'] ?? '')) . ' '
                        . implode(' ', (array) ($item['keywords'] ?? []))
                    );
                    $items[] = $item;
                }
                $group['items'] = $items;
            }
            unset($group);

            self::$faqGroups = array_values(array_filter(
                $groups,
                static fn (array $group): bool => (array) ($group['items'] ?? []) !== []
            ));
        }

        return self::$faqGroups;
    }

    /** Numero de preguntas frecuentes. */
    public static function faqCount(): int
    {
        $count = 0;
        foreach (self::faqGroups() as $group) {
            $count += count((array) ($group['items'] ?? []));
        }

        return $count;
    }

    /**
     * Buscador en servidor.
     *
     * Busca coincidencias parciales en titulo, palabras clave, resumen y
     * contenido. Ignora mayusculas y acentos y devuelve primero los resultados
     * con mayor relevancia.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $query, bool $includePlatform = false, int $limit = 30): array
    {
        $query = trim($query);
        if (mb_strlen($query, 'UTF-8') < self::MIN_QUERY_LENGTH) {
            return [];
        }
        $query = mb_substr($query, 0, 120, 'UTF-8');
        $foldedQuery = self::fold($query);
        if ($foldedQuery === '') {
            return [];
        }

        $tokens = self::tokens($foldedQuery);
        if ($tokens === []) {
            return [];
        }

        $results = [];
        foreach (self::articleData() as $article) {
            if (!$includePlatform && !empty($article['platform_only'])) {
                continue;
            }

            $score = self::score(
                $foldedQuery,
                $tokens,
                (string) $article['title'],
                (string) $article['summary'],
                implode(' ', (array) $article['keywords']),
                (string) $article['body']
            );
            if ($score <= 0) {
                continue;
            }

            $results[] = [
                'type'           => 'article',
                'title'          => (string) $article['title'],
                'summary'        => (string) $article['summary'],
                'category_title' => (string) $article['category_title'],
                'url'            => self::articleUrl((string) $article['path']),
                'path'           => (string) $article['path'],
                'score'          => $score,
            ];
        }

        if (self::category('preguntas-frecuentes', $includePlatform) !== null) {
            foreach (self::faqGroups() as $group) {
                foreach ((array) $group['items'] as $item) {
                    $score = self::score(
                        $foldedQuery,
                        $tokens,
                        (string) $item['question'],
                        '',
                        implode(' ', (array) ($item['keywords'] ?? [])),
                        (string) $item['answer']
                    );
                    if ($score <= 0) {
                        continue;
                    }

                    $results[] = [
                        'type'           => 'faq',
                        'title'          => (string) $item['question'],
                        'summary'        => self::excerpt((string) $item['answer'], 160),
                        'category_title' => 'Preguntas frecuentes',
                        'url'            => self::categoryUrl('preguntas-frecuentes') . '#' . (string) $item['anchor'],
                        'path'           => 'preguntas-frecuentes/' . (string) $item['slug'],
                        'score'          => $score + 20,
                    ];
                }
            }
        }

        usort($results, static function (array $a, array $b): int {
            $byScore = (int) $b['score'] <=> (int) $a['score'];
            if ($byScore !== 0) {
                return $byScore;
            }

            return strcasecmp((string) $a['title'], (string) $b['title']);
        });

        return array_slice($results, 0, max(1, min(100, $limit)));
    }

    /**
     * Enlace de ayuda que corresponde a una pantalla real del panel.
     *
     * @return array<string, mixed>|null
     */
    public static function contextual(string $path, bool $includePlatform = false): ?array
    {
        $path = self::withoutBasePath($path);
        if ($path === '' || str_starts_with($path, '/panel/ayuda')) {
            return null;
        }

        $map = [
            '#^/panel$#'                          => 'primeros-pasos/recorrido-panel',
            '#^/panel/pedidos/nuevo$#'            => 'pedidos/pedido-manual',
            '#^/panel/pedidos/\d+$#'              => 'pedidos/estados-y-cobro',
            '#^/panel/pedidos/buscar$#'           => 'pedidos/guia-pedidos',
            '#^/panel/pedidos#'                  => 'pedidos/guia-pedidos',
            '#^/panel/clientes(?:/\d+)?$#'        => 'pedidos/clientes',
            '#^/panel/menu/previa#'               => 'menu/editar-arbol',
            '#^/panel/menu$#'                     => 'menu/estilos-y-publicacion',
            '#^/panel/diseno$#'                   => 'diseno/identidad-visual',
            '#^/panel/banners#'                   => 'diseno/banners',
            '#^/panel/avisos#'                    => 'diseno/avisos',
            '#^/panel/productos#'                 => 'productos/productos-propios',
            '#^/panel/dominios$#'                 => 'dominios/subdominio',
            '#^/panel/ajustes$#'                  => 'configuracion/datos-contacto-iva',
            '#^/panel/logs$#'                     => 'problemas/registro-actividad',
            '#^/panel/plataforma/menu#'            => 'plataforma/menu-plataforma',
        ];

        foreach ($map as $pattern => $articlePath) {
            if (preg_match($pattern, $path) !== 1) {
                continue;
            }
            $article = self::find($articlePath, $includePlatform);
            if ($article === null) {
                return null;
            }

            return [
                'path'  => $articlePath,
                'title' => (string) $article['title'],
                'url'   => self::articleUrl($articlePath),
            ];
        }

        return null;
    }

    public static function homeUrl(): string
    {
        return View::basePath() . '/panel/ayuda';
    }

    public static function categoryUrl(string $category): string
    {
        return self::homeUrl() . '/categoria/' . rawurlencode($category);
    }

    /** URL publica de un articulo a partir de `categoria/slug`. */
    public static function articleUrl(string $path): string
    {
        $parts = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $part): bool => $part !== ''));
        if (count($parts) !== 2) {
            return self::homeUrl();
        }

        return self::homeUrl() . '/' . rawurlencode($parts[0]) . '/' . rawurlencode($parts[1]);
    }

    /** Texto normalizado para comparaciones: minusculas, sin acentos y sin signos. */
    public static function fold(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = mb_strtolower(trim($value), 'UTF-8');
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }

        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** @return array<string, mixed> */
    private static function rawCategories(): array
    {
        if (self::$rawCategories === null) {
            $file = TIENDA_BASE . '/app/Help/categories.php';
            $categories = is_file($file) ? require $file : [];
            if (!is_array($categories)) {
                $categories = [];
            }
            uasort($categories, static fn (array $a, array $b): int => (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0));
            self::$rawCategories = $categories;
        }

        return self::$rawCategories;
    }

    /**
     * Carga y normaliza todos los articulos de los archivos de contenido.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function articleData(): array
    {
        if (self::$articles !== null) {
            return self::$articles;
        }

        $articles = [];
        foreach (self::rawCategories() as $categorySlug => $category) {
            if (($category['type'] ?? 'articles') !== 'articles') {
                continue;
            }

            $file = TIENDA_BASE . '/app/Help/articles/' . $categorySlug . '.php';
            $items = is_file($file) ? require $file : [];
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $slug = trim((string) ($item['slug'] ?? ''));
                $title = trim((string) ($item['title'] ?? ''));
                $body = (string) ($item['body'] ?? '');
                if ($slug === '' || $title === '' || trim($body) === '') {
                    continue;
                }

                $item['slug'] = $slug;
                $item['title'] = $title;
                $item['summary'] = trim((string) ($item['summary'] ?? ''));
                $item['keywords'] = array_values(array_filter(array_map(
                    static fn (mixed $keyword): string => trim((string) $keyword),
                    (array) ($item['keywords'] ?? [])
                )));
                $item['related'] = array_values(array_filter(array_map(
                    static fn (mixed $related): string => trim((string) $related),
                    (array) ($item['related'] ?? [])
                )));
                $item['location'] = trim((string) ($item['location'] ?? ''));
                $item['panel_path'] = trim((string) ($item['panel_path'] ?? ''));
                $item['panel_label'] = trim((string) ($item['panel_label'] ?? ''));
                $item['category'] = $categorySlug;
                $item['category_title'] = (string) ($category['title'] ?? $categorySlug);
                $item['platform_only'] = !empty($category['platform_only']);
                $item['path'] = $categorySlug . '/' . $slug;
                $item['order'] = (int) ($item['order'] ?? ($index + 1));
                $articles[$item['path']] = $item;
            }
        }

        uasort($articles, static function (array $a, array $b): int {
            $categoryOrder = (int) (self::rawCategories()[$a['category']]['order'] ?? 0)
                <=> (int) (self::rawCategories()[$b['category']]['order'] ?? 0);
            if ($categoryOrder !== 0) {
                return $categoryOrder;
            }

            return (int) $a['order'] <=> (int) $b['order'];
        });

        return self::$articles = $articles;
    }

    /** @return array<int, string> */
    private static function tokens(string $folded): array
    {
        $tokens = preg_split('/\s+/u', trim($folded)) ?: [];

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    /** @param array<int, string> $tokens */
    private static function score(string $query, array $tokens, string $title, string $summary, string $keywords, string $body): int
    {
        $titleFold = self::fold($title);
        $summaryFold = self::fold($summary);
        $keywordsFold = self::fold($keywords);
        $bodyFold = self::fold($body);
        $score = 0;

        if ($titleFold === $query) {
            $score += 1200;
        } elseif (str_starts_with($titleFold, $query)) {
            $score += 800;
        } elseif (str_contains($titleFold, $query)) {
            $score += 550;
        }

        if ($keywordsFold !== '' && str_contains($keywordsFold, $query)) {
            $score += 260;
        }
        if ($summaryFold !== '' && str_contains($summaryFold, $query)) {
            $score += 120;
        }
        if ($bodyFold !== '' && str_contains($bodyFold, $query)) {
            $score += 30;
        }

        foreach ($tokens as $token) {
            if (str_contains($titleFold, $token)) {
                $score += 100;
            }
            if (str_contains($keywordsFold, $token)) {
                $score += 45;
            }
            if (str_contains($summaryFold, $token)) {
                $score += 25;
            }
            if (str_contains($bodyFold, $token)) {
                $score += 8;
            }
        }

        return $score;
    }

    private static function excerpt(string $html, int $length): string
    {
        $text = self::plainText($html);
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . '…';
    }

    private static function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) (preg_replace('/\s+/u', ' ', $text) ?? ''));

        return $text;
    }

    private static function withoutBasePath(string $path): string
    {
        $base = View::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        return '/' . trim($path, '/');
    }
}
