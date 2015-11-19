<?php
require_once __DIR__  . '/vendor/autoload.php';

use Goutte\Client;

$BASE_URL = 'https://vertretungsplan.leiningergymnasium.de';
$INDEX_URL = $BASE_URL . '/index';
$TOMORROW_URL = $BASE_URL . '/morgen';

date_default_timezone_set('Europe/Berlin');

$URL = $INDEX_URL;
if (isset($_GET['type'])) {
  if ($_GET['type'] == 'tomorrow') {
    $URL = $TOMORROW_URL;
  }
}

$client = new Client();
$crawler = $client->request('POST', $URL, array(
  'username' => '',
  'password' => ''
));

function loadMetadata($crawler) {
  $nameElement = $crawler->filter('#right')->first();
  if ($nameElement == NULL) {
    return NULL;
  }
  $name = $nameElement->text();
  
  $dateElement = $crawler->filter('#date')->first();
  if ($dateElement == NULL) {
    return NULL;
  }
  
  $dateString = $dateElement->text();
  $components = explode(' ', $dateString);
  $dateComponents = explode('.', end($components));
  $date = $dateComponents[2] . '-' . $dateComponents[1] . '-' . $dateComponents[0];
  
  $lastUpdateElement = $crawler->filter('#stand')->first();
  if ($lastUpdateElement == NULL) {
    return NULL;
  }
  
  $lastUpdateString = $lastUpdateElement->text();
  $components = explode(' ', $lastUpdateString);
  $dateComponents = explode('.', $components[2]);
  $timeComponents = explode(':', $components[3]);
  
  $day = intval($dateComponents[0]);
  $month = intval($dateComponents[1]);
  $year = intval($dateComponents[2]);
  $hour = intval($timeComponents[0]);
  $minute = intval($timeComponents[1]);
  $second = intval($timeComponents[2]);
  
  $lastUpdate = new DateTime();
  $lastUpdate->setDate($year, $month, $day);
  $lastUpdate->setTime($hour, $minute, $second);
    
  return array(
    'name' => $name,
    'date' => $date,
    'lastUpdate' => $lastUpdate->format(DateTime::ATOM)
  );
}

function loadEntry($element) {
  $columns = $element->filter('td')->each(function($node) {
    return preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', str_replace('---', '', $node->text()));
  });
  if (count($columns) < 9) {
    return NULL;
  }
  
  return array(
    'classes' => $columns[0],
    'hours' => $columns[1],
    'subject' => $columns[2],
    'substituteTeacher' => $columns[3],
    'substituteSubject' => $columns[4],
    'room' => $columns[5],
    'type' => $columns[6],
    'text' => $columns[7],
    'replaces' => $columns[8]
  );
}

function loadEntries($crawler) {
  return $crawler->filter('#vertretungsplan tr')->each(function($node) {
    return loadEntry($node);
  });
}

function loadPlan($crawler) {
  $data = loadMetadata($crawler);
  $data['entries'] = loadEntries($crawler);
  return $data;
}

header('Content-Type: application/json');
echo json_encode(loadPlan($crawler));