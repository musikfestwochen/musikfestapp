<?php

use App\Casts\BinaryHexCast;

covers(BinaryHexCast::class);

it('converts binary data to hex string in get method', function () {
    $cast = new BinaryHexCast;
    $binaryData = pack('H*', 'deadbeef');

    $result = $cast->get(null, 'test_key', $binaryData, []);

    expect($result)->toBe('deadbeef');
});

it('returns null when value is null in get method', function () {
    $cast = new BinaryHexCast;

    $result = $cast->get(null, 'test_key', null, []);

    expect($result)->toBeNull();
});

it('converts hex string to binary data in set method', function () {
    $cast = new BinaryHexCast;
    $hexString = 'deadbeef';

    $result = $cast->set(null, 'test_key', $hexString, []);

    expect($result)->toBe(pack('H*', 'deadbeef'));
});

it('returns null when value is null in set method', function () {
    $cast = new BinaryHexCast;

    $result = $cast->set(null, 'test_key', null, []);

    expect($result)->toBeNull();
});

it('throws exception when invalid hex string is provided in set method', function () {
    $cast = new BinaryHexCast;
    $invalidHex = 'invalid_hex_string';

    expect(fn () => $cast->set(null, 'test_key', $invalidHex, []))
        ->toThrow(ErrorException::class);
});

it('handles round-trip conversion correctly', function () {
    $cast = new BinaryHexCast;
    $originalHex = 'deadbeef';

    // Convert hex to binary (set)
    $binary = $cast->set(null, 'test_key', $originalHex, []);

    // Convert binary back to hex (get)
    $resultHex = $cast->get(null, 'test_key', $binary, []);

    expect($resultHex)->toBe($originalHex);
});

it('handles various hex string formats', function (string $hexInput, string $expected) {
    $cast = new BinaryHexCast;

    $binary = $cast->set(null, 'test_key', $hexInput, []);
    $result = $cast->get(null, 'test_key', $binary, []);

    expect($result)->toBe($expected);
})->with([
    ['00', '00'],
    ['ff', 'ff'],
    ['0123456789abcdef', '0123456789abcdef'],
    ['DEADBEEF', 'deadbeef'], // uppercase should work
    ['a1b2c3', 'a1b2c3'],
]);

it('throws exception for invalid hex strings', function (string $invalidHex) {
    $cast = new BinaryHexCast;

    expect(fn () => $cast->set(null, 'test_key', $invalidHex, []))
        ->toThrow(ErrorException::class);
})->with([
    ['xyz'],
    ['12g3'],
    ['odd_length_hex'],
    ['!@#$%'],
]);

it('handles empty string by returning empty string', function () {
    $cast = new BinaryHexCast;

    $result = $cast->set(null, 'test_key', '', []);

    expect($result)->toBe('');
});

it('preserves binary data integrity', function () {
    $cast = new BinaryHexCast;

    // Test with various binary patterns
    $testData = [
        "\x00\x01\x02\x03",
        "\xFF\xFE\xFD\xFC",
        "\x7F\x80\x81\x82",
        random_bytes(16),
    ];

    foreach ($testData as $originalBinary) {
        $hex = $cast->get(null, 'test_key', $originalBinary, []);
        $restoredBinary = $cast->set(null, 'test_key', $hex, []);

        expect($restoredBinary)->toBe($originalBinary);
    }
});
