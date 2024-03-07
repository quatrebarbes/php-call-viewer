<?php

namespace App;

class Comparison
{

    private const HEAD = 'head';
    private const BASE = 'base';

    public array $identical = [];
    public array $updated   = [];
    public array $created   = [];
    public array $deleted   = [];

    public function __construct(array $head, array $base, string $idProperty, string $compareProperty)
    {
        $toBeComparedLists = [
            self::HEAD => $head,
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
            if (isset($item[self::HEAD])){
                if (isset($item[self::BASE])){
                    if ($item[self::HEAD][$compareProperty] == $item[self::BASE][$compareProperty]){
                        $this->identical[] = $item[self::HEAD];
                    } else {
                        $this->updated[] = $item[self::HEAD];
                    }
                } else {
                    $this->created[] = $item[self::HEAD];
                }
            } elseif (isset($item[self::BASE])){
                $this->deleted[] = $item[self::BASE];
            }
        }

    }

}
