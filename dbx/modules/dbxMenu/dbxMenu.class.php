<?php
namespace dbx\dbxMenu;
dbx()->get_system_obj('dbxForm', 'use');

Class dbxMenu {
  Public $o_tpl;
  private $texts;

  public function __construct() {
     $this->o_tpl=dbx()->get_system_obj('dbxTPL');
  }

   private function texts() {
      if ($this->texts) {
         return $this->texts;
      }
      $texts = new \dbxForm();
      $texts->set_form_help_enabled(false);
      $texts->set_field_definition('dbxMenu|menu-ui');
      $texts->load_fd_messages();
      $this->texts = $texts;
      return $this->texts;
   }


   private function get_menu_tpl($menu='undef') {
     $access=1; $content='';
     if (strpos($menu, '_admin')) $access=dbx()->has_group('admin');
     if (strpos($menu, '-admin')) $access=dbx()->has_group('admin');
     if (strpos($menu, 'admin_')) $access=dbx()->has_group('admin');
     if (strpos($menu, 'admin-')) $access=dbx()->has_group('admin');     
         
     dbx()->debug("load get_menu_tpl=($menu)");

     if ($access) {
       $data=array();
       $o_web = dbx()->get_system_obj('dbxWebApp');
       $base_self = $o_web->normalize_self_url(dbx()->get_system_var('dbx_self_url','?','*'));
       $data['self'] = $o_web->route_param_prefix($base_self);
       $edit = (int) dbx()->get_system_var('dbx_edit', 0, 'int');
       $next_edit = $this->next_editor_level($edit);
       $data['edit_level']        = $edit;
       $data['edit_next_level']   = $next_edit;
       $data['edit_toggle_url']   = $this->editor_level_url($base_self, $next_edit);
       $data['edit_level_menu']   = $this->render_editor_level_menu($base_self, $edit);
       $data['edit_state_class']  = $edit ? 'is-active' : 'is-disabled';
       $data['edit_toggle_title'] = $this->texts()->format_fd_message(
          'editor_level_title',
          array('level' => $edit, 'label' => $this->editor_level_label($edit))
       );
       $lng = strtolower(trim((string) dbx()->get_system_var('dbx_lng', 'de')));
       $lng_meta = $this->language_meta($lng);
       $data['lng_active']        = $lng;
       $data['lng_active_flag']   = $lng_meta['flag'];
       $data['lng_active_label']  = $lng_meta['label'];
       $data['lng_menu']          = $this->render_language_menu($base_self, $lng);
       $data['lng_toggle_title']  = $this->texts()->format_fd_message(
          'language_title',
          array('language' => $lng_meta['label'])
       );
        $active_design = $this->active_frontend_design();
        $data['design_active']       = $active_design;
        $data['design_active_label'] = $this->design_label($active_design);
        $data['design_menu']         = $this->render_design_menu($base_self, $active_design);
        $data['design_toggle_title'] = $this->texts()->format_fd_message(
           'design_title',
           array('design' => $data['design_active_label'])
        );
        $night_active = dbx()->get_system_obj('dbxPresentation')->get_skin() === 'dunkel';
        $data['theme_toggle_url']   = $this->theme_toggle_url($base_self, $night_active);
        $data['theme_toggle_icon']  = $night_active ? 'bi-moon-stars-fill' : 'bi-sun';
        $data['theme_toggle_title'] = $this->texts()->format_fd_message(
           'theme_title',
           array('mode' => $this->texts()->get_fd_message($night_active ? 'theme_night' : 'theme_day'))
        );
       $open_contact_count = $this->open_contact_count($menu);
       $data['contact_open_count'] = $open_contact_count;
       $contact_key = $open_contact_count === 1
          ? 'contact_open_one'
          : 'contact_open_many';
       $data['contact_open_title'] = $this->texts()->format_fd_message(
          $contact_key,
          array('count' => $open_contact_count)
       );
       $data['contact_open_badge'] = $open_contact_count > 0
          ? '<span class="dbx-contact-open-badge" aria-label="' . $this->h($data['contact_open_title']) . '">'
             . $open_contact_count . '</span>'
          : '';
       $cart_count = $this->shop_cart_count();
       $data['shop_cart_count'] = $cart_count;
       $data['shop_cart_badge'] = $this->shop_cart_badge($cart_count);
       $i = dbx()->next_id();
       $content=$this->o_tpl->get_tpl($menu,$data,'htm',$i);
       $content=$this->replace_cms_placeholders($content);
     }

     return $content;
   }

   /**
    * Ersetzt die opt-in Slots der Kunden-Menuevorlage. Ohne Slot wird weder
    * die Registrierungsablage gelesen noch ein Modul-Template gerendert.
    */
   private function replace_module_menu_slots(string $content): string {
      $markers = array(
         'user' => '{dbx:modul_menu_user}',
         'admin' => '{dbx:modul_menu_admin}',
      );
      if (strpos($content, $markers['user']) === false
          && strpos($content, $markers['admin']) === false) {
         return $content;
      }

      try {
         $slots = dbx()->get_include_obj('dbxMenuSlot', 'dbxMenu');
         foreach ($markers as $area => $marker) {
            if (strpos($content, $marker) !== false) {
               $content = str_replace($marker, $slots->render($area), $content);
            }
         }
      } catch (\Throwable $e) {
         dbx()->debug('[dbxMenu] Modul-Menues konnten nicht gerendert werden: ' . $e->getMessage());
         $content = str_replace(array_values($markers), '', $content);
      }

      return $content;
   }

   private function open_contact_count(string $menu): int {
      if (stripos($menu, 'admin') === false || !dbx()->has_group('admin')) {
         return 0;
      }

      try {
         $tickets = dbx()->get_include_obj('dbxContactTicket', 'dbxContact');
         if (is_object($tickets) && method_exists($tickets, 'open_count')) {
            return max(0, (int) $tickets->open_count());
         }
      } catch (\Throwable $e) {
         dbx()->debug('[dbxMenu] offene Kontaktanfragen konnten nicht gezaehlt werden: ' . $e->getMessage());
      }

      return 0;
   }

   private function next_editor_level(int $edit): int {
      $levels = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
      $idx = array_search($edit, $levels, true);

      if ($idx === false) {
         return 0;
      }

      return $levels[($idx + 1) % count($levels)];
   }

   private function editor_level_label(int $edit): string {
      if ($edit >= 0 && $edit <= 9) {
         return $this->texts()->get_fd_message('editor_level_' . $edit);
      }

      return $this->texts()->get_fd_message('editor_level_reserve');
   }

   private function editor_level_icon(int $edit): string {
      switch ($edit) {
         case 0: return 'bi-x-circle';
         case 1:
         case 2:
         case 3: return 'bi-pencil-square';
         case 4: return 'bi-card-checklist';
         case 5: return 'bi-database';
         case 6: return 'bi-braces';
         case 7: return 'bi-cpu';
         case 8: return 'bi-gear';
         case 9: return 'bi-files';
      }

      return 'bi-question-circle';
   }

   private function render_editor_level_menu(string $base_self, int $active_edit): string {
      $levels = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
      $html = '';

      foreach ($levels as $level) {
         $class = $level === $active_edit ? ' class="is-active"' : '';
         $url = htmlspecialchars($this->editor_level_url($base_self, $level), ENT_QUOTES, 'UTF-8');
         $label = htmlspecialchars($this->editor_level_label($level), ENT_QUOTES, 'UTF-8');
         $icon = htmlspecialchars($this->editor_level_icon($level), ENT_QUOTES, 'UTF-8');

         $html .= '<li' . $class . '>';
         $title = $this->h($this->texts()->format_fd_message(
            'editor_level_title',
            array('level' => $level, 'label' => $this->editor_level_label($level))
         ));
         $item = $this->h($this->texts()->format_fd_message(
            'editor_level_item',
            array('level' => $level, 'label' => $this->editor_level_label($level))
         ));
         $html .= '<a class="dbxEditLevel" href="' . $url . '" data-dbx-tooltip="' . $title . '">';
         $html .= '<i class="bi ' . $icon . '"></i>';
         $html .= '<span>' . $item . '</span>';
         $html .= '</a>';
         $html .= '</li>';
      }

      return $html;
   }

   private function editor_level_url(string $url, int $edit): string {
      $o_web = dbx()->get_system_obj('dbxWebApp');
      return $o_web->append_route_params(
         $o_web->normalize_self_url($url),
         array('dbx_edit' => (string) $edit)
      );
   }

   private function language_catalog(): array {
      return array(
         'de' => array('label' => 'Deutsch',  'flag' => 'de'),
         'en' => array('label' => 'English',  'flag' => 'gb'),
         'es' => array('label' => 'Español',  'flag' => 'es'),
      );
   }

   private function language_options(): array {
      $config = dbx()->get_cfg('dbx');
      $accessible = $config['accessible_lng'] ?? 'de,en,es';

      if (!is_array($accessible)) {
         $accessible = array_filter(array_map('trim', explode(',', (string) $accessible)));
      }

      $catalog = $this->language_catalog();
      $options = array();

      foreach ($accessible as $code) {
         $code = strtolower(trim((string) $code));
         if ($code === '' || !isset($catalog[$code])) {
            continue;
         }
         $options[$code] = $catalog[$code];
      }

      if (empty($options)) {
         $options['de'] = $catalog['de'];
      }

      return $options;
   }

   private function language_meta(string $lng): array {
      $options = $this->language_options();
      $lng = strtolower(trim($lng));

      if (isset($options[$lng])) {
         return $options[$lng];
      }

      return reset($options);
   }

   private function language_url(string $url, string $lng): string {
      $o_web = dbx()->get_system_obj('dbxWebApp');
      $source_lng = strtolower(trim((string)dbx()->get_system_var('dbx_lng', 'de')));
      $cid = (int)dbx()->get_system_var('dbx_content_route_cid', 0, 'int');
      if ($cid > 0) {
         $routes = dbx()->get_include_obj('dbxContentCanonicalRoute', 'dbxContent');
         if (is_object($routes) && method_exists($routes, 'page_url')) {
            $canonical = $routes->page_url($cid, $source_lng, strtolower(trim($lng)));
            if ($canonical !== '') {
               return $canonical;
            }
         }
      }
      return $o_web->append_route_params(
         $o_web->normalize_self_url($url),
         array('dbx_lng' => strtolower(trim($lng)))
      );
   }

   private function render_language_menu(string $base_self, string $active_lng): string {
      $active_lng = strtolower(trim($active_lng));
      $html = '';

      foreach ($this->language_options() as $code => $meta) {
         $class = $code === $active_lng ? ' class="is-active"' : '';
         $url = htmlspecialchars($this->language_url($base_self, $code), ENT_QUOTES, 'UTF-8');
         $label = htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8');
         $flag = htmlspecialchars($meta['flag'], ENT_QUOTES, 'UTF-8');
         $code_label = htmlspecialchars(strtoupper($code), ENT_QUOTES, 'UTF-8');

         $html .= '<li' . $class . '>';
         $html .= '<a class="dbxLngOpt' . ($code === $active_lng ? ' is-active' : '')
             . '" href="' . $url . '" data-dbx-tooltip="' . $label . '">';
         $html .= '<span class="dbx-lng-flag" data-dbx-flag="' . $flag . '" aria-hidden="true"></span>';
         $html .= '<span class="dbx-lng-code">' . $code_label . '</span>';
         $html .= '</a>';
         $html .= '</li>';
      }

      return $html;
   }

   /**
    * Liefert eigenständige Frontend-Designs mit einer default.htm.
    * Interne Pakete mit fuehrendem _ oder - werden nicht im Benutzermenue angeboten.
    */
   private function frontend_design_options(): array {
      $options = array();
      foreach (dbx()->get_system_obj('dbxPresentation')->get_design_catalog() as $name => $design) {
         if ($name === '' || $name[0] === '_' || $name[0] === '-') {
            continue;
         }
         $options[$name] = (string)($design['title'] ?? $this->design_label($name));
      }

      if (empty($options)) {
         $options['dbxapp'] = 'dbXapp';
      }

      uasort($options, static function ($a, $b) {
         return strnatcasecmp((string)$a, (string)$b);
      });

      return $options;
   }

   private function design_label(string $design): string {
      $key = strtolower($design);
      if ($key === 'dbxapp') {
         return 'dbXapp';
      }
      $label = trim(str_replace(array('-', '_'), ' ', $design));
      return $label !== '' ? ucfirst($label) : $design;
   }

   /**
    * Erstellt den GET-Aufruf fuer einen Design-Wechsel. Der Farbzustand
    * (Tag/Nacht) bleibt dabei unangetastet - normalize_skin() faengt es
    * automatisch ab, falls das Zieldesign den aktuellen Skin nicht kennt.
    */
   private function design_url(string $url, string $design): string {
      $o_web = dbx()->get_system_obj('dbxWebApp');

      return $o_web->append_route_params(
         $o_web->normalize_self_url($url),
         array('dbx_design' => $design)
      );
   }

   /**
    * Rendert die flache Design-Auswahl (kein Skin-Untermenue mehr). Der
    * Tag/Nacht-Wechsel ist ein eigenes, separates Menue-Icon
    * (siehe theme_toggle_url()) und wirkt designuebergreifend.
    */
   private function render_design_menu(string $base_self, string $active_design): string {
      $html = '';

      foreach ($this->frontend_design_options() as $design => $design_label) {
         $active = $design === $active_design;
         $design_esc = $this->h($design);
         $url = $this->h($this->design_url($base_self, $design));

         $html .= '<li class="dbx-design-item' . ($active ? ' is-active' : '') . '">';
         $html .= '<a href="' . $url . '" class="dbx-design-opt' . ($active ? ' is-active' : '')
            . '" data-design="' . $design_esc . '" data-dbx-tooltip="' . $this->h($design_label) . '">';
         $html .= '<i class="bi bi-window-sidebar dbx-design-icon" aria-hidden="true"></i><span>' . $this->h($design_label) . '</span>';
         if ($active) {
            $html .= '<i class="bi bi-check2 dbx-design-check" aria-hidden="true"></i>';
         }
         $html .= '</a></li>';
      }

      return $html;
   }

   /**
    * Erstellt den GET-Aufruf fuer den Tag/Nacht-Umschalter. Wirkt auf jedes
    * Design, das einen "dunkel"-Skin mitbringt (aktuell nur dbxapp); bei
    * Designs ohne Nacht-Skin faengt normalize_skin() den Wert automatisch auf
    * die einzige vorhandene Farbvariante zurueck, die Darstellung bleibt dann
    * unveraendert.
    */
   private function theme_toggle_url(string $url, bool $night_active): string {
      $o_web = dbx()->get_system_obj('dbxWebApp');

      return $o_web->append_route_params(
         $o_web->normalize_self_url($url),
         array('dbx_color' => $night_active ? 'blau' : 'dunkel')
      );
   }

   private function active_frontend_design(): string {
      $config = dbx()->get_cfg('dbx');
      $design = trim((string)dbx()->get_system_var('dbx_design', $config['default_design_user'] ?? 'dbxapp'));

      if ($design === 'user') {
         $design = (string)($config['default_design_user'] ?? 'dbxapp');
      } elseif ($design === 'admin') {
         $design = (string)($config['default_design_admin'] ?? 'dbxapp');
      }

      $options = $this->frontend_design_options();
      if (!isset($options[$design])) {
         $default = (string)($config['default_design_user'] ?? 'dbxapp');
         $design = isset($options[$default]) ? $default : (string)array_key_first($options);
      }

      return $design;
   }

   private function h($value): string {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function shop_cart_count(): int {
      if (session_status() === PHP_SESSION_NONE) {
         if (headers_sent()) {
            return 0;
         }
         session_start();
      }
      $cart = dbx()->get_session_var('cart', array(), 'state', 'dbxShop');
      if (!is_array($cart)) {
         return 0;
      }
      $count = 0;
      foreach ($cart as $qty) {
         $count += max(0, (int)$qty);
      }
      return $count;
   }

   private function shop_cart_badge(int $count): string {
      if ($count <= 0) {
         return '';
      }
      $label = $this->texts()->format_fd_message(
         $count === 1 ? 'cart_item_one' : 'cart_item_many',
         array('count' => $count)
      );
      return '<span class="dbx-shop-cart-menu-badge" aria-label="' . $this->h($label) . '">' . $this->h((string)$count) . '</span>';
   }

   private function user_avatar_url(int $uid): string {
      $file = '';
      try {
         $db = dbx()->get_system_obj('dbxDB');
         $rec = $db->select1('dbxUser', $uid, 'avatar', 0);
         if (is_array($rec)) {
            $file = trim((string)($rec['avatar'] ?? ''));
         }
      } catch (\Throwable $e) {
         $file = trim((string)dbx()->user('avatar'));
      }

      if ($file === '' || !preg_match('/^avatar-[0-9]+\.(?:webp|png|jpe?g|gif)$/i', $file)) {
         $file = 'avatar-0.png';
      }

      $base = dbx()->get_base_dir();
      $public = 'files/user/avatar/' . $file;
      if (is_file(dbx()->os_path($base . $public))) {
         return dbx()->get_base_url() . $public;
      }

      $legacy = 'dbx/modules/dbxUser/img/avatar/' . $file;
      if (is_file(dbx()->os_path($base . $legacy))) {
         return dbx()->get_base_url() . $legacy;
      }

      return dbx()->get_base_url() . 'files/user/avatar/avatar-0.png';
   }

   private function user_menu_html(int $uid): string {
      $avatar = $this->h($this->user_avatar_url($uid));
      $cart_count = $this->shop_cart_count();
      $cart_badge = $this->shop_cart_badge($cart_count);
      $texts = $this->texts();
      $user = $this->h($texts->get_fd_message('user'));
      return '<li class="align-right dbx-user-avatar-menu">'
         . '<a data-dbx-tooltip="' . $user . '" style="cursor:pointer;">'
         . '<span class="dbx-user-menu-avatar-wrap">'
         . '<img class="dbx-user-menu-avatar" src="' . $avatar . '?' . time() . '" alt="' . $user . '">'
         . '<span class="dbx-user-menu-mail" aria-hidden="true"><i class="bi bi-envelope-fill"></i></span>'
         . '</span>'
         . '<span class="dbx-menu-mobile-label">' . $user . '</span>'
         . '</a>'
         . '<ul>'
         . '<li><a href="?dbx_modul=dbxShop&amp;dbx_run1=catalog"><i class="bi bi-bag"></i> ' . $this->h($texts->get_fd_message('shop')) . '</a></li>'
         . '<li><a class="dbx-shop-cart-menu-link" href="?dbx_modul=dbxShop&amp;dbx_run1=cart"><span class="dbx-shop-cart-menu-icon"><i class="bi bi-basket"></i>' . $cart_badge . '</span> ' . $this->h($texts->get_fd_message('cart')) . '</a></li>'
         . '<li><a href="?dbx_modul=dbxShop&amp;dbx_run1=orders"><i class="bi bi-receipt"></i> ' . $this->h($texts->get_fd_message('orders')) . '</a></li>'
         . '<li><a href="?dbx_modul=dbxShop&amp;dbx_run1=withdrawal"><i class="bi bi-arrow-counterclockwise"></i> ' . $this->h($texts->get_fd_message('withdrawal')) . '</a></li>'
         . '<li><a href="?dbx_modul=dbxContact&amp;dbx_run1=form"><i class="bi bi-envelope-plus"></i> ' . $this->h($texts->get_fd_message('new_request')) . '</a></li>'
         . '<li><a href="?dbx_modul=dbxContact&amp;dbx_run1=my"><i class="bi bi-envelope"></i> ' . $this->h($texts->get_fd_message('my_requests')) . '</a></li>'
         . '<li><a href="?dbx_modul=dbxUser&amp;dbx_run1=user&amp;dbx_run2=edit_profil"><i class="bi bi-person-circle"></i> ' . $this->h($texts->get_fd_message('my_profile')) . '</a></li>'
         . '</ul>'
         . '</li>';
   }

   private function replace_cms_placeholders($content) {
      if (!is_string($content) || strpos($content, '[cms:') === false) {
         return $content;
      }

      $has_flat_marker = stripos($content, '[cms:flat]') !== false;
      $flat_marker_token = '###DBX_CMS_FLAT_MARKER_' . md5((string)microtime(true)) . '###';
      $flat_marker_html = '';

      $content = preg_replace_callback('/\[cms:([^\]]*)\]/i', function ($match) use ($has_flat_marker, $flat_marker_token, &$flat_marker_html) {
         $raw = trim((string)$match[1]);

         if (preg_match('/^flat$/i', $raw)) {
            return $flat_marker_token;
         }

         $params = array();
         parse_str(str_replace('&amp;', '&', $raw), $params);

         $root = trim((string)($params['root'] ?? '0'));
         if (ctype_digit($root)) {
            $root = (int)$root;
         }
         $flat = (int)($params['flat'] ?? 1);
         $split_flat = $has_flat_marker && $flat === 1;

         $obj = dbx()->get_include_obj('dbxContent_menu');
         if ($obj && method_exists($obj, 'render_placeholder')) {
            if ($split_flat && method_exists($obj, 'render_flat_marker')) {
               $flat_marker_html .= $obj->render_flat_marker($root, $flat);
            }
            return $obj->render_placeholder($root, $flat, $split_flat);
         }

         return '';
      }, $content);

      return str_replace($flat_marker_token, $flat_marker_html, $content);
   }

   public function run() {
      $action=dbx()->get_modul_var('dbx_run1' ,'a-undef','*');
      $menu  =dbx()->get_modul_var('tpl'      ,'b-undef','*');
      $uid   =dbx()->user();
      $mid   =dbx()->get_system_var('dbx_activ_modul_id',-1);
      $content="Menu ($menu) not found"; // default output !

      dbx()->debug("#Modul Menu# action=($action) Menu=($menu) Modul-id=($mid)");

      switch ($action) {
        case 'load':
          $content=$this->get_menu_tpl($menu);
        break;

        case 'content':
          $obj=dbx()->get_include_obj('dbxContent_menu');
          $content=$obj->run();
        break;
      }


      if ($uid > 0) {
         $login_out ='LogOut'; $login_out = 'logout'; $login_icon = 'bi-power';
      } else {
         $login_out ='LogIn' ; $login_out = 'login';  $login_icon = 'bi-person-circle';
      }
      $profile_link = $this->user_menu_html((int)$uid);

      $admin_menu='';
      $design=dbx()->get_system_var('dbx_activ_design');
      $page  =dbx()->get_system_var('dbx_activ_page');
      $lng   =dbx()->get_system_var('dbx_activ_lng');

      if (dbx()->has_group('admin'))         $admin_menu="Admin";
      $content=$this->replace_module_menu_slots($content);
      $content=str_replace('{dbx:Admin}'    ,$admin_menu,$content);
      $content=str_replace('{dbx:LogInOut}' ,$login_out ,$content);
      $content=str_replace('{dbx:login_out}',$login_out,$content);
      $content=str_replace('{dbx:login_icon}',$login_icon,$content);
      $content=str_replace('{dbx:profile_link}',$profile_link,$content);
      $content=str_replace('{dbx:design}'   ,$design   ,$content);
      $content=str_replace('{dbx:page}'     ,$page     ,$content);
      $content=str_replace('{dbx:lng}'      ,$lng      ,$content);

      return $content;
   }

}

?>
