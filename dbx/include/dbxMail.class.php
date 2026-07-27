<?php

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

class dbxMail {

  public $errstr = '';
  public $headers = array();
  public $textbody = '';
  public $htmlbody = '';
  public $attachments = array();
  public $html_images = array();
  public $auto_embbed_images = true;
  public $auto_embed_images = true;
  public $boundary = '';
  public $sendmail_path = '';
  public $subject = '';
  public $spam_protection = true;

  private $from = '';
  private $fromname = '';

  public function init() {
    $this->errstr = '';
    $this->headers = array();
    $this->textbody = '';
    $this->htmlbody = '';
    $this->attachments = array();
    $this->html_images = array();
    $this->auto_embbed_images = true;
    $this->auto_embed_images = true;
    $this->boundary = '==Multipart_Boundary_x' . md5((string) microtime(true)) . 'x';
    $this->sendmail_path = '';
    $this->subject = '';
    $this->spam_protection = true;
    $this->from = '';
    $this->fromname = '';
  }

  public function checkEmail($inAddress) {
    return filter_var((string) $inAddress, FILTER_VALIDATE_EMAIL) !== false;
  }

  public function get_body() {
    return $this->textbody . $this->htmlbody;
  }

  public function get_header() {
    $retval = '';
    foreach ($this->headers as $key => $value) {
      $retval .= $key . ': ' . $value . "\n";
    }
    return $retval;
  }

  public function set_header($name, $value) {
    $name = trim((string) $name);
    $value = trim((string) $value);

    if (strcasecmp($name, 'From') === 0) {
      $from = $this->parse_address($value);
      $this->from = $from['email'];
      $this->fromname = $from['name'];
    }

    $this->headers[$name] = $value;
  }

  public function set_from($email, $name = '') {
    $this->from = trim((string) $email);
    $this->fromname = trim((string) $name);
    $this->headers['From'] = $this->format_address($this->from, $this->fromname);
  }

  public function attachfile($file, $disp = 'attachment', $contentType = '', $cid = '') {
    $file = trim((string) $file);

    if ($file === '' || !is_file($file) || !is_readable($file)) {
      $this->errstr = 'Attachment nicht lesbar: ' . $file;
      return 0;
    }

    $this->attachments[] = array(
      'path' => $file,
      'name' => basename($file),
      'disposition' => $disp ?: 'attachment',
      'content_type' => $contentType ?: $this->getContentType($file),
      'cid' => (string) $cid,
    );

    return 1;
  }

  public function getContentType($inFileName) {
    $file = (string) $inFileName;

    if (function_exists('mime_content_type') && is_file($file)) {
      $type = @mime_content_type($file);
      if ($type) {
        return $type;
      }
    }

    $extension = strtolower((string) strrchr(basename($file), '.'));

    switch ($extension) {
      case '.gz':   return 'application/gzip';
      case '.zip':  return 'application/zip';
      case '.tar':  return 'application/x-tar';
      case '.htm':
      case '.html': return 'text/html';
      case '.txt':  return 'text/plain';
      case '.jpg':
      case '.jpeg': return 'image/jpeg';
      case '.gif':  return 'image/gif';
      case '.png':  return 'image/png';
      case '.pdf':  return 'application/pdf';
      case '.csv':  return 'text/csv';
      case '.json': return 'application/json';
      default:      return 'application/octet-stream';
    }
  }

  public function bodytext($text) {
    if (strlen(trim((string) $text)) > 0) {
      $this->textbody = (string) $text;
    }
  }

  public function bodyhtml($text) {
    if (strlen(trim((string) $text)) > 0) {
      $this->htmlbody = (string) $text;
    }
  }

  public function htmltext($text) {
    if (strlen(trim((string) $text)) > 0) {
      $this->htmlbody .= (string) $text;
    }
  }

  public function clear_bodytext() { $this->textbody = ''; }
  public function clear_htmltext() { $this->htmlbody = ''; }
  public function get_error() { return $this->errstr; }

