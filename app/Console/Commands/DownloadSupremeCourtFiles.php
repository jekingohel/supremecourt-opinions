<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use App\Models\Opinion;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DownloadSupremeCourtFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'download-supreme-court-files';

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
        $client = new Client();

        $baseUrl = 'https://supremecourt.flcourts.gov/search/opinions/?sort=opinion/disposition_date%20desc,%20opinion/case_number%20asc&view=embed_custom&searchtype=opinions&show_scopes=1&limit=50&scopes[]=supreme_court&scopes[]=first_district_court_of_appeal&scopes[]=second_district_court_of_appeal&scopes[]=third_district_court_of_appeal&scopes[]=fourth_district_court_of_appeal&scopes[]=fifth_district_court_of_appeal&scopes[]=sixth_district_court_of_appeal&startdate=&enddate=&date[year]=&date[month]=&date[day]=&query=&offset=';

        $totalPages = 5315; // Adjust this based on the total number of pages

        $requests = function ($totalPages) use ($baseUrl) {
            for ($page = 1; $page <= $totalPages; $page++) {
                yield new Request('GET', $baseUrl . '&offset=' . (($page - 1) * 50));
            }
        };

        $pool = new Pool($client, $requests($totalPages), [
            'concurrency' => 50,
            'fulfilled' => function (Response $response, $index) use ($client) {
                // Callback for successful responses
                $htmlContent = $response->getBody()->getContents();
                $this->processPageData($htmlContent, $client);
            },
            'rejected' => function (RequestException $reason, $index) {
                // Log or handle the failure
                Log::error('Request ' . $index . ' failed: ' . $reason->getMessage());
            },
        ]);

        // Initiate the transfers and create a promise
        $promise = $pool->promise();

        // Force the pool of requests to complete.
        $promise->wait();
    }

    private function processPageData($htmlContent, $client)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($htmlContent);

        $xPath = new DOMXPath($dom);

        // Find the table rows
        $nodes = $xPath->query("//table[@class='tablesaw-me search-results-opinions__table']/tbody/tr");

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
                        $pdfData[] = $pdfUrl;
                        $pdfData[] = $lastId; // file identifier
                    }
                }
            }
            // Check if PDF data is available
            if(isset($pdfData[7])){
                $pdf_file_url = "https://supremecourt.flcourts.gov/content/download/".$pdfData[7]."/opinion/Opinion_".$pdfData[2].".pdf";
                $pdf_file = 'public/pdfs/' . $pdfData[7] . '.pdf';
                // Check if the file already exists in the storage folder
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

                    // Generate requests for PDF file downloads
                    $pdfRequests = function ($pdf_file_url) {
                        yield new Request('GET', $pdf_file_url);
                    };

                    // Create a pool of asynchronous PDF file download requests
                    $pdfPool = new Pool($client, $pdfRequests($pdf_file_url), [
                        'concurrency' => 50,
                        'fulfilled' => function (Response $response, $index) use ($pdf_file){
                            // Callback for successful PDF responses
                            // Save the PDF content to Storage or perform other actions
                            $pdfContent = $response->getBody()->getContents();
                            Storage::put($pdf_file, $pdfContent);
                        },
                        'rejected' => function (RequestException $reason, $index) {
                            // Log or handle the failure
                            Log::error('Request ' . $index . ' failed: ' . $reason->getMessage());
                        },
                    ]);

                    // Initiate the PDF transfers and create a promise
                    $pdfPromise = $pdfPool->promise();

                    // Force the pool of PDF requests to complete.
                    $pdfPromise->wait(); 
                }
            }
        }
    }
}
