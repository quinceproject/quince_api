<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Quince\\Api\\';
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }
        $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });
}

/**
 * Quince API — luki w Admin API rdzenia PrestaShop.
 *
 * Jedyne zadanie: oddawać przez Admin API dane, których natywne Admin API
 * i Webservice nie oddają. Dziś to natywne zwroty towaru (RMA) — lista
 * i szczegół z pozycjami (`order_return_detail` + ceny z `order_detail`,
 * czego Webservice nie daje wprost), lista statusów zwrotu, i zmiana
 * statusu przez komendę rdzenia `UpdateOrderReturnStateCommand` (ten sam
 * kod co natywny panel: mail do klienta, haki `actionObjectOrderReturn*`).
 *
 * Moduł nie ma żadnego ekranu w panelu — to świadome. Jedyni odbiorcy to
 * systemy zewnętrzne przez OAuth2; sprzedawca zarządza zwrotami tam, gdzie
 * już to robi (natywny ekran „Merchandise Returns").
 *
 * Stoi obok `quince_withdrawal` i `quince_cmp`, nie w nich — te mają własne
 * dane, ten moduł ma dane RDZENIA, których żaden moduł Quince nie jest
 * właścicielem.
 */
class Quince_Api extends Module
{
    private const DOMAIN_ADMIN = 'Modules.Quinceapi.Admin';

    public function __construct()
    {
        $this->name = 'quince_api';
        $this->tab = 'administration';
        $this->version = '0.1.1';
        $this->author = 'Quince Project';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.2.0', 'max' => '9.99.99'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Quince API', [], self::DOMAIN_ADMIN);
        $this->description = $this->trans(
            'Admin API endpoints for data PrestaShop\'s native API does not expose. Today, merchandise returns with their line items. No settings screen: external systems only, over OAuth2.',
            [],
            self::DOMAIN_ADMIN
        );
    }

    public function install(): bool
    {
        return parent::install();
    }

    public function uninstall(): bool
    {
        return parent::uninstall();
    }
}
