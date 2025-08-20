<?php
function fmt_duration(int $seconds): string {
    $seconds = max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

/**
 * Normalizes a phone number to international format.
 *
 * Accepts numbers such as "+41791234567", "0041791234567" or "0791234567"
 * and converts them to the uniform "+41791234567" style. Any non-digit
 * characters are stripped. If the number cannot be interpreted, the string
 * "unknown" is returned.
 */
function normalizePhoneNumber(?string $number): string {
    $number = trim((string)$number);
    if ($number === '') {
        return 'unknown';
    }

    // Remove spaces, dashes, parentheses, etc., but keep leading '+'
    $number = preg_replace('/[^\d+]/', '', $number);
    if ($number === '' || $number === '+') {
        return 'unknown';
    }

    if (str_starts_with($number, '+')) {
        $number = substr($number, 1);
    } elseif (str_starts_with($number, '00')) {
        $number = substr($number, 2);
    } elseif (str_starts_with($number, '0')) {
        // Assume Swiss local number without country code
        $number = '41' . substr($number, 1);
    }

    if ($number === '' || !ctype_digit($number)) {
        return 'unknown';
    }

    return '+' . $number;
}
