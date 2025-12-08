<?php
namespace PeOnline;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use PeOnline\Exceptions\ApiException;
use PeOnline\Exceptions\ValidationException;

/**
 * PE-online API Client for Attendance Management
 *
 * Supports the classic
 * PE-online ASMX webservice (XML/SOAP) via submitAttendanceXml.
 */
class PeOnlineClient
{
    private HttpClient $httpClient;
    private string $baseUrl; // base URL (for REST) or endpoint (for SOAP)
    private int $userId;
    private int $userKey;
    private int $orgID;

    public function __construct(string $baseUrl, string $userId, string $userKey, int $orgID)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->orgID = $orgID;
        $this->userId = $userId;
        $this->userKey = $userKey;


        $this->httpClient = new HttpClient([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }


    /**
     * Submit attendance using the ASMX XML webservice described
     * in `documentation/PE-online-webservice.txt`.
     *
     * Parameters:
     *  - $request: AttendanceRequest describing the attendance
     *
     * Returns an associative array with parsed summary (accepted/rejected rows and errors).
     *
     * Example endpoint (live): https://www.pe-online.org/pe-services/pe-attendanceelearning/WriteAttendance.asmx/ProcessXML
     */
    public function submitAttendanceXml( AttendanceRequest $request ): array 
    {

        // Build the Entry XML (single Attendance). Use DOMDocument to ensure valid XML.
        $entryXml = $this->buildEntryXml($request, $this->userId, $this->userKey, $this->orgID);

        try {
            echo $entryXml;
            $response = $this->httpClient->post($this->baseUrl, [
                'form_params' => [
                    'sXML' => $entryXml,
                ],
            ]);

            $body = $response->getBody()->getContents();
            // Parse the returned XML summary (ASMX returns SOAP envelope for SOAP mode)
            $parsed = $this->parseXmlResponse($body);
            return $parsed;
        } catch (GuzzleException $e) {
            $this->handleApiException($e);
        }
    }

    /**
     * Build the <Entry> XML according to the documentation.
     */
    private function buildEntryXml(AttendanceRequest $request, string $userId, string $userKey, int $orgID): string
    {
        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->formatOutput = false;

        $entry = $doc->createElement('Entry');
        $doc->appendChild($entry);

        $settings = $doc->createElement('Settings');
        $entry->appendChild($settings);

        $settings->appendChild($doc->createElement('userID', $userId));
        $settings->appendChild($doc->createElement('userRole', 'EDU'));
        $settings->appendChild($doc->createElement('userKey', $userKey));
        $settings->appendChild($doc->createElement('orgID', (string)$orgID));
        $settings->appendChild($doc->createElement('settingOutput', '1'));
        $settings->appendChild($doc->createElement('emailOutput', ''));
        $settings->appendChild($doc->createElement('languageID', '1'));
        $settings->appendChild($doc->createElement('defaultLanguageID', '1'));

        // Attendance element
        $attendance = $doc->createElement('Attendance');
        $entry->appendChild($attendance);

        // Course: preference to PECourseID if available, otherwise externalCourseID
        if ($request->getPeCourseId() !== null) {
            $attendance->appendChild($doc->createElement('PECourseID', (string)$request->getPeCourseId()));
        } elseif ($request->getExternalCourseId() !== null) {
            $attendance->appendChild($doc->createElement('externalCourseID', $request->getExternalCourseId()));
        }

        if ($request->getPEEditionID() !== null) {
            $attendance->appendChild($doc->createElement('PEEditionID', (string)$request->getPEEditionID()));
        }

        // Person: externalPersonID preferred
        if ($request->getExternalPersonId() !== null) {
            $attendance->appendChild($doc->createElement('externalPersonID', $request->getExternalPersonId()));
        } elseif ($request->getPePersonId() !== null) {
            // The XML docs show externalPersonID in examples, but include PEPersonID if you have internal ID.
            $attendance->appendChild($doc->createElement('PEPersonID', (string)$request->getPePersonId()));
        }

        // Module: PEModuleID or externalmoduleID
        if ($request->getPeModuleId() !== null) {
            $attendance->appendChild($doc->createElement('PEModuleID', (string)$request->getPeModuleId()));
        } elseif ($request->getExternalModuleId() !== null) {
            $attendance->appendChild($doc->createElement('externalmoduleID', $request->getExternalModuleId()));
        }

        // endDate: the docs show ISO8601 timestamp; if only date provided, append time component.
        if ( $request->getEndDate() !== null ) {
            $endDate = $request->getEndDate();
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                // date-only, add 9 AM with timezone offset "+00:00"
                $endDate = $endDate . 'T09:00:00+00:00';
            }
            $attendance->appendChild($doc->createElement('endDate', $endDate));
        }

        return $doc->saveXML();
    }

