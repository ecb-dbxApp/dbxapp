<?php
namespace dbx\dbxContact;

class dbxContactTicket {

   public const DD_TICKET = 'dbxContact|contactRequest';
   public const DD_MESSAGE = 'dbxContact|contactMessage';

   public static function statuses(): array {
      return array(
         'open' => 'Offen',
         'in_progress' => 'In Bearbeitung',
         'waiting_customer' => 'Rueckfrage',
         'answered' => 'Beantwortet',
         'closed' => 'Geschlossen',
      );
   }

   public static function priorities(): array {
      return array(
         'low' => 'Niedrig',
         'normal' => 'Normal',
         'high' => 'Hoch',
         'urgent' => 'Dringend',
      );
   }

   public function openCount(): int {
      $db = dbx()->get_system_obj('dbxDB');
      if (!is_object($db)) {
         return 0;
      }

      $count = $db->count(self::DD_TICKET, array('status' => 'open'));
      return max(0, (int) $count);
   }

   public static function normalizeStatus(string $status, string $fallback = 'open'): string {
      return array_key_exists($status, self::statuses()) ? $status : $fallback;
   }

   public static function normalizePriority(string $priority, string $fallback = 'normal'): string {
      return array_key_exists($priority, self::priorities()) ? $priority : $fallback;
   }

   public static function ticket($db, int $ticketId): array {
      if (!is_object($db) || $ticketId <= 0) {
         return array();
      }
      $row = $db->select1(self::DD_TICKET, $ticketId, '*', 0);
      return is_array($row) ? $row : array();
   }

   public static function userOwns(array $ticket, int $uid): bool {
      return $uid > 0 && (int) ($ticket['uid'] ?? 0) === $uid;
   }

   public static function messages($db, int $ticketId, bool $includeInternal = false): array {
      if (!is_object($db) || $ticketId <= 0) {
         return array();
      }

      $where = 'ticket_id = ' . $ticketId;
      if (!$includeInternal) {
         $where .= " AND visibility = 'public'";
      }

      $rows = $db->select(
         self::DD_MESSAGE,
         $where,
         array('id', 'create_date', 'author_uid', 'author_type', 'message_type', 'visibility', 'body', 'status_from', 'status_to', 'mail_sent', 'mail_sent_date'),
         'create_date,id',
         'ASC',
         '',
         1000,
         0,
         0
      );

      return is_array($rows) ? $rows : array();
   }

   public static function ensureInitialMessage($db, array $ticket): void {
      $ticketId = (int) ($ticket['id'] ?? 0);
      if (!is_object($db) || $ticketId <= 0) {
         return;
      }
      $message = trim((string) ($ticket['message'] ?? ''));
      $hasRequest = $db->count(
         self::DD_MESSAGE,
         "ticket_id = " . $ticketId . " AND message_type = 'request'"
      ) > 0;
      if ($message !== '' && !$hasRequest) {
         self::addMessage($db, $ticketId, array(
            'author_uid' => (int) ($ticket['uid'] ?? 0),
            'author_type' => 'requester',
            'message_type' => 'request',
            'visibility' => 'public',
            'body' => $message,
            'status_to' => self::normalizeStatus((string) ($ticket['status'] ?? 'open')),
            'create_date' => (string) ($ticket['create_date'] ?? ''),
         ));
      }

   }

   public static function addMessage($db, int $ticketId, array $data): int {
      if (!is_object($db) || $ticketId <= 0) {
         return 0;
      }

      $values = array(
         'ticket_id' => $ticketId,
         'author_uid' => (int) ($data['author_uid'] ?? dbx()->user()),
         'author_type' => (string) ($data['author_type'] ?? 'system'),
         'message_type' => (string) ($data['message_type'] ?? 'message'),
         'visibility' => (string) ($data['visibility'] ?? 'public'),
         'body' => trim((string) ($data['body'] ?? '')),
         'status_from' => (string) ($data['status_from'] ?? ''),
         'status_to' => (string) ($data['status_to'] ?? ''),
         'mail_sent' => (int) ($data['mail_sent'] ?? 0),
         'mail_sent_date' => (string) ($data['mail_sent_date'] ?? ''),
      );

      if (!empty($data['create_date'])) {
         $values['create_date'] = (string) $data['create_date'];
      }

      $ok = $db->insert(self::DD_MESSAGE, $values, 0, 1, 1, 1);
      return $ok > 0 ? (int) $db->get_insert_id() : 0;
   }

   public static function touch($db, int $ticketId, array $values = array()): bool {
      if (!is_object($db) || $ticketId <= 0) {
         return false;
      }
      $values['last_activity_date'] = date('Y-m-d H:i:s');
      return $db->update(self::DD_TICKET, $values, $ticketId, 0, 1, 1, 1) === 1;
   }

   public static function statusLabel(string $status): string {
      $statuses = self::statuses();
      return $statuses[$status] ?? $status;
   }

   public static function priorityLabel(string $priority): string {
      $priorities = self::priorities();
      return $priorities[$priority] ?? $priority;
   }
}
