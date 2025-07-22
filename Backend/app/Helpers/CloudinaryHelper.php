<?php

namespace App\Helpers;

class CloudinaryHelper
{
    public static function getPublicIdFromUrl($url)
    {
        $parts = parse_url($url);
        $path = pathinfo($parts['path']);
        $pathParts = explode('/', $path['dirname']);
        $publicId = end($pathParts) . '/' . $path['filename'];
        return $publicId;
    }
}