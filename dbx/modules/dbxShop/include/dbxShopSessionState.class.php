<?php
declare(strict_types=1);

namespace dbx\dbxShop;

/** Einziger Besitzer des requestübergreifenden Shop-Sessionzustands. */
final class dbxShopSessionState
{
    private const SECTION = 'state';
    private const MODULE = 'dbxShop';
    private const CART = 'cart';
    private const CHECKOUT_REQUESTS = 'checkout_requests';
    private const LAST_ORDER = 'last_order_no';

    public static function ensure(): void
    {
        $cart = dbx()->get_session_var(self::CART, array(), self::SECTION, self::MODULE);
        if (!is_array($cart)) {
            dbx()->set_session_var(self::CART, array(), self::SECTION, self::MODULE);
        }
    }

    public static function cart(): array
    {
        self::ensure();
        return (array)dbx()->get_session_var(self::CART, array(), self::SECTION, self::MODULE);
    }

    public static function clear_cart(): void
    {
        self::ensure();
        dbx()->set_session_var(self::CART, array(), self::SECTION, self::MODULE);
    }

    public static function add_quantity(string $sku, int $quantity): void
    {
        self::ensure();
        $cart = self::cart();
        $cart[$sku] = max(0, (int)($cart[$sku] ?? 0)) + $quantity;
        dbx()->set_session_var(self::CART, $cart, self::SECTION, self::MODULE);
    }

    public static function has_sku(string $sku): bool
    {
        self::ensure();
        return isset(self::cart()[$sku]);
    }

    public static function set_quantity(string $sku, int $quantity): void
    {
        self::ensure();
        $cart = self::cart();
        $cart[$sku] = $quantity;
        dbx()->set_session_var(self::CART, $cart, self::SECTION, self::MODULE);
    }

    public static function remove_sku(string $sku): void
    {
        self::ensure();
        $cart = self::cart();
        unset($cart[$sku]);
        dbx()->set_session_var(self::CART, $cart, self::SECTION, self::MODULE);
    }

    public static function checkout_order_no(string $request_id): string
    {
        self::ensure();
        $requests = dbx()->get_session_var(self::CHECKOUT_REQUESTS, array(), self::SECTION, self::MODULE);
        return is_array($requests) ? (string)($requests[$request_id] ?? '') : '';
    }

    public static function remember_checkout(string $request_id, string $order_no): void
    {
        self::ensure();
        $requests = dbx()->get_session_var(self::CHECKOUT_REQUESTS, array(), self::SECTION, self::MODULE);
        $requests = is_array($requests) ? $requests : array();
        $requests[$request_id] = $order_no;
        dbx()->set_session_var(self::CHECKOUT_REQUESTS, array_slice($requests, -25, null, true), self::SECTION, self::MODULE);
    }

    public static function last_order_no(): string
    {
        self::ensure();
        return (string)dbx()->get_session_var(self::LAST_ORDER, '', self::SECTION, self::MODULE);
    }

    public static function set_last_order_no(string $order_no): void
    {
        self::ensure();
        dbx()->set_session_var(self::LAST_ORDER, $order_no, self::SECTION, self::MODULE);
    }
}
