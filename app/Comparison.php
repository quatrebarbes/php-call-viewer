<?php

namespace App;

class Comparison
{

    private const CURRENT = 'current';
    private const BASE = 'base';

    public array $identical = [];
    public array $updated   = [];
    public array $created   = [];
    public array $deleted   = [];

    public function __construct(array $current, array $base, string $idProperty, string $compareProperty)
    {
        $toBeComparedLists = [
            self::CURRENT => $current,
            self::BASE => $base,
        ];

        $mergedById = [];
        foreach ($toBeComparedLists as $src => $list) {
            foreach ($list as $item) {
                $mergedById[$item[$idProperty]] = $mergedById[$item[$idProperty]] ?? [];
                $mergedById[$item[$idProperty]][$src] = $item;
            }
        }

        foreach ($mergedById as $item) {
            if (isset($item[self::CURRENT])){
                if (isset($item[self::BASE])){
                    if ($item[self::CURRENT][$compareProperty] == $item[self::BASE][$compareProperty]){
                        $this->identical[] = $item[self::CURRENT];
                    } else {
                        $this->updated[] = $item[self::CURRENT];
                    }
                } else {
                    $this->created[] = $item[self::CURRENT];
                }
            } elseif (isset($item[self::BASE])){
                $this->deleted[] = $item[self::BASE];
            }
        }

    }

}
