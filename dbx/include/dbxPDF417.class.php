<?php
class dbxPDF417 {

    public function __construct() {
        $load=dbx()->os_path(dbx()->get_base_dir().'vendor/autoload.php');
        require_once $load; // Pfad ggf. anpassen
    }

    /**
     * Erzeugt einen PDF417-Barcode.
     *
     * @param string $data Der Inhalt des Barcodes.
     * @param string $type 'svg' für rohes SVG, 'img' für Base64-bild
     * @param float  $scale_w Breite pro Modul
     * @param float  $scale_h Höhe pro Modul
     * @param string $color Farbe (z. B. 'black' oder '#000000')
     *
     * @return string SVG-XML oder <img>-Tag mit Base64
     */
    public function get_pdf417(string $data = '', string $type = 'svg', float $scale_w = 0.4, float $scale_h = 1.2, string $color = 'black'): string {
        $barcodeobj = new \TCPDF2DBarcode($data, 'PDF417');

        $svg = $barcodeobj->getBarcodeSVGcode($scale_w, $scale_h, $color);

        if ($type === 'img') {
            $width_mm  =  70; // z. B. 50mm Breite
            $height_mm =  22; // z. B. 20mm Höhe
            $base64 = base64_encode($svg);
            return '<img src="data:image/svg+xml;base64,' . $base64 . '" style="width: ' . $width_mm . 'mm; height: ' . $height_mm . 'mm;" />';
        }

        // Default: raw SVG
        return $svg;
    }
}
