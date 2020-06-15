<?php

if(!isset($_GET['postcode'])) {
    http_response_code(400);
    die('Missing "postcode" parameter');
}

if(!isset($_GET['number'])) {
    http_response_code(400);
    die('Missing "number" parameter');
}

const MONTH_NUMBERS = [
    'januari' => 1,
    'februari' => 2,
    'maart' => 3,
    'april' => 4,
    'mei' => 5,
    'juni' => 6,
    'juli' => 7,
    'augustus' => 8,
    'september' => 9,
    'oktober' => 10,
    'november' => 11,
    'december' => 12,
];

$post_code = $_GET['postcode'];
$number = $_GET['number'];
$api_url = 'https://www.mijnafvalwijzer.nl/nl/' . $post_code . '/' . $number . '/';

$html = file_get_contents($api_url);

$document = new \DOMDocument();

if(!$document->loadHTML($html)) {
    http_response_code(500);
    die('Malformed HTML');
}

$tables = $document->getElementsByTagName('table');

$events = [];

foreach($tables as $table) {
    foreach($table->childNodes as $tr) {
        if($tr instanceof DOMElement === false || $tr->tagName !== 'tr') { continue; }

        foreach($tr->childNodes as $td) {
            if($td instanceof DOMElement === false || $td->tagName !== 'td') { continue; }

            foreach($td->childNodes as $a) {
                if($a instanceof DOMElement === false || $a->tagName !== 'a') { continue; }
                
                foreach($a->childNodes as $p) {
                    if($p instanceof DOMElement === false || $p->tagName !== 'p') { continue; }

                    $event = [];

                    foreach($p->childNodes as $node) {
                        $text = trim($node->textContent);

                        if(empty($text)) { continue; }

                        $date_matches = [];

                        preg_match('/[a-z]+ ([0-9]+) ([a-z]+)/', $text, $date_matches);

                        if(isset($date_matches[1]) && isset($date_matches[2])) {
                            $day_of_month = $date_matches[1];
                            $name_of_month = $date_matches[2];

                            if(!is_numeric($day_of_month) || !is_string($name_of_month)) { continue; }
                            if(!isset(MONTH_NUMBERS[$name_of_month])) { continue; }
                            
                            $date = new DateTime();
                            
                            $date->setDate(
                                (int) $date->format('Y'),
                                MONTH_NUMBERS[$name_of_month],
                                (int) $day_of_month
                            );
                            
                            $event['time'] = $date;
                        
                        } else {
                            $event['name'] = $text;
                        }
                    }

                    if(!isset($event['time']) || !isset($event['name'])) { continue; }

                    $events[] = $event;
                }
            }
        }
    }    
}

header('Content-type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename=afvalwijzer.ics');

echo "BEGIN:VCALENDAR\n";
echo "VERSION:2.0\n";
echo "PRODID:-//hacksw/handcal//NONSGML v1.0//EN\n";

foreach($events as $event) {
    echo "BEGIN:VEVENT\n";
    echo "UID:" . md5(uniqid(mt_rand(), true)) . "@" . $_SERVER["SERVER_NAME"] . "\n";
    echo "DTSTART;VALUE=DATE:" . $event['time']->format('Ymd') . "\n";
    echo "SUMMARY:" . $event['name'] . "\n";
    echo "END:VEVENT\n";
}

echo "END:VCALENDAR";

?>
