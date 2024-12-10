<?php
$config=dbx_get_cfg($record['modul']);
$activ='#N#';
if ($config['active'] == 1) $activ='#Y#';

dbx_set_DataVar('config.active',$activ);
dbx_set_DataVar('config.version',$config['version']);
dbx_set_DataVar('config.access1',$config['access1']);
dbx_set_DataVar('config.access2',$config['access2']);
