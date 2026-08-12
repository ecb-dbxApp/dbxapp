<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryInvoiceServiceTrait {

   public function nextInvoiceNo(): string {
      $prefix = 'R' . date('Y');
      $rows = $this->db()->select($this->dd('shopOrder'), 'invoice_no LIKE ' . $this->sqlValue($prefix . '%'), 'invoice_no', 'invoice_no DESC', 'DESC', '', 1, 0, 0);
      $last = is_array($rows) && isset($rows[0]['invoice_no']) ? (string)$rows[0]['invoice_no'] : '';
      $next = 1;
      if (preg_match('/(\d+)$/', $last, $m)) {
         $next = ((int)$m[1]) + 1;
      }
      return $prefix . '-' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
   }

   private function invoiceArchiveDir(string $year): string {
      $base = rtrim(dbx()->get_base_dir(), '/\\');
      return $base . '/files/shop/invoices/' . $year;
   }

   private function invoiceArchiveRelPath(string $year, string $fileName): string {
      return 'files/shop/invoices/' . $year . '/' . $fileName;
   }

   private function pdfText(string $text): string {
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

   private function createSimpleInvoicePdf(array $order, string $absFile): bool {
      $invoiceNo = trim((string)($order['invoice_no'] ?? ''));
      $invoiceDate = trim((string)($order['invoice_date'] ?? date('Y-m-d')));
      $lines = array(
         'Rechnung ' . $invoiceNo,
         'Datum: ' . $invoiceDate,
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

      $content = "BT\n/F1 18 Tf\n50 790 Td\n(" . $this->pdfText(array_shift($lines) ?: 'Rechnung') . ") Tj\n/F1 10 Tf\n0 -24 Td\n";
      foreach ($lines as $line) {
         foreach (explode("\n", wordwrap((string)$line, 95, "\n", true)) as $wrapped) {
            $content .= '(' . $this->pdfText($wrapped) . ") Tj\n0 -14 Td\n";
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

      $dir = dirname($absFile);
      if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
         return false;
      }
      return @file_put_contents($absFile, $pdf) !== false;
   }

   public function ensureOrderInvoicePdf(int $id): ?array {
      $this->install();
      $order = $this->orderById($id);
      if (!is_array($order)) {
         return null;
      }

      $invoiceNo = trim((string)($order['invoice_no'] ?? ''));
      $invoiceDate = trim((string)($order['invoice_date'] ?? ''));
      $updates = array();
      if ($invoiceNo === '') {
         $invoiceNo = $this->nextInvoiceNo();
         $updates['invoice_no'] = $invoiceNo;
      }
      if ($invoiceDate === '') {
         $invoiceDate = date('Y-m-d');
         $updates['invoice_date'] = $invoiceDate;
      }
      if ($updates !== array()) {
         $updates['update_date'] = date('Y-m-d H:i:s');
         $this->db()->update($this->dd('shopOrder'), $updates, 'id = ' . (int)$id . ' AND trash = 0', 0);
         $order = $this->orderById($id) ?: $order;
      }

      $year = substr($invoiceDate, 0, 4);
      if (!preg_match('/^\d{4}$/', $year)) {
         $year = date('Y');
      }
      $safeNo = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $invoiceNo) ?: ('rechnung-' . $id);
      $fileName = $safeNo . '.pdf';
      $relPath = $this->invoiceArchiveRelPath($year, $fileName);
      $absPath = $this->invoiceArchiveDir($year) . '/' . $fileName;
      $oldRelPath = trim((string)($order['invoice_pdf_path'] ?? ''));
      $created = false;

      if (!is_file($absPath)) {
         if (!$this->createSimpleInvoicePdf($order, $absPath)) {
            return null;
         }
         $created = true;
      }

      $this->db()->update($this->dd('shopOrder'), array(
         'invoice_pdf_path' => $relPath,
         'invoice_pdf_date' => date('Y-m-d H:i:s'),
         'update_date' => date('Y-m-d H:i:s'),
      ), 'id = ' . (int)$id . ' AND trash = 0', 0);
      if ($created || $oldRelPath !== $relPath) {
         $this->addOrderHistory($id, 'invoice_pdf', '', $relPath, 'Rechnungs-PDF wurde erzeugt oder aktualisiert.');
      }
      return $this->orderById($id);
   }

   public function invoicePdfAbsolutePath(array $order): string {
      $rel = trim((string)($order['invoice_pdf_path'] ?? ''));
      if ($rel === '') {
         return '';
      }
      $base = rtrim(dbx()->get_base_dir(), '/\\');
      $path = $base . '/' . ltrim($rel, '/\\');
      return is_file($path) ? $path : '';
   }
}
