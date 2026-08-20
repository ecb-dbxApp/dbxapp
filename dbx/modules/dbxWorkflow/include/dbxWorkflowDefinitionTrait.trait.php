<?php

namespace dbx\dbxWorkflow;

/** Interne Komponente von dbxWorkflowEngine. */
trait dbxWorkflowDefinitionTrait {

private function default_definition() {
      return array(
         'workflow_key' => 'invoice_demo',
         'title' => 'Rechnung erstellen',
         'result' => 'Rechnung',
         'description' => 'Zielorientierter Beispiel-Workflow: Alle für eine richtige Rechnung benötigten Angaben werden geprüft und anschließend in klaren Schritten vervollständigt.',
         'needs' => array(
            array(
               'key' => 'customer',
               'label' => 'Kunde',
               'mode' => 'single',
               'required' => true,
               'actions' => array('select', 'create'),
               'preferred' => 'select',
               'question' => 'Welcher Kunde erhält die Rechnung?',
               'validation' => 'exactly_one',
               'missing_message' => 'Kein Kunde ausgewählt – der Workflow ist unvollständig.',
               'resolver' => array('type' => 'select', 'label' => 'Kundenauswahl öffnen'),
               'hint' => 'Waehle einen vorhandenen Kunden oder erfasse direkt einen neuen Kunden.',
               'options' => array('Muster GmbH', 'Beispiel AG', 'Max Mustermann')
            ),
            array(
               'key' => 'billing_address',
               'label' => 'Rechnungsadresse',
               'mode' => 'single',
               'required' => true,
               'actions' => array('form'),
               'preferred' => 'form',
               'question' => 'Welche vollständige Rechnungsadresse wird verwendet?',
               'validation' => 'not_empty',
               'missing_message' => 'Keine vollständige Rechnungsadresse vorhanden – der Workflow ist unvollständig.',
               'resolver' => array('type' => 'form', 'label' => 'Adressformular öffnen'),
               'depends_on' => 'customer',
               'hint' => 'Prüfe Name, Straße, Postleitzahl und Ort der Rechnungsadresse.'
            ),
            array(
               'key' => 'articles',
               'label' => 'Artikel',
               'mode' => 'multiple',
               'required' => true,
               'actions' => array('select', 'create'),
               'preferred' => 'select',
               'question' => 'Welche Artikel werden berechnet?',
               'validation' => 'at_least_one',
               'missing_message' => 'Es ist kein Artikel ausgewählt – der Workflow ist unvollständig.',
               'resolver' => array('type' => 'select', 'label' => 'Artikelauswahl öffnen'),
               'hint' => 'Ein oder mehrere Artikel sind fuer die Rechnung notwendig.',
               'options' => array('Beratung 1 Stunde', 'Software Lizenz', 'Einrichtungspaket')
            ),
            array(
               'key' => 'payment_terms',
               'label' => 'Zahlungsbedingung',
               'mode' => 'single',
               'required' => true,
               'actions' => array('select'),
               'preferred' => 'select',
               'question' => 'Welche Zahlungsbedingung ist ausgewählt?',
               'validation' => 'exactly_one',
               'missing_message' => 'Keine Zahlungsbedingung ausgewählt – der Workflow ist unvollständig.',
               'resolver' => array('type' => 'select', 'label' => 'Zahlungsbedingung auswählen'),
               'hint' => 'Wähle die für diese Rechnung geltende Zahlungsfrist.',
               'options' => array(
                  array('value' => 'sofort', 'label' => 'Sofort fällig'),
                  array('value' => '7_tage', 'label' => '7 Tage netto'),
                  array('value' => '14_tage', 'label' => '14 Tage netto'),
                  array('value' => '30_tage', 'label' => '30 Tage netto')
               )
            ),
            array(
               'key' => 'invoice_number',
               'label' => 'Rechnungsnummer',
               'mode' => 'single',
               'required' => true,
               'actions' => array('form'),
               'preferred' => 'form',
               'question' => 'Welche eindeutige Rechnungsnummer wird verwendet?',
               'validation' => 'not_empty',
               'missing_message' => 'Keine Rechnungsnummer vorhanden – der Workflow ist unvollständig.',
               'resolver' => array('type' => 'form', 'label' => 'Rechnungsnummer erzeugen'),
               'depends_on' => 'customer',
               'event' => 'Rechnungsnummer erzeugt',
               'hint' => 'Erzeuge oder übernimm eine eindeutige Rechnungsnummer für den ausgewählten Kunden.'
            )
         ),
         'finish' => array(
            'label' => 'Rechnung erzeugen'
         )
      );
   }

private function ticket_demo_definition() {
      return array(
         'workflow_key' => 'ticket_demo',
         'title' => 'Kontaktanfrage bearbeiten',
         'result' => 'Kontaktanfrage-Antwort',
         'description' => 'Bearbeitet Kontaktanfragen aus dbxContact ueber Modul-Binding contact_reply.',
         'bind_ref' => 'dbxContact|contact_reply',
         'needs' => array(
            array(
               'key' => 'contact_request',
               'label' => 'Kontaktanfrage',
               'mode' => 'single',
               'required' => true,
               'actions' => array('select'),
               'preferred' => 'select',
               'hint' => 'Offene Kontaktanfrage aus dbxContact auswaehlen.',
               'options' => array()
            ),
            array(
               'key' => 'status',
               'label' => 'Status',
               'mode' => 'single',
               'required' => true,
               'actions' => array('select'),
               'preferred' => 'select',
               'hint' => 'Bearbeitungsstatus in dbxContact (offen, beantwortet, geschlossen).',
               'options' => array()
            ),
            array(
               'key' => 'internal_note',
               'label' => 'Interne Notiz',
               'mode' => 'single',
               'required' => false,
               'actions' => array('form'),
               'hint' => 'Optional: interne Bearbeitungsnotiz (nur im Workflow gespeichert).'
            ),
            array(
               'key' => 'customer_reply',
               'label' => 'Rueckmeldung',
               'mode' => 'single',
               'required' => true,
               'actions' => array('form'),
               'hint' => 'Antwort an den Anfragenden. Wird in dbxContact als reply_text gespeichert.'
            ),
            array(
               'key' => 'send_mail',
               'label' => 'Antwort per E-Mail senden',
               'mode' => 'single',
               'required' => true,
               'actions' => array('select'),
               'preferred' => 'select',
               'hint' => 'Versand nur wenn in dbxContact mail_on_reply aktiviert ist.',
               'options' => array(
                  array('value' => '1', 'label' => 'Ja, E-Mail senden'),
                  array('value' => '0', 'label' => 'Nein, nur speichern'),
               )
            )
         ),
         'finish' => array(
            'label' => 'Kontaktanfrage abschliessen'
         )
      );
   }

private function shop_article_publish_definition() {
      return array(
         'workflow_key' => 'shop_article_publish',
         'title' => 'Neuen Artikel im Shop veroeffentlichen',
         'result' => 'Neuer Artikel ist im Shop veroeffentlicht',
         'description' => 'Führt durch Artikelanlage, Gruppenzuordnung, Bilder, Verkaufsdaten und Freigabe. Die eigentliche Bearbeitung erfolgt in den dafuer vorgesehenen Shop-Formularen.',
         'needs' => array(
            array(
               'key' => 'product_create',
               'label' => 'Artikel anlegen',
               'mode' => 'single',
               'required' => true,
               'actions' => array('module'),
               'preferred' => 'module',
               'hint' => 'Lege den Artikel im Shop-Artikel-Formular an. Danach hier bestaetigen und den gespeicherten Artikel im naechsten Schritt auswaehlen.',
               'event' => 'Artikel angelegt',
               'complete_label' => 'Artikel wurde im Formular gespeichert.',
               'module_links' => array(
                  array(
                     'label' => 'Artikel anlegen',
                     'icon' => 'bi-plus-square',
                     'url' => '?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=0&workflow_preset=shop_article_publish',
                     'title' => 'Neuen Artikel anlegen',
                     'width' => '92%',
                     'height' => '90%'
                  )
               )
            ),
            array(
               'key' => 'product',
               'label' => 'Gespeicherten Artikel auswaehlen',
               'mode' => 'single',
               'required' => true,
               'actions' => array('select'),
               'preferred' => 'select',
               'hint' => 'Waehle den gerade gespeicherten Artikel. Dadurch kennt der Workflow den Datensatz fuer alle folgenden Aufgaben.',
               'event' => 'Artikel ausgewaehlt',
               'source' => array(
                  'dd' => 'dbxShop|shopProduct',
                  'where' => array('trash' => 0, 'active' => 1),
                  'fields' => array('id', 'sku', 'title'),
                  'value' => 'id',
                  'label' => '{sku} - {title}',
                  'order_field' => 'id',
                  'order_dir' => 'DESC',
                  'limit' => 300
               )
            ),
            array(
               'key' => 'product_group',
               'label' => 'Artikelgruppe zuordnen',
               'mode' => 'single',
               'required' => true,
               'actions' => array('module'),
               'preferred' => 'module',
               'hint' => 'Ordne den Artikel einer passenden Artikelgruppe zu. Falls die Gruppe noch fehlt, kannst du die Gruppenverwaltung direkt oeffnen.',
               'event' => 'Artikelgruppe zugeordnet',
               'depends_on' => 'product',
               'complete_label' => 'Artikelgruppe ist zugeordnet.',
               'module_links' => array(
                  array(
                     'label' => 'Artikel bearbeiten',
                     'icon' => 'bi-pencil-square',
                     'url' => '?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id={product}',
                     'title' => 'Artikel bearbeiten',
                     'width' => '92%',
                     'height' => '90%'
                  ),
                  array(
                     'label' => 'Artikelgruppen',
                     'icon' => 'bi-diagram-3',
                     'url' => '?dbx_modul=dbxShop_admin&dbx_run1=groups',
                     'title' => 'Artikelgruppen bearbeiten',
                     'width' => '54%',
                     'height' => '84%'
                  )
               )
            ),
            array(
               'key' => 'images',
               'label' => 'Bilder zuordnen',
               'mode' => 'single',
               'required' => true,
               'actions' => array('module'),
               'preferred' => 'module',
               'hint' => 'Ordne Primaerbild und weitere Artikelbilder im Artikel-Formular zu. Dateien bleiben im CMS-Medienbrowser; hier wird nur die Zuordnung gepflegt.',
               'event' => 'Bilder zugeordnet',
               'depends_on' => 'product_group',
               'complete_label' => 'Artikelbilder sind geprueft und zugeordnet.',
               'module_links' => array(
                  array(
                     'label' => 'Artikelbilder bearbeiten',
                     'icon' => 'bi-images',
                     'url' => '?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id={product}',
                     'title' => 'Artikelbilder bearbeiten',
                     'width' => '92%',
                     'height' => '90%'
                  )
               )
            ),
            array(
               'key' => 'sales_data',
               'label' => 'Preis, Versand und Sichtbarkeit pruefen',
               'mode' => 'single',
               'required' => true,
               'actions' => array('module'),
               'preferred' => 'module',
               'hint' => 'Pruefe Bruttopreis, MwSt.-Quelle, Versand-Quelle, Lagerbestand und ob der Artikel aktiv ist.',
               'event' => 'Verkaufsdaten geprueft',
               'depends_on' => 'images',
               'complete_label' => 'Preis, Versand, Lager und Sichtbarkeit sind korrekt.',
               'module_links' => array(
                  array(
                     'label' => 'Artikel pruefen',
                     'icon' => 'bi-clipboard-check',
                     'url' => '?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id={product}',
                     'title' => 'Artikel pruefen',
                     'width' => '92%',
                     'height' => '90%'
                  )
               )
            ),
            array(
               'key' => 'final_check',
               'label' => 'Freigabe',
               'mode' => 'single',
               'required' => true,
               'actions' => array('select'),
               'preferred' => 'select',
               'hint' => 'Bestaetige, wie der Artikel nach dem Workflow behandelt werden soll. Beim Abschluss markiert der Workflow den Artikel als aktiv.',
               'event' => 'Freigabe entschieden',
               'depends_on' => 'sales_data',
               'options' => array(
                  array('value' => 'draft', 'label' => 'Als Entwurf vorbereiten'),
                  array('value' => 'shop', 'label' => 'Im Shop veroeffentlichen')
               )
            )
         ),
         'finish' => array(
            'label' => 'Artikel im Shop freigeben'
         )
      );
   }

private function shop_ebay_publish_definition() {
      return array(
         'workflow_key' => 'shop_ebay_publish',
         'title' => 'Bestehenden Artikel auf eBay veroeffentlichen',
         'result' => 'Bestehender Artikel ist bei eBay veroeffentlicht',
         'description' => 'Führt durch die wenigen notwendigen Aufgaben: Artikel auswaehlen, eBay-Daten bestaetigen, Export ausfuehren und Statusmeldungen pruefen.',
         'needs' => array(
             array(
                'key' => 'product',
                'label' => 'Artikel auswaehlen',
                'kind' => 'input',
                'automation' => 'manual',
                'mode' => 'single',
               'required' => true,
               'actions' => array('select'),
               'preferred' => 'select',
               'hint' => 'Waehle den bestehenden Shop-Artikel, der bei eBay veroeffentlicht werden soll.',
               'event' => 'Artikel ausgewaehlt',
               'source' => array(
                  'dd' => 'dbxShop|shopProduct',
                  'where' => array('trash' => 0, 'active' => 1),
                  'fields' => array('id', 'sku', 'title'),
                  'value' => 'id',
                  'label' => '{sku} - {title}',
                  'order_field' => 'sorter',
                  'order_dir' => 'ASC',
                  'limit' => 300
                )
             ),
             array(
                'key' => 'readiness_check',
                'label' => 'eBay-Bereitschaft automatisch prüfen',
                'kind' => 'check',
                'automation' => 'observe',
                'mode' => 'single',
                'required' => true,
                'actions' => array('select'),
                'preferred' => 'select',
                'hint' => 'Prüft automatisch, ob Channel, Zugang, Zuordnung, Kategorie und Business Policies grundsätzlich vollständig sind. Nur wenn keine eindeutige Prüfung möglich ist, bleibt eine manuelle Auswahl sichtbar.',
                'event' => 'Technische Bereitschaft geprüft',
                'depends_on' => 'product',
                'options' => array(
                   array('value' => 'ready', 'label' => 'Grunddaten vollständig'),
                   array('value' => 'needs_work', 'label' => 'Daten oder Zuordnung ergänzen')
                )
             ),
             array(
                'key' => 'ebay_data',
                'label' => 'eBay-Daten bearbeiten und bestaetigen',
                'kind' => 'action',
                'automation' => 'manual',
                'mode' => 'single',
               'required' => true,
               'actions' => array('module'),
               'preferred' => 'module',
               'hint' => 'Pruefe und bestaetige alle eBay-relevanten Daten: eBay-Channel, Kategorie, Pflichtmerkmale, Business Policies, Preis, Versand und ggf. abweichende Channel-Werte.',
               'event' => 'eBay-Daten bestaetigt',
                'depends_on' => 'readiness_check',
               'complete_label' => 'eBay-Daten sind geprueft und gespeichert.',
               'module_links' => array(
                  array(
                     'label' => 'Artikel bearbeiten',
                     'icon' => 'bi-pencil-square',
                     'url' => '?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id={product}',
                     'title' => 'Artikel bearbeiten',
                     'width' => '92%',
                     'height' => '90%'
                  ),
                  array(
                     'label' => 'eBay-Mapping',
                     'icon' => 'bi-sliders2',
                     'url' => '?dbx_modul=dbxShop_admin&dbx_run1=product_channel_mapping&id={product}&channel=ebay',
                     'title' => 'Channel-Mapping: eBay',
                     'width' => '68%',
                     'height' => '84%'
                  )
               )
            ),
             array(
                'key' => 'export_run',
                'label' => 'Export durchfuehren',
                'kind' => 'action',
                'automation' => 'manual',
               'mode' => 'single',
               'required' => true,
               'actions' => array('module'),
               'preferred' => 'module',
               'hint' => 'Fuehre den eBay-Export ueber den Shop-Connector aus. Der Connector speichert Exportstatus, Rueckmeldung und externe IDs beim Artikel-Channel.',
               'event' => 'Export ausgefuehrt',
               'depends_on' => 'ebay_data',
               'complete_label' => 'eBay-Export wurde ausgefuehrt.',
               'module_links' => array(
                  array(
                     'label' => 'eBay exportieren',
                     'icon' => 'bi-broadcast',
                     'url' => '?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id={product}&export_channel=ebay',
                     'title' => 'eBay exportieren',
                     'width' => '92%',
                     'height' => '90%'
                  )
               )
            ),
             array(
                'key' => 'status_check',
                'label' => 'Statusmeldungen pruefen',
                'kind' => 'decision',
                'automation' => 'observe',
               'mode' => 'single',
               'required' => true,
               'actions' => array('select'),
               'preferred' => 'select',
                'hint' => 'Die Connector-Rückmeldung wird automatisch ausgewertet. Wenn noch keine eindeutige Rückmeldung vorliegt, kann der Status manuell eingeordnet werden.',
               'event' => 'Status geprueft',
               'depends_on' => 'export_run',
               'options' => array(
                  array('value' => 'ok', 'label' => 'Erfolgreich / Listing-ID vorhanden'),
                  array('value' => 'open', 'label' => 'Offen / spaeter erneut pruefen'),
                  array('value' => 'error', 'label' => 'Fehler vorhanden')
               )
            ),
             array(
                'key' => 'ebay_view',
                'label' => 'eBay-Angebot ansehen',
                'kind' => 'check',
                'automation' => 'manual',
               'mode' => 'single',
               'required' => false,
               'actions' => array('module'),
               'preferred' => 'module',
               'hint' => 'Optionaler Kontrollschritt: Oeffne das veroeffentlichte eBay-Angebot und pruefe, ob Titel, Bilder, Preis, Versand und Beschreibung richtig angezeigt werden.',
               'event' => 'eBay-Angebot angesehen',
               'depends_on' => 'status_check',
               'depends_value' => 'ok',
               'complete_label' => 'eBay-Angebot wurde angesehen oder bewusst uebersprungen.',
               'module_links' => array(
                  array(
                     'label' => 'eBay-Mapping',
                     'icon' => 'bi-sliders2',
                     'url' => '?dbx_modul=dbxShop_admin&dbx_run1=product_channel_mapping&id={product}&channel=ebay',
                     'title' => 'Channel-Mapping: eBay',
                     'width' => '68%',
                     'height' => '84%'
                  )
               )
            )
         ),
         'finish' => array(
            'label' => 'eBay-Veroeffentlichung abschliessen'
         )
      );
   }

public function default_definition_text() {
      return json_encode($this->default_definition(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

public function ticket_demo_definition_text() {
      return json_encode($this->ticket_demo_definition(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

private function normalize_key($value) {
      return dbxWorkflowValue::key($value);
   }

private function normalize_validation_rule($rule, $mode = 'single') {
      $rule = strtolower(trim((string)$rule));
      $allowed = array('not_empty', 'exactly_one', 'at_least_one', 'positive_integer', 'confirmed');
      if (!in_array($rule, $allowed, true)) {
         $rule = ((string)$mode === 'multiple') ? 'at_least_one' : 'exactly_one';
      }
      return $rule;
   }

private function requirement_question(array $need): string {
      $question = trim((string)($need['question'] ?? ''));
      if ($question !== '') return $question;
      $label = trim((string)($need['label'] ?? $need['key'] ?? 'Angabe'));
      return 'Ist „' . $label . '“ vollständig und richtig?';
   }

private function requirement_missing_message(array $need): string {
      $message = trim((string)($need['missing_message'] ?? ''));
      if ($message !== '') return $message;
      $label = trim((string)($need['label'] ?? $need['key'] ?? 'Angabe'));
      return $label . ' fehlt oder ist nicht vollständig – der Workflow ist unvollständig.';
   }

private function requirement_resolver(array $need): array {
      $resolver = is_array($need['resolver'] ?? null) ? $need['resolver'] : array();
      $actions = array_values((array)($need['actions'] ?? array('form')));
      $type = (string)($resolver['type'] ?? ($need['preferred'] ?? ($actions[0] ?? 'form')));
      if (!in_array($type, array('form', 'select', 'create', 'module'), true)) $type = 'form';
      $labels = array(
         'form' => 'Eingabeformular öffnen',
         'select' => 'Auswahlformular öffnen',
         'create' => 'Erfassungsformular öffnen',
         'module' => 'Modulformular öffnen',
      );
      $label = trim((string)($resolver['label'] ?? ''));
      if ($label === '') $label = $labels[$type];
      return array_merge($resolver, array('type' => $type, 'label' => $label));
   }

private function derived_checks(array $needs, array $existing_checks = array()): array {
      $existing_by_key = array();
      foreach ($existing_checks as $check) {
         if (!is_array($check)) continue;
         $key = $this->normalize_key($check['key'] ?? '');
         if ($key !== '') $existing_by_key[$key] = $check;
      }

      $checks = array();
      foreach ($needs as $need) {
         if (!is_array($need)) continue;
         $key = $this->normalize_key($need['key'] ?? '');
         $existing = $existing_by_key[$key] ?? array();
         $existing_resolver = is_array($existing['resolver'] ?? null) ? $existing['resolver'] : array();
         $resolver = array_merge($existing_resolver, $this->requirement_resolver($need));
         $checks[] = array_merge($existing, array(
            'key' => $key,
            'label' => (string)($need['label'] ?? $need['key'] ?? ''),
            'question' => $this->requirement_question($need),
            'validation' => $this->normalize_validation_rule($need['validation'] ?? '', $need['mode'] ?? 'single'),
            'required' => !empty($need['required']),
            'missing_message' => $this->requirement_missing_message($need),
            'resolver' => $resolver,
         ));
      }
      return $checks;
   }

private function parse_need_line($line) {
      $line = trim((string)$line);
      if ($line === '' || strpos($line, '=') === false) return array();

      list($name, $rule_text) = explode('=', $line, 2);
      $name = trim($name);
      $need = array(
         'key' => $this->normalize_key($name),
         'label' => $name,
         'mode' => 'single',
         'required' => true,
         'actions' => array(),
         'preferred' => '',
         'hint' => '',
         'options' => array()
      );

      foreach (explode('|', $rule_text) as $token) {
         $token = trim($token);
         if ($token === '') continue;

         if ($token === 'single' || $token === 'multiple' || $token === 'multiple') {
            $need['mode'] = ($token === 'single') ? 'single' : 'multiple';
            continue;
         }

         if ($token === 'required' || $token === 'need' || $token === 'must') {
            $need['required'] = true;
            continue;
         }

         if ($token === 'optional') {
            $need['required'] = false;
            continue;
         }

         if (strpos($token, 'label=') === 0) {
            $need['label'] = trim(substr($token, 6));
            continue;
         }

         if (strpos($token, 'hint=') === 0) {
            $need['hint'] = trim(substr($token, 5));
            continue;
         }

         if (strpos($token, 'options=') === 0) {
            $items = array_map('trim', explode(',', substr($token, 8)));
            $need['options'] = array_values(array_filter($items, 'strlen'));
            continue;
         }

         $preferred = false;
         if (substr($token, -1) === '!') {
            $preferred = true;
            $token = substr($token, 0, -1);
         }

         if (in_array($token, array('select', 'create', 'form', 'module'), true)) {
            $need['actions'][] = $token;
            if ($preferred) $need['preferred'] = $token;
         }
      }

      if (!$need['actions']) $need['actions'] = array('form');
      if (!$need['preferred']) $need['preferred'] = $need['actions'][0];

      return $need;
   }

public function normalize_definition($source, $workflow_key = '') {
      if (is_array($source)) {
         $definition = $source;
      } else {
         $text = trim((string)$source);
         $json = $this->read_json($text, null);
         if (is_array($json)) {
            $definition = $json;
         } else {
            $definition = array('needs' => array());
            foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
               if (stripos(trim($line), 'result=') === 0) {
                  $definition['result'] = trim(substr(trim($line), 7));
                  continue;
               }
               $need = $this->parse_need_line($line);
               if ($need) $definition['needs'][] = $need;
            }
         }
      }

      if (empty($definition['workflow_key'])) $definition['workflow_key'] = $workflow_key ?: 'workflow';
      if (empty($definition['title'])) $definition['title'] = $definition['result'] ?? 'Workflow';
      if (empty($definition['result'])) $definition['result'] = $definition['result_label'] ?? $definition['title'];
      if (empty($definition['description'])) $definition['description'] = '';
      if (empty($definition['finish']) || !is_array($definition['finish'])) {
         $definition['finish'] = array('label' => $definition['result'] . ' erzeugen');
      }

      $needs = array();
      foreach ((array)($definition['needs'] ?? array()) as $need) {
         if (!is_array($need)) continue;
         $key = $this->normalize_key($need['key'] ?? ($need['label'] ?? ''));
         if ($key === '') continue;

         $actions = $need['actions'] ?? array('form');
         if (!is_array($actions)) $actions = array_filter(array_map('trim', explode(',', (string)$actions)));

         $mode = (($need['mode'] ?? 'single') === 'multiple') ? 'multiple' : 'single';
         $normalized_need = array_merge($need, array(
             'key' => $key,
             'label' => (string)($need['label'] ?? $key),
             'kind' => in_array((string)($need['kind'] ?? ''), array('input', 'action', 'check', 'decision'), true)
                ? (string)$need['kind']
                : (in_array('module', $actions, true) ? 'action' : 'input'),
             'automation' => ((string)($need['automation'] ?? 'manual') === 'observe') ? 'observe' : 'manual',
             'mode' => $mode,
            'required' => array_key_exists('required', $need) ? (bool)$need['required'] : true,
            'actions' => array_values(array_intersect($actions, array('select', 'create', 'form', 'module'))) ?: array('form'),
            'preferred' => (string)($need['preferred'] ?? ''),
            'hint' => (string)($need['hint'] ?? ''),
            'options' => array_values((array)($need['options'] ?? array())),
            'event' => (string)($need['event'] ?? ($need['result_event'] ?? '')),
            'depends_on' => $this->normalize_key($need['depends_on'] ?? ''),
            'depends_value' => (string)($need['depends_value'] ?? ''),
            'validation' => $this->normalize_validation_rule($need['validation'] ?? '', $mode),
            'question' => $this->requirement_question(array_merge($need, array('key' => $key))),
            'missing_message' => $this->requirement_missing_message(array_merge($need, array('key' => $key))),
            'resolver' => $this->requirement_resolver($need),
         ));
         $needs[] = $normalized_need;
      }

      $definition['needs'] = $needs;
      // Die Prüfung ist absichtlich abgeleitet. Ein Schritt wird nur einmal
      // gepflegt und erscheint dadurch automatisch in der Prüfliste.
      $definition['checks'] = $this->derived_checks($needs, (array)($definition['checks'] ?? array()));
      if (!isset($definition['bind_ref'])) {
         $definition['bind_ref'] = '';
      }
      return $definition;
   }

public function load_definition($workflow_key, bool $active_only = true) {
      $workflow_key = $workflow_key ?: (string)dbx()->get_cfg('dbxWorkflow', 'default_workflow');
      if (!$workflow_key) $workflow_key = 'invoice_demo';

      $where = array('workflow_key' => $workflow_key);
      if ($active_only) $where['active'] = 1;
      $rows = $this->db()->select($this->dd_definition, $where, '*', 'id', 'DESC', '', 1, 0, 0);
      if (is_array($rows) && isset($rows[0])) {
         $row = $rows[0];
         $definition = $this->normalize_definition($row['definition_json'] ?? '', $workflow_key);
         $definition['workflow_key'] = $row['workflow_key'] ?? $workflow_key;
         $definition['title'] = $row['title'] ?? ($definition['title'] ?? $workflow_key);
         $definition['result'] = $row['result_label'] ?? ($definition['result'] ?? $definition['title']);
         $definition['description'] = $row['description'] ?? ($definition['description'] ?? '');
         return $this->enrich_definition($definition);
      }

      // Laufzeitdefinitionen stammen ausschliesslich aus der Verwaltung.
      // Built-ins werden bei der Installation einmalig als editierbare
      // Datensaetze angelegt und duerfen hier weder reaktiviert noch ueber
      // eine Admin-Aenderung geschrieben werden.
      return array();
   }

private function definition_record(array $definition): array {
      return array(
         'workflow_key' => $definition['workflow_key'],
         'title' => $definition['title'],
         'result_label' => $definition['result'],
         'description' => $definition['description'],
         'definition_json' => json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
         'active' => 1
      );
   }

private function ensure_definition(array $definition): int {
      $db = $this->db();
      $workflow_key = (string)($definition['workflow_key'] ?? '');
      if ($workflow_key === '') {
         return 0;
      }

      $rows = $db->select($this->dd_definition, array('workflow_key' => $workflow_key), array('id'), 'id', 'DESC', '', 1, 0, 0);
      if (isset($rows[0]['id'])) {
         return (int)$rows[0]['id'];
      }

      return (int)$db->insert($this->dd_definition, $this->definition_record($definition), 0, 1, 1, 1);
   }

public function seed_default_definition() {
      return $this->ensure_definition($this->default_definition());
   }

public function seed_ticket_demo_definition() {
      return $this->ensure_definition($this->ticket_demo_definition());
   }

public function seed_shop_definitions() {
      return $this->ensure_shop_definitions();
   }

public function ensure_shop_definitions() {
      $this->ensure_definition($this->shop_article_publish_definition());
      return $this->ensure_definition($this->shop_ebay_publish_definition());
   }

public function seed_demo_definitions() {
      dbx()->get_include_obj('dbxWorkflowBindRegistry', 'dbxWorkflow')->seed_default_binds();
      $this->ensure_definition($this->default_definition());
      $this->ensure_definition($this->ticket_demo_definition());
      return $this->ensure_shop_definitions();
   }
}
