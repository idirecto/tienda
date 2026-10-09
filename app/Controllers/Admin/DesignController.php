<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Appearance;
use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Favicon;
use Tienda\Core\Logger;
use Tienda\Core\Session;
use Tienda\Core\Storage\StorageManager;
use Tienda\Core\Tenant;
use Tienda\Models\Media;
use Tienda\Models\Store;
use Tienda\Models\Theme;

/**
 * Diseno de la tienda: plantilla, identidad visual (tokens) y textos.
 *
 * Los colores y la forma no se guardan "sueltos": se guardan como los tokens
 * que `Tienda\Core\Appearance` convierte en variables CSS. La vista previa que
 * ve el tendero en el panel usa exactamente el mismo generador, asi que lo que
 * ve es lo que se publicara.
 */
final class DesignController extends Controller
{
    /** Campos de color que pueden quedar vacios (heredan el valor del tema). */
    private const OPTIONAL_COLORS = ['color_accent', 'color_bg', 'color_surface', 'color_text', 'color_border'];

    /** Limite del CSS propio (evita que un pegado enorme rompa la tienda). */
    private const MAX_CUSTOM_CSS = 20000;

    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->requireStoreId();

        $store = Store::findWithPlan($storeId) ?? [];

        return $this->view('panel/design', [
            'pageTitle' => 'Diseno',
            'store'     => $store,
            'themes'    => Theme::active(),
            'presets'   => Appearance::presets(),
            'schemes'   => Appearance::schemes(),
            'radii'     => Appearance::radiusScales(),
            'fonts'     => Appearance::fonts(),
            // Favicon: el de ESTA tienda si lo tiene (y su fichero sigue ahi);
            // si no, el predeterminado de Valduran. El predeterminado es de la
            // plataforma y aqui solo se muestra como referencia.
            'favicon'        => Favicon::resolve(new Tenant($store)),
            'faviconDefault' => Favicon::defaultHref(),
        ], 'panel');
    }

    public function save(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->requireStoreId();

        $themes = array_column(Theme::active(), 'id');
        $theme = (string) $this->input('theme', 'idirecto');
        if (!in_array($theme, $themes, true)) {
            $theme = 'idirecto';
        }

        $font = (string) $this->input('font', 'system');
        if (!array_key_exists($font, Appearance::fonts())) {
            $font = 'system';
        }

        $scheme = (string) $this->input('color_scheme', 'light');
        if (!array_key_exists($scheme, Appearance::schemes())) {
            $scheme = 'light';
        }

        $radius = (string) $this->input('radius_scale', 'standard');
        if (!array_key_exists($radius, Appearance::radiusScales())) {
            $radius = 'standard';
        }

        $data = [
            'name'             => (string) $this->input('name', 'Mi tienda'),
            'tagline'          => (string) $this->input('tagline', ''),
            'about'            => (string) $this->input('about', ''),
            'theme'            => $theme,
            'color_primary'    => $this->color((string) $this->input('color_primary', '')) ?? '#e30613',
            'color_secondary'  => $this->color((string) $this->input('color_secondary', '')) ?? '#1f2937',
            'color_scheme'     => $scheme,
            'radius_scale'     => $radius,
            'font'             => $font,
            'header_style'     => (string) $this->input('header_style', 'classic'),
            'phone'            => (string) $this->input('phone', ''),
            'whatsapp'         => (string) $this->input('whatsapp', ''),
            'email'            => (string) $this->input('email', ''),
            'address'          => (string) $this->input('address', ''),
            'city'             => (string) $this->input('city', ''),
            'province'         => (string) $this->input('province', ''),
            'postal_code'      => (string) $this->input('postal_code', ''),
            'meta_title'       => (string) $this->input('meta_title', ''),
            'meta_description' => (string) $this->input('meta_description', ''),
            'show_prices'      => $this->input('show_prices') ? 1 : 0,
            'allow_orders'     => $this->input('allow_orders') ? 1 : 0,
        ];

        // Colores avanzados: cadena vacia = "el que traiga el tema".
        foreach (self::OPTIONAL_COLORS as $key) {
            $raw = (string) $this->input($key, '');
            $data[$key] = $raw === '' ? null : $this->color($raw);
        }

        // Identidad completa en un clic: el preset pisa los colores y la forma.
        $preset = Appearance::preset((string) ($_POST['apply_preset'] ?? ''));
        if ($preset !== null) {
            $data['color_primary'] = $this->color((string) ($preset['primary'] ?? '')) ?? $data['color_primary'];
            $data['color_secondary'] = $this->color((string) ($preset['secondary'] ?? '')) ?? $data['color_secondary'];
            $data['color_accent'] = $this->color((string) ($preset['accent'] ?? ''));
            foreach (['color_bg', 'color_surface', 'color_text', 'color_border'] as $key) {
                $data[$key] = null;
            }
            if (array_key_exists((string) ($preset['scheme'] ?? ''), Appearance::schemes())) {
                $data['color_scheme'] = (string) $preset['scheme'];
            }
            if (array_key_exists((string) ($preset['radius'] ?? ''), Appearance::radiusScales())) {
                $data['radius_scale'] = (string) $preset['radius'];
            }
            if (array_key_exists((string) ($preset['font'] ?? ''), Appearance::fonts())) {
                $data['font'] = (string) $preset['font'];
            }
        }

        // Tokens libres (JSON): permiten pisar cualquier variable del sistema.
        $rawTokens = trim((string) $this->input('theme_tokens', ''));
        if ($rawTokens === '') {
            $data['theme_tokens'] = null;
        } else {
            $decoded = json_decode($rawTokens, true);
            if (!is_array($decoded)) {
                Session::flash('error', 'Los "tokens personalizados" no son un JSON valido: se ha guardado el resto sin ellos.');
                $data['theme_tokens'] = null;
            } else {
                $data['theme_tokens'] = (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        // CSS propio: se limpia lo que podria cerrar la etiqueta <style>.
        $customCss = (string) $this->input('custom_css', '');
        $data['custom_css'] = $customCss === '' ? null : $this->cleanCss($customCss);

        // Logo (la URL llega desde el uploader AJAX)
        $logoUrl = (string) $this->input('logo_url', '');
        $logoKey = (string) $this->input('logo_key', '');
        if ($logoUrl !== '') {
            $data['logo_url'] = $logoUrl;
            $data['logo_key'] = $logoKey !== '' ? $logoKey : null;
        }

        // Favicon propio. La tienda solo puede cambiar EL SUYO: si lo quita, la
        // web vuelve al predeterminado de Valduran (config/brand.php), que nunca
        // se toca desde aqui.
        foreach ($this->faviconData($storeId) as $columna => $valor) {
            $data[$columna] = $valor;
        }

        Store::updateById($storeId, $data);

        Session::flash('success', $preset !== null
            ? 'Identidad aplicada con el preset "' . (string) ($preset['label'] ?? '') . '".'
            : 'Diseno actualizado correctamente.');

        $this->redirect('panel/diseno');
    }

    /**
     * Cambios del favicon propio a guardar en `mt_stores`.
     *
     *   - Sin favicon (el tendero pulsa «volver al predeterminado», o el campo
     *     llega vacio): se borra el propio y la tienda usa el de Valduran.
     *   - Con favicon distinto del guardado: se guarda, se **borra el fichero
     *     anterior** y se sube `favicon_version` (sello de tiempo) para que el
     *     navegador no sirva el icono viejo de su cache.
     *   - Sin cambios: no se toca nada (no se invalida la cache sin motivo).
     *
     * @return array<string, mixed>
     */
    private function faviconData(int $storeId): array
    {
        $actual = Store::findWithPlan($storeId) ?? [];
        $keyActual = trim((string) ($actual['favicon_key'] ?? ''));
        $urlActual = trim((string) ($actual['favicon_url'] ?? ''));
        $versionActual = (int) ($actual['favicon_version'] ?? 0);

        $reset = (string) $this->input('favicon_action', '') === 'reset';
        $url = $reset ? '' : trim((string) $this->input('favicon_url', ''));
        $key = $reset ? '' : trim((string) $this->input('favicon_key', ''));

        if ($url === '') {
            if ($keyActual !== '') {
                $this->deleteFaviconFile($keyActual, $storeId);
            }

            return ['favicon_url' => null, 'favicon_key' => null, 'favicon_version' => null];
        }

        $segura = Favicon::safeStoreUrl($url);
        if ($segura === null) {
            Session::flash('warning', 'La direccion del favicon no es valida: se ha conservado el anterior.');

            return [];
        }

        if ($segura === $urlActual && $key === $keyActual) {
            return [];
        }

        // Favicon nuevo: fuera el anterior y version nueva. La version es
        // monotona (nunca repite la anterior) para que dos cambios seguidos
        // dentro del mismo segundo no dejen la misma URL en la cache.
        if ($keyActual !== '' && $keyActual !== $key) {
            $this->deleteFaviconFile($keyActual, $storeId);
        }

        return [
            'favicon_url'     => $segura,
            'favicon_key'     => $key !== '' ? $key : null,
            'favicon_version' => max(time(), $versionActual + 1),
        ];
    }

    /**
     * Borra el fichero de un favicon propio (y su fila de `mt_media`).
     *
     * Nunca puede tocar el favicon predeterminado de la plataforma: solo se
     * llama con claves guardadas en `mt_stores.favicon_key` de esta tienda. Si
     * el borrado fisico falla, no se rompe el guardado: la tienda ya apunta al
     * predeterminado y el fichero huerfano no molesta.
     */
    private function deleteFaviconFile(string $key, int $storeId): void
    {
        try {
            $media = Media::findByKeyForStore($key, $storeId);
            if ($media !== null) {
                Media::deleteById((int) $media['id']);
            }
            StorageManager::driver()->delete($key);
        } catch (\Throwable $e) {
            Logger::warning('panel', 'No se ha podido borrar el favicon anterior de la tienda.', [
                'store_id' => $storeId,
                'key'      => $key,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Vista previa de la identidad visual (se carga dentro de un <iframe> del
     * panel). Es una pagina autonoma con la hoja del storefront y los tokens
     * que genera la configuracion guardada.
     */
    public function preview(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->requireStoreId();

        $tenant = new Tenant(Store::findWithPlan($storeId) ?? []);

        return $this->view('panel/design_preview', [
            'tenant'    => $tenant,
            'tokensCss' => Appearance::css($tenant),
            'scheme'    => Appearance::scheme($tenant),
        ], null);
    }

    /**
     * Vista previa en vivo: devuelve el CSS de tokens que generaria la
     * configuracion que se esta editando (sin guardarla).
     *
     * Usa el MISMO generador que el storefront (`Appearance::css`), de modo que
     * la previa no puede desviarse de lo que se publica.
     */
    public function tokens(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->requireStoreId();

        $store = Store::findWithPlan($storeId) ?? [];

        foreach (['color_primary', 'color_secondary', 'color_accent', 'color_bg', 'color_surface', 'color_text', 'color_border'] as $key) {
            $value = $this->color((string) $this->input($key, ''));
            $store[$key] = $value ?? '';
        }
        foreach (['font', 'header_style'] as $key) {
            $store[$key] = (string) $this->input($key, (string) ($store[$key] ?? ''));
        }

        $scheme = (string) $this->input('color_scheme', (string) ($store['color_scheme'] ?? 'light'));
        $store['color_scheme'] = array_key_exists($scheme, Appearance::schemes()) ? $scheme : 'light';

        $radius = (string) $this->input('radius_scale', (string) ($store['radius_scale'] ?? 'standard'));
        $store['radius_scale'] = array_key_exists($radius, Appearance::radiusScales()) ? $radius : 'standard';

        $rawTokens = trim((string) $this->input('theme_tokens', ''));
        $decoded = $rawTokens === '' ? null : json_decode($rawTokens, true);
        $store['theme_tokens'] = is_array($decoded) ? (string) json_encode($decoded) : '';

        $tenant = new Tenant($store);

        $this->json([
            'css'        => Appearance::css($tenant),
            'scheme'     => Appearance::scheme($tenant),
            'themeColor' => Appearance::themeColor($tenant),
        ]);
    }

    /** Valida un color hexadecimal. Devuelve null si no lo es. */
    private function color(string $value): ?string
    {
        return Appearance::hex($value);
    }

    /** Limpia el CSS propio de secuencias que romperian la etiqueta <style>. */
    private function cleanCss(string $css): string
    {
        $css = str_ireplace(['</style', '<script', '</script', '<?'], '', $css);
        $css = mb_substr($css, 0, self::MAX_CUSTOM_CSS);
        return trim($css);
    }

    private function requireStoreId(): int
    {
        $id = Auth::storeId();
        if ($id === null || $id <= 0) {
            $this->redirect('panel/logout');
        }
        return $id;
    }
}
