<?php

/**
 * Pflegbare Schreibstil-Vorgaben fuer KI-Auftraege (dbxKi Briefing).
 * Jeder Stil liefert label (Formular) und prompt (wird in KI-AUFTRAG.md eingefuegt).
 */
$writing_styles = array(
   'sachlich' => array(
      'label' => 'Sachlich und professionell',
      'prompt' => 'Schreibe sachlich, klar und professionell. Kurze Saetze, keine Uebertreibungen, Zielgruppe: Unternehmen und Fachpublikum.',
   ),
   'freundlich' => array(
      'label' => 'Freundlich und einladend',
      'prompt' => 'Schreibe warm, einladend und verstaendlich. Du-Ansprache wenn passend. Positiv, aber glaubwuerdig.',
   ),
   'marketing' => array(
      'label' => 'Marketing / verkaufsorientiert',
      'prompt' => 'Schreibe aktivierend mit klaren Nutzenargumenten und Call-to-Action. Keine leeren Floskeln, konkrete Vorteile nennen.',
   ),
   'technisch' => array(
      'label' => 'Technisch / fuer Entwickler',
      'prompt' => 'Schreibe praezise und technisch. Fachbegriffe sind erlaubt, Struktur mit Abschnitten und Listen.',
   ),
   'kurz' => array(
      'label' => 'Sehr kurz und knapp',
      'prompt' => 'Maximal knapp: nur das Wesentliche, wenige Absaetze, keine Wiederholungen.',
   ),
);
