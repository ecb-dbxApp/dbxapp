<?php

return array(
    'id' => 'core-4.0.3-user-identity',
    'version' => '4.0.3',
    'description' => 'Eindeutige Benutzer- und Gruppennamen sowie verbindliche Core-Gruppen.',
    'affected_dd' => array(
        'dbx|dbxUser',
        'dbx|dbxUser_groups',
    ),
    'operations' => array(
        array('type' => 'sync_dd', 'dd' => 'dbx|dbxUser'),
        array('type' => 'sync_dd', 'dd' => 'dbx|dbxUser_groups'),
        array('type' => 'seed_core'),
    ),
);
