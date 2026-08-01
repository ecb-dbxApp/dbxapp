# Credits und externe Libraries {#dbxapp_credits}

dbxapp nutzt mehrere externe Libraries aus `vendor` und `add_ons`. Diese
Bibliotheken sind Teil der Auslieferung und werden über dbxapp-Systemklassen
oder JavaScript-Libs eingebunden.

## PHP/Vendor

| Library | Verwendung | Link |
| --- | --- | --- |
| jQuery (`components/jquery`) | DOM/AJAX-Basis für Teile der UI | https://jquery.com/ |
| Bootstrap (`twbs/bootstrap`) | Layout, Komponenten, Styles | https://getbootstrap.com/ |
| Bootstrap Icons (`twbs/bootstrap-icons`) | Icons in Buttons, Menüs, Panels | https://icons.getbootstrap.com/ |
| PHPMailer (`phpmailer/phpmailer`) | Mailversand | https://github.com/PHPMailer/PHPMailer |
| phpseclib (`phpseclib/phpseclib`) | Kryptografie/SSH/Security-Werkzeuge je nach Modul | https://phpseclib.com/ |
| paragonie/constant_time_encoding | sichere Encoding-Hilfen | https://github.com/paragonie/constant_time_encoding |
| paragonie/random_compat | Kompatibilität für sichere Zufallswerte | https://github.com/paragonie/random_compat |

## Add-ons

| Add-on | Verwendung | Link |
| --- | --- | --- |
| Ace Editor | Source-/Template-Editor | https://ace.c9.io/ |
| GLightbox | Lightbox für Medien/Galerien | https://biati-digital.github.io/glightbox/ |
| jsTree | Baumdarstellungen, z.B. CMS/Struktur | https://www.jstree.com/ |
| PureCounter | animierte Zähler | https://github.com/srexi/purecounterjs |
| Remix Icon | Iconset | https://remixicon.com/ |
| Tabulator | interaktive Tabellen/Grid | https://tabulator.info/ |
| jsPDF | PDF-Erzeugung im Browser | https://github.com/parallax/jsPDF |
| jsPDF AutoTable | Tabellen in jsPDF | https://github.com/simonbengtsson/jsPDF-AutoTable |
| SheetJS/xlsx | Excel/XLSX Import/Export | https://sheetjs.com/ |

## Verwendung in dbxapp

Externe Libraries werden nicht direkt aus Fachmodulen heraus wild eingebunden.
Sie werden über dbxapp-Libs, Templates oder Systemklassen genutzt.

Beispiele:

- Tabulator wird über `grid.js` und `dbxReport` Grid-Modus verwendet.
- Ace wird über `ace.js` für Editorbereiche verwendet.
- Bootstrap Icons werden in Templates für Aktionen verwendet.
- PHPMailer wird über `dbxMail` genutzt.

## Regel für Updates

Ein Library-Update ist eine Infrastrukturaufgabe. Es muss geprüft werden:

- API-Kompatibilität.
- CSS-/JS-Namenskonflikte.
- Auswirkungen auf dbxapp-Libs.
- Lizenz und Auslieferungsform.

Fachmodule sollen externe Libraries nicht direkt aktualisieren oder ersetzen.