  public function send($to = 'root@localhost', $subject = 'Default Subject', $options = array()) {
    $this->errstr = '';
    $subject = (string) ($subject !== '' ? $subject : $this->subject);

    try {
      $mail = new PHPMailer(true);
      $mail->CharSet = 'UTF-8';
      $mail->Encoding = 'base64';

      $config = $this->mail_config($options);
      $this->configure_transport($mail, $config);
      $this->configure_sender($mail, $config, $options);

      $this->add_addresses($mail, $to, 'addAddress');
      $this->add_addresses($mail, $options['cc'] ?? $this->headers['Cc'] ?? array(), 'addCC');
      $this->add_addresses($mail, $options['bcc'] ?? $this->headers['Bcc'] ?? array(), 'addBCC');
      $this->add_addresses($mail, $options['reply_to'] ?? $this->headers['Reply-To'] ?? array(), 'addReplyTo');

      $mail->Subject = $subject;
      $this->configure_body($mail, $options);
      $this->configure_headers($mail);
      $this->configure_attachments($mail);

      $spamReason = $this->spam_guard_reason($mail, $to, $subject, $options);
      if ($spamReason !== '') {
        $this->errstr = 'Mail wurde durch den Spam-Schutz blockiert: ' . $spamReason;
        $this->sys_msg('security', $to, $subject, $this->errstr);
        return 0;
      }

      $ok = $mail->send() ? 1 : 0;
      $this->sys_msg($ok ? 'info' : 'error', $to, $subject, $ok ? 'sent' : $mail->ErrorInfo);

      return $ok;
    } catch (PHPMailerException $e) {
      $this->errstr = $e->getMessage();
    } catch (Throwable $e) {
      $this->errstr = $e->getMessage();
    }

    $this->sys_msg('error', $to, $subject, $this->errstr);
    return 0;
  }

  private function mail_config($options) {
    $config = array();

    if (function_exists('dbx')) {
      $cfg = dbx()->get_config('dbx', 'mail');
      if (is_array($cfg)) {
        $config = $cfg;
      }

      $defaultMail = dbx()->get_config('dbx', 'default_mail');
      if ($defaultMail !== 'undef' && $defaultMail !== '') {
        $config['default'] = (string) $defaultMail;
      }
    }

    if (is_string($options['mail'] ?? null)) {
      $options['mail_profile'] = $options['mail'];
    }

    $config = $this->select_mail_profile($config, $options);

    if (is_array($options['mail'] ?? null)) {
      $config = array_replace_recursive($config, $options['mail']);
    }

    return $config;
  }

  private function select_mail_profile(array $config, array $options) {
    $profiles = $this->mail_profiles_from_config($config);
    if (!$profiles) {
      return $config;
    }

    $profileName = (string) ($options['mail_profile'] ?? $options['profile'] ?? '');
    $from = $this->normalize_from($options['from'] ?? null);

    if ($profileName === '' && $from['email'] !== '') {
      $profileName = $this->profile_for_sender($profiles, $from['email']);
    }

    if ($profileName === '') {
      $profileName = (string) ($config['default'] ?? '');
    }

    if ($profileName === '' || !isset($profiles[$profileName]) || !is_array($profiles[$profileName])) {
      $profileName = (string) ($config['default'] ?? '');
    }

    if (($profileName === '' || !isset($profiles[$profileName])) && count($profiles)) {
      $keys = array_keys($profiles);
      $profileName = (string) $keys[0];
    }

    $base = $this->mail_base_config($config);

    if ($profileName !== '' && isset($profiles[$profileName]) && is_array($profiles[$profileName])) {
      return array_replace_recursive($base, $profiles[$profileName], array('profile' => $profileName));
    }

    return $base;
  }

  private function mail_profiles_from_config(array $config) {
    if (is_array($config['profiles'] ?? null)) {
      return $config['profiles'];
    }

    $profiles = array();
    foreach ($config as $key => $value) {
      if ($key === 'default' || $key === 'profiles') {
        continue;
      }
      if (is_array($value)) {
        $profiles[(string) $key] = $value;
      }
    }

    return $profiles;
  }

  private function mail_base_config(array $config) {
    $base = $config;
    unset($base['profiles'], $base['default']);

    foreach ($base as $key => $value) {
      if (is_array($value)) {
        unset($base[$key]);
      }
    }

    return $base;
  }

