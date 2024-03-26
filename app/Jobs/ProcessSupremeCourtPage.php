<?php

namespace App\Jobs;


use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use DOMDocument;
use DOMElement;
use DOMXPath;
use App\Models\Opinion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessSupremeCourtPage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $url;
    protected $page;

    /**
     * Create a new job instance.
     */
    public function __construct($url, $page)
    {
        $this->url = $url;
        $this->page = $page;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Initialize Guzzle client
        $client = new Client();

        // Create the URL for the specific page
        $pageUrl = "{$this->url}&offset=" . (($this->page - 1) * 50);

        // Fetch the HTML content of the page
        $response = $client->get($pageUrl);
        $htmlContent = $response->getBody()->getContents();

        // Parse HTML and process data
        $this->processPageData($htmlContent);
    }

    private function processPageData($htmlContent)
    {
        // Initialize Guzzle client
        $client = new Client();

        $dom = new DOMDocument();
        @$dom->loadHTML($htmlContent);

        $xPath = new DOMXPath($dom);

        // Find the table rows
        $nodes = $xPath->query("//table[@class='tablesaw-me search-results-opinions__table']/tbody/tr");

        $all_pdf_files = [];
        // Iterate through each tr element
        foreach ($nodes as $node) {
            $pdfData = [];
            // Iterate through each td element in the current tr
            foreach ($node->childNodes as $index => $tdNode) {
                // Check if the node is a DOMElement and has the tag name 'td'
                if ($tdNode instanceof DOMElement && $tdNode->tagName == 'td') {
                    // Add the text content to the array
                    $pdfData[] = trim($tdNode->textContent);
                    // If it's the 5th td and contains an anchor tag
                    if ($tdNode->getElementsByTagName('a')->length > 0) {
                        // Get the href attribute of the anchor tag
                        $pdfUrl = $tdNode->getElementsByTagName('a')[0]->getAttribute('href');
                        // Extract the last part after the last '/'
                        $lastId = basename($pdfUrl);
                        // Add the PDF URL and ID to the array
                        $pdfData[] = $pdfUrl;
                        $pdfData[] = $lastId;
                    }
                }
            }
            if(isset($pdfData[7])){
                $pdf_file_url = "https://supremecourt.flcourts.gov/content/download/".$pdfData[7]."/opinion/Opinion_".$pdfData[2].".pdf";
                $pdf_file = 'public/pdfs/' . $pdfData[7] . '.pdf';
                if (!Storage::exists($pdf_file)) {
                    // Save records to the database
                    Opinion::firstOrCreate([
                        "pdf_file_identifier" => $pdfData[7],
                    ], [
                        "release_date" => $pdfData[0],
                        'court' => $pdfData[1],
                        'case_number' => $pdfData[2],
                        'case_name' => $pdfData[3],
                        'note' => $pdfData[4],
                        'pdf_file_url' => $pdf_file_url,
                        'pdf_file' => $pdf_file
                    ]);
                    $all_pdf_files[] = array('pdf_file_url' => $pdf_file_url, 'pdf_file' => $pdf_file);
                }
                
            }
            
        }

        $requests = function ($all_pdf_files) {
            foreach($all_pdf_files as $files){
                yield new Request('GET', $files['pdf_file_url']);
            }
        };

        $pool = new Pool($client, $requests($all_pdf_files), [
            'concurrency' => 50,
            'fulfilled' => function (Response $response, $index) use ($all_pdf_files) {
                // Callback for successful responses
                $pdf_file = $all_pdf_files[$index]['pdf_file'];
                $pdfContent = $response->getBody()->getContents();
                Storage::put($pdf_file, $pdfContent);
            },
            'rejected' => function (RequestException $reason, $index) {
                // Log or handle the failure
                Log::error('Request ' . $index . ' failed: ' . $reason->getMessage());
            },
        ]);

         // Initiate the PDF transfers and create a promise
        $pdfPromise = $pool->promise();

        // Force the pool of PDF requests to complete.
        $pdfPromise->wait(); 
    }
}
