<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_bootstrap.php';
require_once __DIR__ . '/dbxContentContextHelpProvision.class.php';

class dbxContentContextHelp {

   public const HELP_ROOT = 'outside/help';

   public function topicSlug(string $topic): string {
      $topic = strtolower(trim($topic));
      $topic = str_replace('_', '-', $topic);
      $topic = preg_replace('/[^a-z0-9-]+/', '-', $topic);
      $topic = preg_replace('/-+/', '-', $topic);
      return trim((string) $topic, '-');
   }

   public function permalinkForTopic(string $topic): string {
      $help = dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin');
      if (is_object($help) && method_exists($help, 'topicPermalink')) {
         $permalink = trim((string)$help->topicPermalink($topic));
         if (dbxContent_permalink::isValid($permalink)) {
            return $permalink;
         }
      }

      $slug = $this->topicSlug($topic);
      return $slug !== '' ? 'help-' . $slug : '';
   }

   public function legacyPermalinksForTopic(string $topic): array {
      $slug = $this->topicSlug($topic);
      if ($slug === '') {
         return array();
      }
      return array(
         self::HELP_ROOT . '/' . $slug,
         'help/' . $slug,
      );
   }

   public function resolveCidByPermalink(string $permalink, string $lng = ''): int {
      $permalink = trim($permalink);
      if (!dbxContent_permalink::isValid($permalink)) {
         return 0;
      }

      if ($lng === '') {
         $lng = dbx()->lng_current();
      }
      $lng = strtolower(trim($lng));

      $hit = dbxContentPermalinkIndex::resolve($permalink, $lng);
      if (is_array($hit) && (int) ($hit['cid'] ?? 0) > 0) {
         return (int) $hit['cid'];
      }

      $db = dbx()->get_system_obj('dbxDB');
      if (!is_object($db)) {
         return 0;
      }

      $dd = dbxContentLng::ddContent($lng);
      $rec = $db->select1($dd, array('permalink' => $permalink), 'id', 0);
      if (is_array($rec) && (int) ($rec['id'] ?? 0) > 0) {
         return (int) $rec['id'];
      }

      $masterLng = strtolower(trim((string) dbx()->get_cfg('dbx', 'default_lng', 'de')));
      if ($masterLng !== '' && $masterLng !== $lng) {
         $ddMaster = dbxContentLng::ddContent($masterLng);
         $rec = $db->select1($ddMaster, array('permalink' => $permalink), 'id', 0);
         if (is_array($rec) && (int) ($rec['id'] ?? 0) > 0) {
            return (int) $rec['id'];
         }
      }

      return 0;
   }

