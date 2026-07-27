<?php
namespace dbx\dbxWorkflow;

class dbxWorkflowEngine {

   private $ddDefinition = 'dbxWorkflow|workflowDefinition';
   private $ddInstance   = 'dbxWorkflow|workflowInstance';
   private $ddStep       = 'dbxWorkflow|workflowStep';

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   private function h($value) {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function now() {
      return date('Y-m-d H:i:s');
   }

   private function read_json($value, $default = array()) {
      $value = trim((string)$value);
      if ($value === '') return $default;
      $data = json_decode($value, true);
      return is_array($data) ? $data : $default;
   }

   private function write_json($value) {
      return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

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

   private function workflowModule() {
      return dbx()->get_include_obj('dbxWorkflowModule', 'dbxWorkflow');
   }

   private function workflow_bar_data(string $topic, string $actions, string $title, string $subtitle = ''): array {
      $help = dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin');
      if (is_object($help) && method_exists($help, 'moduleBarTemplateData')) {
         $data = $help->moduleBarTemplateData($topic, $actions, $title, 'bi-diagram-3', $subtitle);
         if (is_array($data) && $data) return $data;
      }

      return array(
         'bar_class' => 'dbx-module-bar',
         'bar_title_class' => 'dbx-module-bar-titleblock',
         'bar_actions_class' => 'dbx-module-bar-actions',
         'bar_title' => $title,
         'bar_icon' => 'bi-diagram-3',
         'bar_subtitle' => $subtitle,
         'bar_title_pre' => '',
         'bar_title_heading_attrs' => '',
         'bar_middle' => '',
         'bar_extra' => '',
         'bar_actions' => $actions,
      );
   }

   /**
    * Erstellt ein geschuetztes Laufzeitformular fuer eine Workflow-Instanz.
    *
    * Die Formular-ID enthaelt Instanz und Formulartyp. So kann kein Token
    * zwischen zwei parallelen Workflows oder zwischen Schritt und Abschluss
    * wiederverwendet werden. Die fachliche Validierung bleibt in apply_submit(),
    * waehrend dbxForm Submit-Erkennung, CSRF-Schutz und Rendering uebernimmt.
    *
    * @param int    $iid Workflow-Instanz
    * @param string $kind step oder review
    * @param string $template Template ohne Modul-Praefix
    * @param array  $replacements Templatewerte
    *
    * @return \dbxForm
    */
   private function runtime_form(int $iid, string $kind, string $template, array $replacements = array()) {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('workflow-' . $kind . '-' . max(0, $iid), $template);
      $form->_action = $this->instance_action_url(
         '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . max(0, $iid),
         $iid
      );
      $form->_msg_info = '';
      foreach ($replacements as $key => $value) {
         $form->add_rep((string)$key, $value);
      }
      return $form;
   }

   /** Scope fuer alle zustandsaendernden Aktionen einer Instanz. */
   private function instance_action_scope(int $iid): string {
      return 'dbxWorkflow.instance.' . max(0, $iid);
   }

   /** Ergaenzt eine URL um das vorhandene sessiongebundene dbXapp-Aktionstoken. */
   private function action_url(string $url, string $scope): string {
      $separator = strpos($url, '?') === false ? '?' : '&';
      return $url . $separator . 'dbx_token=' . rawurlencode(dbx()->action_token($scope));
   }

   private function instance_action_url(string $url, int $iid): string {
      return $this->action_url($url, $this->instance_action_scope($iid));
   }

   /** Prueft nur die Absicht der Aktion; Modul- und DD-Rechte bleiben zusaetzlich aktiv. */
   private function has_instance_action_token(int $iid): bool {
      $token = (string)dbx()->get_modul_var('dbx_token', '', 'varchar|max=128');
      return dbx()->check_action_token($this->instance_action_scope($iid), $token);
   }

   /**
    * Merkt oeffentliche Instanzen in genau der Session, die sie angelegt hat.
    *
    * DD-Owner-Rechte greifen absichtlich erst ab UID > 0. Fuer Gast-Workflows
    * ist diese enge Sessionbindung daher die zusaetzliche Modulpruefung.
    */
   private function register_guest_instance(int $iid): void {
      if ($iid <= 0 || (int)dbx()->user() > 0) return;
      $ids = dbx()->get_session_var('instance_ids', array(), 'access', 'dbxWorkflow');
      $ids = is_array($ids) ? array_map('intval', $ids) : array();
      $ids[] = $iid;
      $ids = array_slice(array_values(array_unique(array_filter($ids))), -100);
      dbx()->set_session_var('instance_ids', $ids, 'access', 'dbxWorkflow');
   }

   private function guest_can_access_instance(int $iid): bool {
      if ($iid <= 0 || (int)dbx()->user() > 0) return false;
      $ids = dbx()->get_session_var('instance_ids', array(), 'access', 'dbxWorkflow');
      return is_array($ids) && in_array($iid, array_map('intval', $ids), true);
   }

   /** Authentifizierte Nutzer schreiben ueber DD-Owner-Rechte; Gaeste nach Sessionpruefung. */
   private function instance_write_access(): int {
      return (int)dbx()->user() > 0 ? 1 : 0;
   }

   private function unavailable_definition_message(string $workflowKey): string {
      $workflowKey = trim($workflowKey);
      $detail = $workflowKey !== '' ? ' <code>' . $this->h($workflowKey) . '</code>' : '';
      return $this->tpl()->get_tpl('dbx|alert-warning', array(
         'msg' => 'Workflow' . $detail . ' wurde nicht gefunden oder ist nicht aktiv.',
      ));
   }

   private function enrich_definition(array $definition, array $values = array()) {
      return $this->workflowModule()->enrichDefinition($definition, $values);
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
         'description' => 'Fuehrt durch Artikelanlage, Gruppenzuordnung, Bilder, Verkaufsdaten und Freigabe. Die eigentliche Bearbeitung erfolgt in den dafuer vorgesehenen Shop-Formularen.',
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
         'description' => 'Fuehrt durch die wenigen notwendigen Aufgaben: Artikel auswaehlen, eBay-Daten bestaetigen, Export ausfuehren und Statusmeldungen pruefen.',
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
      $value = strtolower(trim((string)$value));
      $value = preg_replace('/[^a-z0-9_]+/', '_', $value);
      return trim($value, '_');
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

   private function derived_checks(array $needs, array $existingChecks = array()): array {
      $existingByKey = array();
      foreach ($existingChecks as $check) {
         if (!is_array($check)) continue;
         $key = $this->normalize_key($check['key'] ?? '');
         if ($key !== '') $existingByKey[$key] = $check;
      }

      $checks = array();
      foreach ($needs as $need) {
         if (!is_array($need)) continue;
         $key = $this->normalize_key($need['key'] ?? '');
         $existing = $existingByKey[$key] ?? array();
         $existingResolver = is_array($existing['resolver'] ?? null) ? $existing['resolver'] : array();
         $resolver = array_merge($existingResolver, $this->requirement_resolver($need));
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

      list($name, $ruleText) = explode('=', $line, 2);
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

      foreach (explode('|', $ruleText) as $token) {
         $token = trim($token);
         if ($token === '') continue;

         if ($token === 'single' || $token === 'multiple' || $token === 'multible') {
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

   public function normalize_definition($source, $workflowKey = '') {
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

      if (empty($definition['workflow_key'])) $definition['workflow_key'] = $workflowKey ?: 'workflow';
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
         $normalizedNeed = array_merge($need, array(
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
         $needs[] = $normalizedNeed;
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

   public function load_definition($workflowKey, bool $activeOnly = true) {
      $workflowKey = $workflowKey ?: (string)dbx()->get_config('dbxWorkflow', 'default_workflow');
      if (!$workflowKey) $workflowKey = 'invoice_demo';

      $where = array('workflow_key' => $workflowKey);
      if ($activeOnly) $where['active'] = 1;
      $rows = $this->db()->select($this->ddDefinition, $where, '*', 'id', 'DESC', '', 1, 0, 0);
      if (is_array($rows) && isset($rows[0])) {
         $row = $rows[0];
         $definition = $this->normalize_definition($row['definition_json'] ?? '', $workflowKey);
         $definition['workflow_key'] = $row['workflow_key'] ?? $workflowKey;
         $definition['title'] = $row['title'] ?? ($definition['title'] ?? $workflowKey);
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
      $workflowKey = (string)($definition['workflow_key'] ?? '');
      if ($workflowKey === '') {
         return 0;
      }

      $rows = $db->select($this->ddDefinition, array('workflow_key' => $workflowKey), array('id'), 'id', 'DESC', '', 1, 0, 0);
      if (isset($rows[0]['id'])) {
         return (int)$rows[0]['id'];
      }

      return (int)$db->insert($this->ddDefinition, $this->definition_record($definition), 0, 1, 1, 1);
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
      dbx()->get_include_obj('dbxWorkflowBindRegistry', 'dbxWorkflow')->seedDefaultBinds();
      $this->ensure_definition($this->default_definition());
      $this->ensure_definition($this->ticket_demo_definition());
      return $this->ensure_shop_definitions();
   }

   public function start($workflowKey) {
      $definition = $this->load_definition($workflowKey);
      if (!$definition) return $this->unavailable_definition_message((string)$workflowKey);
      $startToken = (string)dbx()->get_modul_var('dbx_token', '', 'varchar|max=128');
      if (!dbx()->check_action_token('dbxWorkflow.start', $startToken)) {
         // Alte Direktlinks bleiben aufrufbar, mutieren aber nicht mehr blind:
         // Die Uebersicht liefert einen frischen, bestaetigbaren Startlink.
         return $this->overview((string)$workflowKey);
      }

      $uid = (int)dbx()->user();
      $values = $this->workflowModule()->prefillStart(
         $definition,
         (int)dbx()->get_modul_var('rid', 0, 'int')
      );

      $record = array(
         'workflow_key' => $definition['workflow_key'],
         'result_label' => $definition['result'],
         'status' => 'running',
         'current_need' => '',
         'percent' => 0,
         'step_percent' => 0,
         'message' => 'Workflow gestartet.',
         'definition_json' => $this->write_json($definition),
         'data_json' => $this->write_json($values),
         'owner' => $uid,
      );

      $db = $this->db();
      $iid = ($db->insert($this->ddInstance, $record, 1, 1, 1, 1) === 1) ? (int)$db->get_insert_id() : 0;
      if ($iid <= 0) {
         return $this->tpl()->get_tpl('dbx|alert-warning', array('msg' => 'Workflow konnte nicht gestartet werden.'));
      }
      $this->register_guest_instance($iid);
      return $this->render($iid, true);
   }

   private function load_instance($iid) {
      $iid = (int)$iid;
      if ($iid <= 0) return array();

      if ((int)dbx()->user() > 0) {
         // DD liefert fuer normale Nutzer automatisch den Owner-Filter und
         // fuer Administratoren den vollen, konfigurierten Zugriff.
         $rows = $this->db()->select($this->ddInstance, array('id' => $iid), '*', 'id', 'DESC', '', 1, 0, 1);
      } else {
         if (!$this->guest_can_access_instance($iid)) return array();
         $rows = $this->db()->select($this->ddInstance, array('id' => $iid, 'owner' => 0), '*', 'id', 'DESC', '', 1, 0, 0);
      }

      return (is_array($rows) && isset($rows[0])) ? $rows[0] : array();
   }

   private function values_from_instance($instance) {
      return $this->read_json($instance['data_json'] ?? '', array());
   }

   private function validate_need_value($need, $value): bool {
      if (is_array($value) && !empty($value['skipped'])) return empty($need['required']);

      $rule = $this->normalize_validation_rule($need['validation'] ?? '', $need['mode'] ?? 'single');
      $items = is_array($value)
         ? array_values(array_filter($value, fn($item) => trim((string)$item) !== ''))
         : (trim((string)$value) !== '' ? array($value) : array());

      $allowed = array();
      foreach ((array)($need['options'] ?? array()) as $option) {
         $optionValue = is_array($option) ? (string)($option['value'] ?? '') : (string)$option;
         if ($optionValue !== '') $allowed[] = $optionValue;
      }
      $onlyAllowedValues = function(array $selected) use ($allowed): bool {
         if (!$allowed) return true;
         foreach ($selected as $item) {
            if (!in_array((string)$item, $allowed, true)) return false;
         }
         return true;
      };
      $actions = (array)($need['actions'] ?? array());
      $enforceAllowed = in_array('select', $actions, true)
         && !array_intersect($actions, array('create', 'form', 'module'));

      if ($rule === 'positive_integer') {
         return count($items) === 1 && filter_var($items[0], FILTER_VALIDATE_INT) !== false && (int)$items[0] > 0;
      }
      if ($rule === 'confirmed') {
         if (count($items) !== 1) return false;
         return in_array(strtolower(trim((string)$items[0])), array('1', 'true', 'yes', 'ja', 'ok', 'confirmed', 'bestaetigt', 'bestätigt'), true);
      }
      if ($rule === 'exactly_one') return count($items) === 1 && (!$enforceAllowed || $onlyAllowedValues($items));
      if ($rule === 'at_least_one') return count($items) >= 1 && (!$enforceAllowed || $onlyAllowedValues($items));
      return count($items) >= 1;
   }

   private function is_done($values, $need) {
      $key = $need['key'];
      if (!array_key_exists($key, $values)) return false;
      return $this->validate_need_value($need, $values[$key]);
   }

   private function value_matches($actual, $expected) {
      $expected = trim((string)$expected);
      if ($expected === '') return true;
      if (is_array($actual) && !empty($actual['skipped'])) return false;
      if (is_array($actual)) {
         foreach ($actual as $item) {
            if (trim((string)$item) === $expected) return true;
         }
         return false;
      }
      return trim((string)$actual) === $expected;
   }

   private function dependency_has_value(string $key, array $values): bool {
      if ($key === '' || !array_key_exists($key, $values)) return false;
      $value = $values[$key];
      if (is_array($value) && !empty($value['skipped'])) return false;
      if (is_array($value)) {
         return count(array_filter($value, fn($item) => trim((string)$item) !== '')) > 0;
      }
      return trim((string)$value) !== '';
   }

   private function need_is_applicable($need, $values) {
      $dependsOn = $this->normalize_key($need['depends_on'] ?? '');
      if ($dependsOn === '') return true;
      if (!$this->dependency_has_value($dependsOn, $values)) return false;
      return $this->value_matches($values[$dependsOn], $need['depends_value'] ?? '');
   }

   private function next_need($definition, $values) {
      foreach ((array)$definition['needs'] as $need) {
         if (!$this->need_is_applicable($need, $values)) continue;
         if (!$this->is_done($values, $need)) return $need;
      }
      return array();
   }

   private function completed_count($definition, $values) {
      $count = 0;
      foreach ((array)$definition['needs'] as $need) {
         if (!$this->need_is_applicable($need, $values)) continue;
         if ($this->is_done($values, $need)) $count++;
      }
      return $count;
   }

   private function applicable_count($definition, $values) {
      $count = 0;
      foreach ((array)$definition['needs'] as $need) {
         if ($this->need_is_applicable($need, $values)) $count++;
      }
      return $count;
   }

   /**
    * Fuer die Fortschrittsanzeige zaehlen auch spaetere, noch gesperrte
    * Schritte. Erst wenn eine Entscheidung gefallen ist, werden nicht
    * gewaehlte Zweige aus dem Gesamtumfang entfernt.
    */
   private function progress_total_count($definition, $values) {
      $count = 0;
      foreach ((array)$definition['needs'] as $need) {
         $dependsOn = $this->normalize_key($need['depends_on'] ?? '');
         if ($dependsOn === '') {
            $count++;
            continue;
         }
         if (!$this->dependency_has_value($dependsOn, $values)) {
            $count++;
            continue;
         }
         if ($this->value_matches($values[$dependsOn], $need['depends_value'] ?? '')) $count++;
      }
      return $count;
   }

   private function apply_command($instance, $cmd) {
      $cmd = strtolower(trim((string)$cmd));
      if (!$cmd) return $instance;

      $iid = (int)($instance['id'] ?? 0);
      if (!$this->has_instance_action_token($iid)) {
         $instance['_transient_message'] = 'Aktion nicht ausgefuehrt: ungueltiges oder abgelaufenes Aktionstoken.';
         return $instance;
      }

      $values = $this->values_from_instance($instance);
      $update = array();

      if ($cmd === 'pause') {
         $update = array('status' => 'paused', 'message' => 'Workflow angehalten.');
      } elseif ($cmd === 'resume' || $cmd === 'continue') {
         $update = array('status' => 'running', 'message' => 'Workflow fortgesetzt.');
      } elseif ($cmd === 'cancel') {
         $update = array('status' => 'canceled', 'message' => 'Workflow abgebrochen.');
      } elseif ($cmd === 'restart') {
         $values = array();
         $update = array('status' => 'running', 'message' => 'Workflow neu gestartet.', 'data_json' => $this->write_json($values), 'percent' => 0, 'step_percent' => 0);
      }

      if ($update) {
         $ok = $this->db()->update(
            $this->ddInstance,
            $update,
            array('id' => $iid),
            $this->instance_write_access(),
            1,
            1,
            1
         );
         if ($ok === 1) {
            $instance = array_merge($instance, $update);
         } else {
            $instance['message'] = 'Workflow-Aktion konnte nicht gespeichert werden.';
         }
      }

      return $instance;
   }

   private function post_value($need) {
      $value = '';

      if (isset($_POST['workflow_skip']) && !$need['required']) {
         return array('skipped' => 1, 'label' => 'Uebersprungen');
      }

      $created = trim((string)($_POST['created_value'] ?? ''));
      if ($created !== '') return $created;

      if (isset($_POST['form_value'])) {
         return trim((string)$_POST['form_value']);
      }

      if (isset($_POST['selected_value'])) {
         $value = $_POST['selected_value'];
         if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), 'strlen'));
         }
         return trim((string)$value);
      }

      return '';
   }

   private function apply_submit($instance, $definition) {
      if (empty($_POST['workflow_submit']) && empty($_POST['workflow_finish'])) return $instance;
      if (($instance['status'] ?? '') !== 'running') return $instance;

      $iid = (int)($instance['id'] ?? 0);
      $isFinish = !empty($_POST['workflow_finish']);
      $form = $this->runtime_form(
         $iid,
         $isFinish ? 'review' : 'step',
         $isFinish ? 'workflow-review' : 'workflow-step-choice'
      );
      if (!$form->submit()) {
         $instance['message'] = 'Ungueltiger oder abgelaufener Formular-Token.';
         return $instance;
      }
      if (!$this->has_instance_action_token($iid)) {
         $instance['message'] = 'Ungueltiger oder abgelaufener Aktions-Token.';
         return $instance;
      }

      $values = $this->values_from_instance($instance);

      if (!empty($_POST['workflow_finish'])) {
         $missing = $this->missing_required_labels($definition, $values);
         if ($missing) {
            $instance['message'] = 'Workflow unvollständig. Es fehlen: ' . implode(', ', $missing) . '.';
            return $instance;
         }

         // Der Abschluss kann Modulupdates oder E-Mail-Versand ausloesen.
         // Vor dem externen Effekt wird die Instanz daher atomar beansprucht.
         // Ein paralleler oder wiederholter Request sieht "finishing" und
         // fuehrt applyFinish() nicht ein zweites Mal aus.
         $db = $this->db();
         if ($db->begin($this->ddInstance) !== 1) {
            $instance['message'] = 'Workflow-Abschluss konnte nicht atomar gestartet werden.';
            return $instance;
         }
         $claim = $db->update(
            $this->ddInstance,
            array('status' => 'finishing', 'message' => 'Workflow wird abgeschlossen.'),
            array('id' => $iid, 'status' => 'running'),
            $this->instance_write_access(),
            1,
            1,
            1
         );
         if ($claim !== 1 || (int)$db->_update_count !== 1 || $db->commit($this->ddInstance) !== 1) {
            $db->rollback($this->ddInstance);
            $current = $this->load_instance($iid);
            return $current ?: array_merge($instance, array('message' => 'Workflow wird bereits abgeschlossen.'));
         }
         $instance['status'] = 'finishing';
         $instance['message'] = 'Workflow wird abgeschlossen.';

         $finishMessage = 'Workflow abgeschlossen.';
         try {
            $moduleResult = $this->workflowModule()->applyFinish($definition, $values);
         } catch (\Throwable $e) {
            dbx()->debug('#Workflow finish failed iid=(' . $iid . ') error=(' . $e->getMessage() . ')');
            $errorUpdate = array(
               'status' => 'error',
               'message' => 'Workflow-Abschluss ist fehlgeschlagen und muss geprueft werden.',
            );
            $db->update($this->ddInstance, $errorUpdate, array('id' => $iid), $this->instance_write_access(), 1, 1, 1);
            return array_merge($instance, $errorUpdate);
         }
         if (is_array($moduleResult)) {
            if (empty($moduleResult['ok'])) {
               $failedUpdate = array(
                  'status' => 'running',
                  'message' => $moduleResult['message'] ?? 'Workflow konnte nicht abgeschlossen werden.',
               );
               $db->update($this->ddInstance, $failedUpdate, array('id' => $iid), $this->instance_write_access(), 1, 1, 1);
               return array_merge($instance, $failedUpdate);
            }
            $finishMessage = (string)($moduleResult['message'] ?? $finishMessage);
         }

         $update = array(
            'status' => 'finished',
            'percent' => 100,
            'step_percent' => 100,
            'current_need' => '',
            'message' => $finishMessage,
            'data_json' => $this->write_json($values)
         );
         if ($db->update($this->ddInstance, $update, array('id' => $iid), $this->instance_write_access(), 1, 1, 1) !== 1) {
            $instance['message'] = 'Abschluss wurde ausgefuehrt; der Instanzstatus muss administrativ geprueft werden.';
            return $instance;
         }
         return array_merge($instance, $update);
      }

      $needKey = $this->normalize_key($_POST['need_key'] ?? '');
      $need = array();
      foreach ((array)$definition['needs'] as $candidate) {
         if ($candidate['key'] === $needKey) {
            $need = $candidate;
            break;
         }
      }

      if (!$need) return $instance;
      $value = $this->post_value($need);

      if (!$this->validate_need_value($need, $value)) {
         $instance['message'] = (string)($need['missing_message'] ?? ('Bitte einen gültigen Wert für „' . $need['label'] . '“ eintragen.'));
         return $instance;
      }

      $values[$need['key']] = $value;
      $stepNo = $this->completed_count($definition, $values);
      $stepRecord = array(
         'instance_id' => (int)$instance['id'],
         'step_pos' => $stepNo,
         'need_key' => $need['key'],
         'action' => !empty($_POST['workflow_skip']) ? 'skip' : 'set',
         'status' => 'finished',
         'value_json' => $this->write_json($value),
         'message' => $need['label'] . ' erfasst.',
         'owner' => (int)dbx()->user()
      );

      $total = max(1, $this->progress_total_count($definition, $values));
      $percent = (int)floor(($stepNo / $total) * 100);
      $update = array(
         'data_json' => $this->write_json($values),
         'percent' => $percent,
         'step_percent' => 100,
         'message' => $need['label'] . ' wurde uebernommen.'
      );

      $db = $this->db();
      if ($db->begin($this->ddInstance) !== 1) {
         $instance['message'] = 'Workflow-Schritt konnte nicht atomar gespeichert werden.';
         return $instance;
      }

      try {
         // Browser-Wiederholung desselben POSTs erzeugt keinen zweiten Step.
         $stepAccess = (int)dbx()->user() > 0 ? 1 : 0;
         $previous = $db->select(
            $this->ddStep,
            array('instance_id' => $iid, 'need_key' => $need['key'], 'status' => 'finished'),
            '*',
            'id',
            'DESC',
            '',
            1,
            0,
            $stepAccess
         );
         $sameStep = is_array($previous)
            && isset($previous[0])
            && (string)($previous[0]['value_json'] ?? '') === (string)$stepRecord['value_json'];

         if (!$sameStep && $db->insert($this->ddStep, $stepRecord, 1, 1, 1, 1) !== 1) {
            throw new \RuntimeException('step_insert_failed');
         }
         if ($db->update(
            $this->ddInstance,
            $update,
            array('id' => $iid, 'status' => 'running'),
            $this->instance_write_access(),
            1,
            1,
            1
         ) !== 1) {
            throw new \RuntimeException('instance_update_failed');
         }
         if ($db->commit($this->ddInstance) !== 1) {
            throw new \RuntimeException('commit_failed');
         }
      } catch (\Throwable $e) {
         $db->rollback($this->ddInstance);
         dbx()->debug('#Workflow step failed iid=(' . $iid . ') error=(' . $e->getMessage() . ')');
         $instance['message'] = 'Workflow-Schritt konnte nicht gespeichert werden.';
         return $instance;
      }

      return array_merge($instance, $update);
   }

   private function apply_automations(array $instance, array $definition): array {
      if (($instance['status'] ?? '') !== 'running') return $instance;

      $values = $this->values_from_instance($instance);
      $messages = array();
      $stepRecords = array();
      $limit = max(1, count((array)($definition['needs'] ?? array())));

      for ($i = 0; $i < $limit; $i++) {
         $need = $this->next_need($definition, $values);
         if (!$need || (string)($need['automation'] ?? 'manual') !== 'observe') break;

         $result = $this->workflowModule()->automateNeed($definition, $need, $values);
         if (!is_array($result) || !array_key_exists('value', $result)) break;
         $value = $result['value'];
         if ((is_array($value) && !$value) || (!is_array($value) && trim((string)$value) === '')) break;

         $values[$need['key']] = $value;
         $stepNo = $this->completed_count($definition, $values);
         $message = trim((string)($result['message'] ?? ($need['label'] . ' automatisch geprüft.')));
         $messages[] = $message;
         $stepRecords[] = array(
            'instance_id' => (int)$instance['id'],
            'step_pos' => $stepNo,
            'need_key' => $need['key'],
            'action' => 'automation',
            'status' => 'finished',
            'value_json' => $this->write_json($value),
            'message' => $message,
            'owner' => (int)dbx()->user()
         );
      }

      if (!$messages) return $instance;

      $completed = $this->completed_count($definition, $values);
      $total = max(1, $this->progress_total_count($definition, $values));
      $update = array(
         'data_json' => $this->write_json($values),
         'percent' => (int)floor(($completed / $total) * 100),
         'step_percent' => 100,
         'message' => implode(' ', $messages),
      );

      $db = $this->db();
      if ($db->begin($this->ddInstance) !== 1) {
         $instance['message'] = 'Automatische Workflow-Schritte konnten nicht atomar gespeichert werden.';
         return $instance;
      }
      try {
         foreach ($stepRecords as $stepRecord) {
            if ($db->insert($this->ddStep, $stepRecord, 1, 1, 1, 1) !== 1) {
               throw new \RuntimeException('automation_step_insert_failed');
            }
         }
         if ($db->update(
            $this->ddInstance,
            $update,
            array('id' => (int)$instance['id'], 'status' => 'running'),
            $this->instance_write_access(),
            1,
            1,
            1
         ) !== 1) {
            throw new \RuntimeException('automation_instance_update_failed');
         }
         if ($db->commit($this->ddInstance) !== 1) {
            throw new \RuntimeException('automation_commit_failed');
         }
      } catch (\Throwable $e) {
         $db->rollback($this->ddInstance);
         dbx()->debug('#Workflow automation failed iid=(' . (int)$instance['id'] . ') error=(' . $e->getMessage() . ')');
         $instance['message'] = 'Automatische Workflow-Schritte konnten nicht gespeichert werden.';
         return $instance;
      }

      return array_merge($instance, $update);
   }

   private function render_options($need, $currentValue = null) {
      $html = '';
      $currentValues = is_array($currentValue) ? array_map('strval', $currentValue) : array((string)$currentValue);
      foreach ((array)$need['options'] as $opt) {
         if (is_array($opt)) {
            $value = (string)($opt['value'] ?? '');
            $label = (string)($opt['label'] ?? $value);
         } else {
            $value = $label = (string)$opt;
         }
         if ($value === '') {
            continue;
         }
         $selected = in_array($value, $currentValues, true) ? ' selected' : '';
         $html .= $this->tpl()->get_tpl('dbxWorkflow|workflow-option', array(
            'value' => $this->h($value),
            'label' => $this->h($label),
            'selected' => $selected
         ));
      }
      return $html;
   }

   private function render_step_context($definition, $need, $values) {
      return $this->workflowModule()->renderStepContext($definition, $need, $values);
   }

   private function render_form_value($definition, $need, $values) {
      if (!in_array('form', (array)($need['actions'] ?? array()), true)) {
         return '';
      }

      if (array_key_exists($need['key'], $values)) {
         return $this->h($values[$need['key']]);
      }

      return $this->workflowModule()->renderFormValue($definition, $need, $values);
   }

   private function workflow_value_for_token($key, array $values) {
      $key = $this->normalize_key($key);
      if ($key === '' || !array_key_exists($key, $values)) {
         return '';
      }
      $value = $values[$key];
      if (is_array($value) && !empty($value['skipped'])) {
         return '';
      }
      if (is_array($value)) {
         return implode(',', array_map('strval', $value));
      }
      return (string)$value;
   }

   private function resolve_workflow_text($text, array $values, $urlEncode = false) {
      return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function($match) use ($values, $urlEncode) {
         $value = $this->workflow_value_for_token($match[1], $values);
         return $urlEncode ? rawurlencode($value) : $value;
      }, (string)$text);
   }

   private function render_module_links($need, array $values) {
      $links = '';
      foreach ((array)($need['module_links'] ?? array()) as $link) {
         if (!is_array($link)) {
            continue;
         }
         $url = trim((string)($link['url'] ?? ''));
         if ($url === '') {
            continue;
         }
         $url = $this->resolve_workflow_text($url, $values, true);
         $label = trim((string)($link['label'] ?? 'Oeffnen'));
         $title = trim((string)($link['title'] ?? $label));
         $icon = trim((string)($link['icon'] ?? 'bi-box-arrow-up-right'));
         $width = trim((string)($link['width'] ?? '86%'));
         $height = trim((string)($link['height'] ?? '86%'));
         $links .= '<a class="btn btn-outline-primary btn-sm openWin dbx-win" href="' . $this->h($url)
            . '" data-url="' . $this->h($url)
            . '" data-title="' . $this->h($this->resolve_workflow_text($title, $values, false))
            . '" data-width="' . $this->h($width)
            . '" data-height="' . $this->h($height)
            . '" title="' . $this->h($this->resolve_workflow_text($title, $values, false))
            . '"><i class="' . $this->h($icon) . '"></i> ' . $this->h($label) . '</a>';
      }
      return $links;
   }

   private function render_module_block($definition, $need, array $values) {
      if (!in_array('module', (array)($need['actions'] ?? array()), true)) {
         return '';
      }

      $links = $this->render_module_links($need, $values);
      if ($links === '') {
         $links = '<div class="alert alert-warning mb-0">Fuer diese Aufgabe ist noch kein Modulformular hinterlegt.</div>';
      }

      return $this->tpl()->get_tpl('dbxWorkflow|workflow-module-block', array(
         'label' => $this->h($need['label']),
         'module_links' => $links,
         'complete_value' => $this->h($need['event'] ?: ($need['label'] . ' erledigt')),
         'complete_label' => $this->h((string)($need['complete_label'] ?? 'Aufgabe wurde im geoeffneten Formular erledigt.'))
      ));
   }

   private function render_step($iid, $definition, $need, $values, $targetId) {
      $actions = (array)$need['actions'];
      $selectBlock = '';
      $createBlock = '';
      $formBlock = '';
      $moduleBlock = '';
      $stepNo = $this->completed_count($definition, $values) + 1;
      $stepCount = max(1, $this->progress_total_count($definition, $values));
      $hint = (string)($need['hint'] ?? '');
      if (!empty($need['event'])) {
         $hint .= ($hint !== '' ? ' ' : '') . 'Zwischenergebnis: ' . (string)$need['event'];
      }

      if (in_array('select', $actions, true)) {
         $selectBlock = $this->tpl()->get_tpl('dbxWorkflow|workflow-select-block', array(
            'label' => $this->h($need['label']),
            'options' => $this->render_options($need, $values[$need['key']] ?? null),
            'select_hint' => $need['mode'] === 'multiple' ? 'Mehrfachauswahl moeglich.' : 'Eine Auswahl ist notwendig.',
            'name_suffix' => $need['mode'] === 'multiple' ? '[]' : '',
            'select_attrs' => $need['mode'] === 'multiple' ? 'multiple size="5"' : ''
         ));
      }

      if (in_array('create', $actions, true)) {
         $createBlock = $this->tpl()->get_tpl('dbxWorkflow|workflow-create-block', array(
            'label' => $this->h($need['label']),
            'create_placeholder' => $this->h($need['label'] . ' neu erfassen')
         ));
      }

      if (in_array('form', $actions, true)) {
         $formBlock = $this->tpl()->get_tpl('dbxWorkflow|workflow-form-block', array(
            'label' => $this->h($need['label']),
            'form_placeholder' => $this->h($need['hint'] ?: $need['label']),
            'form_value' => $this->render_form_value($definition, $need, $values)
         ));
      }

      if (in_array('module', $actions, true)) {
         $moduleBlock = $this->render_module_block($definition, $need, $values);
      }

      $skip = !$need['required'] ? $this->tpl()->get_tpl('dbxWorkflow|workflow-skip-button') : '';
      $contextBlock = $this->render_step_context($definition, $need, $values);

      $data = array(
         'action' => $this->h($this->instance_action_url(
            '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . (int)$iid,
            (int)$iid
         )),
         'target_id' => $this->h($targetId),
         'need_key' => $this->h($need['key']),
         'step_no' => $stepNo,
         'step_count' => $stepCount,
          'label' => $this->h($need['label']),
          'question' => $this->h($this->requirement_question($need)),
          'requirement_badge' => !empty($need['required']) ? 'Pflichtangabe' : 'Optional',
          'validation_hint' => $this->h((string)($need['missing_message'] ?? '')),
          'hint' => $this->h($hint),
         'context_block' => $contextBlock,
         'select_block' => $selectBlock,
         'create_block' => $createBlock,
         'form_block' => $formBlock,
         'module_block' => $moduleBlock,
         'skip_button' => $skip
      );

      return $this->runtime_form(
         (int)$iid,
         'step',
         'workflow-step-choice',
         $data
      )->run();
   }

   private function render_requirements_check($iid, $definition, $values, string $status): string {
      $items = '';
      $requiredTotal = 0;
      $requiredDone = 0;

      foreach ((array)($definition['needs'] ?? array()) as $need) {
         $key = (string)($need['key'] ?? '');
         if ($key === '') continue;
         $required = !empty($need['required']);
         if ($required) $requiredTotal++;
         $applicable = $this->need_is_applicable($need, $values);
         $done = $applicable && $this->is_done($values, $need);
         if ($required && $done) $requiredDone++;

         $state = 'locked';
         $stateLabel = 'Wartet auf Voraussetzung';
         $icon = 'bi-lock';
         if ($done) {
            $state = 'done';
            $stateLabel = 'Vollständig';
            $icon = 'bi-check2-circle';
         } elseif ($applicable && $required) {
            $state = 'missing';
            $stateLabel = 'Unvollständig';
            $icon = 'bi-exclamation-circle';
         } elseif ($applicable) {
            $state = 'optional';
            $stateLabel = 'Optional';
            $icon = 'bi-circle';
         }

         $resolver = $this->requirement_resolver($need);
         $action = '';
         if ($applicable && !$done && $status === 'running') {
            $url = '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . (int)$iid . '&need=' . rawurlencode($key);
            $action = '<a class="btn btn-sm btn-outline-primary dbx-workflow-requirement-action" href="' . $this->h($url) . '"><i class="bi bi-pencil-square"></i> ' . $this->h((string)$resolver['label']) . '</a>';
         }

         $items .= '<article class="dbx-workflow-requirement is-' . $state . '">'
            . '<span class="dbx-workflow-requirement-icon"><i class="bi ' . $icon . '"></i></span>'
            . '<div class="dbx-workflow-requirement-copy"><strong>' . $this->h($this->requirement_question($need)) . '</strong>'
            . '<small>' . $this->h((string)($need['label'] ?? $key)) . ' · ' . $this->h((string)($need['missing_message'] ?? '')) . '</small></div>'
            . '<span class="dbx-workflow-requirement-state">' . $this->h($stateLabel) . '</span>'
            . $action
            . '</article>';
      }

      $complete = $requiredTotal > 0 && $requiredDone >= $requiredTotal;
      $summary = $complete
         ? 'Alle ' . $requiredTotal . ' Pflichtangaben sind vollständig.'
         : $requiredDone . ' von ' . $requiredTotal . ' Pflichtangaben sind vollständig.';

      return '<section class="dbx-workflow-requirements' . ($complete ? ' is-complete' : '') . '">'
         . '<header><span class="dbx-workflow-stage-number">2</span><div><small>Automatisch aus den Schritten abgeleitet</small><h3>Prüfung</h3><p>' . $this->h($summary) . '</p></div></header>'
         . '<div class="dbx-workflow-requirement-list">' . $items . '</div>'
         . '</section>';
   }

   private function value_label($definition, $need, $value) {
      $formatted = $this->workflowModule()->formatValueLabel($definition, $need, $value);
      if ($formatted !== null) {
         return $formatted;
      }

      if (is_array($value) && !empty($value['skipped'])) return '<em>Uebersprungen</em>';
      if (is_array($value)) return $this->h(implode(', ', $value));
      return nl2br($this->h($value));
   }

   private function render_review_rows($definition, $values) {
      $rows = '';
      foreach ((array)$definition['needs'] as $need) {
         if (!$this->need_is_applicable($need, $values)) continue;
         $rows .= $this->tpl()->get_tpl('dbxWorkflow|workflow-review-row', array(
            'label' => $this->h($need['label']),
            'value' => $this->value_label($definition, $need, $values[$need['key']] ?? '')
         ));
      }
      return $rows;
   }

   private function missing_required_labels($definition, $values): array {
      $missing = array();
      foreach ((array)$definition['needs'] as $need) {
         if (!$this->need_is_applicable($need, $values)) {
            continue;
         }
         if (empty($need['required'])) {
            continue;
         }
         if (!$this->is_done($values, $need)) {
            $missing[] = (string)($need['label'] ?? $need['key'] ?? '');
         }
      }
      return array_values(array_filter($missing, fn($value) => trim((string)$value) !== ''));
   }

   private function render_final_status($definition, $values, string $status): string {
      $completed = $this->completed_count($definition, $values);
      $total = max(1, $this->progress_total_count($definition, $values));
      $missing = $this->missing_required_labels($definition, $values);
      return $this->workflowModule()->renderFinalStatus($definition, $values, $status, $completed, $total, $missing);
   }

   private function render_review($iid, $definition, $values, $targetId) {
      return $this->runtime_form((int)$iid, 'review', 'workflow-review', array(
         'action' => $this->h($this->instance_action_url(
            '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . (int)$iid,
            (int)$iid
         )),
         'target_id' => $this->h($targetId),
         'result_label' => $this->h($definition['result']),
         'finish_label' => $this->h($definition['finish']['label'] ?? ($definition['result'] . ' erzeugen')),
         'rows' => $this->render_review_rows($definition, $values),
         'final_status' => $this->render_final_status($definition, $values, 'running')
      ))->run();
   }

   private function status_label($status) {
      $map = array(
         'running'=>'Laeuft',
         'finishing'=>'Wird abgeschlossen',
         'paused'=>'Angehalten',
         'canceled'=>'Abgebrochen',
         'finished'=>'Fertig',
         'error'=>'Fehler'
      );
      return $map[$status] ?? $status;
   }

   private function status_class($status) {
      if ($status === 'finished') return 'bg-success';
      if ($status === 'paused' || $status === 'finishing') return 'bg-warning';
      if ($status === 'canceled' || $status === 'error') return 'bg-danger';
      return 'bg-primary';
   }

   private function status_icon($status) {
      if ($status === 'finished') return 'bi bi-check2-circle';
      if ($status === 'paused') return 'bi bi-pause-fill';
      if ($status === 'finishing') return 'bi bi-hourglass-split';
      if ($status === 'canceled' || $status === 'error') return 'bi bi-exclamation-triangle';
      return 'bi bi-play-fill';
   }

   public function render($iid, bool $allowAutomations = false) {
      $iid = (int)$iid;
      $instance = $this->load_instance($iid);
      if (!$instance) {
         return $this->tpl()->get_tpl('dbx|alert-warning', array('msg' => 'Workflow-Instanz nicht gefunden.'));
      }

      $workflowKey = (string)($instance['workflow_key'] ?? '');
      // Neue Instanzen besitzen einen Definitions-Snapshot. Dadurch wirken
      // Admin-Aenderungen nur auf neue Starts. Alte Instanzen ohne Snapshot
      // verwenden fuer die Abwaertskompatibilitaet weiterhin den Datensatz.
      $snapshot = $this->read_json($instance['definition_json'] ?? '', array());
      if ($snapshot) {
         $baseDefinition = $this->normalize_definition($snapshot, $workflowKey);
         // Das beim Start eingebettete Binding gehoert zum Snapshot. Eine
         // spaetere Aenderung desselben bind_ref darf es nicht ersetzen.
         $baseDefinition['bind_ref'] = '';
      } else {
         $baseDefinition = $this->load_definition($workflowKey, false);
      }
      if (!$baseDefinition) return $this->unavailable_definition_message($workflowKey);
      $definition = $this->enrich_definition($baseDefinition, $this->values_from_instance($instance));
      $instance = $this->apply_command($instance, dbx()->get_modul_var('proc_cmd', '', 'parameter'));
      $instance = $this->apply_submit($instance, $definition);
      $values = $this->values_from_instance($instance);
      $definition = $this->enrich_definition($baseDefinition, $values);
      if ($allowAutomations || $this->has_instance_action_token($iid)) {
         $instance = $this->apply_automations($instance, $definition);
      }
      $values = $this->values_from_instance($instance);
      $definition = $this->enrich_definition($baseDefinition, $values);
      $status = (string)($instance['status'] ?? 'running');
      $targetId = 'dbx_workflow_' . $iid;

      $stepContent = '';
      if ($status === 'finished') {
         $stepContent = $this->tpl()->get_tpl('dbxWorkflow|workflow-finished', array(
            'result_label' => $this->h($definition['result']),
            'rows' => $this->render_review_rows($definition, $values),
            'final_status' => $this->render_final_status($definition, $values, $status),
            'new_url' => $this->h($this->action_url(
               '?dbx_modul=dbxWorkflow&dbx_run1=start&workflow=' . rawurlencode($definition['workflow_key']),
               'dbxWorkflow.start'
            ))
         ));
      } elseif ($status === 'paused' || $status === 'finishing' || $status === 'canceled' || $status === 'error') {
         $stepContent = $this->tpl()->get_tpl('dbx|alert-info', array('msg' => $instance['message'] ?? $this->status_label($status)));
      } else {
         $requested = $this->normalize_key(dbx()->get_modul_var('need', '', 'parameter'));
         $canReview = $this->completed_count($definition, $values) >= max(1, $this->applicable_count($definition, $values));
         $need = ($requested === 'review') ? array() : $this->requested_need($definition, $values);
         if (!$need && $requested !== 'review') {
            $need = $this->next_need($definition, $values);
         }
         if ($need) {
            $stepContent = $this->render_step($iid, $definition, $need, $values, $targetId);
            $instance['current_need'] = $need['key'];
         } elseif ($canReview) {
            $stepContent = $this->render_review($iid, $definition, $values, $targetId);
            $instance['current_need'] = 'review';
         } else {
            $need = $this->next_need($definition, $values);
            if ($need) {
               $stepContent = $this->render_step($iid, $definition, $need, $values, $targetId);
               $instance['current_need'] = $need['key'];
            }
         }
      }

      $completed = $this->completed_count($definition, $values);
      $total = max(1, $this->progress_total_count($definition, $values));
      $percent = ($status === 'finished') ? 100 : (int)floor(($completed / $total) * 100);
      $message = $instance['_transient_message'] ?? ($instance['message'] ?? '');
      if ($status === 'running' && $message === '') $message = 'Naechsten Schritt ausfuellen.';
      $stepsNav = $this->render_steps_nav($iid, $definition, $values, (string)($instance['current_need'] ?? ''), $status);
      $requirementsCheck = $this->render_requirements_check($iid, $definition, $values, $status);

      $stateUpdate = array(
         'current_need' => $instance['current_need'] ?? '',
         'percent' => $percent,
         'step_percent' => ($status === 'finished') ? 100 : 0,
         'message' => $message
      );
      if (isset($instance['_transient_message'])) {
         unset($stateUpdate['message']);
      }
      foreach ($stateUpdate as $field => $value) {
         if ((string)($instance[$field] ?? '') === (string)$value) {
            unset($stateUpdate[$field]);
         }
      }
      if ($stateUpdate) {
         $this->db()->update(
            $this->ddInstance,
            $stateUpdate,
            array('id' => $iid),
            $this->instance_write_access(),
            1,
            1,
            0
         );
         $instance = array_merge($instance, $stateUpdate);
      }

      $nextUrl = '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . $iid;
      $instanceActionUrl = $this->instance_action_url($nextUrl, $iid);
      $restartUrl = $instanceActionUrl . '&proc_cmd=restart';
      $autostart = 0;
      $interval = (int)dbx()->get_config('dbxWorkflow', 'step_interval');
      $stepPercent = ($status === 'finished') ? 100 : 0;
      $statusBadge = '<span class="badge ' . $this->h($this->status_class($status)) . '"><i class="' . $this->h($this->status_icon($status)) . '"></i> ' . $this->h($this->status_label($status)) . '</span>';
      $processAttrs = 'data-dbx="lib=process|id=' . $this->h($targetId)
         . '|url=' . $this->h($nextUrl)
         . '|interval=' . $interval
         . '|autostart=' . $autostart . '"'
         . ' data-process-status="' . $this->h($status) . '"'
         . ' data-process-percent="' . $percent . '"'
         . ' data-process-step-percent="' . $stepPercent . '"'
         . ' data-process-next-url="' . $this->h($nextUrl) . '"'
         . ' data-process-pause-url="' . $this->h($instanceActionUrl . '&proc_cmd=pause') . '"'
         . ' data-process-resume-url="' . $this->h($instanceActionUrl . '&proc_cmd=resume') . '"'
         . ' data-process-continue-url="' . $this->h($instanceActionUrl . '&proc_cmd=continue') . '"'
         . ' data-process-cancel-url="' . $this->h($instanceActionUrl . '&proc_cmd=cancel') . '"'
         . ' data-process-restart-url="' . $this->h($restartUrl) . '"'
         . ' data-process-autostart="' . $autostart . '"'
         . ' data-process-interval="' . $interval . '"';

      return $this->tpl()->get_tpl('dbxWorkflow|workflow-frame', array_merge(
         $this->workflow_bar_data('workflow_run', $statusBadge, $this->h($definition['title']), $this->h($definition['result'])),
         array(
         'target_id' => $this->h($targetId),
         'title' => $this->h($definition['title']),
         'result_label' => $this->h($definition['result']),
         'status' => $this->h($status),
         'status_label' => $this->h($this->status_label($status)),
         'status_class' => $this->h($this->status_class($status)),
         'status_icon' => $this->h($this->status_icon($status)),
         'process_bar_class' => $this->h($status === 'finished' ? 'bg-success' : 'bg-primary'),
         'percent' => $percent,
         'step_percent' => $stepPercent,
          'message' => $this->h($message),
          'requirements_check' => $requirementsCheck,
          'steps_nav' => $stepsNav,
         'step_content' => $stepContent,
         'next_url' => $this->h($nextUrl),
         'pause_url' => $this->h($instanceActionUrl . '&proc_cmd=pause'),
         'resume_url' => $this->h($instanceActionUrl . '&proc_cmd=resume'),
         'continue_url' => $this->h($instanceActionUrl . '&proc_cmd=continue'),
         'cancel_url' => $this->h($instanceActionUrl . '&proc_cmd=cancel'),
         'restart_url' => $this->h($restartUrl),
         'autostart' => $autostart,
         'interval' => $interval,
         'frame_id' => $this->h($targetId),
         'frame_panel_class' => 'py-3 dbx-workflow dbx-process',
         'frame_panel_attrs' => $processAttrs,
         'frame_subbar' => '',
         'frame_form_open' => '',
         'frame_form_close' => '',
         'frame_body_class' => '',
         'frame_body_head' => '',
         'frame_body_tail' => '',
         )
      ));
   }

   private function requested_need($definition, $values) {
      $requested = $this->normalize_key(dbx()->get_modul_var('need', '', 'parameter'));
      if ($requested === '') {
         return array();
      }

      foreach ((array)$definition['needs'] as $need) {
         if (($need['key'] ?? '') !== $requested) {
            continue;
         }
         if (!$this->need_is_applicable($need, $values)) {
            return array();
         }
         return $need;
      }

      return array();
   }

   private function render_steps_nav($iid, $definition, $values, $activeKey, $status) {
      $html = '<div class="dbx-workflow-step-nav" aria-label="Workflow Schritte">';
      $pos = 0;
      $allDone = true;

      foreach ((array)$definition['needs'] as $need) {
         $pos++;
         $key = (string)($need['key'] ?? '');
         $applicable = $this->need_is_applicable($need, $values);
         $done = $applicable && $this->is_done($values, $need);
         if ($applicable && !$done) {
            $allDone = false;
         }

         $state = 'locked';
         $icon = 'bi-lock';
         $stateLabel = 'Gesperrt';
         if ($applicable) {
            if ($key === $activeKey) {
               $state = 'active';
               $icon = 'bi-pencil-square';
               $stateLabel = 'Aktuell';
            } elseif ($done) {
               $state = 'done';
               $icon = 'bi-check2';
               $stateLabel = 'Erledigt';
            } else {
               $state = 'open';
               $icon = 'bi-circle';
               $stateLabel = 'Offen';
            }
         }

         $dependsOn = $this->normalize_key($need['depends_on'] ?? '');
         $title = (string)($need['label'] ?? $key);
         if (!$applicable && $dependsOn !== '') {
            $title .= ' - erst nach "' . $dependsOn . '" moeglich';
         }

         $inner = '<span class="dbx-workflow-step-nav-no">' . $pos . '</span>'
            . '<span class="dbx-workflow-step-nav-text">'
            . '<strong>' . $this->h((string)($need['label'] ?? $key)) . '</strong>'
            . '<small><i class="bi ' . $icon . '"></i> ' . $this->h($stateLabel) . '</small>'
            . '</span>';

         if ($applicable && $status === 'running') {
            $url = '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . (int)$iid . '&need=' . rawurlencode($key);
            $html .= '<a class="dbx-workflow-step-nav-item is-' . $state . '" href="' . $this->h($url) . '" title="' . $this->h($title) . '">' . $inner . '</a>';
         } else {
            $html .= '<span class="dbx-workflow-step-nav-item is-' . $state . '" title="' . $this->h($title) . '">' . $inner . '</span>';
         }
      }

      $reviewState = ($activeKey === 'review') ? 'active' : ($allDone ? 'open' : 'locked');
      $reviewIcon = ($activeKey === 'review') ? 'bi-pencil-square' : ($allDone ? 'bi-check2-circle' : 'bi-lock');
      $reviewLabel = ($activeKey === 'review') ? 'Aktuell' : ($allDone ? 'Pruefen' : 'Gesperrt');
      $reviewInner = '<span class="dbx-workflow-step-nav-no"><i class="bi bi-flag"></i></span>'
         . '<span class="dbx-workflow-step-nav-text"><strong>Abschluss</strong><small><i class="bi ' . $reviewIcon . '"></i> ' . $this->h($reviewLabel) . '</small></span>';
      if ($allDone && $status === 'running') {
         $url = '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . (int)$iid . '&need=review';
         $html .= '<a class="dbx-workflow-step-nav-item is-' . $reviewState . '" href="' . $this->h($url) . '" title="Workflow pruefen und abschliessen">' . $reviewInner . '</a>';
      } else {
         $html .= '<span class="dbx-workflow-step-nav-item is-' . $reviewState . '" title="Workflow pruefen und abschliessen">' . $reviewInner . '</span>';
      }

      return $html . '</div>';
   }

   public function overview($workflowKey) {
      $definition = $this->load_definition($workflowKey);
      if (!$definition) return $this->unavailable_definition_message((string)$workflowKey);
      return $this->tpl()->get_tpl('dbxWorkflow|workflow-overview', array_merge(
         $this->workflow_bar_data('workflow_use', '', $this->h($definition['title']), 'Workflow starten'),
         array(
         'title' => $this->h($definition['title']),
         'description' => $this->h($definition['description']),
         'result_label' => $this->h($definition['result']),
         'start_url' => $this->h($this->action_url(
            '?dbx_modul=dbxWorkflow&dbx_run1=start&workflow=' . rawurlencode($definition['workflow_key']),
            'dbxWorkflow.start'
         )),
         'frame_id' => 'dbx_workflow_overview',
         'frame_panel_class' => 'dbx-workflow',
         'frame_panel_attrs' => '',
         'frame_subbar' => '',
         'frame_form_open' => '',
         'frame_form_close' => '',
         'frame_body_class' => '',
         'frame_body_head' => '',
         'frame_body_tail' => '',
         )
      ));
   }
}
?>
