<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Help;

/**
 * Centro de Ayuda del panel.
 *
 * Todo el contenido es estatico y comun a las tiendas. Las rutas exigen sesion;
 * la categoria de plataforma solo esta disponible para el rol `platform`.
 */
final class HelpController extends Controller
{
    public function index(array $params = []): string
    {
        $this->requireAuth();

        $includePlatform = Auth::isPlatform();
        $query = trim((string) $this->input('q', ''));
        $search = $query !== '' ? Help::search($query, $includePlatform) : [];
        $queryTooShort = $query !== '' && mb_strlen($query, 'UTF-8') < 2;

        return $this->view('panel/help_index', [
            'pageTitle'       => 'Ayuda',
            'categories'      => Help::categories($includePlatform),
            'query'           => $query,
            'queryTooShort'   => $queryTooShort,
            'results'         => $search,
            'didSearch'       => $query !== '',
            'isPlatformUser'  => $includePlatform,
        ], 'panel');
    }

    public function category(array $params = []): string
    {
        $this->requireAuth();

        $includePlatform = Auth::isPlatform();
        $slug = (string) ($params['category'] ?? '');
        $category = Help::category($slug, $includePlatform);
        if ($category === null) {
            return $this->notFound();
        }

        if (($category['type'] ?? 'articles') === 'faq') {
            return $this->view('panel/help_faq', [
                'pageTitle'  => 'Ayuda',
                'documentTitle' => (string) $category['title'] . ' · Ayuda',
                'categories' => Help::categories($includePlatform),
                'category'   => $category,
                'groups'     => Help::faqGroups(),
            ], 'panel');
        }

        return $this->view('panel/help_category', [
            'pageTitle'  => 'Ayuda',
            'documentTitle' => (string) $category['title'] . ' · Ayuda',
            'categories' => Help::categories($includePlatform),
            'category'   => $category,
            'categorySlug' => $slug,
            'articles'   => Help::articlesForCategory($slug, $includePlatform),
        ], 'panel');
    }

    public function article(array $params = []): string
    {
        $this->requireAuth();

        $includePlatform = Auth::isPlatform();
        $categorySlug = (string) ($params['category'] ?? '');
        $slug = (string) ($params['slug'] ?? '');
        $article = Help::article($categorySlug, $slug, $includePlatform);
        if ($article === null) {
            return $this->notFound();
        }

        return $this->view('panel/help_article', [
            'pageTitle'  => 'Ayuda',
            'documentTitle' => (string) $article['title'] . ' · Ayuda',
            'categories' => Help::categories($includePlatform),
            'article'    => $article,
            'related'    => Help::related($article, $includePlatform),
            'category'   => Help::category($categorySlug, $includePlatform),
        ], 'panel');
    }

    /** 404 dentro del panel, sin revelar articulos de plataforma a otras cuentas. */
    private function notFound(): string
    {
        http_response_code(404);

        return $this->view('panel/help_404', [
            'pageTitle'  => 'Ayuda no encontrada',
            'categories' => Help::categories(Auth::isPlatform()),
        ], 'panel');
    }
}
