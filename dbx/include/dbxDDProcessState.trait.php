<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxDDProcessStateTrait
{
/* =====================================================
     * STEP STATE
     * ===================================================== */

    /**
     * Erzeugt einen technischen Prozessschlüssel.
     *
     * @param string $type Prozesstyp
     * @param array  $parts Schlüsselteile
     *
     * @return string Prozessschlüssel
     */
    protected function proc_key(string $type, array $parts): string
    {
        return 'dbxdd_' . $type . '_' . md5(json_encode($parts));
    }

/**
     * Liest einen technischen Prozessstatus.
     *
     * @param string $key Prozessschlüssel
     *
     * @return array Prozessstatus
     */
    protected function get_proc_state(string $key): array
    {
        $state = dbx()->get_remember_var($key, [], $this->_remember_modul);
        return is_array($state) ? $state : [];
    }

/**
     * Speichert einen technischen Prozessstatus.
     *
     * @param string $key Prozessschlüssel
     * @param array  $state Prozessstatus
     *
     * @return void
     */
    protected function set_proc_state(string $key, array $state): void
    {
        dbx()->set_remember_var($key, $state, $this->_remember_modul);
    }

/**
     * Löscht einen technischen Prozessstatus.
     *
     * @param string $key Prozessschlüssel
     *
     * @return void
     */
    protected function clear_proc_state(string $key): void
    {
        dbx()->set_remember_var($key, [], $this->_remember_modul);
    }

/**
     * Initialisiert einen technischen Prozessstatus.
     *
     * @param string $type Prozesstyp
     * @param string $key Prozessschlüssel
     * @param array  $state Startstatus
     *
     * @return array Initialisierter Prozessstatus
     */
    protected function init_proc_state(string $type, string $key, array $state): array
    {
        $state['proc_type']      = $type;
        $state['proc_key']       = $key;
        $state['status']         = $state['status']         ?? 'running';
        $state['message']        = $state['message']        ?? '';
        $state['percent']        = $state['percent']        ?? 0;
        $state['step_percent']   = $state['step_percent']   ?? 0;
        $state['chunk_size']     = $state['chunk_size']     ?? $this->_chunk_size;
        $state['step_maxsec']    = $state['step_maxsec']    ?? $this->_max_step_runtime;
        $state['started_at']     = $state['started_at']     ?? date('Y-m-d H:i:s');
        $state['updated_at']     = date('Y-m-d H:i:s');
        $this->set_proc_state($key, $state);
        return $state;
    }

/**
     * Prüft, ob ein Prozess aktuell keine weitere Arbeit ausfuehren darf.
     *
     * @param array $state Prozessstatus
     *
     * @return bool True wenn der Prozess warten oder beendet ist
     */
    protected function proc_is_waiting(array $state): bool
    {
        return in_array(($state['status'] ?? ''), ['finished', 'error', 'paused', 'canceled'], true);
    }

/**
     * Steuert einen vorhandenen Prozessstatus.
     *
     * @param string $key Prozessschluessel
     * @param string $cmd pause|resume|continue|cancel|restart
     *
     * @return array Prozessstatus
     */
    protected function control_proc_state(string $key, string $cmd): array
    {
        $cmd = strtolower(trim($cmd));

        if ($cmd === 'restart') {
            $this->clear_proc_state($key);
            return [
                'proc_key' => $key,
                'status'   => 'reset',
                'message'  => 'process restarted',
                'percent'  => 0,
                'step_percent' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        $state = $this->get_proc_state($key);
        if (!$state) {
            $state = [
                'proc_key' => $key,
                'status'   => 'new',
                'message'  => 'no active state',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        $status = $state['status'] ?? 'new';

        if ($cmd === 'pause') {
            if (!in_array($status, ['finished', 'error', 'canceled'], true)) {
                $state['status'] = 'paused';
                $state['paused_at'] = date('Y-m-d H:i:s');
                $state['message'] = 'process paused';
            }
        } elseif ($cmd === 'resume' || $cmd === 'continue') {
            if (in_array($status, ['paused', 'canceled', 'new'], true)) {
                $state['status'] = 'running';
                $state['resumed_at'] = date('Y-m-d H:i:s');
                $state['message'] = ($cmd === 'continue') ? 'process continued' : 'process resumed';
            }
        } elseif ($cmd === 'cancel') {
            if (!in_array($status, ['finished', 'error'], true)) {
                $state['status'] = 'canceled';
                $state['canceled_at'] = date('Y-m-d H:i:s');
                $state['message'] = 'process canceled';
            }
        }

        $state = $this->proc_response($state);
        $this->set_proc_state($key, $state);
        return $state;
    }

/**
     * Aktualisiert einen Prozessstatus für Rückgabe.
     *
     * @param array $state Prozessstatus
     *
     * @return array Rückgabe-Prozessstatus
     */
    protected function proc_response(array $state): array
    {
        $state['updated_at'] = date('Y-m-d H:i:s');
        return $state;
    }

/**
     * Erzeugt einen Fehlerstatus für einen Prozess.
     *
     * @param array  $state Prozessstatus
     * @param string $message Fehlermeldung
     *
     * @return array Fehlerstatus
     */
    protected function proc_error(array $state, string $message): array
    {
        $state['status']  = 'error';
        $state['message'] = $message;
        $state['percent'] = $state['percent'] ?? 0;
        $state['step_percent'] = $state['step_percent'] ?? 0;
        return $this->proc_response($state);
    }

/**
     * Erzeugt einen Fertig-Status für einen Prozess.
     *
     * @param array  $state Prozessstatus
     * @param string $message Meldung
     *
     * @return array Fertig-Status
     */
    protected function proc_finish(array $state, string $message = 'finished'): array
    {
        $state['status']      = 'finished';
        $state['message']     = $message;
        $state['percent']     = 100;
        $state['step_percent'] = 100;
        $state['finished_at'] = date('Y-m-d H:i:s');
        return $this->proc_response($state);
    }

/**
     * Liefert einen Step-Startzeitpunkt.
     *
     * @return float Startzeitpunkt
     */
    protected function step_start_time(): float
    {
        return microtime(true);
    }

/**
     * Prüft, ob noch Laufzeit für den aktuellen Prozessschritt übrig ist.
     *
     * @param float $started_at Startzeitpunkt
     * @param float $max_seconds Maximale Laufzeit
     *
     * @return bool True wenn noch Zeit übrig
     */
    protected function step_time_left(float $started_at, float $max_seconds): bool
    {
        return (microtime(true) - $started_at) < $max_seconds;
    }
}
