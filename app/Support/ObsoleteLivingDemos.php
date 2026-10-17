<?php

namespace App\Support;

/**
 * Exact identities of short Recognised/Acclaimed living demos retired from the
 * public Demo Profiles set. Production retirement must match both the demo
 * email and the display name before changing status.
 */
final class ObsoleteLivingDemos
{
    /**
     * @return list<array{email: string, full_name: string, slug_hint: string}>
     */
    public static function targets(): array
    {
        return [
            ['email' => 'demo.fr.joseph.mathew@jannayaks.internal', 'full_name' => 'Fr. Joseph Mathew', 'slug_hint' => 'fr.joseph.mathew'],
            ['email' => 'demo.p.rajeev.menon@jannayaks.internal', 'full_name' => 'P. Rajeev Menon', 'slug_hint' => 'p.rajeev.menon'],
            ['email' => 'demo.s.beena.kumari@jannayaks.internal', 'full_name' => 'S. Beena Kumari', 'slug_hint' => 's.beena.kumari'],
            ['email' => 'demo.k.shafiq.rahman@jannayaks.internal', 'full_name' => 'K. Shafiq Rahman', 'slug_hint' => 'k.shafiq.rahman'],
            ['email' => 'demo.r.leelamma@jannayaks.internal', 'full_name' => 'R. Leelamma', 'slug_hint' => 'r.leelamma'],
            ['email' => 'demo.c.manoj.kumar@jannayaks.internal', 'full_name' => 'C. Manoj Kumar', 'slug_hint' => 'c.manoj.kumar'],
            ['email' => 'demo.a.mariamma@jannayaks.internal', 'full_name' => 'A. Mariamma', 'slug_hint' => 'a.mariamma'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function emails(): array
    {
        return array_column(self::targets(), 'email');
    }

    /**
     * @return list<string>
     */
    public static function fullNames(): array
    {
        return array_column(self::targets(), 'full_name');
    }
}