    /**
     * Parse response body (SOAP or raw XML) into an associative array.
     */
    private function parseXmlResponse(string $body): array
    {
        // If SOAP envelope, try to extract inner XML content or summary.
        // Try to load as XML directly.
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            // Not XML - return raw body
            return ['raw' => $body];
        }

        // If SOAP envelope, get Body content
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['soap']) || isset($namespaces['SOAP-ENV'])) {
            // navigate to Body -> ProcessXMLResponse -> ProcessXMLResult (common ASMX pattern)
            $bodyNode = null;
            foreach ($xml->children() as $child) {
                if (stripos($child->getName(), 'Body') !== false) {
                    $bodyNode = $child;
                    break;
                }
            }
            if ($bodyNode) {
                // try to find result text
                $result = null;
                foreach ($bodyNode->children() as $op) {
                    // find any child that contains the result XML string
                    foreach ($op->children() as $inner) {
                        $result = (string)$inner;
                        if ($result) break 2;
                    }
                }
                if ($result) {
                    // result contains the Summary XML (or inner message) - parse it
                    $innerXml = simplexml_load_string($result);
                    if ($innerXml !== false) {
                        return $this->summaryXmlToArray($innerXml);
                    }
                    return ['result' => $result];
                }
            }
        }

        // If not SOAP, assume the body is a Summary XML directly
        return $this->summaryXmlToArray($xml);
    }

    private function summaryXmlToArray(\SimpleXMLElement $xml): array
    {
        $ns = (array)$xml->Results;
        $results = [];
        if (isset($xml->Results->rejected_rows)) {
            $results['rejected_rows'] = (int)$xml->Results->rejected_rows;
            $results['accepted_rows'] = (int)$xml->Results->accepted_rows;
            $results['total_rows'] = (int)$xml->Results->total_rows;
        }

        // Collect errors
        $errors = [];
        if (isset($xml->Error)) {
            foreach ($xml->Error as $err) {
                $errors[] = [
                    'errorNR' => (string)$err->errorNR,
                    'errorMsg' => (string)$err->errorMsg,
                ];
            }
        }

        // Collect accepted entries
        $accepted = [];
        if (isset($xml->Accepted)) {
            foreach ($xml->Accepted as $acc) {
                $accepted[] = [
                    'person' => (string)($acc->person ?? ''),
                    'course' => (string)($acc->course ?? ''),
                    'meeting' => (string)($acc->meeting ?? ''),
                    'date' => (string)($acc->date ?? ''),
                ];
            }
        }

        return [
            'results' => $results,
            'errors' => $errors,
            'accepted' => $accepted,
            'raw_xml' => $xml->asXML(),
        ];
    }

    /**
     * Validate attendance request before submission (JSON path)
     */
    private function validateAttendanceRequest(AttendanceRequest $request): void
    {
        if (!$request->getOrgId()) {
            throw new ValidationException('orgId is required', 1001);
        }

        $endDate = \DateTime::createFromFormat('Y-m-d', $request->getEndDate());
        if ($endDate && $endDate > new \DateTime()) {
            throw new ValidationException('endDate cannot be in the future', 1002);
        }

        if (!$request->getPeCourseId() && !$request->getExternalCourseId()) {
            throw new ValidationException(
                'Either peCourseId or externalCourseId must be provided',
                1003
            );
        }

        if (!$request->getPerPersonId() && !$request->getExternalPersonId()) {
            throw new ValidationException(
                'Either pePersonId or externalPersonId must be provided',
                1004
            );
        }
    }

    /**
     * Handle API exceptions and throw appropriate exception
     */
    private function handleApiException(GuzzleException $e): void
    {
        if ($e->getResponse()) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = $e->getResponse()->getBody()->getContents();

            // If response body looks like XML error, include it in exception
            throw new ApiException('API error: ' . (is_string($body) ? $body : json_encode($body)), $statusCode);
        }

        throw new ApiException('Failed to communicate with PE-online API: ' . $e->getMessage(), 0, $e);
    }

}