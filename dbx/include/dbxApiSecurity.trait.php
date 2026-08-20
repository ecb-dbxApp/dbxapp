<?php

/** Internal responsibility extracted from the stable dbxApi facade. */
trait dbxApiSecurityTrait
{
/**
    * Prüft den benutzerbezogenen Nur-Lese-Demomodus.
    *
    * Ausschliesslich die exakte Rolle `demo` aktiviert den Modus. Globale
    * Konfigurations- oder Umgebungswerte beeinflussen normale Benutzer nicht.
    * Der Wert wird nicht gecached, weil sich der Benutzer im selben Request-
    * Prozess an- oder abmelden kann.
    *
    * Wirkung: get_cfg()/set_cfg() maskieren bzw. sperren im Demomodus
    * schreibende Konfigurationszugriffe (siehe `for_display`-Parameter von
    * get_cfg() und die Sperre in set_cfg()).
    *
    * Beispiel:
    * ```php
    * if (dbx()->is_demo_mode()) {
    *     // z. B. Speichern-Button im Formular ausblenden
    * }
    * ```
    *
    * @return bool true, wenn der aktuelle Benutzer exakt die Rolle `demo` hat.
    */
   public function is_demo_mode(): bool {
      $roles = $_SESSION['dbx']['current_user']['roles'] ?? array();
      $roles = $this->normalize_group_list($roles, true);
      return in_array('demo', $roles, true);
   }

/**
    * Prüft, ob der Entwicklungs-Admin-Bypass im aktuellen Request wirksam ist.
    *
    * Aktiv nur, wenn die Konstante `dbxRunAsAdmin` auf 1 gesetzt ist UND kein
    * Benutzer eingeloggt ist. Eine echte Anmeldung hat immer Vorrang. Dadurch
    * werden Demo- und andere Benutzer weder als Administrator ueberlagert
    * noch mit einer irrefuehrenden Bypass-Meldung dargestellt, nur weil die
    * Entwicklungskonstante gesetzt ist.
    *
    * Wichtig: Der Bypass ist ein reines Entwicklungswerkzeug fuer lokale
    * Installationen und darf in produktiven `index.php`-Einstiegspunkten
    * nicht aktiv sein (siehe SOURCE-REFACTOR-TODO.md Abschlusspruefung).
    * user() und has_group()/has_module_access() beruecksichtigen diesen
    * Bypass automatisch.
    *
    * @return bool true, wenn der Admin-Bypass fuer den aktuellen Request gilt.
    */
   public function is_admin_bypass_active(): bool {
      if (!defined('dbxRunAsAdmin') || (int)constant('dbxRunAsAdmin') !== 1) {
         return false;
      }

      return (int)($_SESSION['dbx']['current_user']['id'] ?? 0) <= 0;
   }

/**
    * Normalisiert einen Action-Scope kompatibel zur bisherigen API.
    *
    * Bestehende Modul-Scopes bleiben dadurch gueltig. Neue automatisch
    * erzeugte Routen-Scopes bestehen bereits nur aus sicheren Zeichen.
    *
    * @param string $scope Fachlicher oder kanonischer Action-Scope.
    * @return string Normalisierter Scope.
    */
   private function normalize_action_scope(string $scope): string {
     return preg_replace('/[^a-zA-Z0-9_.:-]/', '', $scope) ?: 'global';
   }

/**
    * Liefert das einzige sessiongebundene Secret fuer alle Action-Tokens.
    *
    * Anders als die fruehere Ablage eines Zufallstokens je Scope bleibt der
    * Sessionbedarf konstant. Das Secret wird nur einmal je Session erzeugt;
    * konkrete Tokens werden danach stateless per HMAC abgeleitet.
    *
    * @return string 64-stelliges Hex-Secret.
    */
   private function action_token_secret(): string {
     $secret = (string)$this->get_session_var('action_token_secret', '', 'security', 'dbx');

     if (!preg_match('/^[a-f0-9]{64}$/', $secret)) {
       $secret = bin2hex(random_bytes(32));
       $this->set_session_var('action_token_secret', $secret, 'security', 'dbx');
     }

     return $secret;
   }

/**
    * Liefert ein sessiongebundenes Token fuer zustandsaendernde Link-Aktionen.
    *
    * Das Token wird aus einem einzigen Session-Secret und dem Scope abgeleitet.
    * Dadurch koennen auch RID-spezifische Scopes verwendet werden, ohne fuer
    * jede RID einen weiteren Sessioneintrag anzulegen.
    *
    * Bestehende Aufrufe bleiben kompatibel:
    *
    * ```php
    * $token = dbx()->action_token('myModule.delete.17');
    * ```
    *
    * Modulcode nutzt stattdessen `action_url()`. Standardaktionen wie
    * `delete` oder `save` werden zusammen mit `rid` direkt aus der URL
    * erkannt; Scope und Transportparameter entstehen automatisch.
    *
    * @param string $scope Eindeutiger Aktionsbereich.
    * @return string Token.
    */
   public function action_token(string $scope = 'global'): string {
     $scope = $this->normalize_action_scope($scope);
     return hash_hmac('sha256', 'dbx-action-v2|' . $scope, $this->action_token_secret());
   }

/**
    * Prüft ein sessiongebundenes Aktions-Token.
    *
    * Leere Tokens werden ohne Session-Schreibzugriff verworfen. Gueltige Tokens
    * verwenden ausschliesslich das konstante, sessiongebundene HMAC-Secret.
    *
    * @param string $scope Eindeutiger Aktionsbereich.
    * @param string $token Uebergebenes Token.
    * @return bool
    */
   public function check_action_token(string $scope = 'global', string $token = ''): bool {
     if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
       return false;
     }

