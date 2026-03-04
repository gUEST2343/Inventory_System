<?php

namespace App\Enums;

class SizeCategory
{
    // Clothing Sizes
    public const XS = 'XS';
    public const S = 'S';
    public const M = 'M';
    public const L = 'L';
    public const XL = 'XL';
    public const XXL = 'XXL';
    public const XXXL = 'XXXL';
    
    // Shoe Sizes (EU)
    public const SIZE_35 = '35';
    public const SIZE_36 = '36';
    public const SIZE_37 = '37';
    public const SIZE_38 = '38';
    public const SIZE_39 = '39';
    public const SIZE_40 = '40';
    public const SIZE_41 = '41';
    public const SIZE_42 = '42';
    public const SIZE_43 = '43';
    public const SIZE_44 = '44';
    public const SIZE_45 = '45';
    public const SIZE_46 = '46';
    public const SIZE_47 = '47';
    public const SIZE_48 = '48';
    
    public function isClothingSize(string $size): bool
    {
        return in_array($size, [self::XS, self::S, self::M, self::L, self::XL, self::XXL, self::XXXL]);
    }
    
    public function isShoeSize(string $size): bool
    {
        return !$this->isClothingSize($size);
    }
}