<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Imagor Service
 * 
 * Generates signed URLs for Imagor image processing server
 * Documentation: https://github.com/cshum/imagor
 */
class ImagorService
{
    private string $baseUrl;
    private string $secret;
    private string $signerType;
    private bool $autoWebp;
    private bool $autoAvif;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('imagor.url'), '/');
        $this->secret = config('imagor.secret');
        $this->signerType = config('imagor.signer_type', 'sha1');
        $this->autoWebp = config('imagor.auto_webp', true);
        $this->autoAvif = config('imagor.auto_avif', false);

        if (empty($this->secret)) {
            throw new InvalidArgumentException('Imagor secret is not configured. Set IMAGOR_SECRET in .env');
        }
    }

    /**
     * Generate a signed Imagor URL
     *
     * @param string $imagePath Image path relative to storage (e.g., 'img/beaches/hr-102-o-pag/image.jpg')
     * @param array $options Image transformation options
     * @return string Signed Imagor URL
     */
    public function url(string $imagePath, array $options = []): string
    {
        $path = $this->buildPath($imagePath, $options);
        $signature = $this->sign($path);
        
        return "{$this->baseUrl}/{$signature}/{$path}";
    }

    /**
     * Generate an unsafe Imagor URL (no signature)
     * Only use in development or when IMAGOR_UNSAFE=1
     *
     * @param string $imagePath Image path
     * @param array $options Image transformation options
     * @return string Unsigned Imagor URL
     */
    public function unsafeUrl(string $imagePath, array $options = []): string
    {
        $path = $this->buildPath($imagePath, $options);
        
        return "{$this->baseUrl}/unsafe/{$path}";
    }

    /**
     * Build the Imagor path from options
     *
     * @param string $imagePath Image path
     * @param array $options Transformation options
     * @return string Imagor path
     */
    private function buildPath(string $imagePath, array $options): string
    {
        $parts = [];

        // Meta endpoint
        if ($options['meta'] ?? false) {
            $parts[] = 'meta';
        }

        // Trim
        if (isset($options['trim'])) {
            $trim = is_array($options['trim']) 
                ? 'trim:' . implode(':', $options['trim'])
                : 'trim';
            $parts[] = $trim;
        }

        // Crop
        if (isset($options['crop'])) {
            $crop = $options['crop'];
            $parts[] = "{$crop['left']}x{$crop['top']}:{$crop['right']}x{$crop['bottom']}";
        }

        // Fit-in
        if ($options['fit_in'] ?? false) {
            $parts[] = 'fit-in';
        }

        // Dimensions (width x height)
        if (isset($options['width']) || isset($options['height'])) {
            $width = $options['width'] ?? 0;
            $height = $options['height'] ?? 0;
            $parts[] = "{$width}x{$height}";
        }

        // Horizontal alignment
        if (isset($options['halign'])) {
            $parts[] = $options['halign']; // left, right, center
        }

        // Vertical alignment
        if (isset($options['valign'])) {
            $parts[] = $options['valign']; // top, bottom, middle
        }

        // Smart crop
        if ($options['smart'] ?? false) {
            $parts[] = 'smart';
        }

        // Filters
        if (isset($options['filters']) && is_array($options['filters'])) {
            $filters = $this->buildFilters($options['filters']);
            if (!empty($filters)) {
                $parts[] = 'filters:' . implode(':', $filters);
            }
        }

        // Image path (URL encode special characters)
        $encodedPath = $this->encodeImagePath($imagePath);
        $parts[] = $encodedPath;

        return implode('/', $parts);
    }

    /**
     * Build filters string
     *
     * @param array $filters Array of filters
     * @return array Formatted filters
     */
    private function buildFilters(array $filters): array
    {
        $formatted = [];

        foreach ($filters as $key => $value) {
            if (is_numeric($key)) {
                // Simple filter like 'grayscale'
                $formatted[] = $value;
            } else {
                // Filter with parameters like 'quality' => 80
                if (is_array($value)) {
                    $formatted[] = $key . '(' . implode(',', $value) . ')';
                } else {
                    $formatted[] = $key . '(' . $value . ')';
                }
            }
        }

        return $formatted;
    }

    /**
     * Encode image path for URL
     *
     * @param string $path Image path
     * @return string Encoded path
     */
    private function encodeImagePath(string $path): string
    {
        // Remove leading slash if present
        $path = ltrim($path, '/');
        
        // Split by / and encode each segment
        $segments = explode('/', $path);
        $encoded = array_map(function ($segment) {
            // Only encode if needed (preserve already encoded segments)
            if (preg_match('/[^A-Za-z0-9\-._~]/', $segment)) {
                return rawurlencode($segment);
            }
            return $segment;
        }, $segments);

        return implode('/', $encoded);
    }

    /**
     * Sign the Imagor path
     *
     * @param string $path Imagor path
     * @return string Base64 URL-safe signature
     */
    private function sign(string $path): string
    {
        $hash = hash_hmac($this->signerType, $path, $this->secret, true);
        $signature = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        return $signature;
    }

    /**
     * Preset: Thumbnail (small square)
     *
     * @param string $imagePath Image path
     * @param int $size Thumbnail size (default: 150)
     * @return string Signed URL
     */
    public function thumbnail(string $imagePath, int $size = 150): string
    {
        return $this->url($imagePath, [
            'width' => $size,
            'height' => $size,
            'fit_in' => true,
            'smart' => true,
            'filters' => [
                'quality' => 85,
            ],
        ]);
    }

    /**
     * Preset: Small image
     *
     * @param string $imagePath Image path
     * @return string Signed URL
     */
    public function small(string $imagePath): string
    {
        return $this->url($imagePath, [
            'width' => 400,
            'height' => 300,
            'fit_in' => true,
            'filters' => [
                'quality' => 85,
            ],
        ]);
    }

    /**
     * Preset: Medium image
     *
     * @param string $imagePath Image path
     * @return string Signed URL
     */
    public function medium(string $imagePath): string
    {
        return $this->url($imagePath, [
            'width' => 800,
            'height' => 600,
            'fit_in' => true,
            'filters' => [
                'quality' => 90,
            ],
        ]);
    }

    /**
     * Preset: Large image
     *
     * @param string $imagePath Image path
     * @return string Signed URL
     */
    public function large(string $imagePath): string
    {
        return $this->url($imagePath, [
            'width' => 1200,
            'height' => 900,
            'fit_in' => true,
            'filters' => [
                'quality' => 90,
            ],
        ]);
    }

    /**
     * Preset: Hero/Banner image
     *
     * @param string $imagePath Image path
     * @return string Signed URL
     */
    public function hero(string $imagePath): string
    {
        return $this->url($imagePath, [
            'width' => 1920,
            'height' => 600,
            'smart' => true,
            'filters' => [
                'quality' => 90,
            ],
        ]);
    }

    /**
     * Get image metadata
     *
     * @param string $imagePath Image path
     * @return string Signed URL for metadata endpoint
     */
    public function metadata(string $imagePath): string
    {
        return $this->url($imagePath, [
            'meta' => true,
        ]);
    }
}

