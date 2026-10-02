<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Contexto de la tienda resuelta para la peticion actual.
 * Sustituye al uso de sesion/cookies para la identidad del tenant.
 */
final class Tenant
{
    private array $store;

    public function __construct(array $store)
    {
        $this->store = $store;
    }

    public function id(): int
    {
        return (int) ($this->store['id'] ?? 0);
    }

    public function slug(): string
    {
        return (string) ($this->store['slug'] ?? '');
    }

    public function name(): string
    {
        return (string) ($this->store['name'] ?? 'Tienda');
    }

    public function legalName(): string
    {
        return (string) ($this->store['legal_name'] ?? '');
    }

    public function email(): string
    {
        return (string) ($this->store['email'] ?? '');
    }

    public function phone(): string
    {
        return (string) ($this->store['phone'] ?? '');
    }

    public function whatsapp(): string
    {
        return (string) ($this->store['whatsapp'] ?? '');
    }

    public function status(): int
    {
        return (int) ($this->store['status'] ?? 0);
    }

    public function isActive(): bool
    {
        return $this->status() === 1;
    }

    // ----- Diseno -------------------------------------------------------

    public function theme(): string
    {
        return (string) ($this->store['theme'] ?? 'idirecto');
    }

    public function colorPrimary(): string
    {
        return (string) ($this->store['color_primary'] ?? '#e30613');
    }

    public function colorSecondary(): string
    {
        return (string) ($this->store['color_secondary'] ?? '#1f2937');
    }

    /** Color de acento (detalles, focos, enlaces secundarios). */
    public function colorAccent(): string
    {
        return (string) ($this->store['color_accent'] ?? '');
    }

    /** Fondo de la pagina. Cadena vacia = usar el valor por defecto del tema. */
    public function colorBackground(): string
    {
        return (string) ($this->store['color_bg'] ?? '');
    }

    /** Fondo de las tarjetas y bloques. */
    public function colorSurface(): string
    {
        return (string) ($this->store['color_surface'] ?? '');
    }

    /** Color del texto principal. */
    public function colorText(): string
    {
        return (string) ($this->store['color_text'] ?? '');
    }

    /** Color de las lineas y bordes. */
    public function colorBorder(): string
    {
        return (string) ($this->store['color_border'] ?? '');
    }

    /** Esquema de color: light | dark | auto. */
    public function colorScheme(): string
    {
        return (string) ($this->store['color_scheme'] ?? 'light');
    }

    /** Escala de radios: compact | standard | rounded. */
    public function radiusScale(): string
    {
        return (string) ($this->store['radius_scale'] ?? 'standard');
    }

    /** Tokens de diseno libres (JSON) que pisan al sistema. */
    public function themeTokens(): string
    {
        return (string) ($this->store['theme_tokens'] ?? '');
    }

    /** CSS propio de la tienda (avanzado, se inyecta al final del <head>). */
    public function customCss(): string
    {
        return (string) ($this->store['custom_css'] ?? '');
    }

    public function font(): string
    {
        return (string) ($this->store['font'] ?? 'system');
    }

    public function headerStyle(): string
    {
        return (string) ($this->store['header_style'] ?? 'classic');
    }

    /**
     * Estilo de menu elegido por la tienda: `compacto` o `catalogo`.
     *
     * Es un ajuste distinto del estilo de cabecera (`headerStyle`) y no se
     * confunde con el: aqui solo hay dos valores posibles.
     */
    public function menuStyle(): string
    {
        return \Tienda\Models\Menu::style($this->store);
    }

    public function logoUrl(): ?string
    {
        $logo = (string) ($this->store['logo_url'] ?? '');
        return $logo !== '' ? $logo : null;
    }

    public function tagline(): string
    {
        return (string) ($this->store['tagline'] ?? '');
    }

    // ----- Contenido / contacto ----------------------------------------

    public function about(): string
    {
        return (string) ($this->store['about'] ?? '');
    }

    public function address(): string
    {
        return (string) ($this->store['address'] ?? '');
    }

    public function city(): string
    {
        return (string) ($this->store['city'] ?? '');
    }

    public function province(): string
    {
        return (string) ($this->store['province'] ?? '');
    }

    public function postalCode(): string
    {
        return (string) ($this->store['postal_code'] ?? '');
    }

    public function metaTitle(): string
    {
        return (string) ($this->store['meta_title'] ?? $this->name());
    }

    public function metaDescription(): string
    {
        return (string) ($this->store['meta_description'] ?? $this->tagline());
    }

    public function showPrices(): bool
    {
        return (int) ($this->store['show_prices'] ?? 1) === 1;
    }

    public function allowOrders(): bool
    {
        return (int) ($this->store['allow_orders'] ?? 1) === 1;
    }

    // ----- Plan ---------------------------------------------------------

    public function planCode(): string
    {
        return (string) ($this->store['plan_code'] ?? '');
    }

    public function planName(): string
    {
        return (string) ($this->store['plan_name'] ?? 'Sin plan');
    }

    /** -1 = ilimitado, 0 = no permitido, N = limite */
    public function ownProductsQuota(): int
    {
        return (int) ($this->store['own_products_quota'] ?? 0);
    }

    public function maxBanners(): int
    {
        return (int) ($this->store['max_banners'] ?? 3);
    }

    public function maxNotices(): int
    {
        return (int) ($this->store['max_notices'] ?? 5);
    }

    public function allowCustomDomain(): bool
    {
        return (int) ($this->store['allow_custom_domain'] ?? 0) === 1;
    }

    public function quotaIsUnlimited(): bool
    {
        return $this->ownProductsQuota() === -1;
    }

    public function canAddOwnProduct(int $current): bool
    {
        if ($this->quotaIsUnlimited()) {
            return true;
        }
        if ($this->ownProductsQuota() <= 0) {
            return false;
        }
        return $current < $this->ownProductsQuota();
    }

    // ----- Utilidades ---------------------------------------------------

    /** URL publica de la tienda (subdominio o dominio propio). */
    public function publicUrl(): string
    {
        if (!empty($this->store['primary_domain'])) {
            return 'https://' . $this->store['primary_domain'];
        }
        $base = Config::get('tenant.base_domains', ['localhost'])[0] ?? 'localhost';
        return 'https://' . $this->slug() . '.' . $base;
    }

    public function toArray(): array
    {
        return $this->store;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }
}
