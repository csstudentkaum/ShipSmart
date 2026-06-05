<?php
require(__DIR__ . '/trackingmore/trackingmore-sdk-php/src/TrackingMoreException.php');
require(__DIR__ . '/trackingmore/trackingmore-sdk-php/src/ErrorMessages.php');
require(__DIR__ . '/trackingmore/trackingmore-sdk-php/src/Request.php');
require(__DIR__ . '/trackingmore/trackingmore-sdk-php/src/Interfaces/CouriersInterface.php');
require(__DIR__ . '/trackingmore/trackingmore-sdk-php/src/Couriers.php');
require(__DIR__ . '/trackingmore/trackingmore-sdk-php/src/Interfaces/TrackingsInterface.php');
require(__DIR__ . '/trackingmore/trackingmore-sdk-php/src/Trackings.php');
require(__DIR__ . '/trackingmore/trackingmore-sdk-php/src/Interfaces/AirWaybillsInterface.php');
require(__DIR__ . '/trackingmore/trackingmore-sdk-php/src/AirWaybills.php');

$key = 'm8ki266j-uquq-xsv1-88m2-3kvdnjuxgfh9'; 

$couriers = new TrackingMore\Couriers($key);
$response = null;

try {
    $response = $couriers->getAllCouriers();
} catch (TrackingMore\TrackingMoreException $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

print_r($response);