  private function profile_for_sender(array $profiles, $email) {
    $domain = strtolower((string) substr(strrchr((string) $email, '@') ?: '', 1));
    if ($domain === '') {
      return '';
    }

    foreach ($profiles as $name => $profile) {
      if ($this->profile_matches_domain($profile, $domain, false)) {
        return (string) $name;
      }
    }

    foreach ($profiles as $name => $profile) {
      if ($this->profile_matches_domain($profile, $domain, true)) {
        return (string) $name;
      }
    }

    return '';
  }

  private function profile_matches_domain($profile, $domain, $allowWildcard) {
    if (!is_array($profile)) {
      return false;
    }

    $domains = $profile['from_domains'] ?? $profile['from_domain'] ?? array();
    if (is_string($domains)) {
      $domains = preg_split('/[;,]+/', $domains);
    }

    foreach ((array) $domains as $allowed) {
      $allowed = strtolower(trim((string) $allowed));
      if ($allowed === '') {
        continue;
      }
      if ($allowed === $domain) {
        return true;
      }
      if ($allowWildcard && ($allowed === '*' || (substr($allowed, 0, 2) === '*.' && str_ends_with($domain, substr($allowed, 1))))) {
        return true;
      }
    }

    return false;
  }

  private function configure_transport(PHPMailer $mail, array $config) {
    $transport = strtolower((string) ($config['transport'] ?? $config['type'] ?? ''));
    $host = trim((string) ($config['host'] ?? $config['smtp_host'] ?? ''));

    if ($transport === 'smtp' || $host !== '') {
      $username = (string) ($config['user'] ?? $config['username'] ?? '');
      $password = (string) ($config['pass'] ?? $config['password'] ?? '');
      $auth = (bool) ($config['auth'] ?? $config['smtp_auth'] ?? ($username !== ''));

      if ($auth && ($username === '' || $password === '')) {
        throw new PHPMailerException('SMTP auth configuration incomplete.');
      }

      $mail->isSMTP();
      $mail->Host = $host;
      $mail->Port = (int) ($config['port'] ?? $config['smtp_port'] ?? 587);
      $mail->Timeout = max(1, (int) ($config['timeout'] ?? $config['smtp_timeout'] ?? 3));
      $mail->SMTPAuth = $auth;
      $mail->Username = $username;
      $mail->Password = $password;

      $secure = strtolower((string) ($config['secure'] ?? $config['smtp_secure'] ?? ''));
      if (in_array($secure, array('tls', 'ssl'), true)) {
        $mail->SMTPSecure = $secure;
      }
      return;
    }

    if ($transport === 'sendmail' || $this->sendmail_path !== '' || !empty($config['sendmail_path'])) {
      $mail->isSendmail();
      $path = $this->sendmail_path ?: (string) ($config['sendmail_path'] ?? '');
      if ($path !== '') {
        $mail->Sendmail = $path;
      }
      return;
    }

    $mail->isMail();
  }

  private function configure_sender(PHPMailer $mail, array $config, array $options) {
    $forceFrom = !empty($config['force_from']);
    $from = $forceFrom ? array('email' => '', 'name' => '') : $this->normalize_from($options['from'] ?? null);

    if ($from['email'] === '') {
      $from = $this->normalize_from($this->from ? array('email' => $this->from, 'name' => $this->fromname) : null);
    }

    if ($from['email'] === '') {
      $from = $this->normalize_from($config['from'] ?? array(
        'email' => $config['from_email'] ?? '',
        'name' => $config['from_name'] ?? '',
      ));
    }

    if ($from['email'] === '') {
      throw new PHPMailerException('Absender fehlt.');
    }

    $mail->setFrom($from['email'], $from['name']);

    $sender = trim((string) ($config['sender'] ?? $config['return_path'] ?? $config['envelope_from'] ?? ''));
    if ($sender !== '') {
      $mail->Sender = $sender;
    }
  }

