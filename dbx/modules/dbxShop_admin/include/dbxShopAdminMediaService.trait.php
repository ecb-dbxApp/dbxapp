<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

/**
 * Shop-Medienupload und Medienansicht ueber den zentralen CMS-Medienvertrag.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxShopAdminMediaServiceTrait {


   private function shopMediaDir(bool $ensure = false): string {
      $dir = dirname(__DIR__, 4) . '/files/shop/img';
      if ($ensure && !is_dir($dir)) {
         mkdir($dir, 0775, true);
      }
      return $dir;
   }



   private function safeFileName(string $name): string {
      $name = strtolower(trim($name));
      $name = preg_replace('~[^a-z0-9._-]+~', '-', $name);
      $name = trim((string)$name, '-.');
      return $name !== '' ? $name : 'shop-image';
   }



   private function handleMediaUpload(): string {
      if (!$this->posted('upload_media')) {
         return '';
      }
      if (empty($_FILES['shop_image']['tmp_name']) || !is_uploaded_file($_FILES['shop_image']['tmp_name'])) {
         return '<div class="alert alert-warning m-3">Keine Datei ausgewaehlt.</div>';
      }
      $original = (string)($_FILES['shop_image']['name'] ?? 'shop-image');
      $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
      $allowed = array('jpg','jpeg','png','gif','webp','svg');
      if (!in_array($ext, $allowed, true)) {
         return '<div class="alert alert-danger m-3">Dieser Bildtyp ist fuer Shop-Medien nicht erlaubt.</div>';
      }

      $base = $this->safeFileName(pathinfo($original, PATHINFO_FILENAME));
      $name = $base . '.' . $ext;
      $target = $this->shopMediaDir(true) . '/' . $name;
      $i = 2;
      while (is_file($target)) {
         $name = $base . '-' . $i . '.' . $ext;
         $target = $this->shopMediaDir() . '/' . $name;
         $i++;
      }
      if (!move_uploaded_file($_FILES['shop_image']['tmp_name'], $target)) {
         return '<div class="alert alert-danger m-3">Upload konnte nicht gespeichert werden.</div>';
      }

      $rel = 'files/shop/img/' . $name;
      $productId = (int)($_POST['product_id'] ?? 0);
      $groupId = (int)($_POST['group_id'] ?? 0);
      if ($productId > 0 || $groupId > 0) {
         $this->repo()->saveImage($productId, $groupId, $rel, (string)($_POST['title'] ?? $base), (string)($_POST['alt'] ?? $base), !empty($_POST['is_primary']) ? 1 : 0, (int)($_POST['sorter'] ?? 100));
      }
      return '<div class="alert alert-success m-3">Bild wurde hochgeladen: ' . $this->h($name) . '</div>';
   }



   private function media(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      $selectedProduct = (int)dbx()->get_modul_var('product_id', '0', 'int');
      $selectedGroup = (int)dbx()->get_modul_var('group_id', '0', 'int');

      $productOptions = '<option value="0">' . $this->h($texts->get_fd_message('media_no_product')) . '</option>';
      foreach ($this->repo()->products(false) as $product) {
         $sel = (int)($product['id'] ?? 0) === $selectedProduct ? ' selected' : '';
         $productOptions .= '<option value="' . (int)($product['id'] ?? 0) . '"' . $sel . '>' . $this->h($product['title'] ?? '') . '</option>';
      }
      $groupOptions = '<option value="0">' . $this->h($texts->get_fd_message('media_no_group')) . '</option>';
      foreach ($this->repo()->groups() as $group) {
         $sel = (int)($group['id'] ?? 0) === $selectedGroup ? ' selected' : '';
         $groupOptions .= '<option value="' . (int)($group['id'] ?? 0) . '"' . $sel . '>' . $this->h($group['title'] ?? '') . '</option>';
      }

      $mediaCfg = $this->shopMediaConfig();
      $attrs = $this->shopMediaAttrs($mediaCfg);

      $html = '<div class="dbx-shop-media-manager m-3"' . $attrs . '>';
      $targetForm = dbx()->get_system_obj('dbxForm');
      $targetForm->init('shop-media-target-form', 'shop-media-target-form');
      $targetForm->_action = '?dbx_modul=dbxShop_admin&dbx_run1=media';
      $targetForm->set_form_help_enabled(false);
      $targetForm->add_rep('frame_skip_form_wrap', '1');
      $targetForm->add_rep('form_body',
         '<div class="row g-2 align-items-end">'
         . '<div class="col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('media_product')) . '</label><select class="form-select form-select-sm" name="product_id" data-shop-product-select>' . $productOptions . '</select></div>'
         . '<div class="col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('media_group')) . '</label><select class="form-select form-select-sm" name="group_id" data-shop-group-select>' . $groupOptions . '</select></div>'
         . '<div class="col-md-2"><label class="form-label">' . $this->h($texts->get_fd_message('media_sort')) . '</label><input class="form-control form-control-sm" name="sorter" value="100" data-shop-sorter></div>'
         . '<div class="col-md-2 form-check pb-1"><input class="form-check-input" type="checkbox" name="is_primary" value="1" id="shop_img_primary" data-shop-primary><label class="form-check-label" for="shop_img_primary">' . $this->h($texts->get_fd_message('media_primary')) . '</label></div>'
         . '<div class="col-md-8"><div class="form-text">' . $this->h($texts->get_fd_message('media_hint')) . '</div></div>'
         . '<div class="col-md-2"><button class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-filter"></i> ' . $this->h($texts->get_fd_message('media_load_selection')) . '</button></div>'
         . '<div class="col-md-2"><button type="button" class="btn btn-outline-primary btn-sm w-100 dbx-shop-media-pick" data-shop-media-folder="img/shop" data-dbx-tooltip="' . $this->h($texts->get_fd_message('media_select_title')) . '"><i class="bi bi-images"></i><i class="bi bi-camera-video"></i><i class="bi bi-upload"></i><span>' . $this->h($texts->get_fd_message('selection')) . '</span></button></div>'
         . '</div>'
      );
      $html .= $targetForm->run();

      $assigned = '';
      foreach ($this->repo()->allImages() as $image) {
         $assigned .= '<div class="col"><div class="card h-100"><img src="' . $this->h($this->mediaItemUrl($image, true)) . '" class="card-img-top" alt="" style="height:120px;object-fit:cover;"><div class="card-body p-2"><strong>' . $this->h($image['title'] ?? '') . '</strong><br><small>' . $this->h($image['product_title'] ?: $image['group_title'] ?: $texts->get_fd_message('not_assigned')) . '</small></div></div></div>';
      }
      $html .= '<div class="m-3"><h5>' . $this->h($texts->get_fd_message('media_assigned_title')) . '</h5><div class="row row-cols-2 row-cols-md-4 row-cols-xl-6 g-2">' . $assigned . '</div></div>';

      $files = glob($this->shopMediaDir() . '/*.{jpg,jpeg,png,gif,webp,svg}', GLOB_BRACE) ?: array();
      $fileCards = '';
      foreach ($files as $file) {
         $rel = 'files/shop/img/' . basename($file);
         $fileCards .= '<div class="col"><div class="card h-100"><img src="' . $this->h($this->mediaUrl($rel)) . '" class="card-img-top" alt="" style="height:120px;object-fit:cover;"><div class="card-body p-2"><small>' . $this->h(basename($file)) . '</small></div></div></div>';
      }
      $html .= '<div class="m-3"><h5>' . $this->h($texts->get_fd_message('media_legacy_title')) . '</h5><div class="row row-cols-2 row-cols-md-4 row-cols-xl-6 g-2">' . $fileCards . '</div></div>';
      $html .= $this->shopMediaFormTemplates($mediaCfg);
      $html .= '</div>';

      $barActions = $this->helpButton($this->ensureShopMediaHelpPage(), $texts->get_fd_message('media_help'));
      return $this->frame($html, $texts->get_fd_message('media_title'), $barActions);
   }
}
