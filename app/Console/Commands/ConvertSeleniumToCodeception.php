<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ConvertSeleniumToCodeception extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'convert {source_path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert Selenium to Codeception Files';

    /**
     * Path to read Selenium Tests From
     *
     * @var string
     */
    private $source_path;

    /**
     * Path to store output
     *
     * @var string
     */
    private $storage_path;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->source_path = $this->argument('source_path');
        $this->storage_path = storage_path() . '/codeception';
        $directories = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->source_path),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $bar = $this->output->createProgressBar(count($directories));

        foreach ($directories as $path => $file) {
            if ($file->isDir()) continue;

            if ($file->getExtension() === "html") {
                $test = $this->processFile($file);
                $this->writeFile($test, $path);
            }
            $bar->advance();
        }

        $bar->finish();
    }

    /**
     * Primary Workhorse converting all the selenium commands
     * to codeception commands and updating the target
     * based on codeception formats/needs
     *
     * @param $steps
     * @return array
     */
    private function convertSeleniumToCodceptionSteps($steps)
    {
        foreach ($steps as $index => $step) {
            switch ($step['command']) {
                case "clickAt":
                    $steps[$index]['command'] = 'click';
                    break;
                case "clickAndWait":
                    $steps[$index]['command'] = 'click';
                    break;
                case "pause":
                    $steps[$index]['command'] = 'wait';
                    $steps[$index]['target'] = $step['target'] / 1000;
                    break;
                case "captureEntirePageScreenshot":
                    $steps[$index]['command'] = 'makeScreenshot';
                    $steps[$index]['target'] = null;
                    break;
            }

            if (strpos($step['target'], 'xpath=') !== false) {
                $steps[$index]['target'] = str_replace('xpath=', '#', $step['target']);
            }

            if (strpos($step['target'], 'id=') !== false) {
                $steps[$index]['target'] = str_replace('id=', '#', $step['target']);
            }

            if (strpos($step['target'], 'link=') !== false) {
                $steps[$index]['target'] = str_replace('link=', '', $step['target']);
            }

            if (strpos($step['target'], 'css=') !== false) {
                $steps[$index]['target'] = str_replace('css=', '', $step['target']);
            }

            if (strpos($step['target'], '"') !== false) {
                $steps[$index]['target'] = str_replace('"', '', $step['target']);
            }
        }

        return $steps;
    }

    /**
     * Simplistic conversion of general title to
     * suitable PHP class name || file name
     * @param $title
     * @return string
     */
    private function convertTitleToClassName($title)
    {
        $title = str_replace(' ', '', $title);
        $title = str_replace('(', '', $title);
        $title = str_replace(')', '', $title);

        return $title . 'Cest';
    }

    /**
     * Returns an array of all of the test steps
     * from the table rows given
     *
     * @param $rows
     * @return array
     */
    private function getTestSteps($rows)
    {
        $steps = [];
        foreach ($rows as $key => $row) {
            $steps[$key]['command'] = $row->childNodes->item(0)->childNodes->item(0)->data;
            $steps[$key]['target'] = $row->childNodes->item(2)->childNodes->item(0)->data;
            if (!is_null($row->childNodes->item(4)->childNodes->item(0))) {
                $steps[$key]['extra'] = $row->childNodes->item(4)->childNodes->item(0)->data;
            }
        }

        return $steps;
    }

    /**
     * Gets the HTML title element from the file
     *
     * @param $doc
     * @return string
     */
    private function getTitleFromDoc($doc)
    {
        $xpath = new \DOMXPath($doc);
        $title_search = $xpath->query('//title');
        if ($title_search->length > 0) {
            return $title_search->item(0)->nodeValue;
        }

        return "Untitled";
    }

    /**
     * Process $file_name and return array of
     * title, class, steps
     *
     * @param $file_name
     * @return array
     */
    private function processFile($file_name)
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML(file_get_contents($file_name));

        $title = $this->getTitleFromDoc($doc);
        $tables = $doc->getElementsByTagName('tbody');
        $rows = $tables->item(0)->getElementsByTagName('tr');
        $steps = $this->getTestSteps($rows);
        $steps = $this->convertSeleniumToCodceptionSteps($steps);

        return [
            'title' => $title,
            'class' => $this->convertTitleToClassName($title),
            'steps' => $steps,
        ];
    }

    /**
     * Write the $test contents to a file
     *
     * @param $test
     * @param $path
     */
    private function writeFile($test, $path)
    {
        // Preserve Test folder structure
        $save_path = pathinfo(str_replace($this->source_path, '', $path));
        if (!file_exists($this->storage_path . '/' . $save_path['dirname'])) {
            mkdir($this->storage_path . '/' . $save_path['dirname'], 0755, true);
        }

        // Render the new PHP file
        $new_file = view('codeception-cest')
            ->with('title', $test['title'])
            ->with('class', $test['class'])
            ->with('steps', $test['steps'])
            ->with('header', '<?php');

        // Save the file to disk
        file_put_contents(
            $this->storage_path
            . '/'
            . $save_path['dirname']
            . '/'
            . $test['class'] . '.php',
            $new_file
        );
    }
}
