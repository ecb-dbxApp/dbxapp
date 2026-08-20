<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryWithdrawalServiceTrait {

   public function save_withdrawal(array $data): ?array {
      $this->install();
      $order_no = trim((string)($data['order_no'] ?? ''));
      $order = $order_no !== '' ? $this->order_by_no($order_no) : null;
      $order_id = is_array($order) ? (int)($order['id'] ?? 0) : 0;
      $ok = (int)$this->db()->insert($this->dd('shopWithdrawal'), array(
         'order_id' => $order_id,
         'order_no' => $order_no,
         'customer_name' => trim((string)($data['customer_name'] ?? '')),
         'customer_email' => trim((string)($data['customer_email'] ?? '')),
         'customer_address' => trim((string)($data['customer_address'] ?? '')),
         'reason' => trim((string)($data['reason'] ?? '')),
         'status' => 'new',
      ), 0);
      if ($ok !== 1) {
         return null;
      }
      $id = (int)$this->db()->get_insert_id();
      if ($order_id > 0) {
         $this->add_order_history($order_id, 'withdrawal', '', 'new', 'Widerruf wurde eingereicht.');
      }
      return $this->withdrawal_by_id($id);
   }

   public function update_withdrawal_admin(int $id, string $status, string $note = ''): bool {
      $this->install();
      $allowed = array('new', 'processing', 'accepted', 'rejected', 'refunded', 'closed');
      if (!in_array($status, $allowed, true)) {
         return false;
      }
      $before = $this->withdrawal_by_id($id);
      if (!is_array($before)) {
         return false;
      }

      $ok = $this->db()->update($this->dd('shopWithdrawal'), array(
         'status' => $status,
         'admin_note' => $note !== '' ? $note : (string)($before['admin_note'] ?? ''),
         'update_date' => date('Y-m-d H:i:s'),
      ), 'id = ' . (int)$id . ' AND trash = 0', 0);

      $order_id = (int)($before['order_id'] ?? 0);
      if ($order_id > 0) {
         $old = (string)($before['status'] ?? '');
         if ($old !== $status) {
            $this->add_order_history($order_id, 'withdrawal_status', $old, $status, 'Widerrufsstatus wurde geaendert.');
         }
         if (in_array($status, array('accepted', 'refunded'), true)) {
            $order = $this->order_by_id($order_id);
            if (is_array($order)) {
               $this->release_stock_for_order($order, 'Bestand wurde durch Widerruf zurueckgebucht.');
            }
         }
      }

      return $ok !== 0 || $this->withdrawal_by_id($id) !== null;
   }

   public function withdrawal_by_id(int $id): ?array {
      $row = $this->db()->select1($this->dd('shopWithdrawal'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      return is_array($row) ? $row : null;
   }

   public function withdrawals(array $filters = array(), int $limit = 50, int $offset = 0): array {
      $this->install();
      $where = array('trash = 0');
      $query = trim((string)($filters['query'] ?? ''));
      if ($query !== '') {
         $like = $this->sql_like_value($query);
         $where[] = '(order_no LIKE ' . $like . ' OR customer_name LIKE ' . $like . ' OR customer_email LIKE ' . $like . ')';
      }
      $status = trim((string)($filters['status'] ?? ''));
      if ($status !== '') {
         $where[] = 'status = ' . $this->sql_value($status);
      }
      $rows = $this->db()->select($this->dd('shopWithdrawal'), implode(' AND ', $where), '*', 'create_date DESC, id DESC', 'ASC', '', $limit > 0 ? $limit : 0, max(0, $offset), 0);
      return is_array($rows) ? $rows : array();
   }

   public function withdrawal_count(array $filters = array()): int {
      $where = array('trash = 0');
      $query = trim((string)($filters['query'] ?? ''));
      if ($query !== '') {
         $like = $this->sql_like_value($query);
         $where[] = '(order_no LIKE ' . $like . ' OR customer_name LIKE ' . $like . ' OR customer_email LIKE ' . $like . ')';
      }
      $status = trim((string)($filters['status'] ?? ''));
      if ($status !== '') {
         $where[] = 'status = ' . $this->sql_value($status);
      }
      return max(0, (int)$this->db()->count($this->dd('shopWithdrawal'), implode(' AND ', $where)));
   }

   public function withdrawals_for_order(int $order_id): array {
      if ($order_id <= 0) {
         return array();
      }
      $rows = $this->db()->select($this->dd('shopWithdrawal'), 'order_id = ' . (int)$order_id . ' AND trash = 0', '*', 'create_date DESC, id DESC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }
}