     $scope = $this->normalize_action_scope($scope);

     // Eine Pruefung darf niemals erst ein Secret und damit Sessionzustand
     // erzeugen. Das Secret entsteht ausschliesslich beim Rendern eines
     // gueltigen Aktionslinks durch action_token().
     $secret = (string)$this->get_session_var('action_token_secret', '', 'security', 'dbx');
     if (!preg_match('/^[a-f0-9]{64}$/', $secret)) {
       return false;
     }

     $expected = hash_hmac('sha256', 'dbx-action-v2|' . $scope, $secret);
     return hash_equals($expected, $token);
   }

/**
    * Verwirft alle Action-Tokens an einer Authentifizierungsgrenze.
    *
    * Login und Logout wechseln den Sicherheitskontext. Ein zuvor gerendertes
    * Gast- oder Benutzer-Token darf danach nicht weiterverwendet werden.
    * Der naechste action_token()-Aufruf erzeugt automatisch ein neues Secret.
    *
    * @return void
    */
   public function invalidate_action_tokens(): void {
     $this->delete_session_var('action_token_secret', 'security', 'dbx');
   }

/**
    * Tokenisiert eine automatisch erkannte zustandsaendernde Route.
    *
    * Reine Navigation bleibt unveraendert. Nur wenn `dbxWebApp` fuer die URL
    * eine Action-Policy findet (z.B. `delete`/`save` plus `rid`, eine
    * Standardaktion von `dbxReport` oder eine kompatible Moduldefinition),
    * wird `dbx_token` hinzugefuegt.
    * Ein vorhandener Transportparameter wird dabei sicher ersetzt.
    *
    * Mit `$action` und `$bindings` kann Kernel-/Komponentencode einen bereits
    * bekannten Scope explizit aufbauen. Fachmodule verwenden im Normalfall
    * nur die automatische Variante ohne diese Zusatzargumente.
    *
    * @param string $url Bestehende Modul-/Permalink-URL.
    * @param string $action Optionaler kanonischer Aktionsname.
    * @param array $bindings Optional gebundene Schluessel/Werte.
    * @return string Originale oder automatisch tokenisierte URL.
    */
   public function action_url(string $url, string $action = '', array $bindings = array()): string {
     $web_app = $this->get_system_obj('dbxWebApp');
     if (!is_object($web_app)) {
       return $url;
     }

     $scope = '';
     if ($action !== '') {
       $scope = (string)$web_app->action_scope_for_url($url, $action, $bindings);
     } else {
       $policy = $web_app->action_policy_for_url($url);
       $scope = is_array($policy) ? (string)($policy['scope'] ?? '') : '';
     }

     if ($scope === '') {
       return $url;
     }

     return (string)$web_app->append_route_params($url, array(
       'dbx_token' => $this->action_token($scope),
     ));
   }