  private function configure_body(PHPMailer $mail, array $options) {
    $html = (string) ($options['html'] ?? $this->htmlbody);
    $text = (string) ($options['text'] ?? $this->textbody);

    if ($html !== '') {
      $mail->isHTML(true);
      $mail->Body = $this->embed_html_images($mail, $html);
      $mail->AltBody = $text !== '' ? $text : $this->html_to_text($html);
      return;
    }

    $mail->isHTML(false);
    $mail->Body = $text;
  }

  private function configure_headers(PHPMailer $mail) {
    foreach ($this->headers as $key => $value) {
      if (in_array(strtolower((string) $key), array('from', 'to', 'cc', 'bcc', 'reply-to', 'content-type', 'mime-version'), true)) {
        continue;
      }
      $mail->addCustomHeader((string) $key, (string) $value);
    }
  }

  private function configure_attachments(PHPMailer $mail) {
    foreach ($this->attachments as $attachment) {
      if (!is_array($attachment)) {
        continue;
      }

      $path = (string) ($attachment['path'] ?? '');
      if ($path === '' || !is_file($path) || !is_readable($path)) {
        continue;
      }

      $name = (string) ($attachment['name'] ?? basename($path));
      $encoding = PHPMailer::ENCODING_BASE64;
      $type = (string) ($attachment['content_type'] ?? $this->getContentType($path));
      $disp = (string) ($attachment['disposition'] ?? 'attachment');
      $cid = (string) ($attachment['cid'] ?? '');

      if ($disp === 'inline' && $cid !== '') {
        $mail->addEmbeddedImage($path, $cid, $name, $encoding, $type);
      } else {
        $mail->addAttachment($path, $name, $encoding, $type, $disp);
      }
    }
  }

  private function embed_html_images(PHPMailer $mail, $html) {
    if (!$this->auto_embbed_images && !$this->auto_embed_images) {
      return $html;
    }

    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches);
    $images = array_unique($matches[1] ?? array());

    foreach ($images as $src) {
      $file = $this->html_image_path($src);
      if (!$file) {
        continue;
      }

      $cid = 'part.' . md5($file);
      $mail->addEmbeddedImage($file, $cid, basename($file), PHPMailer::ENCODING_BASE64, $this->getContentType($file));
      $html = str_replace($src, 'cid:' . $cid, $html);
      $this->html_images[] = $file;
    }

