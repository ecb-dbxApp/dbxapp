<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
$logoFile = $root . '/dbXapp-Logo.jpeg';
$outDir = $root . '/files/shop/img';
require_once $root . '/dbx/include/dbxApi.php';

if (!extension_loaded('gd')) {
   fwrite(STDERR, "GD extension is required.\n");
   exit(1);
}
if (!is_file($logoFile)) {
   fwrite(STDERR, "Logo not found: $logoFile\n");
   exit(1);
}
if (!is_dir($outDir)) {
   mkdir($outDir, 0775, true);
}

$logo = imagecreatefromjpeg($logoFile);
if (!$logo) {
   fwrite(STDERR, "Could not read logo.\n");
   exit(1);
}

function c($img, int $r, int $g, int $b, int $a = 0): int {
   return imagecolorallocatealpha($img, $r, $g, $b, $a);
}

function roundedRect($img, int $x, int $y, int $w, int $h, int $r, int $color): void {
   imagefilledrectangle($img, $x + $r, $y, $x + $w - $r, $y + $h, $color);
   imagefilledrectangle($img, $x, $y + $r, $x + $w, $y + $h - $r, $color);
   imagefilledellipse($img, $x + $r, $y + $r, $r * 2, $r * 2, $color);
   imagefilledellipse($img, $x + $w - $r, $y + $r, $r * 2, $r * 2, $color);
   imagefilledellipse($img, $x + $r, $y + $h - $r, $r * 2, $r * 2, $color);
   imagefilledellipse($img, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $color);
}

function gradientBackground($img): void {
   $w = imagesx($img);
   $h = imagesy($img);
   for ($y = 0; $y < $h; $y++) {
      $t = $y / max(1, $h - 1);
      $r = (int)(244 - 18 * $t);
      $g = (int)(249 - 28 * $t);
      $b = (int)(255 - 22 * $t);
      imageline($img, 0, $y, $w, $y, c($img, $r, $g, $b));
   }
   imagefilledellipse($img, 260, 120, 520, 220, c($img, 221, 238, 255, 30));
   imagefilledellipse($img, 1060, 150, 360, 180, c($img, 214, 232, 255, 45));
   imagefilledellipse($img, 640, 710, 780, 96, c($img, 24, 50, 93, 94));
}

function addLogo($img, $logo, int $x, int $y, int $w, int $h): void {
   $lw = imagesx($logo);
   $lh = imagesy($logo);
   imagecopyresampled($img, $logo, $x, $y, 0, 0, $w, $h, $lw, $lh);
}

function addTitle($img, string $title, string $subtitle = ''): void {
   $dark = c($img, 27, 44, 68);
   $muted = c($img, 92, 111, 136);
   imagestring($img, 5, 46, 42, $title, $dark);
   if ($subtitle !== '') {
      imagestring($img, 3, 46, 66, $subtitle, $muted);
   }
}

function drawShine($img, int $x, int $y, int $w, int $h): void {
   imagefilledellipse($img, $x + (int)($w * .28), $y + (int)($h * .18), (int)($w * .42), (int)($h * .12), c($img, 255, 255, 255, 68));
   imagefilledrectangle($img, $x + (int)($w * .12), $y + 16, $x + (int)($w * .18), $y + $h - 16, c($img, 255, 255, 255, 95));
}

function drawMug($img, $logo, string $bodyColor): void {
   $dark = $bodyColor === 'blue' ? c($img, 18, 51, 112) : c($img, 246, 250, 255);
   $side = $bodyColor === 'blue' ? c($img, 11, 35, 82) : c($img, 224, 235, 248);
   $rim = c($img, 255, 255, 255, 35);
   imagefilledellipse($img, 641, 270, 394, 72, c($img, 25, 45, 78, 72));
   imagefilledrectangle($img, 444, 270, 838, 560, $dark);
   imagefilledellipse($img, 641, 560, 394, 72, $side);
   imagefilledellipse($img, 641, 270, 394, 72, $rim);
   imageellipse($img, 641, 270, 394, 72, c($img, 110, 150, 205, 20));
   imagefilledellipse($img, 865, 405, 146, 190, c($img, 210, 225, 245, 20));
   imagefilledellipse($img, 856, 405, 92, 132, c($img, 238, 246, 255));
   drawShine($img, 444, 270, 394, 290);
   addLogo($img, $logo, 525, 325, 230, 188);
}