   public function renderTopic(string $topic): string {
      $permalink = $this->permalinkForTopic($topic);
      if ($permalink === '') {
         return '';
      }

      $cid = $this->resolveCidByPermalink($permalink);
      if ($cid <= 0) {
         return dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-info', array(
            'msg' => 'Hilfe-Seite noch nicht angelegt. Im CMS unter Ordner <code>'
               . htmlspecialchars(self::HELP_ROOT, ENT_QUOTES, 'UTF-8')
               . '</code> eine Seite mit Permalink <code>'
               . htmlspecialchars($permalink, ENT_QUOTES, 'UTF-8')
               . '</code> anlegen (Thema: <code>'
               . htmlspecialchars($topic, ENT_QUOTES, 'UTF-8')
               . '</code>).',
         ));
      }

      $contentObj = dbx()->get_include_obj('dbxContent_content');
      if (!is_object($contentObj) || !method_exists($contentObj, 'renderPage')) {
         return '';
      }

      return $contentObj->renderPage($cid, array(
         'admin_help' => true,
         'skip_hits' => true,
         'skip_cache' => true,
         'template' => 'c-content-help',
         'wrap' => false,
      ));
   }

   public function renderFormHelp(string $modul, string $form, string $title = ''): string {
      $modul = trim($modul);
      $form = trim($form);
      $title = trim($title);
      if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $modul)
         || $form === ''
         || !preg_match('/^[a-zA-Z0-9_.:-]+$/', $form)) {
         return dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-danger', array(
            'msg' => 'Der Formular-Kontext ist ungueltig.',
         ));
      }

      if ($title === '') {
         $title = ucwords(str_replace(array('-', '_', '.'), ' ', $form));
      }

      $registry = dbx()->get_include_obj('dbxModuleRegistry', 'dbxAdmin');
      $detail = '';
      if (is_object($registry) && method_exists($registry, 'renderFormHelp')) {
         $detail = (string)$registry->renderFormHelp($modul, $form, array(
            'modul' => htmlspecialchars($modul, ENT_QUOTES, 'UTF-8'),
            'form' => htmlspecialchars($form, ENT_QUOTES, 'UTF-8'),
            'form_title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
         ));
      }
      $detail = str_ireplace(
         array('[modul=', '[/modul]', '[tpl=', '[dbx:'),
         array('&#91;modul=', '&#91;/modul]', '&#91;tpl=', '&#91;dbx:'),
         $detail
      );

      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxAdmin|admin-help-form', array(
         'form_title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
         'form_id' => htmlspecialchars($form, ENT_QUOTES, 'UTF-8'),
         'form_modul' => htmlspecialchars($modul, ENT_QUOTES, 'UTF-8'),
         'form_detail' => $detail,
      ));
   }

   public function run(): string {
      dbx()->set_system_var('dbx_page', '_window');

      $topic = trim((string) dbx()->get_modul_var('topic', '', 'parameter'));
      if ($topic === '') {
         $topic = trim((string) dbx()->get_modul_var('dbx_run2', '', 'parameter'));
      }

      $help = dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin');
      $isFormHelp = ($topic === 'form');
      if (!$isFormHelp) {
         dbxContentContextHelpProvision::run();
      }
      $topics = is_object($help) && method_exists($help, 'topics') ? $help->topics() : array();
      if (!$isFormHelp && ($topic === '' || !isset($topics[$topic]))) {
         $topic = 'dashboard';
      }

      $formTitle = '';
      if ($isFormHelp) {
         $formModul = trim((string)dbx()->get_modul_var('help_modul', '', 'parameter'));
         $formId = trim((string)dbx()->get_modul_var('help_form', '', 'parameter'));
         $formTitle = trim((string)dbx()->get_modul_var('help_title', '', 'varchar'));
         $content = $this->renderFormHelp($formModul, $formId, $formTitle);
      } else {
         $content = $this->renderTopic($topic);
      }
      if (trim(strip_tags((string) $content)) === '') {
         $content = dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-info', array(
            'msg' => 'Fuer diesen Bereich ist noch keine Hilfe hinterlegt.',
         ));
      }

      $bar = array();
      if ($isFormHelp && is_object($help) && method_exists($help, 'formHelpWindowBarTemplateData')) {
         $bar = $help->formHelpWindowBarTemplateData($formTitle);
      } elseif (is_object($help) && method_exists($help, 'helpWindowBarTemplateData')) {
         $bar = $help->helpWindowBarTemplateData($topic);
      }

      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxAdmin|admin-help-shell', array_merge(
         is_array($bar) ? $bar : array(),
         array(
            'frame_id' => 'dbx_context_help_' . preg_replace('/[^a-z0-9_-]+/i', '_', $topic),
            'frame_panel_class' => 'dbx-admin-help py-3 dbx-context-help-preview',
            'frame_form_open' => '',
            'frame_form_close' => '',
            'frame_subbar' => '',
            'frame_body_class' => 'dbx-admin-help-body dbx-context-help-body',
            'frame_body_head' => '',
            'frame_body_tail' => '',
            'frame_panel_attrs' => '',
            'content' => $content,
         )
      ));
   }
}
