<?php
# classes/Support/InputValidator.php
declare(strict_types=1);

namespace QuantumAstrology\Support;

use DateTimeZone;
use InvalidArgumentException;

/**
 * Shared helpers for normalising user-provided location and timezone inputs.
 */
final class InputValidator
{
    /**
     * Normalise a latitude value provided in decimal degrees with optional N/S suffix.
     *
     * @param mixed $value Raw latitude value from user input.
     * @param bool  $required Whether the field is required. If false, null/empty returns null.
     */
    public static function parseLatitude(mixed $value, bool $required = true): ?float
    {
        return self::parseCoordinate(
            $value,
            -90.0,
            90.0,
            'latitude',
            ['N' => 1, 'S' => -1],
            '34.05N',
            $required
        );
    }

    /**
     * Normalise a longitude value provided in decimal degrees with optional E/W suffix.
     *
     * @param mixed $value Raw longitude value from user input.
     * @param bool  $required Whether the field is required. If false, null/empty returns null.
     */
    public static function parseLongitude(mixed $value, bool $required = true): ?float
    {
        return self::parseCoordinate(
            $value,
            -180.0,
            180.0,
            'longitude',
            ['E' => 1, 'W' => -1],
            '118.25W',
            $required
        );
    }

    /**
     * Validate and normalise a timezone identifier.
     *
     * @param string|null $timezone Raw timezone string.
     * @param bool        $required Whether the field is required. If false, empty values return null.
     */
    public static function normaliseTimezone(?string $timezone, bool $required = true): ?string
    {
        $tz = trim((string) ($timezone ?? ''));
        if ($tz === '') {
            if ($required) {
                throw new InvalidArgumentException(
                    'Birth timezone is required. Please choose a location such as "America/New_York" or "Europe/London".'
                );
            }

            return null;
        }

        try {
            $tzObj = new DateTimeZone($tz);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(sprintf(
                'Unknown timezone "%s". Please use a valid IANA timezone like "America/Los_Angeles" or "Asia/Tokyo".',
                $tz
            ));
        }

        return $tzObj->getName();
    }

    /**
     * @param mixed  $value
     * @param float  $min
     * @param float  $max
     * @param string $label
     * @param array<string,int> $directionMap
     */
    private static function parseCoordinate(
        mixed $value,
        float $min,
        float $max,
        string $label,
        array $directionMap,
        string $directionExample,
        bool $required
    ): ?float {
        if ($value === null) {
            if ($required) {
                throw new InvalidArgumentException(ucfirst($label) . ' is required.');
            }

            return null;
        }

        if (is_float($value) || is_int($value)) {
            $number = (float) $value;
        } else {
            $raw = trim((string) $value);
            if ($raw === '') {
                if ($required) {
                    throw new InvalidArgumentException(ucfirst($label) . ' is required.');
                }

                return null;
            }

            $direction = null;
            if (preg_match('/^([NSEW])\s*(.+)$/i', $raw, $matches)) {
                $direction = strtoupper($matches[1]);
                $raw = trim($matches[2]);
            } elseif (preg_match('/^(.+?)\s*([NSEW])$/i', $raw, $matches)) {
                $raw = trim($matches[1]);
                $direction = strtoupper($matches[2]);
            }

            // Remove common degree symbols before validation.
            $raw = str_replace(['°', 'º'], '', $raw);

            $number = filter_var($raw, FILTER_VALIDATE_FLOAT);
            if ($number === false) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid %s format. Use decimal degrees like "%s" or "-%.2f".',
                    $label,
                    $directionExample,
                    abs($min)
                ));
            }

            if ($direction !== null) {
                if (!isset($directionMap[$direction])) {
                    throw new InvalidArgumentException(sprintf(
                        'Invalid %s direction "%s". Use %s.',
                        $label,
                        $direction,
                        implode('/', array_keys($directionMap))
                    ));
                }

                $number = abs((float) $number) * $directionMap[$direction];
            }
        }

        if ($number < $min || $number > $max) {
            throw new InvalidArgumentException(sprintf(
                'The %s must be between %.0f and %.0f degrees.',
                $label,
                $min,
                $max
            ));
        }

        return round((float) $number, 6);
    }
}
