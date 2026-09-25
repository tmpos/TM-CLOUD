<?php
declare(strict_types=1);
namespace App\Services;

final class StorefrontPagesService
{
    public const PAGES = [
        'nosotros' => ['title' => 'Sobre nosotros', 'field' => 'page_about', 'description' => 'Conoce nuestro negocio.'],
        'contacto' => ['title' => 'Contacto', 'field' => 'page_contact', 'description' => 'Estamos aquí para ayudarte.'],
        'preguntas-frecuentes' => ['title' => 'Preguntas frecuentes', 'field' => 'page_faq', 'description' => 'Resuelve tus dudas antes de comprar.'],
        'politicas' => ['title' => 'Políticas de la tienda', 'field' => '', 'description' => 'Información para comprar con tranquilidad.'],
        'privacidad' => ['title' => 'Privacidad', 'field' => 'page_privacy', 'description' => 'Información sobre el tratamiento de tus datos.'],
        'terminos' => ['title' => 'Términos y condiciones', 'field' => 'page_terms', 'description' => 'Condiciones aplicables a las compras en nuestra tienda.'],
        'envios' => ['title' => 'Envíos y entregas', 'field' => 'page_shipping', 'description' => 'Opciones y condiciones para recibir tu compra.'],
        'devoluciones' => ['title' => 'Cambios y devoluciones', 'field' => 'page_returns', 'description' => 'Consulta las condiciones para cambios y devoluciones.'],
        'garantias' => ['title' => 'Garantías', 'field' => 'page_warranty', 'description' => 'Información sobre garantía y atención posventa.'],
    ];
    public static function fields(): array
    {
        return [...array_values(array_filter(array_column(self::PAGES, 'field'))), 'contact_hours'];
    }
    public static function decode(array $store): array
    {
        $content = json_decode((string) ($store['page_content'] ?? '{}'), true);
        $content = is_array($content) ? $content : [];
        foreach (self::fields() as $field) $store[$field] = is_string($content[$field] ?? null) ? $content[$field] : '';
        return $store;
    }
    public static function encode(array $input, array $store): string
    {
        $content = [];
        foreach (self::fields() as $field) {
            $value = $input[$field] ?? $store[$field] ?? '';
            if (!is_string($value)) throw new \InvalidArgumentException('El contenido de las páginas debe ser texto.');
            $value = trim($value);
            if (mb_strlen($value) > 16000) throw new \InvalidArgumentException('Cada página admite hasta 16000 caracteres.');
            $content[$field] = $value;
        }
        return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
