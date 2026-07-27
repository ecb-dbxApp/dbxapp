<?php
namespace dbx\myInvoices;

/**
 * Kleiner Router des ausführbaren Rechnungs-Referenzmoduls.
 *
 * Direkte Requests und serverseitige `[modul=myInvoices]`-Inclusions
 * verwenden denselben Einstieg. Fachlogik bleibt im Service.
 */
class myInvoices
{
    /**
     * Verteilt ausschließlich validierte Modulrouten.
     *
     * @return string Gerenderter Modulinhalt.
     */
    public function run(): string
    {
        $run = (string)dbx()->get_modul_var(
            'dbx_run1',
            'report',
            'parameter|max=32'
        );
        $service = dbx()->get_include_obj(
            'myInvoicesService',
            'myInvoices'
        );

        switch ($run) {
            case 'positions':
                return $service->positions();

            case 'form':
            case 'edit':
                return $service->form();

            case 'delete':
                return $service->delete();

            case 'install':
                return $service->installation();

            case 'report':
            case 'list':
            default:
                return $service->report();
        }
    }
}

?>