function drawShirt($img, $logo, string $kind): void {
   $base = $kind === 'hoodie' ? c($img, 24, 39, 64) : c($img, 16, 26, 44);
   $shade = c($img, 7, 18, 35);
   if ($kind === 'cap') {
      imagefilledellipse($img, 640, 385, 380, 190, $base);
      imagefilledellipse($img, 770, 480, 350, 90, c($img, 12, 32, 70));
      imagearc($img, 640, 385, 348, 164, 190, 350, c($img, 120, 160, 220));
      addLogo($img, $logo, 555, 344, 158, 128);
      return;
   }
   if ($kind === 'hoodie') {
      imagefilledellipse($img, 640, 264, 240, 190, $shade);
      imagefilledellipse($img, 640, 290, 172, 126, c($img, 230, 238, 250));
   }
   imagefilledpolygon($img, array(405,270, 530,220, 600,290, 680,290, 750,220, 875,270, 810,435, 760,412, 760,620, 520,620, 520,412, 470,435), 12, $base);
   imagefilledpolygon($img, array(405,270, 530,220, 520,412, 470,435), 4, $shade);
   imagefilledpolygon($img, array(750,220, 875,270, 810,435, 760,412), 4, $shade);
   if ($kind === 'hoodie') {
      roundedRect($img, 570, 505, 140, 62, 16, c($img, 13, 28, 52));
      imageline($img, 615, 300, 585, 440, c($img, 210, 220, 235));
      imageline($img, 665, 300, 695, 440, c($img, 210, 220, 235));
   }
   addLogo($img, $logo, 548, 342, 184, 150);
}

function drawFlatProduct($img, $logo, string $type): void {
   if ($type === 'poster') {
      roundedRect($img, 430, 150, 420, 520, 10, c($img, 255, 255, 255));
      imagerectangle($img, 430, 150, 850, 670, c($img, 194, 211, 232));
      addLogo($img, $logo, 475, 225, 330, 270);
      return;
   }
   if ($type === 'notebook') {
      roundedRect($img, 448, 180, 384, 500, 22, c($img, 19, 42, 86));
      roundedRect($img, 486, 230, 310, 390, 14, c($img, 240, 247, 255));
      addLogo($img, $logo, 522, 325, 238, 194);
      for ($i = 0; $i < 8; $i++) imagefilledellipse($img, 466, 235 + $i * 48, 14, 14, c($img, 210, 225, 244));
      return;
   }
   $w = $type === 'deskmat' ? 690 : 520;
   $h = $type === 'deskmat' ? 340 : 310;
   $x = 640 - (int)($w / 2);
   $y = 400 - (int)($h / 2);
   roundedRect($img, $x, $y, $w, $h, 28, c($img, 15, 34, 73));
   roundedRect($img, $x + 18, $y + 18, $w - 36, $h - 36, 20, c($img, 22, 55, 126));
   addLogo($img, $logo, $x + (int)($w * .29), $y + (int)($h * .22), (int)($w * .42), (int)($h * .55));
}

