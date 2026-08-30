<?php

namespace App\Flexiwind;

class ButtonHelper
{
    protected static array $variants = [
        'solid' => [
            'base' => 'btn-solid',
            'intents' => [
                'primary' => 'btn-solid-primary text-white',
                'secondary' => 'btn-solid-secondary text-white',
                'success' => 'btn-solid-success text-white',
                'destructive' => 'btn-solid-destructive text-white',
                'neutral'=>'btn-solid-neutral text-background',
            ],
        ],
        'soft' => [
            'base' => 'btn-soft',
            'intents' => [
                'primary' => 'btn-soft-primary',
                'success' => 'btn-soft-success',
                'info' => 'btn-soft-info',
                'gray' => 'btn-soft-gray',
            ],
        ],
        'outline' => [
            'base' => 'btn-outline',
            'intents' => [
                'gray' => 'btn-outline-gray',
            ],
        ],
        'ghost' => [
            'base' => 'btn-ghost',
            'intents' => [
                'primary' => 'btn-ghost-primary',
                'destructive' => 'btn-ghost-destructive',
                'success' => 'btn-ghost-success',
                'warning' => 'btn-ghost-warning',
                'gray' => 'btn-ghost-gray',
            ],
        ],
        'none' => [
        ],
    ];

    public static function getVariants()
    {
        return self::$variants;
    }

    public static function normalizeIntent(?string $intent): ?string
    {
        return $intent === 'danger' ? 'destructive' : $intent;
    }
}
