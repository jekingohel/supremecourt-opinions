# Laravel Supreme Court Page Processing Job

This Laravel job is designed to fetch data from a Supreme Court page, extract relevant information, and download associated PDF files. It utilizes Laravel's Eloquent ORM, Guzzle HTTP client, and Laravel Storage.

## Usage

1. Install dependencies:

    ```bash
    composer install
    ```

2. Set up your Laravel environment:

    ```bash
    cp .env.example .env
    ```

    Update the `.env` file with your database and other relevant configuration settings.

3. Run Laravel migrations:

    ```bash
    php artisan migrate
    ```

4. Dispatch the Laravel job:

    ```bash
    php artisan queue:work
    ```

    This will start the Laravel queue worker, processing any jobs in the queue. Ensure that you have a queue worker running to process jobs in the background.

5. Run the Laravel scheduler:
    ```bash
    php artisan process-supreme-court-opinios
    ```
    OR
    ```bash
    php artisan download-supreme-court-files
    ```

## Configuration

The job can be configured by modifying the `ProcessSupremeCourtPage` class in the `app/Jobs` directory. Adjust the `concurrency` in the Guzzle Pool configuration based on your requirements.

## Dependencies

-   [Laravel](https://laravel.com/)
-   [Guzzle HTTP Client](https://docs.guzzlephp.org/)
-   [DOMDocument](https://www.php.net/manual/en/class.domdocument.php) and [DOMXPath](https://www.php.net/manual/en/class.domxpath.php) for HTML parsing.

## Database

The job uses Laravel's Eloquent ORM to interact with the database. Ensure your database settings are correctly configured in the `.env` file.

## Local PDF Storage

PDF files are saved locally using Laravel Storage. Ensure that the `public/pdfs` directory exists and is writable by the web server.

## Error Logging

Any errors during the PDF download process are logged using Laravel's Log. Check your Laravel logs for detailed information.
