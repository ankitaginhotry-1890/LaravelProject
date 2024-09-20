<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\test;
use App\Models\VtigerContact;


class testCom extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-com';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        echo "\nFunction Start\n";

        $obj = new VtigerContact();
        $emailsandIds  = $obj->processContacts();
        $contactId = "";
        $email = "";

        if(!empty($emailsandIds)){

            
            //$hubspotAccessToken = "pat-na1-de259ba7-b2c7-433e-8b20-50e63796e3fc";
            $hubspotAccessToken = "pat-na1-22e62c8f-a4fc-4fdc-8624-da2d87fbf1fa"; //this is the sandbox access token
            $hubOwnerId = "735240330";

            foreach ($emailsandIds as $key => $value) {
                $contactId = $key;
                $email = $value;
            }

            if(!empty($contactId) && !empty($email)){
                $this->rootCalling($email, $contactId, $hubOwnerId, $hubspotAccessToken);
            }
        }

        echo "\nThe Contact id {$contactId} is proccess for the email {$email}\n\n";

    }

    public function rootCalling($email, $contactId, $hubOwnerId, $hubspotAccessToken){

        $hubspotContact = $this->findContactByEmail($email, $hubspotAccessToken);

        if (!isset($hubspotContact['vid'])) {
            return [
                "success" => false,
                "message" => "HubSpot contact not found"
            ];
        }

        $hubContactId = $hubspotContact['vid'];

        $vtigerResponse = $this->getCommentsFromVtiger($contactId);

        if (!$vtigerResponse || !$vtigerResponse['success']) {
            return "Error fetching comments from vTiger.";
        }

        // Step 2: Extract comment content
        $comments = $this->extractCommentContent($vtigerResponse);

        if (!empty($comments)) {

            // Step 3: Create notes in HubSpot
            $hubspotResponse = $this->createHubSpotNotes($hubContactId, $hubOwnerId, $comments, $hubspotAccessToken);

            echo '<pre>';
            print_r($hubspotResponse);
            echo '[Line]:     ' . __LINE__ . "\n";
            echo '[Function]: ' . __FUNCTION__ . "\n";
            echo '[Class]:    ' . (__CLASS__ ? __CLASS__ : 'N/A') . "\n";
            echo '[Method]:   ' . (__METHOD__ ? __METHOD__ : 'N/A') . "\n";
            echo '[File]:     ' . __FILE__ . "\n";
            die;
            
            

        }


        //email sync
        $emailsResponse = $this->fetchEmailsFromVtiger($contactId);


        if ($emailsResponse['success']) {
            // Prepare email data for batch processing
            $emailsData = $emailsResponse['result'];
            $hubSpotResponse = $this->sendBatchEmailsToHubSpot($emailsData, $hubContactId, $hubspotAccessToken, $hubOwnerId);
        }
    }

    public function findContactByEmail($email, $accessToken)
    {
        $url = "https://api.hubapi.com/contacts/v1/contact/email/{$email}/profile";

        // Prepare the headers with the access token for authorization
        $headers = [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ];

        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Execute cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            // Log the error if needed
            $this->di->getLog()->logContent(json_encode([
                "error" => curl_error($ch),
                "message" => "Error occurred while fetching contact by email"
            ]), 'error', 'system.log');

            return null;
        }

        // Close the cURL session
        curl_close($ch);

        // Decode the JSON response
        $contactData = json_decode($response, true);

        // Check if the response contains valid contact data
        if (isset($contactData['vid'])) {
            return $contactData;
        } else {
            // Log invalid response or error if contact is not found
            return [
                "error" => $contactData,
                "message" => "Invalid response or contact not found"
            ];

            return null;
        }
    }

    public function findOwnerByEmail($email, $accessToken)
    {
        $url = "https://api.hubapi.com/owners/v2/owners/";

        // Prepare the headers with the access token for authorization
        $headers = [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ];

        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Execute cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            // Log the error if needed
            $this->di->getLog()->logContent(json_encode([
                "error" => curl_error($ch),
                "message" => "Error occurred while fetching owner by email"
            ]), 'error', 'system.log');

            return null;
        }

        // Close the cURL session
        curl_close($ch);

        // Decode the JSON response
        $ownerData = json_decode($response, true);


        return $ownerData;
    }

    //Notes Work 
    public function getCommentsFromVtiger($contactId)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://vitelglobalcommunicationsllc2.od2.vtiger.com/restapi/v1/vtiger/default/retrieve_related?id={$contactId}&relatedLabel=ModComments&relatedType=ModComments",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic cGV0ZXJAdml0ZWxnbG9iYWwuY29tOkI0cEhOOW8wQlZwVmpzYQ=='
            ),
        ));

        $response = curl_exec($curl);

        // Decode the JSON response
        return json_decode($response, true);
    }

    public function extractCommentContent($vtigerResponse)
    {
        $comments = [];

        // Check if the response is valid
        if (isset($vtigerResponse['success']) && $vtigerResponse['success'] && isset($vtigerResponse['result'])) {
            foreach ($vtigerResponse['result'] as $comment) {
                $comments[] = [
                    'commentcontent' => strip_tags($comment['commentcontent']), // Clean the comment content
                    'createdtime' => $comment['createdtime'], // Extract created time
                    'modifiedtime' => $comment['modifiedtime'] // Extract modified time
                ];
            }
        }

        return $comments;
    }


    public function createHubSpotNotes($contactId, $hubspotOwnerId, $comments, $accessToken)
    {
        $url = "https://api.hubapi.com/crm/v3/objects/notes/batch/create";

        // Prepare the headers with the access token for authorization
        $headers = [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ];

        // Create the inputs array for each comment
        $inputs = [];
        foreach ($comments as $comment) {
            // Convert createdtime to Unix timestamp
            $timestamp = strtotime($comment['createdtime']) * 1000; // Convert to milliseconds

            $inputs[] = [
                "associations" => [
                    [
                        "types" => [
                            [
                                "associationCategory" => "HUBSPOT_DEFINED",
                                "associationTypeId" => 202 // Replace with the actual association type ID
                            ]
                        ],
                        "to" => [
                            "id" => $contactId
                        ]
                    ]
                ],
                "properties" => [
                    "hubspot_owner_id" => $hubspotOwnerId,
                    "hs_note_body" => $comment['commentcontent'], // Note content
                    "hs_timestamp" => $timestamp // Use Unix timestamp for the note creation time
                ]
            ];
        }

        // Prepare the data for the cURL request
        $data = [
            "inputs" => $inputs
        ];

        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Execute the cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            return null; // Handle errors appropriately
        }

        // Close the cURL session
        curl_close($ch);

        // Decode and return the response
        return json_decode($response, true);
    }

    //Email Work 
    function fetchEmailsFromVtiger($contactId)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://vitelglobalcommunicationsllc2.od2.vtiger.com/restapi/v1/vtiger/default/retrieve_related?id=10x52495&relatedLabel=Emails&relatedType=Emails',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic cGV0ZXJAdml0ZWxnbG9iYWwuY29tOkI0cEhOOW8wQlZwVmpzYQ=='
            ),
        ));

        $response = curl_exec($curl);

        return json_decode($response, true);
    }

    // Function to send a batch of email data to HubSpot
    function fetchExistingAssociations($contactId, $accessToken)
    {
        $url = "https://api.hubapi.com/crm/v3/objects/contacts/{$contactId}/associations/emails";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);

    }
    function sendBatchEmailsToHubSpot($emailsData, $contactId, $accessToken, $ownerID)
    {
        $hubSpotUrl = 'https://api.hubapi.com/crm/v3/objects/emails/batch/create';
        $inputs = [];

        // Check if the contact ID is valid
        if (!$this->isValidContactId($contactId, $accessToken)) {
            return [
                'status' => 'error',
                'message' => 'Invalid contact ID',
            ];
        }

        foreach ($emailsData as $emailData) {
            $hubSpotAttachmentIds = [];

            // Only process attachments if 'imageattachmentids' exists
            if (!empty($emailData['imageattachmentids'])) {
                $attachmentIds = explode(';', $emailData['imageattachmentids']);

                foreach ($attachmentIds as $attachmentId) {
                    // Download the attachment from Vtiger
                    $attachmentData = $this->downloadAttachmentFromVtiger(trim($attachmentId));

                    if (!$attachmentData) {
                        \Log::error("Failed to download attachment with ID {$attachmentId}");
                        continue;
                    }

                    $attachmentData = $attachmentData['result'];
                    $fileName = $attachmentData[0]['filename'];
                    $filePath = 'attachments/' . $fileName;

                    // Check if the file already exists locally
                    if (\Storage::exists($filePath)) {
                        $localFilePath = \Storage::path($filePath);
                        \Log::info("File already exists locally: {$localFilePath}");
                    } else {
                        // Save the attachment locally if it doesn't exist
                        $localFilePath = $this->saveAttachmentLocally($attachmentData);
                    }

                    // Check if the attachment already exists in HubSpot
                    $existingAttachmentId = $this->checkIfAttachmentExistsInHubSpot($fileName, $accessToken);

                    if ($existingAttachmentId) {
                        \Log::info("Attachment '{$fileName}' already exists in HubSpot with ID: {$existingAttachmentId}");
                        $hubSpotAttachmentIds[] = $existingAttachmentId;
                    } else {
                        // Upload the file to HubSpot
                        $hubSpotFileId = $this->uploadFileToHubSpot($localFilePath, $accessToken);

                        if ($hubSpotFileId) {
                            $hubSpotAttachmentIds[] = $hubSpotFileId;
                        } else {
                            \Log::error("Failed to upload attachment with ID {$attachmentId} to HubSpot.");
                        }
                    }

                    // Delete the local file after processing
                    if (\Storage::exists($filePath)) {
                        unlink($localFilePath);
                    }
                }
            }

            // Construct headers
            $headers = [
                "from" => [
                    "email" => $emailData['from_email'],
                    "firstName" => "",
                    "lastName" => "",
                ],
                "to" => [
                    ["email" => json_decode($emailData['saved_toid'], true)],
                ],
                "cc" => json_decode($emailData['ccmail'], true) ?: [],
                "bcc" => json_decode($emailData['bccmail'], true) ?: [],
            ];

            $emailProperties = [
                'hs_email_html' => $emailData['description'], // Body of the email in HTML format
                'hs_email_direction' => "EMAIL", // Email direction
                'hs_timestamp' => strtotime($emailData['createdtime']) * 1000, // Timestamp in milliseconds
                'hs_email_status' => 'SENT', // Set email status
                'hs_email_subject' => $emailData['subject'], // Subject line of the email
                'hubspot_owner_id' => $ownerID,
            ];

            // If there are attachments, add them to the properties as a comma-separated string
            if (!empty($hubSpotAttachmentIds)) {
                $emailProperties['hs_attachment_ids'] = implode(',', $hubSpotAttachmentIds); // Use a string instead of an array
            }

            $inputs[] = [
                'associations' => [
                    [
                        'types' => [
                            [
                                'associationCategory' => 'HUBSPOT_DEFINED',
                                'associationTypeId' => 198, // Adjust this as needed
                            ]
                        ],
                        'to' => [
                            'id' => $contactId, // Associate with the contact ID
                        ],
                    ],
                ],
                'properties' => $emailProperties,
            ];
        }

        if (empty($inputs)) {
            return ['status' => 'no_emails_to_send'];
        }

        // Send the emails batch request
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $hubSpotUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['inputs' => $inputs]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $emailCreationResponse = json_decode($response, true);

        // Log the full response for debugging
        \Log::info('HubSpot Email Creation Response:', ['response' => $emailCreationResponse]);

        // Error handling
        if ($httpCode !== 200 || !empty($emailCreationResponse['errors'])) {
            return [
                'status' => 'error',
                'message' => 'Failed to create emails',
                'response' => $emailCreationResponse,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Emails created successfully',
            'results' => $emailCreationResponse['results'],
        ];
    }

    private function checkIfAttachmentExistsInHubSpot($fileName, $accessToken)
    {
        $url = 'https://api.hubapi.com/files/v3/files';
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url . '?name=' . urlencode($fileName),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$accessToken}",
                "Content-Type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode === 200) {
            $files = json_decode($response, true);
            // Check if the attachment exists in the response
            if (!empty($files['results'])) {
                return $files['results'][0]['id']; // Return the ID of the first matching attachment
            }
        }

        return null; // Attachment does not exist
    }
    function isValidContactId($contactId, $accessToken)
    {
        $url = "https://api.hubapi.com/crm/v3/objects/contacts/{$contactId}";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $contactResponse = json_decode($response, true);
        return isset($contactResponse['id']); // Check if the contact ID is present
    }

    // Function to download attachment from Vtiger
    private function downloadAttachmentFromVtiger($attachmentId)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://vitelglobalcommunicationsllc2.od2.vtiger.com/restapi/v1/vtiger/default/files_retrieve?id={$attachmentId}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic cGV0ZXJAdml0ZWxnbG9iYWwuY29tOkI0cEhOOW8wQlZwVmpzYQ=='
            ],
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            echo "cURL error: $error_msg";
        }

        curl_close($curl);

        if (!$response) {
            return "No response received.";
        }

        return json_decode($response, true);
    }

    // Function to save the attachment locally
    private function saveAttachmentLocally($attachmentData)
    {
        $fileContents = base64_decode($attachmentData[0]['filecontents']);
        $fileName = $attachmentData[0]['filename'];
        $filePath = 'attachments/' . $fileName;

        \Storage::put($filePath, $fileContents);

        return \Storage::path($filePath);
    }

    // Function to upload file to HubSpot
    private function uploadFileToHubSpot($filePath, $accessToken)
    {
        $url = 'https://api.hubapi.com/files/v3/files';
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file' => new \CURLFile($filePath),
                'options' => json_encode(['access' => 'PRIVATE']),
                'folderPath' => '/library/attachments'
            ],
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$accessToken}",
                "Content-Type: multipart/form-data"
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $uploadResponse = json_decode($response, true);
        \Log::info($uploadResponse);

        return $uploadResponse['id'] ?? null;
    }

}