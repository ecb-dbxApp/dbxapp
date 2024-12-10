<?php
namespace dbx\dbxAdmin;

class dbxConfig_dbx {
    private $config;
    private $message;

    public function run() {
        $this->config = dbx_get_cfg('dbx'); // Initialisierung in der run() Funktion
        $output = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->updateConfig($_POST['config']);
            $this->saveConfig();
        }

        if ($this->message) {
            $output .= '<div class="alert alert-info">' . $this->message . '</div>';
        }

        $output .= $this->renderTabs();
        return $output;
    }

    private function renderTabs() {
        ob_start();
        ?>
        <ul class="nav nav-tabs" id="configTabs">
            <li class="nav-item">
                <a class="nav-link active" id="config-tab" data-toggle="tab" href="#config" role="tab">Config</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="database-tab" data-toggle="tab" href="#database" role="tab">Datenbank</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="ftp-tab" data-toggle="tab" href="#ftp" role="tab">FTP</a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="config" role="tabpanel">
                <?php echo $this->renderConfigForm(); ?>
            </div>
            <div class="tab-pane fade" id="database" role="tabpanel">
                <?php echo $this->renderDatabaseTabs(); ?>
            </div>
            <div class="tab-pane fade" id="ftp" role="tabpanel">
                <?php echo $this->renderFTPTabs(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderConfigForm() {
        ob_start();
        ?>
        <div>
            <form method="post" action="?dbx_modul=dbxAdmin&dbx_action=config&dbx_work=edit&xmodul=dbx">
                <input type="hidden" name="activeTab" value="config">
                <?php foreach ($this->config as $key => $value): ?>
                    <?php if (!is_array($value) && !in_array($key, ['db', 'ftp', 'mail'])): ?>
                        <div class="form-group">
                            <label for="<?php echo $key; ?>"><?php echo $key; ?></label>
                            <input type="text" class="form-control" id="<?php echo $key; ?>" name="config[<?php echo $key; ?>]" value="<?php echo $value; ?>">
                        </div>
                    <?php elseif (!in_array($key, ['db', 'ftp', 'mail'])): ?>
                        <?php if (array_keys($value) === range(0, count($value) - 1)): ?>
                            <div class="form-group">
                                <label for="<?php echo $key; ?>"><?php echo $key; ?></label>
                                <input type="text" class="form-control" id="<?php echo $key; ?>" name="config[<?php echo $key; ?>]" value="<?php echo implode(',', $value); ?>">
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label for="<?php echo $key; ?>"><?php echo $key; ?></label>
                                <input type="text" class="form-control" id="<?php echo $key; ?>" name="config[<?php echo $key; ?>]" value="<?php echo http_build_query($value); ?>">
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary">Speichern</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderDatabaseTabs() {
        ob_start();
        ?>
        <div>
            <ul class="nav nav-tabs" id="databaseSubTabs">
                <?php foreach ($this->config['db'] as $server => $values): ?>
                    <li class="nav-item">
                        <a class="nav-link" id="<?php echo $server; ?>-tab" data-toggle="tab" href="#<?php echo $server; ?>" role="tab"><?php echo $server; ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="tab-content">
                <?php foreach ($this->config['db'] as $server => $values): ?>
                    <div class="tab-pane fade" id="<?php echo $server; ?>" role="tabpanel">
                        <form method="post" action="?dbx_modul=dbxAdmin&dbx_action=config&dbx_work=edit&xmodul=dbx">
                            <input type="hidden" name="activeTab" value="database">
                            <input type="hidden" name="activeSubTab" value="<?php echo $server; ?>">
                            <?php foreach ($values as $key => $value): ?>
                                <div class="form-group">
                                    <label for="<?php echo $server . '_' . $key; ?>"><?php echo $key; ?></label>
                                    <input type="text" class="form-control" id="<?php echo $server . '_' . $key; ?>" name="config[db][<?php echo $server; ?>][<?php echo $key; ?>]" value="<?php echo $value; ?>">
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderFTPTabs() {
        ob_start();
        ?>
        <div>
            <ul class="nav nav-tabs" id="ftpSubTabs">
                <?php foreach ($this->config['ftp'] as $server => $values): ?>
                    <li class="nav-item">
                        <a class="nav-link" id="<?php echo $server; ?>-ftp-tab" data-toggle="tab" href="#<?php echo $server; ?>" role="tab"><?php echo $server; ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="tab-content">
                <?php foreach ($this->config['ftp'] as $server => $values): ?>
                    <div class="tab-pane fade" id="<?php echo $server; ?>" role="tabpanel">
                        <form method="post" action="?dbx_modul=dbxAdmin&dbx_action=config&dbx_work=edit&xmodul=dbx">
                            <input type="hidden" name="activeTab" value="ftp">
                            <input type="hidden" name="activeSubTab" value="<?php echo $server; ?>">
                            <?php foreach ($values as $field => $value): ?>
                                <div class="form-group">
                                    <label for="<?php echo 'ftp_' . $server . '_' . $field; ?>"><?php echo $field; ?></label>
                                    <input type="text" class="form-control" id="<?php echo 'ftp_' . $server . '_' . $field; ?>" name="config[ftp][<?php echo $server; ?>][<?php echo $field; ?>]" value="<?php echo $value; ?>">
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function updateConfig($newConfig) {
        foreach ($newConfig as $key => $value) {
            if (is_array($value)) {
                $this->config[$key] = $value;
            } elseif (is_string($value) && strpos($value, '&') !== false) {
                parse_str($value, $this->config[$key]);
            } else {
                $this->config[$key] = $value;
            }
        }
    }

    private function saveConfig() {
        // Arrays korrekt speichern
        foreach ($this->config as $key => $value) {
            if (is_array($value)) {
                if (isset($value['type'])) {
                    // Wenn es sich um eine Datenbank oder FTP-Konfiguration handelt
                    $this->config[$key] = http_build_query($value);
                } else {
                    // Falls das Array nicht für DB oder FTP ist
                    $this->config[$key] = $value;
                }
            }
        }

        $ok = dbx_set_cfg('dbx', $this->config);

        if ($ok) {
            $this->message = 'Die Konfiguration wurde erfolgreich gespeichert.';
        } else {
            $this->message = 'Fehler beim Speichern der Konfiguration.';
        }
    }
}