function drawAccessory($img, $logo, string $type): void {
   switch ($type) {
      case 'bottle':
      case 'thermo':
         roundedRect($img, 555, 185, 170, 475, 52, c($img, 230, 238, 248));
         roundedRect($img, 580, 125, 120, 82, 24, c($img, 28, 50, 82));
         imagefilledrectangle($img, 590, 110, 690, 140, c($img, 18, 34, 58));
         drawShine($img, 555, 185, 170, 475);
         addLogo($img, $logo, 585, 330, 110, 90);
         break;
      case 'usb':
         roundedRect($img, 420, 330, 340, 110, 24, c($img, 25, 47, 82));
         roundedRect($img, 745, 350, 120, 70, 10, c($img, 198, 211, 226));
         addLogo($img, $logo, 486, 345, 160, 72);
         break;
      case 'powerbank':
         roundedRect($img, 430, 235, 420, 290, 34, c($img, 20, 42, 78));
         roundedRect($img, 456, 260, 368, 238, 24, c($img, 30, 72, 144));
         addLogo($img, $logo, 535, 320, 210, 172);
         imagefilledrectangle($img, 797, 315, 820, 360, c($img, 200, 218, 238));
         break;
      case 'bag':
         roundedRect($img, 455, 270, 370, 330, 16, c($img, 238, 241, 245));
         imagearc($img, 640, 278, 220, 170, 190, 350, c($img, 45, 62, 90));
         addLogo($img, $logo, 535, 380, 210, 172);
         break;
      case 'lanyard':
         imagesetthickness($img, 34);
         imagearc($img, 640, 310, 420, 300, 20, 340, c($img, 17, 63, 145));
         imagesetthickness($img, 1);
         roundedRect($img, 570, 470, 140, 95, 12, c($img, 245, 249, 255));
         addLogo($img, $logo, 590, 485, 100, 62);
         break;
      case 'keychain':
         imagefilledellipse($img, 620, 260, 150, 150, c($img, 204, 216, 232));
         imagefilledellipse($img, 620, 260, 92, 92, c($img, 238, 246, 255));
         roundedRect($img, 535, 350, 210, 150, 26, c($img, 20, 50, 105));
         addLogo($img, $logo, 575, 380, 130, 92);
         break;
      case 'phone':
         roundedRect($img, 530, 155, 220, 500, 34, c($img, 18, 31, 52));
         roundedRect($img, 548, 185, 184, 440, 24, c($img, 236, 243, 252));
         addLogo($img, $logo, 570, 335, 140, 114);
         break;
      case 'sleeve':
         roundedRect($img, 430, 235, 420, 300, 28, c($img, 28, 49, 78));
         imageline($img, 450, 295, 830, 295, c($img, 82, 112, 150));
         addLogo($img, $logo, 535, 335, 210, 172);
         break;
      case 'socks':
         roundedRect($img, 500, 170, 110, 390, 34, c($img, 24, 42, 68));
         roundedRect($img, 590, 390, 210, 120, 36, c($img, 24, 42, 68));
         roundedRect($img, 650, 170, 110, 390, 34, c($img, 238, 242, 248));
         roundedRect($img, 740, 390, 210, 120, 36, c($img, 238, 242, 248));
         addLogo($img, $logo, 525, 270, 70, 58);
         addLogo($img, $logo, 675, 270, 70, 58);
         break;
      case 'beanie':
         imagefilledellipse($img, 640, 420, 410, 290, c($img, 22, 39, 68));
         imagefilledrectangle($img, 440, 420, 840, 540, c($img, 19, 34, 61));
         roundedRect($img, 560, 425, 160, 86, 14, c($img, 241, 246, 252));
         addLogo($img, $logo, 580, 440, 120, 56);
         break;
      case 'sticker':
         for ($i = 0; $i < 5; $i++) {
            $x = 420 + $i * 92;
            imagefilledellipse($img, $x, 390 + (($i % 2) * 38), 130, 130, c($img, 255, 255, 255));
            addLogo($img, $logo, $x - 44, 350 + (($i % 2) * 38), 88, 72);
         }
         break;
      case 'pen':
         for ($i = 0; $i < 3; $i++) {
            $y = 310 + $i * 70;
            roundedRect($img, 390, $y, 500, 34, 17, $i === 1 ? c($img, 225, 36, 48) : c($img, 20, 55, 125));
            imagefilledpolygon($img, array(890,$y, 950,$y+17, 890,$y+34), 3, c($img, 170, 185, 205));
            addLogo($img, $logo, 520, $y - 12, 92, 50);
         }
         break;
   }
}

function createMockup($logo, string $type, string $title, string $file): void {
   $img = imagecreatetruecolor(1280, 800);
   imagealphablending($img, true);
   imagesavealpha($img, true);
   gradientBackground($img);
   addTitle($img, $title, 'dbXapp Merchandise');

   switch ($type) {
      case 'mug-blue': drawMug($img, $logo, 'blue'); break;
      case 'mug-white': drawMug($img, $logo, 'white'); break;
      case 'shirt': drawShirt($img, $logo, 'shirt'); break;
      case 'hoodie': drawShirt($img, $logo, 'hoodie'); break;
      case 'cap': drawShirt($img, $logo, 'cap'); break;
      case 'mousepad': drawFlatProduct($img, $logo, 'mousepad'); break;
      case 'deskmat': drawFlatProduct($img, $logo, 'deskmat'); break;
      case 'poster': drawFlatProduct($img, $logo, 'poster'); break;
      default: drawAccessory($img, $logo, $type); break;
   }

   imagewebp($img, $file, 88);
   imagedestroy($img);
}

