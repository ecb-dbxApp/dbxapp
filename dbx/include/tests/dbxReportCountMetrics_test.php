<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/dbxReportChrome.trait.php';

final class dbxReportCountMetricsProbe
{
    use dbxReportChromeTrait;

    public int $_rcount = 0;
    public int $_count_all = -1;
    public array $_replaces = array();

    public function set_counts(int $filtered_count, int $total_count): void
    {
        $this->set_report_counts($filtered_count, $total_count);
    }

    public function get_count_selects(): int
    {
        return 3;
    }

    public function render(string $content): string
    {
        return $this->apply_report_count_replaces($content);
    }
}

$report = new dbxReportCountMetricsProbe();
$report->set_counts(13, 27);

$rendered = $report->render(
    'Gesamt {pagination:count_all}; '
    . 'Selektiert {pagination:count_selected}; '
    . 'Ausgewaehlt {pagination:count_checked}'
);

if ($rendered !== 'Gesamt 27; Selektiert 13; Ausgewaehlt 3') {
    fwrite(STDERR, 'FAIL Report-Zaehler vermischen Gesamt- und Filtermenge: ' . $rendered . "\n");
    exit(1);
}

echo "OK Report-Gesamtzahl bleibt unabhaengig von Suchfilter und Auswahl.\n";
