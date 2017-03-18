<?php
/**
 * Parser to get translated names of divisions from Wikipedia:
 *
 * To execute run the command in shell `php parse-names.php`
 * The result will be saved in files `result/names.csv`
 *
 * @link https://github.com/tigrov/wikipedia-divisions
 * @author Sergei Tigrov <rrr-r@ya.ru>
 */

require(__DIR__ . '/simple_html_dom.php');

define('RESULT_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'result');
define('CSV_DELIMITER', ';');

// CSV file headers
$namesHeader = ['ISO-3166-1', 'ISO-3166-2', 'language_code', 'value', 'wikipedia'];

$namesCsv = fopen(RESULT_DIR . DIRECTORY_SEPARATOR . 'names.csv', 'w+');
$divisionsCsv = fopen(RESULT_DIR . DIRECTORY_SEPARATOR . 'divisions.csv', 'r');

fputcsv($namesCsv, $namesHeader, CSV_DELIMITER);

// Skip headers
fgetcsv($divisionsCsv, 1024, CSV_DELIMITER);

while ($division = fgetcsv($divisionsCsv, 1024, CSV_DELIMITER)) {
    $countryCode = $division[0];
    $divisionCode = $division[1];

    $url = $division[4];
    if ($url && strpos($url, 'redlink=1') === false) {
        /**
         * @var simple_html_dom_node $html
         * @var simple_html_dom_node $table
         * @var simple_html_dom_node $titleNode
         */

        echo $countryCode . '-' . $divisionCode . ': ' . $url . PHP_EOL;

        $html = file_get_html($url, null, null, null);

        if ($names = GetNames($html)) {
            foreach ($names as $langCode => $langData) {
                fputcsv($namesCsv, [$countryCode, $divisionCode, $langCode, $langData['name'], $langData['url']], CSV_DELIMITER);
            }
        }
    }
}

fclose($namesCsv);
fclose($divisionsCsv);

/**
 * @param simple_html_dom_node $node
 * @return null|array
 */
function GetNames($node) {
    /**
     * @var simple_html_dom_node $block
     * @var simple_html_dom_node $links
     */
    if ($block = $node->find('div[id=p-lang]', 0)) {
        if ($links = $block->find('a[class=interlanguage-link-target]')) {
            $list = [];
            foreach ($links as $link) {
                $langCode = $link->lang ?: $link->hreflang;
                list($name) = explode(' – ', $link->title, 2);
                list($name) = explode(',', $name, 2);
                $name = preg_replace('~\([^)]*\)~S', '', $name);
                $list[$langCode] = [
                    'name' => trim($name),
                    'url' => $link->href
                ];
            }

            return $list;
        }
    }

    return null;
}