    return $html;
  }

  private function html_image_path($src) {
    $src = trim((string) $src);
    if ($src === '' || preg_match('/^(https?:|data:|cid:)/i', $src)) {
      return '';
    }

    $path = $src;
    if (!preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) && substr($path, 0, 1) !== '/') {
      $path = dbx()->get_base_dir() . ltrim($path, '/\\');
    }

    $path = dbx()->os_path($path);
    return is_file($path) && is_readable($path) ? $path : '';
  }

  private function add_addresses(PHPMailer $mail, $addresses, $method) {
    foreach ($this->normalize_addresses($addresses) as $address) {
      if ($address['email'] !== '') {
        $mail->$method($address['email'], $address['name']);
      }
    }
  }

  private function normalize_addresses($addresses) {
    if ($addresses === null || $addresses === '') {
      return array();
    }

    if (is_string($addresses)) {
      $parts = preg_split('/[;,]+/', $addresses);
      return array_map(array($this, 'parse_address'), array_filter(array_map('trim', $parts)));
    }

    if (is_array($addresses)) {
      if (isset($addresses['email'])) {
        return array($this->normalize_from($addresses));
      }

      $out = array();
      foreach ($addresses as $key => $value) {
        if (is_string($key) && is_string($value) && filter_var($key, FILTER_VALIDATE_EMAIL)) {
          $out[] = array('email' => $key, 'name' => $value);
        } else {
          $out[] = is_array($value) ? $this->normalize_from($value) : $this->parse_address((string) $value);
        }
      }
      return $out;
    }

    return array();
  }

  private function normalize_from($from) {
    if (is_array($from)) {
      return array(
        'email' => trim((string) ($from['email'] ?? $from['mail'] ?? $from[0] ?? '')),
        'name' => trim((string) ($from['name'] ?? $from[1] ?? '')),
      );
    }

    if (is_string($from)) {
      return $this->parse_address($from);
    }

    return array('email' => '', 'name' => '');
  }

  private function parse_address($value) {
    $value = trim((string) $value);
    if (preg_match('/^(.*)<([^>]+)>$/', $value, $m)) {
      return array('email' => trim($m[2]), 'name' => trim(trim($m[1]), '"\' '));
    }
    return array('email' => $value, 'name' => '');
  }

  private function format_address($email, $name = '') {
    $email = trim((string) $email);
    $name = trim((string) $name);
    return $name !== '' ? $name . ' <' . $email . '>' : $email;
  }

  private function html_to_text($html) {
    return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  }

  private function sys_msg($status, $to, $subject, $message) {
    if (!function_exists('dbx')) {
      return;
    }

    try {
      dbx()->sys_msg($status, 'mail', '', (string) $subject, 'to=' . $this->address_debug($to) . ' ' . (string) $message);
    } catch (Throwable $e) {
      // Mailversand darf nicht an der Protokollierung scheitern.
    }
  }

  private function address_debug($addresses) {
    $list = array();
    foreach ($this->normalize_addresses($addresses) as $address) {
      if ($address['email'] !== '') {
        $list[] = $address['email'];
      }
    }
    return implode(',', $list);
  }

  private function spam_guard_reason(PHPMailer $mail, $to, string $subject, array $options): string {
    if (!$this->spam_protection || !empty($options['skip_spam_guard'])) {
      return '';
    }

    return $this->spam_reason_for_text($this->spam_guard_text($mail, $to, $subject, $options));
  }

  public function spam_reason_for_text(string $text): string {
    if (!$this->spam_protection) {
      return '';
    }

    return $this->spam_content_reason($text);
  }

  private function spam_guard_text(PHPMailer $mail, $to, string $subject, array $options): string {
    $parts = array(
      $subject,
      $mail->Body,
      $mail->AltBody,
      (string) ($options['text'] ?? ''),
      (string) ($options['html'] ?? ''),
      $this->address_debug($to),
      $this->address_debug($options['reply_to'] ?? $this->headers['Reply-To'] ?? array()),
    );

    foreach ($this->headers as $key => $value) {
      $parts[] = (string) $key . ': ' . (string) $value;
    }

    return html_entity_decode(strip_tags(implode("\n", $parts)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }

  private function spam_content_reason(string $text): string {
    $normalized = strtolower($text);
    $normalized = preg_replace('/\s+/u', ' ', $normalized) ?: $normalized;
    $score = 0;

    $hardPatterns = array(
      '/(?:https?:\/\/)?(?:www\.)?telegra\.ph\//i' => 'telegra.ph link',
      '/\btransaction[-\s_]*\d{2}-\d{2}-/i' => 'transaction spam code',
      '/\b(?:transaction|transfer|top\s*up)\b.{0,80}\b(?:get|claim|bonus|payment)\b/i' => 'finance spam phrase',
    );

    foreach ($hardPatterns as $pattern => $reason) {
      if (preg_match($pattern, $text)) {
        return $reason;
      }
    }

    if (preg_match('/\b(?:transaction|transfer|top\s*up|crypto|bitcoin|wallet|usdt|profit|investment)\b/i', $text)) {
      $score += 2;
    }
    if (preg_match('/(?:\$|usd|eur)\s*\d{3,}|\d{3,}\s*(?:\$|usd|eur)/i', $text)) {
      $score += 2;
    }
    if (preg_match('/(?:https?:\/\/|www\.|[a-z0-9-]+\.(?:ph|ru|top|xyz|click|link|icu|buzz|cfd|quest)\b)/i', $text)) {
      $score += 2;
    }
    if (preg_match('/\bget\s*(?:-|>|&gt;|to|:)/i', $text)) {
      $score += 1;
    }
    if (preg_match('/[\x{1F300}-\x{1FAFF}]/u', $text)) {
      $score += 1;
    }

    $urlCount = preg_match_all('/(?:https?:\/\/|www\.|[a-z0-9-]+\.[a-z]{2,}\/)/i', $text);
    if ($urlCount >= 2) {
      $score += 2;
    }

    return $score >= 5 ? 'spam score ' . $score : '';
  }
}

?>
