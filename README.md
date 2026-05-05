# ICCD to MODS Converter (METS-ECOMiC Profile)

Questo strumento PHP permette di convertire schede di catalogo dei beni culturali in formato **ICCD (XML SIGECWeb)** nel formato **MODS**, seguendo rigorosamente le specifiche del profilo applicativo **ECO-MiC 1.2**.

Sviluppato da **Davide Merlitti** (Informatica Umanistica Srl) nel contesto dell'incarico di Business Management per la **Regione Liguria**, il software mira a standardizzare e automatizzare la generazione di metadati validi per i sistemi di conservazione digitale.

## 🚀 Caratteristiche principali

- **Versatilità di Input**: Supporta la lettura di dati da file ZIP (estrazione automatica della scheda), file XML locali o stringhe XML.
- **Mapping Intelligente**: Implementa la logica a cascata per i titoli (OGTN > SGTT > OGTD/OGTT).
- **Normalizzazione ID**: Generazione automatica del `logicalId` pulito per l'attributo `OBJID` del METS.
- **Tabella Extent**: Formattazione avanzata delle misure e quantità secondo etichette standard (Altezza, Larghezza, etc.).
- **Localizzazione**: Gestione delle coordinate geografiche (Current Location) con distinzione Italia/Estero.

## 📋 Stato del supporto schede

Il progetto è modulare e progettato per essere esteso a tutti i tracciati ICCD.
- [x] **Scheda OA 3.00** (Opere e Oggetti d'Arte)
- [ ] *Pianificato: Schede F, FF, RA, etc.*

## 🛠 Installazione

Il progetto utilizza l'autoloader PSR-4 di Composer.

```bash
composer install
```

## 💻 Utilizzo (CLI)

È possibile utilizzare il convertitore da riga di comando passandogli uno ZIP o un file XML:

```bash
# Esempio con file ZIP
php index.php "percorso/all/input.zip" "output_mods.xml"

# Esempio con file XML
php index.php "percorso/all/scheda.xml" "output_mods.xml"
```

## 🏗 Esempio di Integrazione PHP

```php
use InformaticaUmanistica\IccdToMods\IccdToModsConverter;

$converter = new IccdToModsConverter();

// Conversione da ZIP
$modsXml = $converter->convertFromZip('percorso/file.zip');

// Oppure conversione da stringa XML
// $modsXml = $converter->convertFromXml($stringaXml);

echo $modsXml;
```

## ⚖️ Licenza e Filosofia

Questo progetto segue il principio **"Public Money, Public Code"**. Essendo stato sviluppato con il supporto della Regione Liguria, il codice è reso disponibile sotto licenza **MIT** per favorire il riuso tra le Pubbliche Amministrazioni e i loro fornitori.

---
**Autore:** Davide Merlitti  
**Contatti:** [d.merlitti@informaticaumanistica.com](mailto:d.merlitti@informaticaumanistica.com)  
**Web:** [www.informaticaumanistica.com](https://www.informaticaumanistica.com)
