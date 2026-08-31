<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Declarative catalogue of page-section types. Drives BOTH the admin editor
 * (which inputs to render) and the public renderer (which partial to include).
 * Adding a new section type = one entry here + one view partial. Open/closed.
 */
final class SectionRegistry
{
    /**
     * field types: text | textarea | richtext | media | select | repeater
     * @return array<string,array<string,mixed>>
     */
    public static function types(): array
    {
        return [
            'hero' => [
                'label'  => 'Hero',
                'fields' => [
                    'eyebrow'        => ['type' => 'text',     'label' => 'Eyebrow'],
                    'heading'        => ['type' => 'text',     'label' => 'Heading'],
                    'subheading'     => ['type' => 'textarea', 'label' => 'Subheading'],
                    'primary_label'  => ['type' => 'text',     'label' => 'Primary button label'],
                    'primary_url'    => ['type' => 'text',     'label' => 'Primary button URL'],
                    'secondary_label'=> ['type' => 'text',     'label' => 'Secondary button label'],
                    'secondary_url'  => ['type' => 'text',     'label' => 'Secondary button URL'],
                    'image_id'       => ['type' => 'media',    'label' => 'Image'],
                    'align'          => ['type' => 'select',   'label' => 'Alignment', 'options' => ['left', 'center']],
                ],
            ],
            'richtext' => [
                'label'  => 'Rich Text',
                'fields' => [
                    'heading' => ['type' => 'text',     'label' => 'Heading'],
                    'body'    => ['type' => 'richtext', 'label' => 'Body'],
                ],
            ],
            'image_text' => [
                'label'  => 'Image + Text',
                'fields' => [
                    'heading'    => ['type' => 'text',     'label' => 'Heading'],
                    'body'       => ['type' => 'richtext', 'label' => 'Body'],
                    'image_id'   => ['type' => 'media',    'label' => 'Image'],
                    'alt'        => ['type' => 'text',     'label' => 'Image alt text (accessibility / SEO)'],
                    'image_side' => ['type' => 'select',   'label' => 'Image side', 'options' => ['left', 'right']],
                ],
            ],
            'cards' => [
                'label'  => 'Cards',
                'fields' => [
                    'heading'    => ['type' => 'text',     'label' => 'Heading'],
                    'subheading' => ['type' => 'textarea', 'label' => 'Subheading'],
                    'cards'      => ['type' => 'repeater',  'label' => 'Cards', 'subfields' => [
                        'title' => 'Title', 'text' => 'Text', 'url' => 'Link URL',
                    ]],
                ],
            ],
            'cta' => [
                'label'  => 'Call To Action',
                'fields' => [
                    'heading'      => ['type' => 'text',     'label' => 'Heading'],
                    'text'         => ['type' => 'textarea', 'label' => 'Text'],
                    'button_label' => ['type' => 'text',     'label' => 'Button label'],
                    'button_url'   => ['type' => 'text',     'label' => 'Button URL'],
                    'style'        => ['type' => 'select',   'label' => 'Style', 'options' => ['primary', 'dark']],
                ],
            ],
            'faq' => [
                'label'  => 'FAQ',
                'fields' => [
                    'heading' => ['type' => 'text',    'label' => 'Heading'],
                    'items'   => ['type' => 'repeater','label' => 'Questions', 'subfields' => [
                        'question' => 'Question', 'answer' => 'Answer',
                    ]],
                ],
            ],
            'stats' => [
                'label'  => 'Statistics',
                'fields' => [
                    'heading' => ['type' => 'text',    'label' => 'Heading'],
                    'items'   => ['type' => 'repeater','label' => 'Statistics', 'subfields' => [
                        'value' => 'Value', 'label' => 'Label',
                    ]],
                ],
            ],
            'trust' => [
                'label'  => 'Trust Strip',
                'fields' => [
                    'heading' => ['type' => 'text',     'label' => 'Heading (optional)'],
                    'items'   => ['type' => 'repeater', 'label' => 'Items (verified only)', 'subfields' => [
                        'value' => 'Value / short label', 'label' => 'Caption (optional)',
                    ]],
                ],
            ],
            'product_showcase' => [
                'label'  => 'Product Showcase (placeholder)',
                'fields' => [
                    'heading'    => ['type' => 'text',     'label' => 'Heading'],
                    'subheading' => ['type' => 'textarea', 'label' => 'Subheading'],
                    'note'       => ['type' => 'text',     'label' => 'Placeholder note'],
                ],
            ],
            'testimonials' => [
                'label'  => 'Testimonials (placeholder)',
                'fields' => [
                    'heading' => ['type' => 'text',    'label' => 'Heading'],
                    'items'   => ['type' => 'repeater','label' => 'Testimonials', 'subfields' => [
                        'quote' => 'Quote', 'author' => 'Author', 'role' => 'Role',
                    ]],
                ],
            ],
            'contact_cta' => [
                'label'  => 'Contact CTA',
                'fields' => [
                    'heading'      => ['type' => 'text',     'label' => 'Heading'],
                    'text'         => ['type' => 'textarea', 'label' => 'Text'],
                    'button_label' => ['type' => 'text',     'label' => 'Button label'],
                    'button_url'   => ['type' => 'text',     'label' => 'Button URL'],
                ],
            ],
        ];
    }

    public static function exists(string $type): bool
    {
        return array_key_exists($type, self::types());
    }

    /** @return array<int,string> type keys */
    public static function keys(): array
    {
        return array_keys(self::types());
    }

    public static function label(string $type): string
    {
        return self::types()[$type]['label'] ?? ucfirst($type);
    }

    /** @return array<string,array<string,mixed>> */
    public static function fields(string $type): array
    {
        return self::types()[$type]['fields'] ?? [];
    }
}