$specs = array(
   'DBX-SHIRT' => array('shirt', 'T-Shirt mit dbXapp Logo', 'mockup-dbxapp-t-shirt.webp'),
   'DBX-CAP' => array('cap', 'Kappe mit dbXapp Logo', 'mockup-dbxapp-kappe.webp'),
   'DBX-HOODIE' => array('hoodie', 'Hoodie mit dbXapp Logo', 'mockup-dbxapp-hoodie.webp'),
   'DBX-MUG-BLUE' => array('mug-blue', 'dbXapp Kaffeetasse Blau', 'mockup-dbxapp-kaffeetasse-blau.webp'),
   'DBX-MUG-WHITE' => array('mug-white', 'dbXapp Kaffeetasse Weiss', 'mockup-dbxapp-kaffeetasse-weiss.webp'),
   'DBX-MOUSEPAD' => array('mousepad', 'dbXapp Mauspad Classic', 'mockup-dbxapp-mauspad-classic.webp'),
   'DBX-MOUSEPAD-XL' => array('mousepad', 'dbXapp Mauspad XL', 'mockup-dbxapp-mauspad-xl.webp'),
   'DBX-DESKMAT' => array('deskmat', 'dbXapp Deskmat', 'mockup-dbxapp-deskmat.webp'),
   'DBX-STICKER-SET' => array('sticker', 'dbXapp Sticker Set', 'mockup-dbxapp-sticker-set.webp'),
   'DBX-NOTEBOOK' => array('notebook', 'dbXapp Notizbuch', 'mockup-dbxapp-notizbuch.webp'),
   'DBX-PEN-SET' => array('pen', 'dbXapp Kugelschreiber Set', 'mockup-dbxapp-kugelschreiber-set.webp'),
   'DBX-LANYARD' => array('lanyard', 'dbXapp Lanyard', 'mockup-dbxapp-lanyard.webp'),
   'DBX-TOTE-BAG' => array('bag', 'dbXapp Stofftasche', 'mockup-dbxapp-stofftasche.webp'),
   'DBX-BOTTLE' => array('bottle', 'dbXapp Trinkflasche', 'mockup-dbxapp-trinkflasche.webp'),
   'DBX-THERMO-MUG' => array('thermo', 'dbXapp Thermobecher', 'mockup-dbxapp-thermobecher.webp'),
   'DBX-USB-STICK' => array('usb', 'dbXapp USB-Stick', 'mockup-dbxapp-usb-stick.webp'),
   'DBX-POWERBANK' => array('powerbank', 'dbXapp Powerbank', 'mockup-dbxapp-powerbank.webp'),
   'DBX-KEYCHAIN' => array('keychain', 'dbXapp Schluesselanhaenger', 'mockup-dbxapp-schluesselanhaenger.webp'),
   'DBX-POSTER' => array('poster', 'dbXapp Poster', 'mockup-dbxapp-poster.webp'),
   'DBX-SOCKS' => array('socks', 'dbXapp Socken', 'mockup-dbxapp-socken.webp'),
   'DBX-BEANIE' => array('beanie', 'dbXapp Beanie', 'mockup-dbxapp-beanie.webp'),
   'DBX-PHONE-CASE' => array('phone', 'dbXapp Smartphone-Huelle', 'mockup-dbxapp-smartphone-huelle.webp'),
   'DBX-LAPTOP-SLEEVE' => array('sleeve', 'dbXapp Laptop Sleeve', 'mockup-dbxapp-laptop-sleeve.webp'),
);

$repo = dbx()->get_include_obj('dbxShopRepository', 'dbxShop');
$repo->install();
$count = 0;
foreach ($specs as $sku => $spec) {
   [$type, $fallbackTitle, $fileName] = $spec;
   $product = $repo->productBySku($sku, false);
   if (!$product) {
      continue;
   }
   $title = trim((string)($product['title'] ?? '')) ?: $fallbackTitle;
   $path = $outDir . '/' . $fileName;
   createMockup($logo, $type, $title, $path);
   $webPath = 'files/shop/img/' . $fileName;
   $repo->saveImage((int)$product['id'], 0, $webPath, $title, $title . ' Produktbild', 1, 5);
   $count++;
}

imagedestroy($logo);
echo "Generated $count dbXapp merch mockups.\n";
