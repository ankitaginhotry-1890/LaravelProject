<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class VtigerContact extends Model
{
    protected $table = 'vTigerContacts';
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'records',
        'counter',
        'record_fetched',
        'type',
    ];

    /**
     * Fetch and process each contact from the JSON records.
     *
     * @return void
     */
    public function processContacts()
    {
        // Fetch the records from the table
        $contacts = $this->where('type', 'contacts')->get()->toArray();
        $chuckedArray = [];

        foreach ($contacts as $contact) {
            
            // Decode the JSON string from the records field
            $contactsArray = json_decode(json_decode($contact['records'], true), true);
            
            Log::info("Original Length ".count($contactsArray));

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("JSON decoding error: " . json_last_error_msg());
                continue;
            }

            // Process contacts in chunks of 5
            if (count($contactsArray) > 0) {
                // Get the first 5 contacts
                $chunk = array_splice($contactsArray, 0, 1);
                
                // Log the chunk of contacts
                Log::info("Processing contacts: ", $chunk);
                
                // Update the original records field in the database
                $updatedRecords = json_encode(json_encode($contactsArray, true), true);
                Log::info("Chunked Length ".count($contactsArray));
                $this->where('id', $contact['id'])->update(['records' => $updatedRecords]);
                $chuckedArray = $chunk;
            }
        }
        
        return $chuckedArray;
    }

}
