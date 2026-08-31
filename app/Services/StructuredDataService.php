<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Builds JSON-LD structured data. Only factual, admin-configured data is used —
 * NO fabricated reviews, ratings, certifications, awards, or medical claims
 * (Product schema arrives with the Product CMS phase).
 */
final class StructuredDataService
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    /** @return array<string,mixed> schema.org Organization */
    public function organization(): array
    {
        $org = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $this->settings->companyName(),
            'url'      => $this->settings->websiteUrl(),
        ];

        $logo = $this->settings->mediaUrl('company_logo');
        if ($logo !== '') {
            $org['logo'] = $this->absolute($logo);
        }

        $desc = $this->settings->get('company_description');
        if ($desc !== '') {
            $org['description'] = $desc;
        }

        // PostalAddress + areaServed for GEO / local presence. Emitted only from
        // owner-supplied settings — never fabricated. All fields are optional.
        $street  = $this->settings->get('company_address');
        $city    = $this->settings->get('company_city');
        $region  = $this->settings->get('company_state');
        $postal  = $this->settings->get('company_postal');
        $country = $this->settings->get('company_country');
        if ($street !== '' || $city !== '' || $region !== '') {
            $addr = ['@type' => 'PostalAddress'];
            if ($street !== '')  { $addr['streetAddress']   = $street; }
            if ($city !== '')    { $addr['addressLocality'] = $city; }
            if ($region !== '')  { $addr['addressRegion']   = $region; }
            if ($postal !== '')  { $addr['postalCode']      = $postal; }
            if ($country !== '') { $addr['addressCountry']  = $country; }
            $org['address'] = $addr;
            if ($country !== '') {
                $org['areaServed'] = $country; // export regions added when owner confirms
            }
        }

        $sameAs = array_values($this->settings->socialLinks());
        if ($sameAs !== []) {
            $org['sameAs'] = $sameAs;
        }

        $email = $this->settings->get('company_email');
        $phone = $this->settings->get('company_phone');
        if ($email !== '' || $phone !== '') {
            $contact = ['@type' => 'ContactPoint', 'contactType' => 'customer service'];
            if ($email !== '') { $contact['email'] = $email; }
            if ($phone !== '') { $contact['telephone'] = $phone; }
            $org['contactPoint'] = $contact;
        }

        return $org;
    }

    /** @return array<string,mixed> schema.org WebSite */
    public function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $this->settings->websiteName(),
            'url'      => $this->settings->websiteUrl(),
        ];
    }

    /**
     * @param array<int,array{name:string,url:string}> $crumbs
     * @return array<string,mixed> schema.org BreadcrumbList
     */
    public function breadcrumbs(array $crumbs): array
    {
        $items = [];
        foreach ($crumbs as $i => $crumb) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $crumb['name'],
                'item'     => $this->absolute($crumb['url']),
            ];
        }
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * schema.org Product — ONLY properties backed by real data (§20). No price,
     * availability, brand claims, ratings or reviews are ever emitted.
     *
     * @param array{name:string,url:string,description?:string,image?:string,sku?:string} $p
     * @return array<string,mixed>
     */
    public function product(array $p): array
    {
        $out = [
            '@context' => 'https://schema.org',
            '@type'    => 'Product',
            'name'     => (string) $p['name'],
            'url'      => $this->absolute((string) $p['url']),
        ];
        if (!empty($p['description'])) {
            $out['description'] = mb_substr((string) $p['description'], 0, 5000);
        }
        if (!empty($p['image'])) {
            $out['image'] = $this->absolute((string) $p['image']);
        }
        if (!empty($p['sku'])) {
            $out['sku'] = (string) $p['sku'];
        }
        return $out;
    }

    /**
     * FAQPage schema (AEO). Emitted only from real, owner-entered Q&A pairs — a
     * question/answer with empty text is skipped; no fabricated FAQs.
     * @param array<int,array<string,mixed>> $items each ['question'=>..,'answer'=>..]
     * @return array<string,mixed>
     */
    public function faq(array $items): array
    {
        $entities = [];
        foreach ($items as $it) {
            if (!is_array($it)) { continue; }
            $q = trim((string) ($it['question'] ?? ''));
            $a = trim(strip_tags((string) ($it['answer'] ?? '')));
            if ($q === '' || $a === '') { continue; }
            $entities[] = [
                '@type' => 'Question',
                'name'  => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ];
        }
        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    public function encode(array $schema): string
    {
        // JSON_HEX_TAG prevents a "</script>" sequence in admin-entered values
        // (product name/description, company name) from breaking out of the
        // inline <script type="application/ld+json"> block.
        return (string) json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }

    private function absolute(string $url): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }
        return $this->settings->websiteUrl() . '/' . ltrim($url, '/');
    }
}
