# Qualitätsabnahme der dbxapp-Dokumentation

@page dbxapp_documentation_quality Qualitätsabnahme der Dokumentation

## Ziel

Die Dokumentation wird aus zwei voneinander unabhängigen Blickwinkeln geprüft.
Beide Prüfer verwenden ausschließlich beobachtbare Kriterien und vergeben pro
erfülltem Kriterium 0,5 Punkte. Eine Veröffentlichung ist nur zulässig, wenn
beide Ergebnisse mindestens 9,5 von 10 Punkten erreichen.

## Prüfung A: Anwender und Redaktion

Diese Prüfung bewertet Orientierung, Verständlichkeit und Vollständigkeit:

- eindeutige Startseite und genau vier Zielgruppen;
- vollständiger Kontoablauf von Registrierung bis Abmeldung;
- Aktivierung und erneuter Bestätigungslink;
- sicherer Ablauf für „Passwort vergessen“;
- Nutzen einer Anmeldung und Zugriff auf eigene Daten;
- verständliche Erklärung des Owner-Prinzips;
- korrekte Einordnung von Logs, IP-Daten und Trace;
- eindeutige Überschriftenstruktur und nächste Schritte;
- sichtbare Version, Zielgruppe, Stand und kanonische Quelle;
- responsive, tastaturbedienbare Darstellung ohne horizontales Überlaufen.

## Prüfung B: Technik und Sicherheit

Diese Prüfung bewertet Architektur, Schutzwirkung und Wartbarkeit:

- kanonische URLs ausschließlich unter `dbxapp.de/dokumentation/`;
- exakte dauerhafte Weiterleitung ehemaliger Subdomain-URLs;
- getrennte CMS-Ordner für Anwender, Administratoren, Entwickler und KI;
- einheitliche `c-doku`-Templates statt Metadaten im redaktionellen Inhalt;
- installierte Version aus einer zentralen Quelle;
- Passwort-Reset mit generischer Antwort gegen Benutzerermittlung;
- zufälliges Einmal-Token, nur als Hash gespeichert und zeitlich begrenzt;
- zentrale Passwortregel und Widerruf bestehender Sitzungen;
- Doxygen-Referenz und Suchindex aus dem aktuellen Quellbestand;
- automatisierte Struktur-, Inhalts-, Sicherheits- und UI-Verträge im SelfTest.

## Automatisierte Abnahme

`dbxDocumentationDualReview_test.php` setzt beide Perspektiven als getrennte
Prüflisten um und gibt die beiden Punktwerte aus. Der Test ersetzt keine
redaktionelle Stichprobe durch Menschen oder externe KI, stellt aber sicher,
dass zwei voneinander unabhängige Prüfer dieselben nachweisbaren
Mindestanforderungen bewerten können. Ein externer Prüfer erhält diese Seite,
die vier Zielgruppen-Einstiege und die gerenderte Desktop-/Mobilansicht; seine
Bewertung muss die festgestellten Abweichungen mit URL und Kriterium nennen.

## Freigaberegel

Ein fehlendes Pflichtkriterium führt unabhängig von der Gesamtpunktzahl zu
einer fehlgeschlagenen technischen Abnahme. Änderungen an Anmeldung,
Berechtigungen, Templates, Navigation oder Dokumentationsstruktur lösen beide
Prüfungen sowie die betroffenen UI-Abläufe erneut aus.
