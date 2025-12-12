<?php
namespace PeOnline;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use PeOnline\Exceptions\ApiException;
use PeOnline\Exceptions\ValidationException;

/**
 * PE-online Data Request SOAP Client for Data Retrieval
 *
 * Supports the PE-online ASMX webservice for retrieving data
 * via RetrieveDataBySoapMessage endpoint using POST request with form parameters.
 */
class PeOnlineDataRequest
{
    private HttpClient $httpClient;
    private string $baseUrl; // endpoint URL for SOAP requests
    private string $userType; // E.g., 'E' for educator
    private string $id; // User ID
    private string $key; // User Key
    private int $xmlId; // XML Schema ID
    private bool $nested; // Include nested data
    private bool $schema; // Include schema information

    public function __construct(
        string $baseUrl,
        string $userType,
        string $id,
        string $key,
        int $xmlId = 0,
        bool $nested = true,
        bool $schema = false
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->userType = $userType;
        $this->id = $id;
        $this->key = $key;
        $this->xmlId = $xmlId;
        $this->nested = $nested;
        $this->schema = $schema;

        $this->httpClient = new HttpClient([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Retrieve data from PE-online API with custom parameters
     *
     * This is the main method to be called by other PHP scripts.
     * It handles the SOAP message request and returns parsed data.
     *
     * Parameters:
     *  - $parameters: Array of query parameters (e.g., ['param_tblcourseid' => '653570'])
     *  - $options: Array of options to override defaults (e.g., ['xmlId' => 100, 'nested' => false])
     *
     * Returns an associative array with parsed XML response data containing:
     *  - schema: Schema definition (if available)
     *  - data: Parsed data entries
     *  - raw_xml: Raw XML response
     *
     * Example usage:
     *  $client = new PeOnlineDataRequest($url, 'E', '12345', 'key123');
     *  $data = $client->getData(['param_tblcourseid' => '653570']);
     */
    public function getData(array $parameters = [], array $options = []): array
    {
        // Apply option overrides if provided
        $xmlId = $options['xmlId'] ?? $this->xmlId;
        $nested = $options['nested'] ?? $this->nested;
        $schema = $options['schema'] ?? $this->schema;

        try {
            // Prepare form data for POST request
            $formData = [
                'usertype' => $this->userType,
                'ID' => $this->id,
                'Key' => $this->key,
                'XmlID' => (string)$xmlId,
                'Nested' => $nested ? 'true' : 'false',
                'Schema' => $schema ? 'true' : 'false',
                'Parameters' => http_build_query($parameters),
            ];

            // Make POST request
            $response = $this->httpClient->post($this->baseUrl, [
                'form_params' => $formData,
            ]);

            $body = $response->getBody()->getContents();

            // Parse the returned XML response
            $parsed = $this->parseXmlResponse($body);
            return $parsed;
        } catch (GuzzleException $e) {
            $this->handleApiException($e);
        }
    }

    /**
     * Parse XML response into an associative array
     */
    private function parseXmlResponse(string $body): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);

        if ($xml === false) {
            // Not valid XML - return raw body
            return ['raw' => $body];
        }

        // Check if it's a SOAP envelope
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['soap']) || isset($namespaces['SOAP-ENV'])) {
            return $this->parseSoapResponse($xml);
        }

        // Otherwise, assume direct XML response
        return $this->parseDatasetXml($xml);
    }

    /**
     * Parse SOAP envelope response
     */
    private function parseSoapResponse(\SimpleXMLElement $xml): array
    {
        $bodyNode = null;
        foreach ($xml->children() as $child) {
            if (stripos($child->getName(), 'Body') !== false) {
                $bodyNode = $child;
                break;
            }
        }

        if ($bodyNode) {
            // Look for the result content
            foreach ($bodyNode->children() as $op) {
                foreach ($op->children() as $inner) {
                    $result = (string)$inner;
                    if ($result) {
                        // Parse the inner XML content
                        $innerXml = simplexml_load_string($result);
                        if ($innerXml !== false) {
                            return $this->parseDatasetXml($innerXml);
                        }
                        return ['result' => $result];
                    }
                }
            }
        }

        return ['raw_xml' => $xml->asXML()];
    }

    /**
     * Parse dataset XML response (from result.xml structure)
     * Extracts schema and data entries
     */
    private function parseDatasetXml(\SimpleXMLElement $xml): array
    {
        $result = [
            'schema' => null,
            'data' => [],
            'raw_xml' => $xml->asXML(),
        ];

        $rootName = $xml->getName();

        // Extract schema if present
        if (isset($xml->{'xs:schema'})) {
            $result['schema'] = $this->parseSchema($xml->{'xs:schema'});
        }

        // Extract data entries - handle nested structures
        foreach ($xml->children() as $child) {
            $childName = $child->getName();

            // Skip schema elements
            if ($childName === 'xs:schema' || strpos($childName, 'xs:') === 0) {
                continue;
            }

            $result['data'][$childName][] = $this->xmlElementToArray($child);
        }

        return $result;
    }

    /**
     * Parse XML schema definition
     */
    private function parseSchema(\SimpleXMLElement $schema): array
    {
        $schemaData = [];
        foreach ($schema->{'xs:element'} as $element) {
            $name = (string)$element->attributes()['name'];
            $schemaData[$name] = [
                'name' => $name,
                'attributes' => (array)$element->attributes(),
            ];
        }

        return $schemaData;
    }

    /**
     * Convert XML element to array recursively
     */
    private function xmlElementToArray(\SimpleXMLElement $element): array
    {
        $result = [];

        foreach ($element->children() as $child) {
            $childName = $child->getName();
            $childValue = (string)$child;

            // Check if element has children (nested structure)
            if (count($child->children()) > 0) {
                // Recursive structure
                $nested = $this->xmlElementToArray($child);
                if (isset($result[$childName])) {
                    if (!is_array($result[$childName]) || !isset($result[$childName][0])) {
                        $result[$childName] = [$result[$childName]];
                    }
                    $result[$childName][] = $nested;
                } else {
                    $result[$childName] = $nested;
                }
            } else {
                // Leaf node
                if (isset($result[$childName])) {
                    if (!is_array($result[$childName]) || !isset($result[$childName][0])) {
                        $result[$childName] = [$result[$childName]];
                    }
                    $result[$childName][] = $childValue;
                } else {
                    $result[$childName] = $childValue;
                }
            }
        }

        return $result;
    }

    /**
     * Handle API exceptions and throw appropriate exception
     */
    private function handleApiException(GuzzleException $e): void
    {
        if ($e->getResponse()) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = $e->getResponse()->getBody()->getContents();

            throw new ApiException(
                'API error: ' . (is_string($body) ? $body : json_encode($body)),
                $statusCode
            );
        }

        throw new ApiException(
            'Failed to communicate with PE-online API: ' . $e->getMessage(),
            0,
            $e
        );
    }
}