/**
    * Liest einen Wert des aktuellen Benutzers.
    *
    * Beispiel:
    * ```
    * $uid = dbx()->user();
    * $groups = dbx()->user('groups');
    * ```
    *
    * @param string $key Benutzerfeld; Standard ist id.
    * @return mixed
    */
   public function user($key='id') {
      $current_user = $_SESSION['dbx']['current_user'] ?? array();

      if ($this->is_admin_bypass_active()) {
         $current_user = $this->get_admin_bypass_user($current_user);
      }

      if ($key === '*') {
         return $current_user;
      }

      return $current_user[$key] ?? null;
   }

/**
    * Liefert fuer den Admin-Bypass den Benutzerkontext der administrativen ID 1.
    *
    * Der Kontext wird nur fuer den aktuellen Request ueberlagert. Die Session
    * bleibt unveraendert, damit das Abschalten des Bypasses keinen dauerhaften
    * Login hinterlaesst.
    *
    * @param array $session_user Benutzerkontext der aktuellen Session.
    * @return array Administrativer Benutzerkontext.
    */
   private function get_admin_bypass_user(array $session_user = array()): array {
      if ((int)($session_user['id'] ?? 0) === 1) {
         return $session_user;
      }

      if ($this->admin_bypass_user_loaded || $this->admin_bypass_user_loading) {
         return $this->admin_bypass_user;
      }

      $this->admin_bypass_user = array_merge($session_user, array(
         'id'       => 1,
         'uname'    => 'admin',
         'roles'    => 'admin',
         'email'    => '',
         'name'     => 'Admin',
         'design'   => $session_user['design'] ?? 'default',
         'color'    => $session_user['color'] ?? 'default',
         'language' => $session_user['language'] ?? 'de',
         'edit'     => $session_user['edit'] ?? 0,
      ));
      $this->admin_bypass_user_loading = true;

      try {
         $session = $this->get_system_obj('dbxSession');
         if (is_object($session) && method_exists($session, 'get_current_user')) {
            $admin_user = $session->get_current_user(1);
            if (is_array($admin_user) && (int)($admin_user['id'] ?? 0) === 1) {
               $this->admin_bypass_user = $admin_user;
            }
         }
      } catch (\Throwable $e) {
         // Der sichere Admin-Fallback bleibt auch ohne verfuegbare DB erhalten.
      } finally {
         $this->admin_bypass_user_loading = false;
         $this->admin_bypass_user_loaded = true;
      }

      return $this->admin_bypass_user;
   }

/**
    * Prüft Gruppenrechte gegen den aktuellen oder uebergebenen Benutzer.
    *
    * Beispiel:
    * ```
    * if (dbx()->has_group('admin')) { ... }
    * ```
    *
    * @param string|array $access_groups Erforderliche Gruppen.
    * @param string|array $user_groups Optional andere Benutzergruppen.
    * @return bool
    */
   public function has_group($access_groups = '', $user_groups = ''): bool {
       if ($this->is_admin_bypass_active()) return true;
       if ($this->is_demo_mode()) return true;
       if ($access_groups === '*') return true;
       if (!$access_groups) return true;
       $current_user_groups = !$user_groups;
       if ($current_user_groups) $user_groups = $_SESSION['dbx']['current_user']['roles'] ?? '';

       $user_groups   = $this->normalize_group_list($user_groups);
       $access_groups = $this->normalize_group_list($access_groups);

       if ($current_user_groups && in_array('authenticated', $access_groups, true) && (int)$this->user() > 0) {
           return true;
       }
   
       foreach ($user_groups as $role) {
           if ($role === 'admin') return true; // Immer Zugriff fuer Admins
   
           foreach ($access_groups as $group) {
               if ($group === '*' || $group === $role) return true;
           }
       }
   
       return false;
   }

