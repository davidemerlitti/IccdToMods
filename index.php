<?php
require_once __DIR__ . '/vendor/autoload.php';

use InformaticaUmanistica\IccdToMods\IccdToModsConverter;

$inputFile = $argv[1];
$outputFile = $argv[2];

$converter = new IccdToModsConverter();

try {
    if (str_ends_with(strtolower($inputFile), '.zip')) {
        $modsXml = $converter->convertFromZip($inputFile);
    } else {
        $modsXml = $converter->convertFromFile($inputFile);
    }
    
    file_put_contents($outputFile, $modsXml);
    echo "Successo!";
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage();
}