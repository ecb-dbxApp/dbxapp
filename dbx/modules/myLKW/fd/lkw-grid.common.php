<?php

function dbx_my_lkw_lkw_grid_fields(string $lng = 'de'): array {
    $lng = in_array($lng, array('de', 'en', 'es'), true) ? $lng : 'de';

    $base_fields = array(
        array('id', 'int', '2', ''),
        array('create_date', 'varchar', '2', ''),
        array('create_uid', 'varchar', '2', ''),
        array('update_date', 'varchar', '2', ''),
        array('update_uid', 'varchar', '2', ''),
        array('owner', 'varchar', '2', ''),
        array('DOMICILIO', 'varchar', '0', 'vehicles'),
        array('TRACTOR', 'varchar', '0', 'vehicles'),
        array('ITV_TRACT', 'varchar', '0', 'vehicles'),
        array('TIPO', 'varchar', '0', 'vehicles'),
        array('REMOLQUE', 'varchar', '0', 'vehicles'),
        array('ITV_REMOL', 'varchar', '0', 'vehicles'),
        array('CONDUCTOR', 'varchar', '0', 'vehicles'),
        array('TELF', 'varchar', '0', 'vehicles'),
        array('EMPRESA', 'varchar', '0', 'vehicles'),
        array('EXT', 'varchar', '0', 'vehicles'),
        array('MANT', 'varchar', '0', 'vehicles'),
        array('EVENTOS', 'varchar', '0', 'vehicles'),
        array('BUJES', 'varchar', '0', 'vehicles'),
        array('VENCIMIENTO', 'varchar', '0', 'vehicles'),
        array('ANOTACIONES', 'varchar', '0', 'vehicles'),
        array('ODOMETRO', 'varchar', '0', 'vehicles'),
    );

    foreach (range(0, 5) as $day) {
        $group = '{dat' . $day . '}';
        $base_fields[] = array('d' . $day . '_origen_region', 'varchar', '0', $group);
        $base_fields[] = array('d' . $day . '_origen_lugar', 'varchar', '0', $group);
        $base_fields[] = array('d' . $day . '_carga_region', 'varchar', '0', $group);
        $base_fields[] = array('d' . $day . '_carga_lugar', 'varchar', '0', $group);
        $base_fields[] = array('d' . $day . '_observaciones', 'varchar', '0', $group);
    }

    $labels = array(
        'de' => array(
            'id' => 'ID',
            'create_date' => 'Erstellt am',
            'create_uid' => 'Erstellt von',
            'update_date' => 'Geaendert am',
            'update_uid' => 'Geaendert von',
            'owner' => 'Besitzer',
            'DOMICILIO' => 'Depot',
            'TRACTOR' => 'Zugmaschine',
            'ITV_TRACT' => 'ITV Zugmaschine',
            'TIPO' => 'Typ',
            'REMOLQUE' => 'Auflieger',
            'ITV_REMOL' => 'ITV Auflieger',
            'CONDUCTOR' => 'Fahrer',
            'TELF' => 'Telefon',
            'EMPRESA' => 'Firma',
            'EXT' => 'Durchwahl',
            'MANT' => 'Wartung',
            'EVENTOS' => 'Ereignisse',
            'BUJES' => 'Achsen/Naben',
            'VENCIMIENTO' => 'Faelligkeit',
            'ANOTACIONES' => 'Notizen',
            'ODOMETRO' => 'Kilometerstand',
        ),
        'en' => array(
            'id' => 'ID',
            'create_date' => 'Created',
            'create_uid' => 'Created by',
            'update_date' => 'Updated',
            'update_uid' => 'Updated by',
            'owner' => 'Owner',
            'DOMICILIO' => 'Depot',
            'TRACTOR' => 'Tractor',
            'ITV_TRACT' => 'Tractor ITV',
            'TIPO' => 'Type',
            'REMOLQUE' => 'Trailer',
            'ITV_REMOL' => 'Trailer ITV',
            'CONDUCTOR' => 'Driver',
            'TELF' => 'Phone',
            'EMPRESA' => 'Company',
            'EXT' => 'Extension',
            'MANT' => 'Maintenance',
            'EVENTOS' => 'Events',
            'BUJES' => 'Hubs/Axles',
            'VENCIMIENTO' => 'Due date',
            'ANOTACIONES' => 'Notes',
            'ODOMETRO' => 'Odometer',
        ),
        'es' => array(
            'id' => 'ID',
            'create_date' => 'Creado',
            'create_uid' => 'Creado por',
            'update_date' => 'Actualizado',
            'update_uid' => 'Actualizado por',
            'owner' => 'Propietario',
            'DOMICILIO' => 'Domicilio',
            'TRACTOR' => 'Tractor',
            'ITV_TRACT' => 'ITV tractor',
            'TIPO' => 'Tipo',
            'REMOLQUE' => 'Remolque',
            'ITV_REMOL' => 'ITV remolque',
            'CONDUCTOR' => 'Conductor',
            'TELF' => 'Telefono',
            'EMPRESA' => 'Empresa',
            'EXT' => 'Extension',
            'MANT' => 'Mantenimiento',
            'EVENTOS' => 'Eventos',
            'BUJES' => 'Bujes/Ejes',
            'VENCIMIENTO' => 'Vencimiento',
            'ANOTACIONES' => 'Anotaciones',
            'ODOMETRO' => 'Odometro',
        ),
    );

    $day_labels = array(
        'de' => array(
            'origen_region' => 'Start-Region',
            'origen_lugar' => 'Start-Ort',
            'carga_region' => 'Lade-Region',
            'carga_lugar' => 'Lade-Ort',
            'observaciones' => 'Hinweise',
        ),
        'en' => array(
            'origen_region' => 'Origin region',
            'origen_lugar' => 'Origin place',
            'carga_region' => 'Loading region',
            'carga_lugar' => 'Loading place',
            'observaciones' => 'Notes',
        ),
        'es' => array(
            'origen_region' => 'Origen region',
            'origen_lugar' => 'Origen lugar',
            'carga_region' => 'Carga region',
            'carga_lugar' => 'Carga lugar',
            'observaciones' => 'Observaciones',
        ),
    );

    $tooltips = array(
        'de' => array(
            'id' => 'Automatische Datensatz-ID.',
            'create_date' => 'Systemfeld: Zeitpunkt der Anlage.',
            'create_uid' => 'Systemfeld: Benutzer der Anlage.',
            'update_date' => 'Systemfeld: Zeitpunkt der letzten Aenderung.',
            'update_uid' => 'Systemfeld: Benutzer der letzten Aenderung.',
            'owner' => 'Systemfeld: Besitzer des Datensatzes.',
            'TRACTOR' => 'Kennzeichen der Zugmaschine.',
            'CONDUCTOR' => 'Zugeordneter Fahrer.',
            'TELF' => 'Telefonnummer des Fahrers.',
            'EMPRESA' => 'Zugehoerige Firma.',
            'd_observaciones' => 'Entladen / Uhrzeit / Werkstatt.',
        ),
        'en' => array(
            'id' => 'Automatic record ID.',
            'create_date' => 'System field: creation time.',
            'create_uid' => 'System field: creating user.',
            'update_date' => 'System field: last update time.',
            'update_uid' => 'System field: last updating user.',
            'owner' => 'System field: record owner.',
            'TRACTOR' => 'Tractor license plate.',
            'CONDUCTOR' => 'Assigned driver.',
            'TELF' => 'Driver phone number.',
            'EMPRESA' => 'Related company.',
            'd_observaciones' => 'Unloading / time / workshop.',
        ),
        'es' => array(
            'id' => 'ID automatico del registro.',
            'create_date' => 'Campo de sistema: fecha de creacion.',
            'create_uid' => 'Campo de sistema: usuario creador.',
            'update_date' => 'Campo de sistema: ultima actualizacion.',
            'update_uid' => 'Campo de sistema: usuario de la ultima actualizacion.',
            'owner' => 'Campo de sistema: propietario del registro.',
            'TRACTOR' => 'Matricula del tractor.',
            'CONDUCTOR' => 'Conductor asignado.',
            'TELF' => 'Telefono del conductor.',
            'EMPRESA' => 'Empresa relacionada.',
            'd_observaciones' => 'Descarga / hora / taller.',
        ),
    );

    $vehicle_groups = array(
        'de' => 'Fahrzeuge',
        'en' => 'Vehicles',
        'es' => 'Vehiculos',
    );

    $fields = array();

    foreach ($base_fields as $def) {
        list($name, $type, $protect, $group_key) = $def;
        $day_key = '';

        if (preg_match('/^d[0-5]_(.+)$/', $name, $match)) {
            $day_key = $match[1];
        }

        $field = array();
        $field['name'] = $name;
        $field['type'] = $type;
        $field['index'] = $name === 'id' ? 'PRI' : '';
        $field['length'] = $type === 'int' ? '11' : '32';
        $field['default'] = '';
        $field['label'] = $day_key !== ''
            ? ($day_labels[$lng][$day_key] ?? $name)
            : ($labels[$lng][$name] ?? $name);
        $field['rules'] = $protect === '2' ? '' : 'parameter';
        $field['tooltip'] = $tooltips[$lng][$name] ?? '';
        if ($day_key === 'observaciones') {
            $field['tooltip'] = $tooltips[$lng]['d_observaciones'] ?? '';
        }
        $field['errormsg'] = '';
        $field['placeholder'] = '';
        $field['convert'] = '';
        $field['protect'] = $protect;
        $field['group'] = $group_key === 'vehicles' ? $vehicle_groups[$lng] : $group_key;
        $field['mask'] = '';
        $field['data'] = '';
        $field['options'] = '';
        $field['tpl'] = '';
        $field['js'] = '';
        $field['prompt'] = '';
        $fields[] = $field;
    }

    return $fields;
}
