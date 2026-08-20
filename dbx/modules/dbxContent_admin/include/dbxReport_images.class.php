<?php

declare(strict_types=1);

namespace dbx\dbxContent_admin;

dbx()->get_system_obj('dbxReport', 'use');

class dbxReport_images extends \dbxReport
{
    public function run_body($content)
    {
        return $this->forward_run_body($content);
    }

    public function get_img_data($rpos = 0, $rrows = 1): array
    {
        $rdata = array();
        $rcount = 0;
        $extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        $path = dbx()->get_file_dir() . '/dbxContent/img/';
        $url = dbx()->get_base_url() . 'files/dbxContent/img/';
        if (!is_dir($path)) @mkdir($path, 0777, true);
        if (!is_dir($path) || !is_readable($path)) {
            $this->_rcount = 0;
            return $rdata;
        }
        $handle = opendir($path);
        if (!$handle) {
            $this->_rcount = 0;
            return $rdata;
        }
        while (($file = readdir($handle)) !== false) {
            $extension = strtolower((string)(pathinfo($path . $file)['extension'] ?? ''));
            if (!in_array($extension, $extensions, true)) continue;
            $rcount++;
            if ($rcount < $rpos || $rcount > ($rpos + $rrows)) continue;
            $rdata[] = array(
                'id' => $file,
                'name' => $file,
                'src' => $url . $file,
                'alt' => 'Bild (' . $file . ')',
                'tooltip' => 'tt',
                'high' => '600px',
                'width' => '1600px',
                'class' => 'dbxImg',
                'dbx_selectval' => $file,
            );
        }
        closedir($handle);
        $this->_rcount = $rcount;
        return $rdata;
    }
}
