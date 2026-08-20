<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryInvoiceServiceTrait {

   public function next_invoice_no(): string {
      $prefix = 'R' . date('Y');
      $rows = $this->db()->select($this->dd('shopOrder'), 'invoice_no LIKE ' . $this->sql_value($prefix . '%'), 'invoice_no', 'invoice_no DESC', 'DESC', '', 1, 0, 0);
      $last = is_array($rows) && isset($rows[0]['invoice_no']) ? (string)$rows[0]['invoice_no'] : '';
      $next = 1;
      if (preg_match('/(\d+)$/', $last, $m)) {
         $next = ((int)$m[1]) + 1;
      }
      return $prefix . '-' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
   }

   private function invoice_archive_dir(string $year): string {
      $base = rtrim(dbx()->get_base_dir(), '/\\');
      return $base . '/files/shop/invoices/' . $year;
   }

   private function invoice_archive_rel_path(string $year, string $file_name): string {
      return 'files/shop/invoices/' . $year . '/' . $file_name;
   }

   private function pdf_text(string $text): string {
      $text = str_replace(array("\r\n", "\r"), "\n", $text);
      $text = strtr($text, array(
         '€' => 'EUR',
         '„' => '"',
         '“' => '"',
         '’' => "'",
         '–' => '-',
         '—' => '-',
      ));
      $converted = function_exists('iconv') ? @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) : false;
      if ($converted !== false) {
         $text = $converted;
      }
      return str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $text);
   }

   private function create_simple_invoice_pdf(array $order, string $abs_file): bool {
      $invoice_no = trim((string)($order['invoice_no'] ?? ''));
      $invoice_date = trim((string)($order['invoice_date'] ?? date('Y-m-d')));
      $lines = array(
         'Rechnung ' . $invoice_no,
         'Datum: ' . $invoice_date,
         'Bestellung: ' . (string)($order['order_no'] ?? ''),
         '',
         'Kunde:',
         (string)($order['customer_name'] ?? ''),
         (string)($order['customer_email'] ?? ''),
      );
      $address = trim((string)($order['shipping_address'] ?? ''));
      if ($address !== '') {
         $lines[] = '';
         $lines[] = 'Lieferadresse:';
         foreach (explode("\n", str_replace("\r", '', $address)) as $line) {
            $lines[] = $line;
         }
      }
      $lines[] = '';
      $lines[] = 'Positionen:';
      foreach ((array)($order['items'] ?? array()) as $item) {
         $title = trim((string)($item['title'] ?? 'Artikel'));
         $sku = trim((string)($item['sku'] ?? ''));
         $qty = (int)($item['qty'] ?? 0);
         $total = number_format((float)($item['total_gross'] ?? 0), 2, ',', '.') . ' EUR';
         $tax = number_format((float)($item['tax_rate'] ?? 0), 2, ',', '.') . ' % MwSt.';
         $lines[] = $qty . ' x ' . $title . ($sku !== '' ? ' [' . $sku . ']' : '') . ' - ' . $total . ' (' . $tax . ')';
      }
      $lines[] = '';
      $lines[] = 'Gesamtbetrag: ' . number_format((float)($order['total_gross'] ?? 0), 2, ',', '.') . ' EUR';
      $lines[] = '';
      $lines[] = 'Dieser Beleg wurde aus dem gespeicherten Bestell-Snapshot erzeugt.';

      $content = "BT\n/F1 18 Tf\n50 790 Td\n(" . $this->pdf_text(array_shift($lines) ?: 'Rechnung') . ") Tj\n/F1 10 Tf\n0 -24 Td\n";
      foreach ($lines as $line) {
         foreach (explode("\n", wordwrap((string)$line, 95, "\n", true)) as $wrapped) {
            $content .= '(' . $this->pdf_text($wrapped) . ") Tj\n0 -14 Td\n";
         }
      }
      $content .= "ET\n";

      $objects = array();
      $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
      $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
      $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
      $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
      $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";

      $pdf = "%PDF-1.4\n";
      $offsets = array(0);
      foreach ($objects as $i => $object) {
         $offsets[$i + 1] = strlen($pdf);
         $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
      }
      $xref = strlen($pdf);
      $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
      $pdf .= "0000000000 65535 f \n";
      for ($i = 1; $i <= count($objects); $i++) {
         $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
      }
      $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

      $dir = dirname($abs_file);
      if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
         return false;
      }
      return @file_put_contents($abs_file, $pdf) !== false;
   }

   public function ensure_order_invoice_pdf(int $id): ?array {
      $this->install();
      $order = $this->order_by_id($id);
      if (!is_array($order)) {
         return null;
      }

      $invoice_no = trim((string)($order['invoice_no'] ?? ''));
      $invoice_date = trim((string)($order['invoice_date'] ?? ''));
      $updates = array();
      if ($invoice_no === '') {
         $invoice_no = $this->next_invoice_no();
         $updates['invoice_no'] = $invoice_no;
      }
      if ($invoice_date === '') {
         $invoice_date = date('Y-m-d');
         $updates['invoice_date'] = $invoice_date;
      }
      if ($updates !== array()) {
         $updates['update_date'] = date('Y-m-d H:i:s');
         $this->db()->update($this->dd('shopOrder'), $updates, 'id = ' . (int)$id . ' AND trash = 0', 0);
         $order = $this->order_by_id($id) ?: $order;
      }

      $year = substr($invoice_date, 0, 4);
      if (!preg_match('/^\d{4}$/', $year)) {
         $year = date('Y');
      }
      $safe_no = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $invoice_no) ?: ('rechnung-' . $id);
      $file_name = $safe_no . '.pdf';
      $rel_path = $this->invoice_archive_rel_path($year, $file_name);
      $abs_path = $this->invoice_archive_dir($year) . '/' . $file_name;
      $old_rel_path = trim((string)($order['invoice_pdf_path'] ?? ''));
      $created = false;

      if (!is_file($abs_path)) {
         if (!$this->create_simple_invoice_pdf($order, $abs_path)) {
            return null;
         }
         $created = true;
      }

      $this->db()->update($this->dd('shopOrder'), array(
         'invoice_pdf_path' => $rel_path,
         'invoice_pdf_date' => date('Y-m-d H:i:s'),
         'update_date' => date('Y-m-d H:i:s'),
      ), 'id = ' . (int)$id . ' AND trash = 0', 0);
      if ($created || $old_rel_path !== $rel_path) {
         $this->add_order_history($id, 'invoice_pdf', '', $rel_path, 'Rechnungs-PDF wurde erzeugt oder aktualisiert.');
      }
      return $this->order_by_id($id);
   }

   public function invoice_pdf_absolute_path(array $order): string {
      $rel = trim((string)($order['invoice_pdf_path'] ?? ''));
      if ($rel === '') {
         return '';
      }
      $base = rtrim(dbx()->get_base_dir(), '/\\');
      $path = $base . '/' . ltrim($rel, '/\\');
      return is_file($path) ? $path : '';
   }
}
