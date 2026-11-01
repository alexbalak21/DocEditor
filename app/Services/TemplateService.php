<?php

namespace App\Services;

/**
 * TemplateService
 *
 * Loads template manifests from templates/{id}/manifest.json.
 * Templates live in the project root /templates/ folder.
 * Adding a new template = add a folder with manifest.json.
 *
 * Manifest keys:
 *   id, version, name, description, icon, color
 *   numbering.prefix
 *   statuses[]
 *   sections.{id}.{label, open}
 *   features.{show_prices, show_totals, show_bank, show_vat, show_quote_ref,
 *              show_po, show_due_date, show_service_date, show_tracking,
 *              show_delivery, show_signature}
 *   defaults.{balance_label, vat_mention, terms}
 */
class TemplateService
{
    private static string $dir = ROOT . '/templates';

    /** Ordered list of template IDs as they appear in the chooser. */
    private static array $order = [
        'invoice',
        'quote',
        'delivery-note',
        'proforma',
        'credit-note',
    ];

    // ── Public API ──────────────────────────────────────────────

    /** All available templates, in display order. */
    public static function all(): array
    {
        $out = [];
        foreach (self::$order as $id) {
            $m = self::load($id);
            if ($m !== null) $out[] = $m;
        }
        // Also pick up any folder not in the hardcoded order
        foreach (glob(self::$dir . '/*/manifest.json') ?: [] as $file) {
            $id = basename(dirname($file));
            if ($id === '_blocks' || in_array($id, self::$order, true)) continue;
            $m = self::load($id);
            if ($m !== null) $out[] = $m;
        }
        return $out;
    }

    /** Single manifest by id, or null if not found. */
    public static function get(string $id): ?array
    {
        return self::load(self::sanitiseId($id));
    }

    /**
     * Resolve a document type string to the matching template id.
     * 'INVOICE' → 'invoice', 'DELIVERY NOTE' → 'delivery-note', etc.
     * Falls back to 'invoice' for unknown types.
     */
    public static function idForType(string $type): string
    {
        $map = [
            'INVOICE'       => 'invoice',
            'QUOTE'         => 'quote',
            'DELIVERY NOTE' => 'delivery-note',
            'PROFORMA'      => 'proforma',
            'CREDIT NOTE'   => 'credit-note',
        ];
        return $map[strtoupper(trim($type))] ?? 'invoice';
    }

    /**
     * Type string for a template id.
     * 'delivery-note' → 'DELIVERY NOTE'
     */
    public static function typeForId(string $id): string
    {
        $map = [
            'invoice'       => 'INVOICE',
            'quote'         => 'QUOTE',
            'delivery-note' => 'DELIVERY NOTE',
            'proforma'      => 'PROFORMA',
            'credit-note'   => 'CREDIT NOTE',
        ];
        return $map[$id] ?? strtoupper(str_replace('-', ' ', $id));
    }

    /**
     * Return the features array for a given type string,
     * with sensible defaults so callers never get null.
     */
    public static function featuresForType(string $type): array
    {
        $id = self::idForType($type);
        $m  = self::load($id);
        return array_merge(self::defaultFeatures(), $m['features'] ?? []);
    }

    /** Defaults object for a new document of the given template. */
    public static function defaultsForType(string $type): array
    {
        $id = self::idForType($type);
        $m  = self::load($id);
        return $m['defaults'] ?? [];
    }

    /** Number prefix for a type. */
    public static function prefixForType(string $type): string
    {
        $id = self::idForType($type);
        $m  = self::load($id);
        return $m['numbering']['prefix'] ?? 'DOC';
    }

    // ── Private ─────────────────────────────────────────────────

    private static array $cache = [];

    private static function load(string $id): ?array
    {
        if (array_key_exists($id, self::$cache)) return self::$cache[$id];

        $file = self::$dir . '/' . $id . '/manifest.json';
        if (!is_file($file)) {
            return self::$cache[$id] = null;
        }
        $raw = file_get_contents($file);
        $m   = json_decode($raw, true);
        if (!is_array($m)) {
            return self::$cache[$id] = null;
        }
        // Ensure features have all keys (merge with defaults)
        $m['features'] = array_merge(self::defaultFeatures(), $m['features'] ?? []);
        return self::$cache[$id] = $m;
    }

    private static function sanitiseId(string $id): string
    {
        // Only allow letters, digits and hyphens — no path traversal
        return preg_replace('/[^a-z0-9\-]/', '', strtolower($id));
    }

    private static function defaultFeatures(): array
    {
        return [
            'show_prices'       => true,
            'show_totals'       => true,
            'show_bank'         => true,
            'show_vat'          => true,
            'show_quote_ref'    => true,
            'show_po'           => true,
            'show_due_date'     => true,
            'show_service_date' => true,
            'show_tracking'     => false,
            'show_delivery'     => false,
            'show_signature'    => false,
        ];
    }
}
