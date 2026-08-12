<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryWithdrawalServiceTrait {

   public function saveWithdrawal(array $data): ?array {
      $this->install();
      $orderNo = trim((string)($data['order_no'] ?? ''));
      $order = $orderNo !== '' ? $this->orderByNo($orderNo) : null;
      $orderId = is_array($order) ? (int)($order['id'] ?? 0) : 0;
      $ok = (int)$this->db()->insert($this->dd('shopWithdrawal'), array(
         'order_id' => $orderId,
         'order_no' => $orderNo,
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
      if ($orderId > 0) {
         $this->addOrderHistory($orderId, 'withdrawal', '', 'new', 'Widerruf wurde eingereicht.');
      }
      return $this->withdrawalById($id);
   }

   public function updateWithdrawalAdmin(int $id, string $status, string $note = ''): bool {
      $this->install();
      $allowed = array('new', 'processing', 'accepted', 'rejected', 'refunded', 'closed');
      if (!in_array($status, $allowed, true)) {
         return false;
      }
      $before = $this->withdrawalById($id);
      if (!is_array($before)) {
         return false;
      }

      $ok = $this->db()->update($this->dd('shopWithdrawal'), array(
         'status' => $status,
         'admin_note' => $note !== '' ? $note : (string)($before['admin_note'] ?? ''),
         'update_date' => date('Y-m-d H:i:s'),
      ), 'id = ' . (int)$id . ' AND trash = 0', 0);

      $orderId = (int)($before['order_id'] ?? 0);
      if ($orderId > 0) {
         $old = (string)($before['status'] ?? '');
         if ($old !== $status) {
            $this->addOrderHistory($orderId, 'withdrawal_status', $old, $status, 'Widerrufsstatus wurde geaendert.');
         }
         if (in_array($status, array('accepted', 'refunded'), true)) {
            $order = $this->orderById($orderId);
            if (is_array($order)) {
               $this->releaseStockForOrder($order, 'Bestand wurde durch Widerruf zurueckgebucht.');
            }
         }
      }

      return $ok !== 0 || $this->withdrawalById($id) !== null;
   }

   public function withdrawalById(int $id): ?array {
      $row = $this->db()->select1($this->dd('shopWithdrawal'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      return is_array($row) ? $row : null;
   }

   public function withdrawals(array $filters = array(), int $limit = 50, int $offset = 0): array {
      $this->install();
      $where = array('trash = 0');
      $query = trim((string)($filters['query'] ?? ''));
      if ($query !== '') {
         $like = $this->sqlLikeValue($query);
         $where[] = '(order_no LIKE ' . $like . ' OR customer_name LIKE ' . $like . ' OR customer_email LIKE ' . $like . ')';
      }
      $status = trim((string)($filters['status'] ?? ''));
      if ($status !== '') {
         $where[] = 'status = ' . $this->sqlValue($status);
      }
      $rows = $this->db()->select($this->dd('shopWithdrawal'), implode(' AND ', $where), '*', 'create_date DESC, id DESC', 'ASC', '', $limit > 0 ? $limit : 0, max(0, $offset), 0);
      return is_array($rows) ? $rows : array();
   }

   public function withdrawalCount(array $filters = array()): int {
      $where = array('trash = 0');
      $query = trim((string)($filters['query'] ?? ''));
      if ($query !== '') {
         $like = $this->sqlLikeValue($query);
         $where[] = '(order_no LIKE ' . $like . ' OR customer_name LIKE ' . $like . ' OR customer_email LIKE ' . $like . ')';
      }
      $status = trim((string)($filters['status'] ?? ''));
      if ($status !== '') {
         $where[] = 'status = ' . $this->sqlValue($status);
      }
      return max(0, (int)$this->db()->count($this->dd('shopWithdrawal'), implode(' AND ', $where)));
   }

   public function withdrawalsForOrder(int $orderId): array {
      if ($orderId <= 0) {
         return array();
      }
      $rows = $this->db()->select($this->dd('shopWithdrawal'), 'order_id = ' . (int)$orderId . ' AND trash = 0', '*', 'create_date DESC, id DESC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }
}
