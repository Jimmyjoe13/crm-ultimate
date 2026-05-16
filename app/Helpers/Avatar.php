<?php

namespace App\Helpers;

class Avatar
{
    public static function initials(string $name): string
    {
        $parts = array_filter(explode(' ', trim($name)));
        if (count($parts) >= 2) {
            return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        }
        return strtoupper(mb_substr($name, 0, 2));
    }

    public static function color(string $name): string
    {
        $hash = 0;
        foreach (str_split($name) as $char) {
            $hash = (($hash << 5) - $hash) + ord($char);
            $hash &= $hash; // 32-bit int
        }
        return 'c' . ((abs($hash) % 5) + 1);
    }
}
