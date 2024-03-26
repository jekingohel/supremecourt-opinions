<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use DOMDocument;
use DOMElement;
use DOMXPath;
use App\Jobs\ProcessSupremeCourtPage;

class ProcessSupremeCourtOpinios extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process-supreme-court-opinios';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch HTML content, save records, and download PDF files from Supreme Court Opinios page';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Initialize Guzzle client
        $client = new Client();
        // URL of the Supreme Court page
        $url = 'https://supremecourt.flcourts.gov/search/opinions/?sort=opinion/disposition_date%20desc,%20opinion/case_number%20asc&view=embed_custom&searchtype=opinions&show_scopes=1&limit=50&scopes[]=supreme_court&scopes[]=first_district_court_of_appeal&scopes[]=second_district_court_of_appeal&scopes[]=third_district_court_of_appeal&scopes[]=fourth_district_court_of_appeal&scopes[]=fifth_district_court_of_appeal&scopes[]=sixth_district_court_of_appeal&startdate=&enddate=&date[year]=&date[month]=&date[day]=&query=&offset=';
        $headers = ['headers' => ['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36']];
        // Fetch the HTML content of the page
        $response = $client->get($url);
        $htmlContent = $response->getBody()->getContents();

        // Parse HTML and get the total number of pages
        $totalPages = $this->getTotalPages($htmlContent);

        $promises = [];

        // Dispatch jobs for each page
        for ($page = 1; $page <= $totalPages; $page++) {
            $promises[] = ProcessSupremeCourtPage::dispatch($url, $page);
        }

        // Wait for all promises to complete
        Promise\Utils::settle($promises)->wait();
    }

    private function getTotalPages($htmlContent)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($htmlContent);

        $xPath = new DOMXPath($dom);

        // Find the Total number of pages
        $totalPages = 0;
        $pagination = $xPath->query("//ul[@class='pagination']");
        
        // Check if there is a pagination element
        if ($pagination->length > 0) {
            $secondLastLi = $xPath->query("(//ul[@class='pagination']/li)[last()-1]");
            if ($secondLastLi->length > 0) {
                $totalPages = $secondLastLi[0]->textContent;
            }
        }

        return $totalPages;
    }
}
