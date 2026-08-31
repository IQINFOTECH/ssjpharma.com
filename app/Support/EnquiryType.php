<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Maps a public form key → (enquiry type, lead source). This is resolved
 * SERVER-SIDE from which form/page submitted, never trusted from client input
 * (§5). UTM parameters are preserved separately as attribution.
 */
final class EnquiryType
{
    /** form_key => [enquiry_type, source_key, label] */
    private const MAP = [
        'contact-us'           => ['general',      'contact_form',        'General Enquiry'],
        'contact'              => ['general',      'contact_form',        'General Enquiry'],
        'product-enquiry'      => ['product',      'product_enquiry',     'Product Enquiry'],
        'become-a-distributor' => ['distributor',  'distributor_enquiry', 'Distributor Enquiry'],
        'distributor'          => ['distributor',  'distributor_enquiry', 'Distributor Enquiry'],
        'partnership'          => ['partnership',  'partnership_enquiry', 'Partnership Enquiry'],
    ];

    /** @return array{type:string,source:string,label:string} */
    public static function resolve(string $formKey): array
    {
        $m = self::MAP[$formKey] ?? ['general', 'contact_form', 'General Enquiry'];
        return ['type' => $m[0], 'source' => $m[1], 'label' => $m[2]];
    }

    public static function label(string $type): string
    {
        foreach (self::MAP as $m) {
            if ($m[0] === $type) {
                return $m[2];
            }
        }
        return ucfirst($type) . ' Enquiry';
    }

    /** @return array<int,string> valid enquiry types */
    public static function all(): array
    {
        return ['general', 'product', 'distributor', 'partnership', 'other'];
    }
}
