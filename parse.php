<?php
/**
 * Parser to get list of country divisions and subdivisions with ISO-3166-2 codes in CSV format from https://en.wikipedia.org/wiki/ISO_3166-2
 *
 * To execute run the command in shell `php parse.php`
 * The result will be saved in files `result/divisions.csv` and `result/subdivisions.csv`
 *
 * @link https://github.com/tigrov/wikipedia-divisions
 * @author Sergei Tigrov <rrr-r@ya.ru>
 */

require(__DIR__ . '/simple_html_dom.php');

define('RESULT_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'result');
define('CSV_DELIMITER', ';');

// CSV file headers
$divisionsHeader = ['ISO-3166-1', 'ISO-3166-2', 'Name1', 'Name2', 'Wikipedia', 'Type'];
$subdivisionsHeader = ['ISO-3166-1', 'ISO-3166-2', 'ISO Region', 'Name1', 'Name2', 'Wikipedia', 'Type'];

$divisionsCsv = fopen(RESULT_DIR . DIRECTORY_SEPARATOR . 'divisions.csv', 'w');
$subdivisionsCsv = fopen(RESULT_DIR . DIRECTORY_SEPARATOR . 'subdivisions.csv', 'w');

fputcsv($divisionsCsv, $divisionsHeader, CSV_DELIMITER);
fputcsv($subdivisionsCsv, $subdivisionsHeader, CSV_DELIMITER);

$countryLinks = GetCountryLinks();

foreach ($countryLinks as $countryCode => $isoData) {
    /**
     * @var simple_html_dom_node $html
     * @var simple_html_dom_node $table
     * @var simple_html_dom_node[] $trNodes
     * @var simple_html_dom_node[] $tdNodes
     * @var simple_html_dom_node $codeNode
     * @var simple_html_dom_node $typeNode
     * @var simple_html_dom_node $url
     */

    echo $countryCode . ': ' . $isoData['url'] . PHP_EOL;

    $html = file_get_html('https://en.wikipedia.org' . $isoData['url'], null, null, null);

    // Parse divisions
    $table = $html->find('table[class=wikitable sortable]', 0);
    $trNodes = $table->find('tr');
    $typeIndex = GetTypeIndex($trNodes[0]);
    unset($trNodes[0]);

    foreach ($trNodes as $tr) {
        $tdNodes = $tr->childNodes();
        $codeNode = $tdNodes[0]->find('[style*=monospace]', 0) ?: $tdNodes[0];

        list(, $isoCode) = explode('-', $codeNode->text());

        $row = [$countryCode, $isoCode];

        if ($url = GetUrl($tr)) {
            $row[] = $url->title;
            $row[] = $url->text();
            $row[] = 'https://en.wikipedia.org' . $url->href;
        } else {
            $name = $tdNodes[1]->text();
            $row[] = $name;
            $row[] = $name;
            $row[] = '';
        }

        $row[] = $typeIndex ? $tdNodes[$typeIndex]->text() : $isoData['type'];

        $row = array_map('trim', $row);

        fputcsv($divisionsCsv, $row, CSV_DELIMITER);
    }

    // Parse subdivisions
    $parentHeader = $html->find('table[class=wikitable sortable] th[plaintext=Parent subdivision]', 0)
        ?: $html->find('table[class=wikitable sortable] th[plaintext^=In ]', 0);

    if ($parentHeader) {
        $table = $parentHeader->parent()->parent();
        $trNodes = $table->find('tr');
        $typeIndex = GetTypeIndex($trNodes[0]);
        $parentIndex = $parentHeader->get_index();
        unset($trNodes[0]);

        foreach ($trNodes as $tr) {
            $tdNodes = $tr->childNodes();
            $codeNode = $tdNodes[$parentIndex]->find('[style*=monospace]', 0) ?: $tdNodes[$parentIndex];
            $parentIso = $codeNode->text();
            if (strpos($parentIso, '-')) {
                list(, $parentIso) = explode('-', $parentIso);
            }

            $codeNode = $tdNodes[0]->find('[style*=monospace]', 0) ?: $tdNodes[0];
            list(, $isoCode) = explode('-', $codeNode->text());

            $row = [$countryCode, $isoCode, $parentIso];

            if ($url = GetUrl($tr)) {
                RemoveHidden($url);
                $row[] = $url->title;
                $row[] = $url->text();
                $row[] = 'https://en.wikipedia.org' . $url->href;
            } else {
                RemoveHidden($tdNodes[1]);
                $name = $tdNodes[1]->text();
                $row[] = $name;
                $row[] = $name;
                $row[] = '';
            }

            $row[] = $typeIndex ? $tdNodes[$typeIndex]->text() : $isoData['subtype'];

            $row = array_map('trim', $row);

            fputcsv($subdivisionsCsv, $row, CSV_DELIMITER);
        }
    }
}

fclose($divisionsCsv);
fclose($subdivisionsCsv);

/**
 * @return array
 */
function GetCountryLinks() {
    /**
     * @var simple_html_dom_node $html
     * @var simple_html_dom_node $table
     * @var simple_html_dom_node[] $trNodes
     * @var simple_html_dom_node[] $tdNodes
     * @var simple_html_dom_node $url
     */
    $html = file_get_html('https://en.wikipedia.org/wiki/ISO_3166-2', null, null, null);
    $table = $html->find('table[class=wikitable sortable]', 0);
    $trNodes = $table->childNodes();
    unset($trNodes[0]);

    $list = [];
    foreach ($trNodes as $tr) {
        $tdNodes = $tr->childNodes();
        $types = $tdNodes[2]->text();

        // If has divisions
        if ($types != '—') {
            $typeLines = explode("\n", $types);
            $type = explode(' ', $typeLines[0], 2)[1];
            $subtype = count($typeLines) == 2 ? explode(' ', $typeLines[1], 2)[1] : null;
            $url = $tdNodes[0]->find('a[href^=/wiki/ISO_3166-2]', 0);
            $list[$url->text()] = [
                'url' => $url->href,
                'type' => $type,
                'subtype' => $subtype,
            ];
        }
    }

    return $list;
}

/**
 * @param simple_html_dom_node $node
 * @return null|integer
 */
function GetTypeIndex($node) {
    /**
     * @var simple_html_dom_node $type
     */

    $type = $node->find('th[plaintext=Subdivision category]', 0)
        ?: $node->find('th[plaintext=Subdivision type]', 0);

    if ($type) {
        return $type->get_index();
    }

    return null;
}

/**
 * @param simple_html_dom_node $node
 * @return null|simple_html_dom_node
 */
function GetUrl($node) {
    if ($urls = $node->find('a[title]')) {
        foreach ($urls as $url) {
            if ($url->class != 'image') {
                return $url;
            }
        }
    }

    return null;
}

/**
 * @param simple_html_dom_node $node
 */
function RemoveHidden($node) {
    /**
     * @var simple_html_dom_node[] $hiddenNodes
     */
    if ($hiddenNodes = $node->find('[style=display:none;]')) {
        foreach ($hiddenNodes as $hidden) {
            $hidden->outertext = '';
        }
    }
}