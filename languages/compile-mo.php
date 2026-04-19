<?php
/**
 * Compile .po file to .mo file
 * 
 * Run: php compile-mo.php
 * 
 * This creates the binary .mo file that WordPress needs for translations.
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

$po_file = __DIR__ . '/property-spotlight-fi.po';
$mo_file = __DIR__ . '/property-spotlight-fi.mo';

if (!file_exists($po_file)) {
    die("Error: $po_file not found\n");
}

// Parse .po file
$po_content = file_get_contents($po_file);
$entries = [];

// Match msgid/msgstr pairs
preg_match_all('/msgid\s+"(.*)"\s*\nmsgstr\s+"(.*)"/m', $po_content, $matches, PREG_SET_ORDER);

foreach ($matches as $match) {
    $msgid = stripcslashes($match[1]);
    $msgstr = stripcslashes($match[2]);
    
    if ($msgid !== '' && $msgstr !== '') {
        $entries[$msgid] = $msgstr;
    }
}

// Handle multiline strings
preg_match_all('/msgid\s+""\s*\n((?:"[^"]*"\s*\n)+)msgstr\s+""\s*\n((?:"[^"]*"\s*\n)+)/m', $po_content, $multi_matches, PREG_SET_ORDER);

foreach ($multi_matches as $match) {
    preg_match_all('/"([^"]*)"/', $match[1], $id_parts);
    preg_match_all('/"([^"]*)"/', $match[2], $str_parts);
    
    $msgid = implode('', array_map('stripcslashes', $id_parts[1]));
    $msgstr = implode('', array_map('stripcslashes', $str_parts[1]));
    
    if ($msgid !== '' && $msgstr !== '') {
        $entries[$msgid] = $msgstr;
    }
}

if (empty($entries)) {
    die("Error: No translations found in .po file\n");
}

echo "Found " . count($entries) . " translations\n";

// Generate .mo file
$mo = generate_mo($entries);
file_put_contents($mo_file, $mo);

echo "Created: $mo_file (" . strlen($mo) . " bytes)\n";

/**
 * Generate .mo file binary content
 */
function generate_mo(array $entries): string {
    // Sort entries by original string
    ksort($entries);
    
    $offsets = [];
    $ids = '';
    $strings = '';
    
    foreach ($entries as $original => $translation) {
        $offsets[] = [
            strlen($original),
            strlen($ids),
            strlen($translation),
            strlen($strings)
        ];
        $ids .= $original . "\0";
        $strings .= $translation . "\0";
    }
    
    $n = count($entries);
    
    // Header size: magic(4) + revision(4) + n(4) + o(4) + t(4) + s(4) + h(4) = 28 bytes
    // Then: n * 8 bytes for original offsets, n * 8 bytes for translation offsets
    $key_offset_start = 28;
    $value_offset_start = $key_offset_start + $n * 8;
    $key_start = $value_offset_start + $n * 8;
    $value_start = $key_start + strlen($ids);
    
    // Build .mo file
    $mo = pack('V', 0x950412de);  // Magic number (little-endian)
    $mo .= pack('V', 0);          // Revision
    $mo .= pack('V', $n);         // Number of strings
    $mo .= pack('V', $key_offset_start);    // Offset of original strings table
    $mo .= pack('V', $value_offset_start);  // Offset of translation strings table
    $mo .= pack('V', 0);          // Size of hashing table
    $mo .= pack('V', 0);          // Offset of hashing table
    
    // Original string offsets (length, offset pairs)
    $ids_pos = $key_start;
    foreach ($offsets as $offset) {
        $mo .= pack('V', $offset[0]);  // Length
        $mo .= pack('V', $ids_pos);     // Offset
        $ids_pos += $offset[0] + 1;     // +1 for null terminator
    }
    
    // Translation string offsets (length, offset pairs)
    $strings_pos = $value_start;
    foreach ($offsets as $offset) {
        $mo .= pack('V', $offset[2]);   // Length
        $mo .= pack('V', $strings_pos); // Offset
        $strings_pos += $offset[2] + 1; // +1 for null terminator
    }
    
    // Original strings
    $mo .= $ids;
    
    // Translation strings
    $mo .= $strings;
    
    return $mo;
}
