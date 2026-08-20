<?php

/**
 * Stellt Datumsberechnungen unter Berücksichtigung arbeitsfreier Tage bereit.
 */
class dbxDateTime {

    public $holidays=array();

    function init() {
        $holidays = [
            '2024-01-01', // Neujahr
            '2024-12-25', // Weihnachten
            '2024-12-26', // Zweiter Weihnachtsfeiertag
            // Weitere Feiertage hinzufügen
        ];
        $this->holidays=$holidays;

    }


    function get_next_working_day(DateTime $date): DateTime {
        // Feiertage in ein Array von DateTime-Objekten umwandeln
        $holiday      = $this->holidays;
        $holiday_dates = array_map(function($holiday) {
            return new DateTime($holiday);
        }, $holidays);
    
        do {
            // Ein Tag hinzufügen
            $date->modify('+1 day');
    
            // Prüfen, ob der Tag ein Wochenende oder ein Feiertag ist
        } while (in_array($date->format('N'), ['6', '7']) || in_array($date->format('Y-m-d'), array_map(function($holiday) {
            return $holiday->format('Y-m-d');
        }, $holiday_dates)));
    
        return $date;
    }
    

    // Beispiel-Feiertage    
    //$date = new DateTime();
    //$nextWorkingDay = getNextWorkingDay($date, $holidays);
    //echo "Der nächste Arbeitstag ist: " . $nextWorkingDay->format('Y-m-d');
   

}
