# Credits und externe Libraries {#dbxapp_credits}

dbXapp nutzt mehrere externe Libraries aus `vendor` und `add_ons`. Diese
Bibliotheken sind Teil der Auslieferung und werden ueber dbXapp-Systemklassen
oder JavaScript-Libs eingebunden.

## PHP/Vendor

| Library | Verwendung | Link |
| --- | --- | --- |
| jQuery (`components/jquery`) | DOM/AJAX-Basis fuer Teile der UI | https://jquery.com/ |
| Bootstrap (`twbs/bootstrap`) | Layout, Komponenten, Styles | https://getbootstrap.com/ |
| Bootstrap Icons (`twbs/bootstrap-icons`) | Icons in Buttons, Menues, Panels | https://icons.getbootstrap.com/ |
| PHPMailer (`phpmailer/phpmailer`) | Mailversand | https://github.com/PHPMailer/PHPMailer |
| phpseclib (`phpseclib/phpseclib`) | Kryptografie/SSH/Security-Werkzeuge je nach Modul | https://phpseclib.com/ |
| paragonie/constant_time_encoding | sichere Encoding-Hilfen | https://github.com/paragonie/constant_time_encoding |
| paragonie/random_compat | Kompatibilitaet fuer sichere Zufallswerte | https://github.com/paragonie/random_compat |

## Add-ons

| Add-on | Verwendung | Link |
| --- | --- | --- |
| Ace Editor | Source-/Template-Editor | https://ace.c9.io/ |
| GLightbox | Lightbox fuer Medien/Galerien | https://biati-digital.github.io/glightbox/ |
| jsTree | Baumdarstellungen, z.B. CMS/Struktur | https://www.jstree.com/ |
| PureCounter | animierte Zaehler | https://github.com/srexi/purecounterjs |
| Remix Icon | Iconset | https://remixicon.com/ |
| Tabulator | interaktive Tabellen/Grid | https://tabulator.info/ |
| jsPDF | PDF-Erzeugung im Browser | https://github.com/parallax/jsPDF |
| jsPDF AutoTable | Tabellen in jsPDF | https://github.com/simonbengtsson/jsPDF-AutoTable |
| SheetJS/xlsx | Excel/XLSX Import/Export | https://sheetjs.com/ |

## Verwendung in dbXapp

Externe Libraries werden nicht direkt aus Fachmodulen heraus wild eingebunden.
Sie werden ueber dbXapp-Libs, Templates oder Systemklassen genutzt.

Beispiele:

- Tabulator wird ueber `grid.js` und `dbxReport` Grid-Modus verwendet.
- Ace wird ueber `ace.js` fuer Editorbereiche verwendet.
- Bootstrap Icons werden in Templates fuer Aktionen verwendet.
- PHPMailer wird ueber `dbxMail` genutzt.

## Regel fuer Updates

Ein Library-Update ist eine Infrastrukturaufgabe. Es muss geprueft werden:

- API-Kompatibilitaet.
- CSS-/JS-Namenskonflikte.
- Auswirkungen auf dbXapp-Libs.
- Lizenz und Auslieferungsform.

Fachmodule sollen externe Libraries nicht direkt aktualisieren oder ersetzen.
