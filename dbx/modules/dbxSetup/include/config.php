<?php
$config['dbxInstall-1']=(string) (getenv('DBX_INSTALL_ID') ?: '');
$config['host']=(string) (getenv('DBX_INSTALL_DB_HOST') ?: '');
$config['name']=(string) (getenv('DBX_INSTALL_DB_NAME') ?: '');
$config['user']=(string) (getenv('DBX_INSTALL_DB_USER') ?: '');
$config['password']=(string) (getenv('DBX_INSTALL_DB_PASSWORD') ?: '');
$config['port']='';
$config['design']='dbxapp';
$config['stepper']='3';
$config['secure']=(string) (getenv('DBX_INSTALL_SECURE') ?: '');
$config['ok']='1';