/**
    * Prüft, ob der Frontend-/RAD-Edit-Modus aktiv ist (`dbx_edit` > 0).
    *
    * `dbx_edit` steuert die verschiedenen Editor-Marker-Modi (1/2/9 fuer
    * Content, 4-8 fuer FD/DD/Class/Sysclass/Config; siehe editor_marker()).
    * Templates/Module nutzen is_dbx_edit() fuer die grobe Ja/Nein-Frage "wird
    * gerade editiert", ohne den konkreten Modus selbst auszuwerten.
    *
    * Beispiel:
    * ```php
    * if (dbx()->is_dbx_edit()) {
    *     echo dbx()->editor_marker('class', $file);
    * }
    * ```
    *
    * @return bool true, wenn `dbx_edit` > 0 ist.
    */
   public function is_dbx_edit(): bool {
      return (int)$this->get_system_var('dbx_edit', 0, 'int') > 0
         && $this->has_group('admin');
   }

/**
    * Prüft, ob der aktuelle Benutzer ein konkretes Modul aufrufen darf.
    *
    * Sinn und Abgrenzung zu has_group(): has_group() prueft nur eine reine
    * Gruppenmitgliedschaft ohne Bezug zu einem Modul. has_module_access()
    * liest zusaetzlich die `groups`-Konfiguration des uebergebenen Moduls
    * selbst (get_cfg($modul)), beruecksichtigt den Installationsmodus
    * (`dbx_install`, waehrend der Installation immer erlaubt) sowie Admin-
    * Bypass/Demomodus, und setzt bei Ablehnung die Systemvariable
    * `dbx_noaccess_modul` fuer die Fehleranzeige. Beide Methoden sind daher
    * fachlich unterschiedlich und nicht austauschbar.
    *
    * Beispiel:
    * ```php
    * if (!dbx()->has_module_access('dbxShop_admin')) {
    *     return dbx()->get_system_obj('dbxTPL')->tpl_error('no_access');
    * }
    * ```
    *
    * @param string $modul Modulname, dessen `cfg/config.php` gelesen wird.
    * @return bool true, wenn der aktuelle Benutzer das Modul aufrufen darf.
    */
   public function has_module_access(string $modul): bool {
   
     $access=0;
     
     $current_user= $_SESSION['dbx']['current_user'] ?? array();
     $modul_config= $this->get_cfg($modul);
     $groups =$modul_config['groups'] ?? '';
     $uid    =$current_user['id'] ?? 0;
     $install=$this->get_system_var('dbx_install',0,'int');

     if ($this->is_admin_bypass_active()) {
        return true;
     }
     if ($this->is_demo_mode()) {
        return true;
     }
     if ((int)$uid === 1) {
        return true;
     }

     $access = $install ? true : $this->has_group($groups);

     if ($access==0) $this->set_system_var('dbx_noaccess_modul',"(User=$uid Modul=$modul)");
     return (bool)$access;
   }

/**
    * Meldet einen Benutzer im DBX-Kontext an.
    *
    * Beispiel:
    * ```
    * dbx()->login($uid, 1);
    * ```
    *
    * @param int $uid Benutzer-ID.
    * @param int $remember Remember-Login setzen.
    * @return void
    */
   public function login($uid=0,$remember=0) {
     $old=$this->user();
     dbx()->debug("API dbx_login von ($old) Zu ($uid)");
     if ($uid != $old) {
       $o_session=$this->get_system_obj('dbxSession');
       $o_session->login($uid,$remember);
   
       $page     =  $this->get_base_url();
       $from     =  $this->user('email');
       $fromname =  $this->user('name');
       $subject  = 'Login ('.$from.') on ('.$page.') User=('.$uid.')';
       $text     = $subject;
       //$this->sys_msg('info','login',$uid,$subject,'ok');
       //dbx_sendMail($from,$fromname,'login@dbxapp.de',$subject,$text,'text'); // #todo 
   
     }
   }
}
