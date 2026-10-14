<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Http;
use Flight;

trait StorefrontCheckoutCustomer
{
    private function checkoutCustomer(string $slug): void
    {
        header('Cache-Control: private, no-store');
        try {
            $input = Http::input();
            Csrf::verify($input['_csrf'] ?? null);
            $store = $this->storefronts->findBySlug($slug);
            $action = (string) ($input['action'] ?? '');
            if (!in_array($action, ['register', 'lookup', 'verify', 'resend'], true)) {
                throw new \InvalidArgumentException('Selecciona una opción de acceso válida.');
            }
            $this->keys->rateLimitPublic('checkout-customer:' . hash('sha256', $store['uid']), 30, 900);
            $customer = $this->checkoutCustomerForStore($store);
            if ($customer !== null) {
                $this->checkoutCustomerResponse($customer);
                return;
            }
            if ($action === 'verify') {
                $customer = $this->pendingCustomerForStore($store);
                if ($customer === null) throw new \InvalidArgumentException('Busca tu cuenta o regístrate primero.');
                $this->keys->rateLimitPublic('storefront-customer-otp:' . hash('sha256', $store['uid'] . '|' . $customer['uid']), 10, 900);
                $this->storefronts->verifyCustomerOtp($store, $customer, (string) ($input['otp'] ?? ''));
                session_regenerate_id(true);
                unset($_SESSION['storefront_customer_pending'][$store['uid']]);
                $this->startCustomerSession($store, $customer);
                $this->checkoutCustomerResponse($customer);
                return;
            }
            if (!(bool) ($this->mail->settings()['enabled'] ?? false)) {
                throw new \InvalidArgumentException('La tienda debe activar el correo para verificar tu cuenta. Puedes contactar al negocio por WhatsApp.');
            }
            if ($action === 'register') {
                $this->keys->rateLimitPublic('storefront-customer-register:' . hash('sha256', $store['uid']), 12, 900);
                if (trim((string) ($input['telefono'] ?? '')) === '') throw new \InvalidArgumentException('Escribe tu teléfono.');
                $customer = $this->storefronts->registerCustomer($store, $input);
            } elseif ($action === 'lookup') {
                $document = trim((string) ($input['document'] ?? ''));
                $this->keys->rateLimitPublic('checkout-customer-lookup:' . hash('sha256', $store['uid'] . '|' . preg_replace('/[^a-z0-9]/i', '', $document)), 6, 900);
                $customer = $this->storefronts->locateCustomerByDocument($store, $document)['customer'];
            } else {
                $customer = $this->pendingCustomerForStore($store);
                if ($customer === null) throw new \InvalidArgumentException('Busca tu cuenta o regístrate primero.');
            }
            // A document locates an account; only the emailed code authenticates it.
            $this->keys->rateLimitPublic('storefront-customer-otp-resend:' . hash('sha256', $store['uid'] . '|' . $customer['uid']), 3, 900);
            session_regenerate_id(true);
            $this->startPendingCustomer($store, $customer);
            $this->sendCustomerOtp($store, $customer);
            Flight::json(['data' => ['verified' => false, 'pending' => true, 'message' => 'Enviamos un código al correo registrado. Escríbelo aquí para continuar.']]);
        } catch (\Throwable $error) {
            $status = (int) $error->getCode() === 429 ? 429 : 422;
            Http::error($error, $status, null, ['pending' => isset($store) && $this->pendingCustomerForStore($store) !== null]);
        }
    }

    private function checkoutCustomerResponse(array $customer): void
    {
        Flight::json(['data' => ['verified' => true, 'customer' => [
            'name' => $this->customerField($customer, ['nombre', 'nombre_completo', 'name']),
            'phone' => $this->customerField($customer, ['telefono', 'celular', 'whatsapp', 'phone']),
            'email' => $this->storefronts->customerEmail($customer),
            'document' => $this->customerField($customer, ['cedula', 'cedula_rnc', 'rnc', 'documento', 'identificacion', 'tax_id']),
            'address' => $this->customerField($customer, ['direccion', 'domicilio', 'address']),
        ]]]);
    }
}
