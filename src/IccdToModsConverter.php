<?php
namespace InformaticaUmanistica\IccdToMods;

use ZipArchive;
use DOMDocument;
use DOMXPath;
use Exception;

/**
 * IccdToModsConverter
 * 
 * Trasforma schede ICCD (SIGECWeb) in frammenti MODS (Profilo ECO-MiC 1.2)
 */
class IccdToModsConverter {
    
    private ?DOMDocument $sourceDom = null;
    private ?DOMXPath $xpath = null;
    private ?DOMDocument $modsDom = null;

    /**
     * Estrae l'XML corretto da uno ZIP e lo converte
     */
    public function convertFromZip(string $zipPath): string {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== TRUE) {
            throw new Exception("Impossibile aprire il file ZIP: $zipPath");
        }

        $xmlContent = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            // Cerca il file della scheda (es. SS207OA.xml)
            if (preg_match('/OA\.xml$/i', $filename)) {
                $xmlContent = $zip->getFromName($filename);
                break;
            }
        }
        $zip->close();

        if (!$xmlContent) {
            throw new Exception("File XML della scheda OA non trovato nello ZIP.");
        }

        return $this->convertFromXml($xmlContent);
    }

    /**
     * Legge un file XML standalone e lo converte
     */
    public function convertFromFile(string $filePath): string {
        if (!file_exists($filePath)) {
            throw new Exception("File non trovato: $filePath");
        }
        $xmlContent = file_get_contents($filePath);
        return $this->convertFromXml($xmlContent);
    }

    /**
     * Core logic: Converte una stringa XML ICCD in XML MODS
     */
    public function convertFromXml(string $xmlContent): string {
        $this->sourceDom = new DOMDocument();
        libxml_use_internal_errors(true);
        if (!$this->sourceDom->loadXML($xmlContent)) {
            throw new Exception("Errore nel caricamento dell'XML sorgente.");
        }
        $this->xpath = new DOMXPath($this->sourceDom);

        // Inizializza il documento MODS di output
        $this->modsDom = new DOMDocument('1.0', 'UTF-8');
        $this->modsDom->formatOutput = true;
        
        $mods = $this->modsDom->createElementNS('http://www.loc.gov/mods/v3', 'mods:mods');
        $this->modsDom->appendChild($mods);

        $this->applyMapping($mods);

        return $this->modsDom->saveXML();
    }

    /**
     * Applica le regole di mapping definite nelle specifiche
     */
    private function applyMapping($mods): void {
        // 1. Identifiers
        $this->addIdentifier($mods, 'logicalId', $this->generateLogicalId());
        $this->addIdentifier($mods, 'conservativeId', $this->getVal('//CD/ESC'));
        $this->addIdentifier($mods, 'conservativeIdAuthority', 'ESC');
        $this->addIdentifier($mods, 'relationId', 'representation');

        // 2. Record Info
        $ri = $this->modsDom->createElement('mods:recordInfo');
        $ri->appendChild($this->modsDom->createElement('mods:recordContentSource', 'REG01-ABAP-001'));
        $mods->appendChild($ri);

        // 3. Type of Resource
        $tor = $this->modsDom->createElement('mods:typeOfResource', 'beni storici e artistici; opere e oggetti d\'arte');
        $tor->setAttribute('authority', 'ICCD_pregresso');
        $mods->appendChild($tor);

        // 4. Genre
        $genreVal = trim($this->getVal('//OG/OGT/OGTD') . ' ' . $this->getVal('//OG/OGT/OGTT'));
        $genre = $this->modsDom->createElement('mods:genre', $genreVal);
        $genre->setAttribute('authority', 'ICCD_pregresso');
        $mods->appendChild($genre);

        // 5. Title
        $titleInfo = $this->modsDom->createElement('mods:titleInfo');
        $titleInfo->appendChild($this->modsDom->createElement('mods:title', $this->resolveTitle()));
        $mods->appendChild($titleInfo);

        // 6. Abstract
        if ($deso = $this->getVal('//DA/DES/DESO')) {
            $abstract = $this->modsDom->createElement('mods:abstract');
            $abstract->nodeValue = "<p>$deso</p>"; 
            $mods->appendChild($abstract);
        }

        // 7. Authors
        $this->processAuthors($mods);

        // 8. OriginInfo (Date)
        $oi = $this->modsDom->createElement('mods:originInfo');
        $oi->appendChild($this->createDateNode('mods:dateCreated', $this->getVal('//DT/DTS/DTSI'), 'start'));
        $oi->appendChild($this->createDateNode('mods:dateCreated', $this->getVal('//DT/DTS/DTSF'), 'end'));
        $mods->appendChild($oi);

        // 9. Physical Description (Extent e Form)
        $this->processPhysicalDescription($mods);

        // 10. Location
        $this->processLocation($mods);
    }

    // --- METODI HELPER PRIVATI (Invariati nella logica) ---

    private function getVal(string $query): string {
        $nodes = $this->xpath->query($query);
        return ($nodes->length > 0) ? trim($nodes->item(0)->nodeValue) : '';
    }

    private function generateLogicalId(): string {
        $nctr = $this->getVal('//CD/NCT/NCTR');
        $nctn = $this->getVal('//CD/NCT/NCTN');
        $ncts = $this->getVal('//CD/NCT/NCTS');
        $rvel = $this->getVal('//CD/RV/RVEL');
        $id = str_replace(' ', '', $nctr . $nctn . $ncts);
        return $rvel ? $id . '-' . $rvel : $id;
    }

    private function resolveTitle(): string {
        if ($t = $this->getVal('//OG/OGT/OGTN')) return $t;
        if ($t = $this->getVal('//OG/SGT/SGTT')) return $t;
        return trim($this->getVal('//OG/OGT/OGTD') . ' ' . $this->getVal('//OG/OGT/OGTT'));
    }

    private function processAuthors($mods): void {
        if ($autn = $this->getVal('//AU/AUT/AUTN')) {
            $name = $this->modsDom->createElement('mods:name');
            $name->setAttribute('type', 'personal');
            $name->appendChild($this->modsDom->createElement('mods:namePart', $autn));
            if ($date = $this->getVal('//AU/AUT/AUTA')) {
                $dp = $this->modsDom->createElement('mods:namePart', $date);
                $dp->setAttribute('type', 'date');
                $name->appendChild($dp);
            }
            $role = $this->modsDom->createElement('mods:role');
            $role->appendChild($this->modsDom->createElement('mods:roleTerm', 'Autore'))->setAttribute('type', 'text');
            $name->appendChild($role);
            $mods->appendChild($name);
        }
    }

    private function processPhysicalDescription($mods): void {
        $pd = $this->modsDom->createElement('mods:physicalDescription');
        $pd->appendChild($this->modsDom->createElement('mods:form', $this->getVal('//OG/OGT/OGTD')));
        
        $mtcNodes = $this->xpath->query('//MT/MTC');
        foreach ($mtcNodes as $node) {
            $form = $this->modsDom->createElement('mods:form', trim($node->nodeValue));
            $form->setAttribute('type', 'technique');
            $pd->appendChild($form);
        }

        $extentParts = [];
        $misu = $this->getVal('//MT/MIS/MISU');
        $map = ['MISA'=>'Altezza', 'MISL'=>'Larghezza', 'MISP'=>'Profondità', 'MISD'=>'Diametro', 'MISN'=>'Lunghezza', 'MISS'=>'Spessore', 'MISG'=>'Peso'];
        
        foreach ($map as $tag => $label) {
            if ($val = $this->getVal("//MT/MIS/$tag")) {
                $suffix = ($misu && $tag !== 'MISG') ? " $misu" : "";
                $extentParts[] = "$label: $val$suffix";
            }
        }
        
        if ($val = $this->getVal('//MT/MIS/MISV')) $extentParts[] = "Varie: $val";
        if (!empty($extentParts)) {
            $pd->appendChild($this->modsDom->createElement('mods:extent', strip_tags(implode('; ', $extentParts))));
        }
        $mods->appendChild($pd);
    }

    private function processLocation($mods): void {
        $pvcs = $this->getVal('//LC/PVC/PVCS');
        $pvcr = $this->getVal('//LC/PVC/PVCR');
        $pvcc = $this->getVal('//LC/PVC/PVCC');
        $pvcl = $this->getVal('//LC/PVC/PVCL');
        $pvce = $this->getVal('//LC/PVC/PVCE');

        $locString = $pvce ? trim("$pvcs; $pvce", "; ") : implode('; ', array_filter([$pvcs, $pvcr, $pvcc]));
        if (!$pvce && $pvcl) $locString .= " - $pvcl";

        if (!empty($locString)) {
            $location = $this->modsDom->createElement('mods:location');
            $pl = $this->modsDom->createElement('mods:physicalLocation', $locString);
            $pl->setAttribute('type', 'current');
            $location->appendChild($pl);
            $mods->appendChild($location);
        }
    }

    private function addIdentifier($parent, $type, $value): void {
        if (!$value) return;
        $id = $this->modsDom->createElement('mods:identifier', $value);
        $id->setAttribute('type', $type);
        $parent->appendChild($id);
    }

    private function createDateNode($name, $value, $point) {
        $node = $this->modsDom->createElement($name, $value);
        $node->setAttribute('point', $point);
        return $node;
    }
}