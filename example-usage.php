<?php
require_once __DIR__ . '/vendor/autoload.php';

use PeOnline\PeOnlineClient;
use PeOnline\AttendanceRequest;
use PeOnline\Exceptions\ApiException;
use PeOnline\Exceptions\ValidationException;

// Endpoint for the ASMX webservice (test or live)
$asmxEndpoint = 'https://www.pe-online.org/pe-services/pe-attendanceelearning/WriteAttendance.asmx/ProcessXML';

// These credentials are provided to you by PE-online for the EDU account
$userId = '12345';        // example userID
$userKey = '12345123451234';  // example userKey
$orgID = 1234567;          // example orgID

// Initialize the client (baseUrl used only as fallback)
$client = new PeOnlineClient(
    $asmxEndpoint,
    $userId,
    $userKey,
    $orgID
);

// Build an AttendanceRequest:
// Use either PECourseID or externalCourseId, and either externalPersonId or pePersonId.
// endDate should be ISO8601 (date or datetime). Example below uses date-only, which will be extended.
$request = new AttendanceRequest(
    endDate: '2024-09-27',
    PECourseID: '123456',
    externalPersonId: 'BIG-123456'
);


try {
    $result = $client->submitAttendanceXml( $request );

    echo "Submission result:\n";
    print_r($result);
} catch (ValidationException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
} catch (ApiException $e) {
    echo "API error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
}
