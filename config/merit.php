<?php

/*
 | Merit points awarded per event level.
 | Order of values: main committee / committee / participant.
 */
return [
    'scores' => [
        'international' => ['main_committee' => 100, 'committee' => 70, 'participant' => 50],
        'national' => ['main_committee' => 80, 'committee' => 50, 'participant' => 40],
        'state' => ['main_committee' => 60, 'committee' => 40, 'participant' => 30],
        'district' => ['main_committee' => 40, 'committee' => 30, 'participant' => 15],
        'umpsa' => ['main_committee' => 30, 'committee' => 20, 'participant' => 5],
    ],
];
