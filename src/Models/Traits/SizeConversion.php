<?php

namespace App\Traits;

trait SizeConversion {
    /**
     * Convert size to different formats
     */
    public function convertSize(string $size, string $targetFormat): string {
        return $size;
    }
}
