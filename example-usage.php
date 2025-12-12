<?php
require_once __DIR__ . '/vendor/autoload.php';

use PeOnline\PeOnlineClient;
use PeOnline\AttendanceRequest;
use PeOnline\PeOnlineDataRequest;
use PeOnline\Exceptions\ApiException;
use PeOnline\Exceptions\ValidationException;

// ============================================================================
// Example 1: Submit Attendance using PeOnlineClient
// ============================================================================

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
    orgID: $orgID,
    PECourseID: '123456',
    PEEditionID:  '7654321',
    externalPersonId: 'BIG-123456'
);

try {
    $result = $client->submitAttendanceXml($request);

    echo "Submission result:\n";
    print_r($result);
} catch (ValidationException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
} catch (ApiException $e) {
    echo "API error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
}

// ============================================================================
// Example 2: Retrieve Data using PeOnlineDataRequest
// ============================================================================

// Endpoint for the data retrieval ASMX webservice
$dataEndpoint = 'https://www.pe-online.org/pe-services/PEGlobalService/peservice.asmx/RetrieveDataBySoapMessage';

// Initialize the data request client with credentials
$dataClient = new PeOnlineDataRequest(
    $dataEndpoint,
    'E',                    // userType: 'E' for educator
    $userId,                // userID
    $userKey,               // userKey
    xmlId: 00,              // XML Schema ID (required)
    nested: true,           // Include nested data (optional, defaults to true)
    schema: true            // Include schema information (optional, defaults to true)
);

try {
    // Retrieve data with custom parameters
    $data = $dataClient->getData([
        'param_tblcourseid' => '653570'
    ]);

    echo "Retrieved data:\n";
    print_r($data);

    // Access specific parts of the response
    if (isset($data['data']['coursedetail'])) {
        echo "\nCourse details:\n";
        foreach ($data['data']['coursedetail'] as $course) {
            echo "  Course ID: " . $course['courseID'] . "\n";
            echo "  Course Name: " . $course['courseName'] . "\n";
        }
    }

    // You can also override options per request
    $dataWithOptions = $dataClient->getData(
        ['param_tblcourseid' => '653570'],
        ['xmlId' => 100, 'nested' => false]
    );

} catch (ApiException $e) {
    echo "API error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
}